<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ClientIdentityOcrService
{
    public function scan(
        UploadedFile $file
    ): array {
        $directory =
            storage_path(
                'app/private/client-id-scan/'
                . Str::uuid()
            );

        File::ensureDirectoryExists(
            $directory
        );

        try {
            $source =
                $file->getRealPath();

            $extension =
                strtolower(
                    $file
                        ->getClientOriginalExtension()
                );

            $image =
                $source;

            if ($extension === 'pdf') {
                $target =
                    $directory
                    . '/page';

                $convert =
                    $this->run([
                        'pdftoppm',
                        '-f',
                        '1',
                        '-singlefile',
                        '-r',
                        '220',
                        '-png',
                        $source,
                        $target,
                    ]);

                $image =
                    $target
                    . '.png';

                if (
                    $convert['code'] !== 0
                    || !is_file($image)
                ) {
                    throw new RuntimeException(
                        'Unable to convert the scanned PDF.'
                    );
                }
            }

            $barcodeRun =
                $this->run([
                    'zbarimg',
                    '--quiet',
                    '--raw',
                    $image,
                ]);

            $barcode =
                trim(
                    $barcodeRun[
                        'stdout'
                    ]
                );

            $ocrRun =
                $this->run([
                    'tesseract',
                    $image,
                    'stdout',
                    '-l',
                    'eng+ara',
                    '--psm',
                    '6',
                ]);

            $text =
                trim(
                    $ocrRun[
                        'stdout'
                    ]
                );

            if (
                $text === ''
                && $barcode === ''
            ) {
                throw new RuntimeException(
                    'No readable ID/passport information was detected. Use a clearer scan or enter the information manually.'
                );
            }

            return [
                'fields' =>
                    $this->parse(
                        $text,
                        $barcode
                    ),

                'barcode_detected' =>
                    $barcode !== '',
            ];
        } finally {
            File::deleteDirectory(
                $directory
            );
        }
    }

    public function parse(
        string $text,
        ?string $barcode = null
    ): array {
        $barcode =
            trim(
                (string) $barcode
            );

        $fields = [
            'identity_type' =>
                null,

            'identity_number' =>
                null,

            'identity_barcode' =>
                $barcode !== ''
                    ? $barcode
                    : null,

            'name' =>
                null,

            'nationality' =>
                null,

            'date_of_birth' =>
                null,

            'gender' =>
                null,

            'document_expiry_date' =>
                null,
        ];

        $lines =
            preg_split(
                '/\R/u',
                $text
            )
            ?: [];

        /*
         * ICAO TD3 passport MRZ.
         * Standard machine-readable passports
         * from countries worldwide use this form.
         */
        $mrzLines = [];

        foreach (
            $lines
            as $line
        ) {
            $candidate =
                strtoupper(
                    preg_replace(
                        '/[^A-Z0-9<]/',
                        '',
                        $line
                    )
                    ?? ''
                );

            if (
                strlen(
                    $candidate
                ) >= 40
            ) {
                $mrzLines[] =
                    $candidate;
            }
        }

        for (
            $index = 0;
            $index < count($mrzLines) - 1;
            $index++
        ) {
            if (
                !str_starts_with(
                    $mrzLines[
                        $index
                    ],
                    'P<'
                )
            ) {
                continue;
            }

            $line1 =
                str_pad(
                    substr(
                        $mrzLines[
                            $index
                        ],
                        0,
                        44
                    ),
                    44,
                    '<'
                );

            $line2 =
                str_pad(
                    substr(
                        $mrzLines[
                            $index + 1
                        ],
                        0,
                        44
                    ),
                    44,
                    '<'
                );

            $nameArea =
                substr(
                    $line1,
                    5
                );

            $nameParts =
                explode(
                    '<<',
                    $nameArea,
                    2
                );

            $surname =
                $this->cleanMrz(
                    $nameParts[0]
                    ?? ''
                );

            $givenNames =
                $this->cleanMrz(
                    $nameParts[1]
                    ?? ''
                );

            $passportNumber =
                rtrim(
                    substr(
                        $line2,
                        0,
                        9
                    ),
                    '<'
                );

            $nationality =
                str_replace(
                    '<',
                    '',
                    substr(
                        $line2,
                        10,
                        3
                    )
                );

            $gender =
                strtoupper(
                    substr(
                        $line2,
                        20,
                        1
                    )
                );

            $fields[
                'identity_type'
            ] = 'passport';

            $fields[
                'identity_number'
            ] =
                $passportNumber
                ?: null;

            $fields['name'] =
                trim(
                    $surname
                    . ' '
                    . $givenNames
                )
                ?: null;

            $fields[
                'nationality'
            ] =
                $nationality
                ?: null;

            $fields[
                'date_of_birth'
            ] =
                $this->mrzDate(
                    substr(
                        $line2,
                        13,
                        6
                    ),
                    true
                );

            $fields['gender'] =
                in_array(
                    $gender,
                    [
                        'M',
                        'F',
                        'X',
                    ],
                    true
                )
                    ? $gender
                    : null;

            $fields[
                'document_expiry_date'
            ] =
                $this->mrzDate(
                    substr(
                        $line2,
                        21,
                        6
                    ),
                    false
                );

            break;
        }

        $combined =
            $text
            . "\n"
            . $barcode;

        /*
         * Qatar ID number:
         * eleven numeric digits.
         */
        if (
            $fields[
                'identity_type'
            ] !== 'passport'
            && preg_match(
                '/(?<!\d)(\d{11})(?!\d)/',
                $combined,
                $match
            )
        ) {
            $fields[
                'identity_type'
            ] = 'qatar_id';

            $fields[
                'identity_number'
            ] =
                $match[1];
        }

        /*
         * Printed passport number fallback.
         */
        if (
            !$fields[
                'identity_number'
            ]
            && preg_match(
                '/passport\s*(?:no|number|#)?\s*[:\-]?\s*([A-Z0-9]{5,15})/i',
                $text,
                $match
            )
        ) {
            $fields[
                'identity_type'
            ] = 'passport';

            $fields[
                'identity_number'
            ] =
                strtoupper(
                    $match[1]
                );
        }

        /*
         * Printed English name fallback.
         */
        if (
            !$fields['name']
            && preg_match(
                '/(?:full\s*name|name)\s*[:\-]\s*([A-Z][A-Z .\'-]{2,})/i',
                $text,
                $match
            )
        ) {
            $fields['name'] =
                trim(
                    preg_replace(
                        '/\s+/',
                        ' ',
                        $match[1]
                    )
                    ?? ''
                )
                ?: null;
        }

        /*
         * Barcode may directly contain Qatar ID.
         */
        if (
            $barcode !== ''
            && preg_match(
                '/(?<!\d)(\d{11})(?!\d)/',
                $barcode,
                $match
            )
        ) {
            if (
                !$fields[
                    'identity_type'
                ]
            ) {
                $fields[
                    'identity_type'
                ] = 'qatar_id';
            }

            if (
                !$fields[
                    'identity_number'
                ]
            ) {
                $fields[
                    'identity_number'
                ] =
                    $match[1];
            }
        }

        return array_filter(
            $fields,
            fn ($value): bool =>
                $value !== null
                && $value !== ''
        );
    }

    private function cleanMrz(
        string $value
    ): string {
        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                str_replace(
                    '<',
                    ' ',
                    $value
                )
            )
            ?? ''
        );
    }

    private function mrzDate(
        string $value,
        bool $birthDate
    ): ?string {
        if (
            !preg_match(
                '/^\d{6}$/',
                $value
            )
        ) {
            return null;
        }

        $yy =
            (int)
            substr(
                $value,
                0,
                2
            );

        $month =
            (int)
            substr(
                $value,
                2,
                2
            );

        $day =
            (int)
            substr(
                $value,
                4,
                2
            );

        if ($birthDate) {
            $currentYear =
                (int)
                Carbon::now(
                    'Asia/Qatar'
                )->format('y');

            $year =
                $yy > $currentYear
                    ? 1900 + $yy
                    : 2000 + $yy;
        } else {
            $year =
                $yy >= 70
                    ? 1900 + $yy
                    : 2000 + $yy;
        }

        try {
            return Carbon::create(
                $year,
                $month,
                $day,
                0,
                0,
                0,
                'Asia/Qatar'
            )->toDateString();
        } catch (Throwable) {
            return null;
        }
    }

    private function run(
        array $parts
    ): array {
        $command =
            implode(
                ' ',
                array_map(
                    'escapeshellarg',
                    $parts
                )
            );

        $output = [];
        $code = 0;

        exec(
            $command
            . ' 2>/dev/null',
            $output,
            $code
        );

        return [
            'code' =>
                $code,

            'stdout' =>
                implode(
                    "\n",
                    $output
                ),
        ];
    }
}
