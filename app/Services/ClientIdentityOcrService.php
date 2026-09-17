<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Process;

class ClientIdentityOcrService
{
    public function scan(
        UploadedFile $document
    ): array {
        $directory =
            storage_path(
                'app/private/client-id-scan/'
                . Str::uuid()
            );

        File::ensureDirectoryExists(
            $directory,
            0770,
            true
        );

        try {
            $extension = strtolower(
                $document
                    ->getClientOriginalExtension()
                ?: $document->extension()
                ?: 'jpg'
            );

            $input =
                $directory
                . '/document.'
                . $extension;

            if (
                !File::copy(
                    $document->getRealPath(),
                    $input
                )
            ) {
                throw new RuntimeException(
                    'Could not prepare the document for scanning.'
                );
            }

            $images = [];

            if ($extension === 'pdf') {
                $outputBase =
                    $directory
                    . '/page';

                $this->run([
                    'pdftoppm',
                    '-png',
                    '-r',
                    '220',
                    $input,
                    $outputBase,
                ], 45);

                $images =
                    glob(
                        $directory
                        . '/page-*.png'
                    ) ?: [];
            } else {
                $images[] = $input;
            }

            if ($images === []) {
                throw ValidationException::withMessages([
                    'document' =>
                        'No readable image was found in this document.',
                ]);
            }

            $texts = [];
            $barcode = '';

            foreach ($images as $image) {
                $ocrText =
                    $this->ocrImage(
                        $image
                    );

                if (
                    trim($ocrText)
                    !== ''
                ) {
                    $texts[] =
                        $ocrText;
                }

                if ($barcode === '') {
                    $barcode =
                        $this->readBarcode(
                            $image
                        );
                }
            }

            $text =
                trim(
                    implode(
                        "\n\n",
                        $texts
                    )
                );

            $fields =
                $this->parse(
                    $text,
                    $barcode
                );

            $meaningful =
                array_filter(
                    [
                        $fields[
                            'name'
                        ] ?? null,

                        $fields[
                            'qatar_id_number'
                        ] ?? null,

                        $fields[
                            'passport_number'
                        ] ?? null,

                        $fields[
                            'identity_number'
                        ] ?? null,

                        $fields[
                            'nationality'
                        ] ?? null,
                    ],
                    fn ($value) =>
                        $value !== null
                        && trim(
                            (string) $value
                        ) !== ''
                );

            if ($meaningful === []) {
                throw ValidationException::withMessages([
                    'document' =>
                        'No readable ID/passport information was detected. Use a clearer scan or enter the information manually.',
                ]);
            }

            return [
                'fields' =>
                    $fields,
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
        $text =
            str_replace(
                ["\r\n", "\r"],
                "\n",
                $text
            );

        $fields = [
            'identity_type' =>
                null,

            'identity_number' =>
                null,

            'identity_barcode' =>
                $this->nullable(
                    $barcode
                ),

            'qatar_id_number' =>
                null,

            'qatar_id_expiry_date' =>
                null,

            'name' =>
                null,

            'nationality' =>
                null,

            'date_of_birth' =>
                null,

            'gender' =>
                null,

            'occupation' =>
                null,

            'passport_number' =>
                null,

            'passport_expiry_date' =>
                null,

            'document_serial_number' =>
                null,

            'residency_type' =>
                null,

            'employer' =>
                null,

            'place_of_birth' =>
                null,

            'passport_issue_date' =>
                null,

            'issuing_country' =>
                null,

            'issuing_authority' =>
                null,

            /*
             * Backward-compatible generic expiry.
             */
            'document_expiry_date' =>
                null,
        ];

        /*
         * =========================================
         * STANDARD PASSPORT MRZ (ICAO TD3)
         * =========================================
         */
        $mrzLines = [];

        foreach (
            preg_split(
                '/\R/u',
                strtoupper($text)
            ) ?: []
            as $line
        ) {
            $clean =
                $this->cleanMrz(
                    $line
                );

            if (
                substr_count(
                    $clean,
                    '<'
                ) >= 2
            ) {
                $mrzLines[] =
                    $clean;
            }
        }

        for (
            $i = 0;
            $i < count($mrzLines) - 1;
            $i++
        ) {
            $line1 =
                $mrzLines[$i];

            $line2 =
                $mrzLines[$i + 1];

            if (
                !str_starts_with(
                    $line1,
                    'P<'
                )
            ) {
                continue;
            }

            if (
                strlen($line2)
                < 27
            ) {
                continue;
            }

            $fields[
                'identity_type'
            ] = 'passport';

            $passportNumber =
                str_replace(
                    '<',
                    '',
                    substr(
                        $line2,
                        0,
                        9
                    )
                );

            if ($passportNumber !== '') {
                $fields[
                    'passport_number'
                ] = $passportNumber;

                $fields[
                    'identity_number'
                ] = $passportNumber;
            }

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

            if ($nationality !== '') {
                $fields[
                    'nationality'
                ] = $nationality;
            }

            $dob =
                substr(
                    $line2,
                    13,
                    6
                );

            $fields[
                'date_of_birth'
            ] =
                $this->mrzDate(
                    $dob,
                    false
                );

            $sex =
                substr(
                    $line2,
                    20,
                    1
                );

            if (
                in_array(
                    $sex,
                    ['M', 'F', 'X'],
                    true
                )
            ) {
                $fields[
                    'gender'
                ] = $sex;
            }

            $expiry =
                substr(
                    $line2,
                    21,
                    6
                );

            $fields[
                'passport_expiry_date'
            ] =
                $this->mrzDate(
                    $expiry,
                    true
                );

            $fields[
                'document_expiry_date'
            ] =
                $fields[
                    'passport_expiry_date'
                ];

            $issuingCountry =
                str_replace(
                    '<',
                    '',
                    substr(
                        $line1,
                        2,
                        3
                    )
                );

            if (
                $issuingCountry
                !== ''
            ) {
                $fields[
                    'issuing_country'
                ] =
                    $issuingCountry;
            }

            $namePart =
                substr(
                    $line1,
                    5
                );

            $namePart =
                str_replace(
                    '<<',
                    ' ',
                    $namePart
                );

            $namePart =
                str_replace(
                    '<',
                    ' ',
                    $namePart
                );

            $name =
                trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $namePart
                    )
                    ?? ''
                );

            if ($name !== '') {
                $fields[
                    'name'
                ] = $name;
            }

            break;
        }

        /*
         * =========================================
         * QATAR RESIDENCY PERMIT
         * =========================================
         */
        $qid =
            $this->lineValue(
                $text,
                [
                    'ID.No',
                    'ID No',
                    'ID Number',
                    'QID',
                    'Qatar ID',
                ]
            );

        if (
            $qid !== null
            && preg_match(
                '/(\d{11})/',
                $qid,
                $match
            )
        ) {
            $qid =
                $match[1];
        } else {
            $qid = null;
        }

        if (
            !$qid
            && preg_match(
                '/(?<!\d)(\d{11})(?!\d)/',
                $text,
                $match
            )
        ) {
            $qid =
                $match[1];
        }

        if (
            !$qid
            && $barcode
            && preg_match(
                '/(?<!\d)(\d{11})(?!\d)/',
                $barcode,
                $match
            )
        ) {
            $qid =
                $match[1];
        }

        if ($qid) {
            $fields[
                'qatar_id_number'
            ] = $qid;

            $fields[
                'identity_type'
            ] = 'qatar_id';

            $fields[
                'identity_number'
            ] = $qid;
        }

        $dob =
            $this->lineValue(
                $text,
                [
                    'D.O.B',
                    'D.O.B.',
                    'DOB',
                    'Date of Birth',
                ]
            );

        if ($dob !== null) {
            $fields[
                'date_of_birth'
            ] =
                $this->printedDate(
                    $dob
                )
                ?? $fields[
                    'date_of_birth'
                ];
        }

        $qidExpiry =
            $this->lineValue(
                $text,
                [
                    'Expiry',
                    'ID Expiry',
                    'QID Expiry',
                    'Residency Expiry',
                ]
            );

        if (
            $qid
            && $qidExpiry !== null
        ) {
            $fields[
                'qatar_id_expiry_date'
            ] =
                $this->printedDate(
                    $qidExpiry
                );
        }

        $nationality =
            $this->lineValue(
                $text,
                [
                    'Nationality',
                ]
            );

        if ($nationality !== null) {
            $fields[
                'nationality'
            ] =
                $this->cleanTextValue(
                    $nationality
                );
        }

        $occupation =
            $this->lineValue(
                $text,
                [
                    'Occupation',
                    'Profession',
                ]
            );

        if ($occupation !== null) {
            $fields[
                'occupation'
            ] =
                $this->cleanTextValue(
                    $occupation
                );
        }

        $name =
            $this->lineValue(
                $text,
                [
                    'Name',
                    'Full Name',
                ]
            );

        if ($name !== null) {
            $cleanName =
                $this->cleanTextValue(
                    $name
                );

            if (
                $cleanName !== null
                && mb_strlen(
                    $cleanName
                ) >= 2
            ) {
                $fields[
                    'name'
                ] = $cleanName;
            }
        }

        /*
         * =========================================
         * PASSPORT / QATAR ID BACK SIDE
         * =========================================
         */
        $passportNumber =
            $this->lineValue(
                $text,
                [
                    'Passport Number',
                    'Passport No',
                    'Passport No.',
                    'Passport #',
                ]
            );

        if (
            $passportNumber !== null
        ) {
            $passportNumber =
                strtoupper(
                    preg_replace(
                        '/[^A-Z0-9]/i',
                        '',
                        $passportNumber
                    )
                    ?? ''
                );

            if (
                strlen(
                    $passportNumber
                ) >= 5
            ) {
                $fields[
                    'passport_number'
                ] =
                    $passportNumber;

                if (!$qid) {
                    $fields[
                        'identity_type'
                    ] = 'passport';

                    $fields[
                        'identity_number'
                    ] =
                        $passportNumber;
                }
            }
        }

        $passportExpiry =
            $this->lineValue(
                $text,
                [
                    'Passport Expiry',
                    'Passport Expiry Date',
                    'Date of Expiry',
                    'Expiry Date',
                ]
            );

        if (
            $passportExpiry
            !== null
        ) {
            $fields[
                'passport_expiry_date'
            ] =
                $this->printedDate(
                    $passportExpiry
                )
                ?? $fields[
                    'passport_expiry_date'
                ];
        }

        $serial =
            $this->lineValue(
                $text,
                [
                    'Serial No',
                    'Serial No.',
                    'Serial Number',
                ]
            );

        if ($serial !== null) {
            $fields[
                'document_serial_number'
            ] =
                $this->cleanTextValue(
                    $serial
                );
        }

        $residencyType =
            $this->lineValue(
                $text,
                [
                    'Residency Type',
                    'Residence Type',
                    'Permit Type',
                ]
            );

        if (
            $residencyType
            !== null
        ) {
            $fields[
                'residency_type'
            ] =
                $this->cleanTextValue(
                    $residencyType
                );
        }

        $employer =
            $this->lineValue(
                $text,
                [
                    'Employer',
                    'Sponsor',
                ]
            );

        if ($employer !== null) {
            $fields[
                'employer'
            ] =
                $this->cleanTextValue(
                    $employer
                );
        }

        /*
         * =========================================
         * STANDARD PASSPORT PRINTED FIELDS
         * =========================================
         */
        $placeOfBirth =
            $this->lineValue(
                $text,
                [
                    'Place of Birth',
                    'Birth Place',
                ]
            );

        if (
            $placeOfBirth
            !== null
        ) {
            $fields[
                'place_of_birth'
            ] =
                $this->cleanTextValue(
                    $placeOfBirth
                );
        }

        $issueDate =
            $this->lineValue(
                $text,
                [
                    'Date of Issue',
                    'Issue Date',
                    'Passport Issue',
                ]
            );

        if ($issueDate !== null) {
            $fields[
                'passport_issue_date'
            ] =
                $this->printedDate(
                    $issueDate
                );
        }

        $issuingCountry =
            $this->lineValue(
                $text,
                [
                    'Issuing Country',
                    'Country of Issue',
                    'Country Code',
                ]
            );

        if (
            $issuingCountry
            !== null
        ) {
            $fields[
                'issuing_country'
            ] =
                $this->cleanTextValue(
                    $issuingCountry
                );
        }

        $authority =
            $this->lineValue(
                $text,
                [
                    'Issuing Authority',
                    'Authority',
                ]
            );

        if ($authority !== null) {
            $fields[
                'issuing_authority'
            ] =
                $this->cleanTextValue(
                    $authority
                );
        }

        $gender =
            $this->lineValue(
                $text,
                [
                    'Sex',
                    'Gender',
                ]
            );

        if ($gender !== null) {
            $gender =
                strtoupper(
                    trim(
                        $gender
                    )
                );

            if (
                str_starts_with(
                    $gender,
                    'M'
                )
            ) {
                $fields[
                    'gender'
                ] = 'M';
            } elseif (
                str_starts_with(
                    $gender,
                    'F'
                )
            ) {
                $fields[
                    'gender'
                ] = 'F';
            } elseif (
                str_starts_with(
                    $gender,
                    'X'
                )
            ) {
                $fields[
                    'gender'
                ] = 'X';
            }
        }

        /*
         * Backward compatibility:
         * generic expiry represents the main
         * identity document.
         */
        if ($fields['qatar_id_number']) {
            $fields[
                'document_expiry_date'
            ] =
                $fields[
                    'qatar_id_expiry_date'
                ]
                ?? $fields[
                    'document_expiry_date'
                ];
        } elseif (
            $fields[
                'passport_expiry_date'
            ]
        ) {
            $fields[
                'document_expiry_date'
            ] =
                $fields[
                    'passport_expiry_date'
                ];
        }

        return array_map(
            fn ($value) =>
                is_string($value)
                    ? $this->nullable(
                        $value
                    )
                    : $value,
            $fields
        );
    }

