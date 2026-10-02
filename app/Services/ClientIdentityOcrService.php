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
    /*
     * IDENTITY_SCAN_HARD_BUDGET_V7
     *
     * A customer-facing ID scan must never spend
     * close to the web-server 60-second timeout.
     */
    private ?float $scanDeadline = null;

    public function scan(
        UploadedFile $document
    ): array {
        /*
         * SCAN_DEADLINE_START_V7
         * SCAN_DEADLINE_V15B
         */
        $this->scanDeadline =
            microtime(true)
            + 18.0;

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
                    '300',
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

                /*
                 * BARCODE_ONLY_WHEN_OCR_EMPTY_V7
                 *
                 * Qatar ID/passport text already gives
                 * the information we need. Do not waste
                 * another subprocess on every scan.
                 */
                if (
                    $barcode === ''
                    && trim(
                        $ocrText
                    ) === ''
                ) {
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

            /*
             * STRUCTURED_PARSER_GATE_V5
             *
             * Do NOT reject only because the generic
             * parser did not populate fields.
             *
             * QatarIdStructureService and
             * PassportStructureService run afterwards
             * in the controller and need the raw text.
             *
             * Reject only when OCR/barcode produced
             * absolutely nothing.
             */
            if (
                trim($text) === ''
                && trim(
                    (string) $barcode
                ) === ''
            ) {
                throw ValidationException::withMessages([
                    'document' =>
                        'No text could be read from this document. Please reposition the document and scan again.',
                ]);
            }

            return [
                'fields' =>
                    $fields,

                /*
                 * Internal-only OCR text.
                 * Controller removes this before
                 * returning JSON to the browser.
                 */
                '_raw_text' =>
                    $text,
            ];

        } finally {
            /*
             * SCAN_DEADLINE_CLEAR_V7
             */
            $this->scanDeadline = null;

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

        $text =
            $this->prependValidatedPassportMrz(
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

            /* VALIDATED_TD3_PAIR_V2 */
            $validatedPair =
                $this->findBestTd3Mrz(
                    $line1
                    . "\n"
                    . $line2
                );

            if ($validatedPair === null) {
                continue;
            }

            [
                $line1,
                $line2,
            ] = $validatedPair;

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

        /*
         * MRZ_PRIORITY_V2:
         * Printed OCR is useful for fields not present
         * in MRZ, but it must never replace validated
         * passport core identity data.
         */
        $fields =
            $this->applyValidatedPassportMrz(
                $fields,
                $text
            );

        $fields =
            $this->enhancePrintedPassportFields(
                $fields,
                $text
            );

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
        /*
         * OCR_LANGUAGE_FIXED_V4
         *
         * Production server has both eng and ara
         * traineddata installed. Avoid an extra
         * tesseract --list-langs process per scan.
         */
        $language = 'eng+ara';

        /*
         * OCR_FAST_PIPELINE_V3
         *
         * QID-first pipeline:
         *
         * 1. Create only one small enhanced image.
         * 2. Run one fast English OCR pass.
         * 3. If Qatar ID is detected, stop immediately.
         * 4. Only non-QID documents pay the cost of
         *    creating/reading passport MRZ crops.
         */
        $variants =
            $this->prepareOcrVariants(
                $image,
                false
            );

        $enhanced =
            $variants[
                'enhanced'
            ]
            ?? $image;

        $texts = [];

        /*
         * ==========================================
         * FAST PATH 1: PASSPORT MRZ
         * ==========================================
         *
         * Most normal passports and e-passports
         * contain a TD3 MRZ at the bottom.
         *
         * Read this first. It is much smaller than
         * the whole page and therefore faster.
         */
        if (
            isset(
                $variants[
                    'mrz_gray'
                ]
            )
        ) {
            $mrzText =
                $this->tesseractText(
                    $variants[
                        'mrz_gray'
                    ],
                    'eng',
                    6,
                    'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<',
                    5
                );

            if (
                trim(
                    $mrzText
                ) !== ''
            ) {
                $texts[] =
                    $mrzText;
            }

            /*
             * If MRZ is already valid, one English
             * sparse-text pass is enough for fields
             * not present in MRZ:
             *
             * - Place of Birth
             * - Issue Date
             * - Issuing Authority
             */
            if (
                $this->findBestTd3Mrz(
                    $mrzText
                ) !== null
            ) {
                /*
                 * Valid passport MRZ found.
                 *
                 * PSM 6 is intentionally run before
                 * sparse PSM 11 because it preserves
                 * printed label/value relationships
                 * much better on passport bio pages.
                 *
                 * This improves:
                 * - printed holder name
                 * - place of birth
                 * - issue date
                 * - issuing authority
                 */
                $printedStructured =
                    $this->tesseractText(
                        $enhanced,
                        'eng',
                        6
                    );

                if (
                    trim(
                        $printedStructured
                    ) !== ''
                ) {
                    $texts[] =
                        $printedStructured;
                }

                /*
                 * PASSPORT_FAST_MRZ_V2
                 *
                 * A validated MRZ plus the structured
                 * PSM 6 pass is enough here.
                 *
                 * The old extra PSM 11 pass could add
                 * another 45-second wait.
                 */
                $printedSparse =
                    '';

                if (
                    trim(
                        $printedSparse
                    ) !== ''
                    && trim(
                        $printedSparse
                    ) !== trim(
                        $printedStructured
                    )
                ) {
                    $texts[] =
                        $printedSparse;
                }

                return $this->joinOcrTexts(
                    $texts
                );
            }
        }

        /*
         * ==========================================
         * FAST PATH 2: PRINTED PAGE
         * ==========================================
         *
         * PSM 11 works well for passport / ID pages
         * where labels and values are spread around.
         *
         * English first is substantially faster than
         * eng+ara and reads the English side of Qatar
         * residency cards/passports.
         */
        /*
         * QID_BILINGUAL_SINGLE_PASS_V4
         *
         * Benchmark on this VPS:
         * eng+ara PSM6 ~= 2.1 sec at 700-1000px.
         *
         * One pass reads both English and Arabic and
         * replaces the old English-then-Arabic design.
         */
        /*
         * QID_REAL_LAYOUT_PSM11_V5
         *
         * Qatar ID labels/values are scattered across
         * the card, so sparse-text mode is a better
         * real-document fit.
         */
        /*
         * QID_PRIMARY_BUDGET_V9B
         *
         * Real-card OCR was being terminated at
         * approximately the previous 4-second limit.
         */
        /*
         * QID_PRIMARY_PSM6_V11
         *
         * PSM11 repeatedly hit the real-card timeout.
         * PSM6 was previously benchmarked much faster
         * at the current ~1000px OCR size while still
         * reading English + Arabic in one pass.
         */
                /*
         * QID_PRIMARY_ENGLISH_V13
         * QID_PRIMARY_BUDGET_V15B
         * OCR_RELIABILITY_BUDGET_V26
         *
         * Real bilingual OCR exceeded the fast
         * request budget. Qatar ID English text is
         * used for the primary recognition pass.
         * Arabic-aware parsing remains preserved.
         */
        $printed =
            $this->tesseractText(
                $enhanced,
                'eng',
                6,
                null,
                12
            );

        if (
            trim(
                $printed
            ) !== ''
        ) {
            $texts[] =
                $printed;
        }

        $combined =
            $this->joinOcrTexts(
                $texts
            );

        /*
         * Whole-page OCR may itself have recovered
         * the MRZ. Stop immediately if so.
         */
        if (
            $this->findBestTd3Mrz(
                $combined
            ) !== null
        ) {
            return $combined;
        }

        $looksLikeQatarId =
            $this->looksLikeQatarIdOcrText(
                $combined
            );

        $looksLikePassport =
            preg_match(
                '/(?:'
                . '\bPASSPORT\b'
                . '|PASSPORT\s*(?:NO|NUMBER)'
                . '|DATE\s+OF\s+EXPIRY'
                . '|PLACE\s+OF\s+BIRTH'
                . ')/iu',
                $combined
            );

        /*
         * ==========================================
         * QATAR ID PATH
         * ==========================================
         *
         * Qatar cards can be bilingual. Run the
         * heavier Arabic-aware structured pass only
         * when the first pass indicates a Qatar ID.
         */
        if ($looksLikeQatarId) {
            /*
             * QID_ARABIC_OCCUPATION_DETAIL_V17
             *
             * The real Qatar ID front can contain an
             * English "Occupation" label while the
             * actual occupation value is Arabic only.
             *
             * Keep the fast English whole-card pass,
             * then OCR only the lower front-card area
             * in Arabic when front-side labels exist.
             */
            $needsArabicFrontDetail =
                preg_match(
                    '/(?:'
                    . 'OCCUPATION'
                    . '|PROFESSION'
                    . '|NATIONALITY'
                    . '|D\\.?O\\.?B'
                    . ')/iu',
                    $combined
                ) === 1;

            if ($needsArabicFrontDetail) {
                $arabicDetail =
                    $this->qatarIdArabicFrontDetail(
                        $enhanced
                    );

                if (
                    trim(
                        $arabicDetail
                    ) !== ''
                ) {
                    $texts[] =
                        $arabicDetail;
                }
            }

            return $this->joinOcrTexts(
                $texts
            );
        }

        /*
         * We now know this is NOT a Qatar ID.
         * Only at this point prepare passport MRZ
         * crops and run the MRZ OCR.
         */
        /*
         * PASSPORT_MRZ_ON_DEMAND_V3
         *
         * We reach here only after the quick whole-page
         * OCR did not classify this document as QID.
         */
        $passportVariants =
            $this->prepareOcrVariants(
                $image,
                true
            );

        if ($passportVariants !== []) {
            $variants =
                array_merge(
                    $variants,
                    $passportVariants
                );

            $enhanced =
                $variants[
                    'enhanced'
                ]
                ?? $enhanced;
        }

        if (
            isset(
                $variants[
                    'mrz_gray'
                ]
            )
        ) {
            $mrzText =
                $this->tesseractText(
                    $variants[
                        'mrz_gray'
                    ],
                    'eng',
                    6,
                    'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<',
                    5
                );

            if (
                trim(
                    $mrzText
                ) !== ''
            ) {
                $texts[] =
                    $mrzText;
            }

            $combined =
                $this->joinOcrTexts(
                    $texts
                );

            if (
                $this->findBestTd3Mrz(
                    $combined
                ) !== null
            ) {
                return $combined;
            }

            /*
             * Damaged MRZ can still contain enough
             * machine-readable markers to justify
             * the thresholded recovery pass below.
             */
            if (
                preg_match(
                    '/(?:P<|<{2,})/',
                    strtoupper(
                        $mrzText
                    )
                )
            ) {
                $looksLikePassport = true;
            }
        }

        /*
         * ==========================================
         * PASSPORT MRZ RECOVERY
         * ==========================================
         *
         * Thresholded MRZ is used only when the fast
         * gray MRZ could not validate.
         */
        if (
            $looksLikePassport
            && isset(
                $variants[
                    'mrz_bw'
                ]
            )
        ) {
            $mrzBw =
                $this->tesseractText(
                    $variants[
                        'mrz_bw'
                    ],
                    'eng',
                    6,
                    'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<',
                    5
                );

            if (
                trim(
                    $mrzBw
                ) !== ''
            ) {
                $texts[] =
                    $mrzBw;
            }

            $combined =
                $this->joinOcrTexts(
                    $texts
                );

            if (
                $this->findBestTd3Mrz(
                    $combined
                ) !== null
            ) {
                return $combined;
            }

            /*
             * Printed-only or damaged MRZ passport:
             * one structured English fallback.
             */
            $structured =
                $this->tesseractText(
                    $enhanced,
                    'eng',
                    6
                );

            if (
                trim(
                    $structured
                ) !== ''
            ) {
                $texts[] =
                    $structured;
            }

            return $this->joinOcrTexts(
                $texts
            );
        }

        /*
         * ==========================================
         * GENERIC FALLBACK
         * ==========================================
         *
         * Unknown layout: one bilingual structured
         * OCR pass.
         */
        $fallback =
            $this->tesseractText(
                $enhanced,
                $language,
                6
            );

        if (
            trim(
                $fallback
            ) !== ''
        ) {
            $texts[] =
                $fallback;
        }

        $combined =
            $this->joinOcrTexts(
                $texts
            );

        /*
         * Only attempt expensive rotation recovery
         * when there is still very little readable
         * text. Normal correctly-oriented scans will
         * never reach this section.
         */
        $plainLength =
            mb_strlen(
                preg_replace(
                    '/\s+/u',
                    '',
                    $combined
                )
                ?? ''
            );

        if (
            $plainLength < 45
            && is_executable(
                '/usr/bin/convert'
            )
        ) {
            foreach (
                [90, 270]
                as $rotation
            ) {
                $rotated =
                    dirname($image)
                    . '/rotated-fast-'
                    . $rotation
                    . '.png';

                $this->run(
                    [
                        '/usr/bin/convert',
                        $enhanced,
                        '-rotate',
                        (string) $rotation,
                        $rotated,
                    ],
                    30,
                    true
                );

                if (
                    !File::exists(
                        $rotated
                    )
                ) {
                    continue;
                }

                $rotationText =
                    $this->tesseractText(
                        $rotated,
                        'eng',
                        11
                    );

                if (
                    trim(
                        $rotationText
                    ) !== ''
                ) {
                    $texts[] =
                        $rotationText;
                }

                $combined =
                    $this->joinOcrTexts(
                        $texts
                    );

                if (
                    $this->findBestTd3Mrz(
                        $combined
                    ) !== null
                    || preg_match(
                        '/(?:PASSPORT|QATAR|RESIDENCY|QID)/iu',
                        $combined
                    )
                ) {
                    break;
                }
            }
        }

        return $this->joinOcrTexts(
            $texts
        );
    }

    private function qatarIdArabicFrontDetail(
        string $image
    ): string {
        $dimensions =
            $this->imageDimensions(
                $image
            );

        $width =
            (int) (
                $dimensions[0]
                ?? 0
            );

        $height =
            (int) (
                $dimensions[1]
                ?? 0
            );

        if (
            $width < 1
            || $height < 1
        ) {
            return '';
        }

        /*
         * Occupation is in the lower half of the
         * Qatar ID front. Crop only that small area
         * so Arabic OCR stays inexpensive.
         */
        $cropTop =
            (int) round(
                $height
                * 0.45
            );

        $cropHeight =
            max(
                1,
                $height
                - $cropTop
            );

        $detail =
            dirname($image)
            . '/qid-front-arabic-detail.jpg';

        $created =
            false;

        /*
         * Prefer in-process GD when available.
         * This avoids another expensive full
         * ImageMagick preprocessing pass.
         */
        if (
            function_exists(
                'imagecreatefromstring'
            )
            && function_exists(
                'imagecrop'
            )
            && function_exists(
                'imagejpeg'
            )
        ) {
            $bytes =
                @file_get_contents(
                    $image
                );

            $source =
                is_string($bytes)
                    ? @imagecreatefromstring(
                        $bytes
                    )
                    : false;

            if ($source !== false) {
                $cropped =
                    @imagecrop(
                        $source,
                        [
                            'x' => 0,
                            'y' => $cropTop,
                            'width' =>
                                $width,
                            'height' =>
                                $cropHeight,
                        ]
                    );

                if ($cropped !== false) {
                    $created =
                        @imagejpeg(
                            $cropped,
                            $detail,
                            92
                        );

                    imagedestroy(
                        $cropped
                    );
                }

                imagedestroy(
                    $source
                );
            }
        }

        /*
         * Safe fallback when GD is unavailable.
         */
        if (
            !$created
            && is_executable(
                '/usr/bin/convert'
            )
        ) {
            $this->run(
                [
                    '/usr/bin/convert',
                    $image,
                    '-crop',
                    $width
                    . 'x'
                    . $cropHeight
                    . '+0+'
                    . $cropTop,
                    '+repage',
                    '-strip',
                    $detail,
                ],
                2,
                true
            );
        }

        if (
            !File::exists(
                $detail
            )
        ) {
            return '';
        }

        return $this->tesseractText(
            $detail,
            'ara',
            6,
            null,
            4
        );
    }

    private function looksLikeQatarIdOcrText(
        string $text
    ): bool {
        /*
         * QID_ARABIC_SIGNAL_V5
         *
         * Tesseract may read the Arabic side correctly
         * while missing the English "Qatar ID" label.
         * Normalize Arabic digits and recognize genuine
         * Qatar-ID Arabic labels before trying passport
         * fallbacks.
         */
        $qidText =
            strtr(
                $text,
                [
                    '٠' => '0',
                    '١' => '1',
                    '٢' => '2',
                    '٣' => '3',
                    '٤' => '4',
                    '٥' => '5',
                    '٦' => '6',
                    '٧' => '7',
                    '٨' => '8',
                    '٩' => '9',
                    '۰' => '0',
                    '۱' => '1',
                    '۲' => '2',
                    '۳' => '3',
                    '۴' => '4',
                    '۵' => '5',
                    '۶' => '6',
                    '۷' => '7',
                    '۸' => '8',
                    '۹' => '9',
                ]
            );

        if (
            preg_match(
                '/(?:'
                . 'دولة\s*قطر'
                . '|بطاقة\s*شخصية'
                . '|الرقم\s*الشخصي'
                . '|رقم\s*البطاقة'
                . '|بطاقة\s*الإقامة'
                . '|تصريح\s*الإقامة'
                . ')/u',
                $qidText
            )
            || preg_match(
                '/(?<!\d)\d{11}(?!\d)/',
                $qidText
            )
        ) {
            return true;
        }

        /*
         * Front-side Qatar ID signals.
         */
        if (
            preg_match(
                '/(?:'
                . 'STATE\s+OF\s+QATAR'
                . '|RESIDENCY\s+PERMIT'
                . '|RESIDENCE\s+PERMIT'
                . '|QATAR\s+ID'
                . '|QID'
                . '|ID\.?\s*(?:NO|NUMBER)'
                . ')/iu',
                $text
            )
            || preg_match(
                '/(?<!\d)\d{11}(?!\d)/',
                $text
            )
        ) {
            return true;
        }

        /*
         * Qatar ID BACK SIDE.
         *
         * The real card OCR may not contain the
         * 11-digit QID or "State Of Qatar", but it
         * reliably exposes combinations such as:
         *
         * Passport Number
         * Passport Expiry
         * Serial No
         * Residency Type
         * Employer
         *
         * Requiring residency/employer plus another
         * back-side field prevents a normal passport
         * bio page from being classified as QID.
         */
        $hasPassportNumber =
            (bool) preg_match(
                '/PASSPORT\s*'
                . '(?:NO|NUMBER)'
                . '\b/iu',
                $text
            );

        $hasPassportExpiry =
            (bool) preg_match(
                '/PASSPORT\s+'
                . '(?:EXPIRY|EXPIRATION)/iu',
                $text
            );

        $hasSerial =
            (bool) preg_match(
                '/SERIAL\s*'
                . '(?:NO|NUMBER)'
                . '\b/iu',
                $text
            );

        $hasResidencyType =
            (bool) preg_match(
                '/RESIDENCY\s+TYPE/iu',
                $text
            );

        $hasEmployer =
            (bool) preg_match(
                '/(?:EMPLOYER|SPONSOR)/iu',
                $text
            );

        $hasQatarBackSpecific =
            $hasResidencyType
            || $hasEmployer;

        $hasBackIdentityCore =
            $hasPassportNumber
            || $hasPassportExpiry
            || $hasSerial;

        /*
         * Require a Qatar-residency-specific marker
         * AND at least one passport/serial marker.
         */
        return
            $hasQatarBackSpecific
            && $hasBackIdentityCore;
    }

    private function tesseractText(
        string $image,
        string $language,
        int $psm,
        ?string $whitelist = null,
        int $timeout = 5
    ): string {
        /*
         * OCR_TESSERACT_TIMEOUT_V2
         *
         * One OCR attempt must never block the panel
         * for 45 seconds.
         */
        /*
         * TESSERACT_SINGLE_THREAD_V3
         *
         * The VPS has limited CPU. OpenMP spawning
         * several workers can increase latency.
         */
        $command = [
            '/usr/bin/env',
            'OMP_THREAD_LIMIT=1',
            'OMP_NUM_THREADS=1',
            'tesseract',
            $image,
            'stdout',
            '-l',
            $language,
            '--oem',
            '1',
            '--psm',
            (string) $psm,
            '-c',
            'preserve_interword_spaces=1',
            '-c',
            'user_defined_dpi=300',
        ];

        if ($whitelist !== null) {
            $command[] = '-c';
            $command[] =
                'tessedit_char_whitelist='
                . $whitelist;
        }

        /*
         * OCR_SAFE_METRICS_V8A
         *
         * Never log document text, names, numbers,
         * dates or any identity-document contents.
         */
        $metricStarted =
            microtime(true);

        $output =
            $this->run(
                $command,
                $timeout,
                true
            );

        \Illuminate\Support\Facades\Log::info(
            'Identity OCR safe metric.',
            [
                'language' =>
                    $language,

                'psm' =>
                    $psm,

                'budget_seconds' =>
                    $timeout,

                'elapsed_seconds' =>
                    round(
                        microtime(true)
                        - $metricStarted,
                        3
                    ),

                'character_count' =>
                    mb_strlen(
                        trim(
                            (string) $output
                        )
                    ),
            ]
        );

        return $output;
    }

    private function prepareOcrVariants(
        string $image,
        bool $includeMrz = true
    ): array {
        if (
            !is_executable(
                '/usr/bin/convert'
            )
            || !is_executable(
                '/usr/bin/identify'
            )
        ) {
            return [];
        }

        $directory =
            dirname(
                $image
            );

        $enhanced =
            $directory
            . '/ocr-enhanced.png';

        $dimensions =
            $this->imageDimensions(
                $image
            );

        $width =
            $dimensions[0]
            ?? 0;

        $height =
            $dimensions[1]
            ?? 0;

        /*
         * QID_ZERO_CONVERT_V14
         *
         * Browser-side V14 already sends normal image
         * uploads at about the final OCR resolution.
         *
         * The first pass is QID detection only, so use
         * that image directly instead of spending several
         * seconds running ImageMagick merely to resize it.
         *
         * Passport processing is intentionally unchanged:
         * includeMrz=true continues through the normal
         * enhanced-image and MRZ crop pipeline.
         */
        if (
            !$includeMrz
            && $width > 0
            && $height > 0
            && max(
                $width,
                $height
            ) <= 1100
        ) {
            \Illuminate\Support\Facades\Log::info(
                'Identity preprocess safe metric.',
                [
                    'elapsed_seconds' =>
                        0.0,

                    'output_width' =>
                        (int) $width,

                    'output_height' =>
                        (int) $height,

                    'bypassed' =>
                        true,
                ]
            );

            return [
                'enhanced' =>
                    $image,
            ];
        }

        /*
         * Keep phone photos manageable on the VPS,
         * while enlarging low resolution uploads.
         */
        /*
         * OCR_REAL_CARD_FAST_WIDTH_V9B
         *
         * Real Qatar ID labels are much smaller than
         * synthetic benchmark text. 1000px keeps the
         * pipeline fast while preserving more detail.
         */
        if ($width > 0) {
            $targetWidth =
                min(
                    $width,
                    1000
                );
        } else {
            $targetWidth = 1000;
        }

        /*
         * OCR_FAST_JPEG_DECODE_V9B
         *
         * Thumbnail-before-OCR is substantially cheaper
         * than Lanczos resize + sharpening on full-size
         * phone/scanner images.
         *
         * MRZ receives its own sharpening later.
         */
        /*
         * OCR_FAST_JPEG_DECODE_V9B
         *
         * Large JPEG phone photos are decoded near
         * OCR size instead of fully decoding the
         * original multi-megapixel image first.
         */
        $preprocessStarted =
            microtime(true);

        $this->run(
            [
                '/usr/bin/convert',

                '-define',
                'jpeg:size=1400x1400',

                $image,

                '-auto-orient',
                '-strip',

                '-thumbnail',
                $targetWidth . 'x',

                '-colorspace',
                'Gray',

                '-auto-level',

                $enhanced,
            ],
            /*
             * QID_PREPROCESS_BUDGET_V12
             *
             * Real V11 measurement was 4.15s,
             * narrowly exceeding the old 4s cap.
             */
            5,
            true
        );

        $preprocessDimensions =
            @getimagesize(
                $enhanced
            );

        \Illuminate\Support\Facades\Log::info(
            'Identity preprocess safe metric.',
            [
                'elapsed_seconds' =>
                    round(
                        microtime(true)
                        - $preprocessStarted,
                        3
                    ),

                'output_width' =>
                    is_array(
                        $preprocessDimensions
                    )
                        ? (int) (
                            $preprocessDimensions[0]
                            ?? 0
                        )
                        : 0,

                'output_height' =>
                    is_array(
                        $preprocessDimensions
                    )
                        ? (int) (
                            $preprocessDimensions[1]
                            ?? 0
                        )
                        : 0,
            ]
        );

        if (
            !File::exists(
                $enhanced
            )
        ) {
            return [];
        }

        $variants = [
            'enhanced' =>
                $enhanced,
        ];

        /*
         * Qatar ID fast path:
         * do not spend time creating passport MRZ
         * crops until we know this is not a QID.
         */
        if (!$includeMrz) {
            return $variants;
        }

        $enhancedSize =
            $this->imageDimensions(
                $enhanced
            );

        $enhancedWidth =
            $enhancedSize[0]
            ?? 0;

        $enhancedHeight =
            $enhancedSize[1]
            ?? 0;

        if (
            $enhancedWidth < 1
            || $enhancedHeight < 1
        ) {
            return $variants;
        }

        /*
         * ICAO passport MRZ is at the bottom of the
         * bio-data page. Crop a generous lower zone
         * so different passport layouts still work.
         */
        $cropHeight =
            max(
                220,
                (int) round(
                    $enhancedHeight
                    * 0.42
                )
            );

        $cropTop =
            max(
                0,
                $enhancedHeight
                - $cropHeight
            );

        $mrzGray =
            $directory
            . '/ocr-mrz-gray.png';

        $this->run(
            [
                '/usr/bin/convert',
                $enhanced,
                '-crop',
                $enhancedWidth
                . 'x'
                . $cropHeight
                . '+0+'
                . $cropTop,
                '+repage',
                '-resize',
                '160%',
                '-sharpen',
                '0x1',
                $mrzGray,
            ],
            30,
            true
        );

        if (
            File::exists(
                $mrzGray
            )
        ) {
            $variants[
                'mrz_gray'
            ] = $mrzGray;

            $mrzBw =
                $directory
                . '/ocr-mrz-bw.png';

            $this->run(
                [
                    '/usr/bin/convert',
                    $mrzGray,
                    '-threshold',
                    '60%',
                    $mrzBw,
                ],
                25,
                true
            );

            if (
                File::exists(
                    $mrzBw
                )
            ) {
                $variants[
                    'mrz_bw'
                ] = $mrzBw;
            }
        }

        return $variants;
    }

    private function imageDimensions(
        string $image
    ): array {
        /*
         * OCR_NATIVE_DIMENSIONS_V4
         *
         * Avoid spawning ImageMagick identify for
         * ordinary JPG/PNG/WebP files.
         */
        $native =
            @getimagesize(
                $image
            );

        if (
            is_array($native)
            && isset(
                $native[0],
                $native[1]
            )
            && (int) $native[0] > 0
            && (int) $native[1] > 0
        ) {
            return [
                (int) $native[0],
                (int) $native[1],
            ];
        }

        $value =
            $this->run(
                [
                    '/usr/bin/identify',
                    '-format',
                    '%w %h',
                    $image,
                ],
                15,
                true
            );

        if (
            !preg_match(
                '/^(\d+)\s+(\d+)$/',
                trim($value),
                $match
            )
        ) {
            return [0, 0];
        }

        return [
            (int) $match[1],
            (int) $match[2],
        ];
    }

    private function joinOcrTexts(
        array $texts
    ): string {
        $unique = [];

        foreach ($texts as $text) {
            $text =
                trim(
                    (string) $text
                );

            if (
                $text === ''
                || in_array(
                    $text,
                    $unique,
                    true
                )
            ) {
                continue;
            }

            $unique[] = $text;
        }

        return trim(
            implode(
                "\n\n",
                $unique
            )
        );
    }

    /*
     * Find the most trustworthy ICAO TD3 passport
     * MRZ pair and repair common camera/OCR
     * confusions using MRZ check digits.
     */
    private function findBestTd3Mrz(
        string $text
    ): ?array {
        $lines = [];

        foreach (
            preg_split(
                '/\R/u',
                strtoupper($text)
            ) ?: []
            as $raw
        ) {
            $clean =
                $this->cleanMrz(
                    $raw
                );

            $length =
                strlen(
                    $clean
                );

            if (
                $length >= 35
                && $length <= 55
            ) {
                $lines[] = $clean;
            }
        }

        $best = null;
        $bestScore = -1;

        foreach (
            $lines
            as $index => $rawLine1
        ) {
            $line1 =
                $rawLine1;

            /*
             * A missing "<" immediately after P is
             * a very common OCR error.
             */
            if (
                str_starts_with(
                    $line1,
                    'P'
                )
                && !str_starts_with(
                    $line1,
                    'P<'
                )
            ) {
                $line1 =
                    'P<'
                    . substr(
                        $line1,
                        1
                    );
            }

            if (
                !str_starts_with(
                    $line1,
                    'P<'
                )
            ) {
                continue;
            }

            if (
                strlen($line1)
                < 40
            ) {
                continue;
            }

            $line1 =
                substr(
                    str_pad(
                        $line1,
                        44,
                        '<'
                    ),
                    0,
                    44
                );

            /*
             * Fix numeric OCR inside the issuing
             * country code.
             */
            for (
                $position = 2;
                $position <= 4;
                $position++
            ) {
                $line1[$position] =
                    $this->mrzAlphaChar(
                        $line1[
                            $position
                        ]
                    );
            }

            $max =
                min(
                    count($lines) - 1,
                    $index + 3
                );

            for (
                $next =
                    $index + 1;
                $next <= $max;
                $next++
            ) {
                $line2 =
                    $lines[$next];

                if (
                    strlen($line2)
                    < 40
                ) {
                    continue;
                }

                $line2 =
                    substr(
                        str_pad(
                            $line2,
                            44,
                            '<'
                        ),
                        0,
                        44
                    );

                /*
                 * Nationality must be alphabetic.
                 */
                foreach (
                    [10, 11, 12]
                    as $position
                ) {
                    $line2[$position] =
                        $this->mrzAlphaChar(
                            $line2[
                                $position
                            ]
                        );
                }

                /*
                 * These positions are strictly
                 * numeric in a TD3 passport MRZ.
                 */
                foreach (
                    [
                        9,
                        13, 14, 15,
                        16, 17, 18,
                        19,
                        21, 22, 23,
                        24, 25, 26,
                        27,
                        43,
                    ]
                    as $position
                ) {
                    $line2[$position] =
                        $this->mrzNumericChar(
                            $line2[
                                $position
                            ]
                        );
                }

                $line2[20] =
                    strtoupper(
                        $line2[20]
                    );

                if (
                    !in_array(
                        $line2[20],
                        [
                            'M',
                            'F',
                            'X',
                            '<',
                        ],
                        true
                    )
                ) {
                    $line2[20] = '<';
                }

                $passport =
                    substr(
                        $line2,
                        0,
                        9
                    );

                $passport =
                    $this
                        ->correctMrzPassportNumber(
                            $passport,
                            $line2[9]
                        );

                for (
                    $p = 0;
                    $p < 9;
                    $p++
                ) {
                    $line2[$p] =
                        $passport[$p]
                        ?? '<';
                }

                $score = 0;

                if (
                    $this->mrzCheckValid(
                        substr(
                            $line2,
                            0,
                            9
                        ),
                        $line2[9]
                    )
                ) {
                    $score++;
                }

                if (
                    $this->mrzCheckValid(
                        substr(
                            $line2,
                            13,
                            6
                        ),
                        $line2[19]
                    )
                ) {
                    $score++;
                }

                if (
                    $this->mrzCheckValid(
                        substr(
                            $line2,
                            21,
                            6
                        ),
                        $line2[27]
                    )
                ) {
                    $score++;
                }

                $composite =
                    substr(
                        $line2,
                        0,
                        10
                    )
                    . substr(
                        $line2,
                        13,
                        7
                    )
                    . substr(
                        $line2,
                        21,
                        22
                    );

                if (
                    $this->mrzCheckValid(
                        $composite,
                        $line2[43]
                    )
                ) {
                    $score++;
                }

                /*
                 * DOB + expiry both validating is
                 * enough to regard the alignment as
                 * trustworthy. Passport number and
                 * composite checks improve ranking.
                 */
                if (
                    $score >= 2
                    && $score > $bestScore
                ) {
                    $bestScore =
                        $score;

                    $best = [
                        $line1,
                        $line2,
                    ];
                }
            }
        }

        return $best;
    }

    private function prependValidatedPassportMrz(
        string $text
    ): string {
        $mrz =
            $this->findBestTd3Mrz(
                $text
            );

        if ($mrz === null) {
            return $text;
        }

        return $mrz[0]
            . "\n"
            . $mrz[1]
            . "\n\n"
            . $text;
    }

    private function mrzNumericChar(
        string $character
    ): string {
        $character =
            strtoupper(
                $character
            );

        return match ($character) {
            'O', 'Q', 'D' => '0',
            'I', 'L' => '1',
            'Z' => '2',
            'S' => '5',
            'G' => '6',
            'T' => '7',
            'B' => '8',
            default => $character,
        };
    }

    private function mrzAlphaChar(
        string $character
    ): string {
        $character =
            strtoupper(
                $character
            );

        return match ($character) {
            '0' => 'O',
            '1' => 'I',
            '2' => 'Z',
            '5' => 'S',
            '6' => 'G',
            '8' => 'B',
            default => $character,
        };
    }

    private function mrzCheckDigit(
        string $value
    ): int {
        $weights = [
            7,
            3,
            1,
        ];

        $total = 0;

        foreach (
            str_split(
                strtoupper($value)
            )
            as $index => $character
        ) {
            if (
                ctype_digit(
                    $character
                )
            ) {
                $number =
                    (int) $character;
            } elseif (
                $character >= 'A'
                && $character <= 'Z'
            ) {
                $number =
                    ord($character)
                    - ord('A')
                    + 10;
            } else {
                $number = 0;
            }

            $total +=
                $number
                * $weights[
                    $index % 3
                ];
        }

        return $total % 10;
    }

    private function mrzCheckValid(
        string $value,
        string $check
    ): bool {
        $check =
            $this->mrzNumericChar(
                $check
            );

        if (
            !ctype_digit(
                $check
            )
        ) {
            return false;
        }

        return $this->mrzCheckDigit(
            $value
        ) === (int) $check;
    }

    private function correctMrzPassportNumber(
        string $value,
        string $check
    ): string {
        if (
            $this->mrzCheckValid(
                $value,
                $check
            )
        ) {
            return $value;
        }

        $alternatives = [
            'O' => ['O', '0'],
            '0' => ['0', 'O'],
            'I' => ['I', '1'],
            'L' => ['L', '1'],
            '1' => ['1', 'I', 'L'],
            'B' => ['B', '8'],
            '8' => ['8', 'B'],
            'S' => ['S', '5'],
            '5' => ['5', 'S'],
            'Z' => ['Z', '2'],
            '2' => ['2', 'Z'],
            'G' => ['G', '6'],
            '6' => ['6', 'G'],
        ];

        $candidates = [''];

        foreach (
            str_split(
                $value
            )
            as $character
        ) {
            $options =
                $alternatives[
                    $character
                ]
                ?? [$character];

            $next = [];

            foreach (
                $candidates
                as $candidate
            ) {
                foreach (
                    $options
                    as $option
                ) {
                    $next[] =
                        $candidate
                        . $option;

                    if (
                        count($next)
                        >= 1024
                    ) {
                        break 2;
                    }
                }
            }

            $candidates = $next;
        }

        foreach (
            $candidates
            as $candidate
        ) {
            if (
                strlen($candidate) === 9
                && $this->mrzCheckValid(
                    $candidate,
                    $check
                )
            ) {
                return $candidate;
            }
        }

        return $value;
    }

    private function applyValidatedPassportMrz(
        array $fields,
        string $text
    ): array {
        $pair =
            $this->findBestTd3Mrz(
                $text
            );

        if ($pair === null) {
            return $fields;
        }

        [
            $line1,
            $line2,
        ] = $pair;

        if (
            strlen($line1) < 44
            || strlen($line2) < 44
        ) {
            return $fields;
        }

        /*
         * A validated passport MRZ wins over weak
         * printed OCR for core passport identity.
         */
        $fields[
            'identity_type'
        ] = 'passport';

        /*
         * A normal passport bio page does not contain
         * a Qatar residency ID. This also prevents an
         * accidental 11-digit OCR sequence from
         * changing a passport into a Qatar ID.
         */
        $fields[
            'qatar_id_number'
        ] = null;

        $fields[
            'qatar_id_expiry_date'
        ] = null;

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
            $this->mrzDate(
                substr(
                    $line2,
                    13,
                    6
                ),
                false
            );

        if ($dob !== null) {
            $fields[
                'date_of_birth'
            ] = $dob;
        }

        $sex =
            substr(
                $line2,
                20,
                1
            );

        if (
            in_array(
                $sex,
                [
                    'M',
                    'F',
                    'X',
                ],
                true
            )
        ) {
            $fields[
                'gender'
            ] = $sex;
        }

        $expiry =
            $this->mrzDate(
                substr(
                    $line2,
                    21,
                    6
                ),
                true
            );

        if ($expiry !== null) {
            $fields[
                'passport_expiry_date'
            ] = $expiry;

            $fields[
                'document_expiry_date'
            ] = $expiry;
        }

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

        if ($issuingCountry !== '') {
            $fields[
                'issuing_country'
            ] = $issuingCountry;
        }

        /*
         * ICAO TD3 name has no check digit.
         * OCR may therefore turn trailing "<"
         * filler characters into C/E/B etc.
         *
         * Prefer clearly labelled printed
         * Surname + Given Names when both are
         * available. Otherwise keep MRZ name.
         */
        $rawName =
            substr(
                $line1,
                5
            );

        $parts =
            explode(
                '<<',
                $rawName,
                2
            );

        $surname =
            trim(
                str_replace(
                    '<',
                    ' ',
                    $parts[0]
                    ?? ''
                )
            );

        $givenNames =
            trim(
                str_replace(
                    '<',
                    ' ',
                    $parts[1]
                    ?? ''
                )
            );

        $mrzName =
            trim(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    trim(
                        $surname
                        . ' '
                        . $givenNames
                    )
                )
                ?? ''
            );

        $printedName =
            $this->printedPassportFullName(
                $text
            );

        $name =
            $printedName
            ?? (
                $mrzName !== ''
                    ? $mrzName
                    : null
            );

        if ($name !== null) {
            $fields[
                'name'
            ] = $name;
        }

        return $fields;
    }

    private function printedPassportFullName(
        string $text
    ): ?string {
        $surname =
            $this->cleanTextValue(
                $this->lineValue(
                    $text,
                    [
                        'Surname',
                        'Family Name',
                        'Last Name',
                    ]
                )
            );

        $givenNames =
            $this->cleanTextValue(
                $this->lineValue(
                    $text,
                    [
                        'Given Names',
                        'Given Name',
                        'First Names',
                        'First Name',
                    ]
                )
            );

        /*
         * Require both labelled components before
         * overriding the MRZ-derived name.
         */
        if (
            $surname === null
            || $givenNames === null
        ) {
            return null;
        }

        $name =
            trim(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    $surname
                    . ' '
                    . $givenNames
                )
                ?? ''
            );

        if (
            mb_strlen($name) < 3
            || mb_strlen($name) > 120
        ) {
            return null;
        }

        /*
         * Reject obvious OCR garbage.
         */
        if (
            !preg_match(
                '/[\pL]{2}/u',
                $surname
            )
            || !preg_match(
                '/[\pL]{2}/u',
                $givenNames
            )
        ) {
            return null;
        }

        return $name;
    }

    private function enhancePrintedPassportFields(
        array $fields,
        string $text
    ): array {
        $looksLikePassport =
            (
                $fields[
                    'identity_type'
                ] ?? null
            ) === 'passport'
            || preg_match(
                '/\bpassport\b/iu',
                $text
            )
            || $this->findBestTd3Mrz(
                $text
            ) !== null;

        /*
         * Never let passport fallback logic alter a
         * Qatar ID-only scan.
         */
        if (!$looksLikePassport) {
            return $fields;
        }

        $fields[
            'identity_type'
        ] = 'passport';

        if (
            empty(
                $fields[
                    'passport_number'
                ]
            )
        ) {
            $number =
                $this->lineValue(
                    $text,
                    [
                        'Passport Number',
                        'Passport No',
                        'Passport No.',
                        'Passport #',
                        'Document Number',
                        'Document No',
                        'Document No.',
                    ]
                );

            if ($number !== null) {
                $number =
                    strtoupper(
                        preg_replace(
                            '/[^A-Z0-9]/i',
                            '',
                            $number
                        )
                        ?? ''
                    );

                if (
                    strlen($number) >= 5
                    && strlen($number) <= 15
                ) {
                    $fields[
                        'passport_number'
                    ] = $number;
                }
            }
        }

        if (
            empty(
                $fields[
                    'identity_number'
                ]
            )
            && !empty(
                $fields[
                    'passport_number'
                ]
            )
        ) {
            $fields[
                'identity_number'
            ] =
                $fields[
                    'passport_number'
                ];
        }

        if (
            empty(
                $fields[
                    'name'
                ]
            )
        ) {
            $surname =
                $this->lineValue(
                    $text,
                    [
                        'Surname',
                        'Family Name',
                        'Last Name',
                    ]
                );

            $given =
                $this->lineValue(
                    $text,
                    [
                        'Given Names',
                        'Given Name',
                        'First Name',
                    ]
                );

            $combined =
                trim(
                    ($surname ?? '')
                    . ' '
                    . ($given ?? '')
                );

            if ($combined === '') {
                $combined =
                    $this->lineValue(
                        $text,
                        [
                            'Full Name',
                            'Name',
                        ]
                    ) ?? '';
            }

            $fields[
                'name'
            ] =
                $this->cleanTextValue(
                    $combined
                );
        }

        if (
            empty(
                $fields[
                    'nationality'
                ]
            )
        ) {
            $fields[
                'nationality'
            ] =
                $this->cleanTextValue(
                    $this->lineValue(
                        $text,
                        [
                            'Nationality',
                            'Citizenship',
                        ]
                    )
                );
        }

        if (
            empty(
                $fields[
                    'date_of_birth'
                ]
            )
        ) {
            $fields[
                'date_of_birth'
            ] =
                $this->flexiblePassportDate(
                    $this->lineValue(
                        $text,
                        [
                            'Date of Birth',
                            'Birth Date',
                            'DOB',
                        ]
                    )
                );
        }

        if (
            empty(
                $fields[
                    'passport_expiry_date'
                ]
            )
        ) {
            $fields[
                'passport_expiry_date'
            ] =
                $this->flexiblePassportDate(
                    $this->lineValue(
                        $text,
                        [
                            'Date of Expiry',
                            'Date of Expiration',
                            'Expiry Date',
                            'Expiration Date',
                            'Valid Until',
                            'Valid To',
                        ]
                    )
                );
        }

        if (
            empty(
                $fields[
                    'passport_issue_date'
                ]
            )
        ) {
            $fields[
                'passport_issue_date'
            ] =
                $this->flexiblePassportDate(
                    $this->lineValue(
                        $text,
                        [
                            'Date of Issue',
                            'Issue Date',
                            'Issued On',
                            'Passport Issue',
                        ]
                    )
                );
        }

        if (
            empty(
                $fields[
                    'place_of_birth'
                ]
            )
        ) {
            $fields[
                'place_of_birth'
            ] =
                $this->cleanTextValue(
                    $this->lineValue(
                        $text,
                        [
                            'Place of Birth',
                            'Birth Place',
                        ]
                    )
                );
        }

        if (
            empty(
                $fields[
                    'issuing_country'
                ]
            )
        ) {
            $fields[
                'issuing_country'
            ] =
                $this->cleanTextValue(
                    $this->lineValue(
                        $text,
                        [
                            'Issuing Country',
                            'Country of Issue',
                            'Country Code',
                        ]
                    )
                );
        }

        if (
            empty(
                $fields[
                    'issuing_authority'
                ]
            )
        ) {
            $fields[
                'issuing_authority'
            ] =
                $this->cleanTextValue(
                    $this->lineValue(
                        $text,
                        [
                            'Issuing Authority',
                            'Passport Authority',
                            'Authority',
                        ]
                    )
                );
        }

        if (
            empty(
                $fields[
                    'gender'
                ]
            )
        ) {
            $sex =
                strtoupper(
                    trim(
                        $this->lineValue(
                            $text,
                            [
                                'Sex',
                                'Gender',
                            ]
                        )
                        ?? ''
                    )
                );

            if (
                str_starts_with(
                    $sex,
                    'M'
                )
            ) {
                $fields[
                    'gender'
                ] = 'M';
            } elseif (
                str_starts_with(
                    $sex,
                    'F'
                )
            ) {
                $fields[
                    'gender'
                ] = 'F';
            } elseif (
                str_starts_with(
                    $sex,
                    'X'
                )
            ) {
                $fields[
                    'gender'
                ] = 'X';
            }
        }

        if (
            empty(
                $fields[
                    'document_expiry_date'
                ]
            )
            && !empty(
                $fields[
                    'passport_expiry_date'
                ]
            )
        ) {
            $fields[
                'document_expiry_date'
            ] =
                $fields[
                    'passport_expiry_date'
                ];
        }

        return $fields;
    }

    private function flexiblePassportDate(
        ?string $value
    ): ?string {
        $normal =
            $this->printedDate(
                $value
            );

        if ($normal !== null) {
            return $normal;
        }

        if (!$value) {
            return null;
        }

        $months = [
            'JAN' => 1,
            'FEB' => 2,
            'MAR' => 3,
            'APR' => 4,
            'MAY' => 5,
            'JUN' => 6,
            'JUL' => 7,
            'AUG' => 8,
            'SEP' => 9,
            'SEPT' => 9,
            'OCT' => 10,
            'NOV' => 11,
            'DEC' => 12,
        ];

        if (
            preg_match(
                '/(\d{1,2})\s+([A-Z]{3,9})\s+(\d{4})/i',
                $value,
                $match
            )
        ) {
            $monthKey =
                strtoupper(
                    substr(
                        $match[2],
                        0,
                        4
                    )
                );

            if (
                !isset(
                    $months[
                        $monthKey
                    ]
                )
            ) {
                $monthKey =
                    strtoupper(
                        substr(
                            $match[2],
                            0,
                            3
                        )
                    );
            }

            if (
                isset(
                    $months[
                        $monthKey
                    ]
                )
            ) {
                try {
                    return Carbon::create(
                        (int) $match[3],
                        $months[
                            $monthKey
                        ],
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
        }

        return null;
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
            /* BARCODE_TIMEOUT_V7 */
            2,
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
                        /* LINE_VALUE_BOUNDARY_V2 */
                        '/(?<![\pL\pN])'
                        . preg_quote(
                            $label,
                            '/'
                        )
                        . '(?![\pL\pN])\s*[:\-]?\s*(.*)$/iu',
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
        /*
         * PROCESS_REMAINING_BUDGET_V7
         *
         * Best-effort OCR/conversion fallbacks share
         * one global customer-facing deadline.
         */
        if (
            $allowFailure
            && $this->scanDeadline !== null
        ) {
            $remaining =
                $this->scanDeadline
                - microtime(true);

            if ($remaining <= 0.25) {
                return '';
            }

            $timeout =
                max(
                    1,
                    min(
                        $timeout,
                        (int) ceil(
                            $remaining
                        )
                    )
                );
        }

        $process =
            new Process(
                $command
            );

        $process->setTimeout(
            $timeout
        );

        /*
         * OCR_TIMEOUT_RECOVERY_V2
         *
         * Symfony throws before the normal
         * allowFailure handling when a process hits
         * its timeout. OCR fallback calls are allowed
         * to fail, so keep any partial output and move
         * on instead of turning it into a 500 response.
         */
        try {
            $process->run();
        } catch (
            \Symfony\Component\Process\Exception\ProcessTimedOutException
            $exception
        ) {
            if (!$allowFailure) {
                throw $exception;
            }

            return trim(
                $process->getOutput()
            );
        }

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