    private function ocrImage(
        string $image
    ): string {
        $language = 'eng';

        $languages =
            $this->run(
                [
                    'tesseract',
                    '--list-langs',
                ],
                15,
                true
            );

        if (
            preg_match(
                '/^ara$/m',
                $languages
            )
        ) {
            $language =
                'eng+ara';
        }

        return $this->run([
            'tesseract',
            $image,
            'stdout',
            '-l',
            $language,
            '--psm',
            '6',
            '-c',
            'preserve_interword_spaces=1',
        ], 45, true);
    }

    private function readBarcode(
        string $image
    ): string {
        return $this->run(
            [
                'zbarimg',
                '--quiet',
                '--raw',
                $image,
            ],
            20,
            true
        );
    }

    private function lineValue(
        string $text,
        array $labels
    ): ?string {
        $lines =
            preg_split(
                '/\R/u',
                $text
            ) ?: [];

        foreach (
            $lines
            as $index => $line
        ) {
            foreach (
                $labels
                as $label
            ) {
                if (
                    !preg_match(
                        '/'
                        . preg_quote(
                            $label,
                            '/'
                        )
                        . '\s*[:\-]?\s*(.*)$/iu',
                        $line,
                        $match
                    )
                ) {
                    continue;
                }

                $value =
                    trim(
                        $match[1]
                        ?? ''
                    );

                if ($value !== '') {
                    return $value;
                }

                for (
                    $next =
                        $index + 1;
                    $next <
                        min(
                            count($lines),
                            $index + 3
                        );
                    $next++
                ) {
                    $candidate =
                        trim(
                            $lines[
                                $next
                            ]
                        );

                    if (
                        $candidate !== ''
                    ) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    private function printedDate(
        ?string $value
    ): ?string {
        if (!$value) {
            return null;
        }

        if (
            preg_match(
                '/(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})/',
                $value,
                $match
            )
        ) {
            try {
                return Carbon::create(
                    (int) $match[3],
                    (int) $match[2],
                    (int) $match[1],
                    0,
                    0,
                    0,
                    'Asia/Qatar'
                )->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        if (
            preg_match(
                '/(\d{4})[\/.\-](\d{1,2})[\/.\-](\d{1,2})/',
                $value,
                $match
            )
        ) {
            try {
                return Carbon::create(
                    (int) $match[1],
                    (int) $match[2],
                    (int) $match[3],
                    0,
                    0,
                    0,
                    'Asia/Qatar'
                )->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function cleanTextValue(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            trim(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    $value
                )
                ?? ''
            );

        $value =
            trim(
                $value,
                " \t\n\r\0\x0B:;-"
            );

        return $this->nullable(
            $value
        );
    }

    private function cleanMrz(
        string $line
    ): string {
        $line =
            strtoupper(
                trim($line)
            );

        return preg_replace(
            '/[^A-Z0-9<]/',
            '',
            $line
        ) ?? '';
    }

    private function mrzDate(
        string $value,
        bool $expiry
    ): ?string {
        if (
            !preg_match(
                '/^\d{6}$/',
                $value
            )
        ) {
            return null;
        }

        $year =
            (int) substr(
                $value,
                0,
                2
            );

        $month =
            (int) substr(
                $value,
                2,
                2
            );

        $day =
            (int) substr(
                $value,
                4,
                2
            );

        if ($expiry) {
            $fullYear =
                2000 + $year;
        } else {
            $current =
                (int) Carbon::now(
                    'Asia/Qatar'
                )->format('y');

            $fullYear =
                $year > $current
                    ? 1900 + $year
                    : 2000 + $year;
        }

        try {
            return Carbon::create(
                $fullYear,
                $month,
                $day,
                0,
                0,
                0,
                'Asia/Qatar'
            )->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullable(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            trim(
                $value
            );

        return $value === ''
            ? null
            : $value;
    }

    private function run(
        array $command,
        int $timeout = 30,
        bool $allowFailure = false
    ): string {
        $process =
            new Process(
                $command
            );

        $process->setTimeout(
            $timeout
        );

        $process->run();

        $output =
            trim(
                $process
                    ->getOutput()
            );

        if (
            !$process
                ->isSuccessful()
        ) {
            if ($allowFailure) {
                return $output;
            }

            throw new RuntimeException(
                trim(
                    $process
                        ->getErrorOutput()
                )
                ?: 'Document scanner command failed.'
            );
        }

        return $output;
    }
}
