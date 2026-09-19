<?php

namespace App\Services;

class QatarIdStructureService
{
    public function augment(
        array $fields,
        string $text
    ): array {
        $text =
            $this->normalizeDigits(
                $text
            );

        $frontDetected =
            $this->looksLikeFront(
                $fields,
                $text
            );

        $backDetected =
            $this->looksLikeBack(
                $fields,
                $text
            );

        if (
            !$frontDetected
            && !$backDetected
        ) {
            return $fields;
        }

        if ($frontDetected) {
            $qid =
                $this->qidNumber(
                    $fields,
                    $text
                );

            if ($qid !== null) {
                $fields[
                    'qatar_id_number'
                ] = $qid;

                $fields[
                    'identity_number'
                ] = $qid;
            }

            $this->fillTextField(
                $fields,
                'name',
                'front',
                $text
            );

            $this->fillTextField(
                $fields,
                'nationality',
                'front',
                $text
            );

            $this->fillTextField(
                $fields,
                'occupation',
                'front',
                $text
            );

            $structuredDob =
                $this->structuredDateOfBirth(
                    $text
                );

            if (
                $structuredDob
                !== null
            ) {
                $fields[
                    'date_of_birth'
                ] =
                    $structuredDob;
            }

            $this->fillDateField(
                $fields,
                'qatar_id_expiry_date',
                'front',
                $text
            );

            /*
             * Qatar ID front normally contains both
             * DOB and ID expiry. Real camera OCR can
             * read the date value while damaging the
             * DOB label. In that case, resolve DOB
             * from the remaining front-side dates.
             */
            if (
                empty(
                    $fields[
                        'date_of_birth'
                    ]
                )
            ) {
                $fallbackDob =
                    $this->fallbackDateOfBirth(
                        $text,
                        $fields[
                            'qatar_id_expiry_date'
                        ]
                        ?? null
                    );

                if (
                    $fallbackDob
                    !== null
                ) {
                    $fields[
                        'date_of_birth'
                    ] =
                        $fallbackDob;
                }
            }

            $gender =
                $this->value(
                    $text,
                    $this->labels(
                        'front',
                        'gender'
                    )
                );

            if ($gender !== null) {
                $normalizedGender =
                    $this->gender(
                        $gender
                    );

                if (
                    $normalizedGender
                    !== null
                ) {
                    $fields[
                        'gender'
                    ] =
                        $normalizedGender;
                }
            }

            $fields[
                'identity_type'
            ] = 'qatar_id';

            if (
                !empty(
                    $fields[
                        'qatar_id_expiry_date'
                    ]
                )
            ) {
                $fields[
                    'document_expiry_date'
                ] =
                    $fields[
                        'qatar_id_expiry_date'
                    ];
            }
        }

        if ($backDetected) {
            /*
             * QID_STRICT_BACK_CODES_V18
             *
             * Never concatenate neighbouring OCR text
             * into passport/serial identifiers.
             */
            $this->fillTextField(
                $fields,
                'passport_number',
                'back',
                $text
            );

            $passportNumber =
                $this->strictCodeAfterLabel(
                    $text,
                    $this->labels(
                        'back',
                        'passport_number'
                    ),
                    5,
                    15
                );

            if (
                $passportNumber === null
                && !empty(
                    $fields[
                        'passport_number'
                    ]
                )
                && preg_match(
                    '/^[A-Z0-9]{5,15}$/i',
                    trim(
                        (string) $fields[
                            'passport_number'
                        ]
                    ),
                    $passportMatch
                )
            ) {
                $passportNumber =
                    strtoupper(
                        $passportMatch[0]
                    );
            }

            $fields[
                'passport_number'
            ] = $passportNumber;

            $this->fillDateField(
                $fields,
                'passport_expiry_date',
                'back',
                $text
            );

            $this->fillTextField(
                $fields,
                'document_serial_number',
                'back',
                $text
            );

            $serialNumber =
                $this->strictCodeAfterLabel(
                    $text,
                    $this->labels(
                        'back',
                        'document_serial_number'
                    ),
                    5,
                    20
                );

            if (
                $serialNumber === null
                && !empty(
                    $fields[
                        'document_serial_number'
                    ]
                )
                && preg_match(
                    '/^[A-Z0-9]{5,20}$/i',
                    trim(
                        (string) $fields[
                            'document_serial_number'
                        ]
                    ),
                    $serialMatch
                )
            ) {
                $serialNumber =
                    strtoupper(
                        $serialMatch[0]
                    );
            }

            $fields[
                'document_serial_number'
            ] = $serialNumber;

            $this->fillTextField(
                $fields,
                'residency_type',
                'back',
                $text
            );

            $this->fillTextField(
                $fields,
                'employer',
                'back',
                $text
            );

            /*
             * Back side contains passport details,
             * but it is still a Qatar ID scan.
             */
            $fields[
                'identity_type'
            ] = 'qatar_id';

            if (!$frontDetected) {
                /*
                 * Never let a Qatar ID back-side
                 * passport number become the main
                 * client identity.
                 */
                $fields[
                    'identity_number'
                ] = null;

                $fields[
                    'document_expiry_date'
                ] = null;
            }
        }

        return $this->normalizeStructuredFields(
            $fields,
            $text
        );
    }

    private function normalizeStructuredFields(
        array $fields,
        string $text
    ): array {
        /*
         * English nationality has priority.
         */
        $nationality =
            $this->englishLabelValue(
                $text,
                [
                    'Nationality',
                ],
                3
            );

        if ($nationality !== null) {
            $fields[
                'nationality'
            ] =
                strtoupper(
                    $nationality
                );
        } elseif (
            !empty(
                $fields[
                    'nationality'
                ]
            )
            && preg_match(
                '/\p{Arabic}/u',
                (string) $fields[
                    'nationality'
                ]
            )
        ) {
            $fields[
                'nationality'
            ] = null;
        }

        /*
         * Qatar ID front:
         * Prefer readable Arabic occupation over
         * damaged Latin OCR such as "dadl wv".
         */
        $arabicOccupation =
            $this->arabicValueAfterLabel(
                $text,
                [
                    'المهنة',
                ]
            );

        /*
         * QID_ENGLISH_OCCUPATION_V16
         *
         * Primary real-card OCR is currently English.
         * Recover the front-side occupation directly
         * from the English label when Arabic text is
         * not available.
         */
        $englishOccupation =
            $this->englishLabelValue(
                $text,
                [
                    'Occupation',
                    'Profession',
                ],
                3
            );

        if (
            $arabicOccupation === null
            && $englishOccupation !== null
        ) {
            $fields[
                'occupation'
            ] = $englishOccupation;
        }

        if (
            $arabicOccupation
            !== null
        ) {
            $fields[
                'occupation'
            ] =
                $arabicOccupation;
        } elseif (
            !empty(
                $fields[
                    'occupation'
                ]
            )
            && !$this->validDocumentValue(
                (string) $fields[
                    'occupation'
                ],
                4
            )
        ) {
            $fields[
                'occupation'
            ] = null;
        }

        /*
         * Back-side residency type.
         */
        $arabicResidency =
            $this->arabicValueAfterLabel(
                $text,
                [
                    'نوع الإقامة',
                    'نوع الرخصة',
                ]
            );

        $englishResidency =
            $this->englishLabelValue(
                $text,
                [
                    'Residency Type',
                    'Residence Type',
                    'Permit Type',
                    'Permit Class',
                ],
                4
            );

        if (
            $arabicResidency
            !== null
        ) {
            $fields[
                'residency_type'
            ] =
                $this->normalizeResidencyType(
                    $arabicResidency
                );
        } elseif (
            $englishResidency
            !== null
        ) {
            $fields[
                'residency_type'
            ] =
                $this->normalizeResidencyType(
                    $englishResidency
                );
        } elseif (
            !empty(
                $fields[
                    'residency_type'
                ]
            )
            && !$this->validDocumentValue(
                (string) $fields[
                    'residency_type'
                ],
                4
            )
        ) {
            $fields[
                'residency_type'
            ] = null;
        }

        /*
         * Back-side employer / sponsor.
         */
        $arabicEmployer =
            $this->arabicValueAfterLabel(
                $text,
                [
                    'المستقدم',
                    'الكفيل',
                    'صاحب العمل',
                ]
            );

        $englishEmployer =
            $this->englishLabelValue(
                $text,
                [
                    'Employer / Sponsor',
                    'Employer',
                    'Sponsor',
                ],
                4
            );

        if (
            $arabicEmployer
            !== null
        ) {
            $fields[
                'employer'
            ] =
                $arabicEmployer;
        } elseif (
            $englishEmployer
            !== null
        ) {
            $fields[
                'employer'
            ] =
                $englishEmployer;
        } elseif (
            !empty(
                $fields[
                    'employer'
                ]
            )
            && !$this->validDocumentValue(
                (string) $fields[
                    'employer'
                ],
                4
            )
        ) {
            $fields[
                'employer'
            ] = null;
        }

        /*
         * Never infer Qatar-ID gender from name,
         * nationality or QID number.
         */
        if (
            !in_array(
                $fields[
                    'gender'
                ]
                ?? null,
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
            ] = null;
        }

        return $fields;
    }

    private function englishLabelValue(
        string $text,
        array $labels,
        int $minimumLetters = 4
    ): ?string {
        $lines =
            preg_split(
                '/\R/u',
                $this->stripBidi(
                    $text
                )
            ) ?: [];

        /*
         * Pass 1:
         * Prefer value on the SAME line.
         *
         * Real OCR example:
         * Nationality BANGLADESH
         */
        foreach (
            $lines
            as $line
        ) {
            foreach (
                $labels
                as $label
            ) {
                $position =
                    stripos(
                        $line,
                        $label
                    );

                if (
                    $position
                    === false
                ) {
                    continue;
                }

                $value =
                    substr(
                        $line,
                        $position
                        + strlen(
                            $label
                        )
                    );

                $value =
                    preg_split(
                        '/\p{Arabic}/u',
                        $value,
                        2
                    )[0]
                    ?? '';

                $value =
                    $this->cleanEnglishValue(
                        $value
                    );

                if (
                    $this->validEnglishValue(
                        $value,
                        $minimumLetters
                    )
                ) {
                    return $value;
                }
            }
        }

        /*
         * Pass 2:
         * Some OCR puts the value on the next line.
         */
        foreach (
            $lines
            as $index => $line
        ) {
            foreach (
                $labels
                as $label
            ) {
                if (
                    stripos(
                        $line,
                        $label
                    ) === false
                ) {
                    continue;
                }

                if (
                    !isset(
                        $lines[
                            $index + 1
                        ]
                    )
                ) {
                    continue;
                }

                $value =
                    $this->cleanEnglishValue(
                        $lines[
                            $index + 1
                        ]
                    );

                if (
                    $this->looksLikeFieldLabel(
                        $value
                    )
                ) {
                    continue;
                }

                if (
                    $this->validEnglishValue(
                        $value,
                        $minimumLetters
                    )
                ) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function arabicValueAfterLabel(
        string $text,
        array $labels
    ): ?string {
        $lines =
            preg_split(
                '/\R/u',
                $this->stripBidi(
                    $text
                )
            ) ?: [];

        foreach (
            $lines
            as $line
        ) {
            foreach (
                $labels
                as $label
            ) {
                $position =
                    mb_stripos(
                        $line,
                        $label,
                        0,
                        'UTF-8'
                    );

                if (
                    $position
                    === false
                ) {
                    continue;
                }

                $after =
                    mb_substr(
                        $line,
                        $position
                        + mb_strlen(
                            $label,
                            'UTF-8'
                        ),
                        null,
                        'UTF-8'
                    );

                $after =
                    preg_replace(
                        '/^[\s:：.\-]+/u',
                        '',
                        $after
                    )
                    ?? '';

                if (
                    preg_match(
                        '/([\p{Arabic}]'
                        . '[\p{Arabic}\s\-]{1,80})/u',
                        $after,
                        $match
                    )
                ) {
                    $value =
                        trim(
                            preg_replace(
                                '/\s+/u',
                                ' ',
                                $match[1]
                            )
                            ?? ''
                        );

                    if (
                        mb_strlen(
                            preg_replace(
                                '/[^\p{Arabic}]/u',
                                '',
                                $value
                            )
                            ?? '',
                            'UTF-8'
                        ) >= 2
                    ) {
                        return $value;
                    }
                }
            }
        }

        return null;
    }

    private function cleanEnglishValue(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            $this->stripBidi(
                $value
            );

        $value =
            preg_replace(
                '/^[\s:：.\-]+/u',
                '',
                $value
            )
            ?? '';

        $value =
            preg_replace(
                '/[^A-Za-z0-9 &\'().\/\-]+/',
                ' ',
                $value
            )
            ?? '';

        $value =
            trim(
                preg_replace(
                    '/\s+/',
                    ' ',
                    $value
                )
                ?? ''
            );

        return $value === ''
            ? null
            : $value;
    }

    private function validEnglishValue(
        ?string $value,
        int $minimumLetters = 4
    ): bool {
        if ($value === null) {
            return false;
        }

        $letters =
            preg_replace(
                '/[^A-Za-z]/',
                '',
                $value
            )
            ?? '';

        if (
            strlen(
                $letters
            ) < $minimumLetters
        ) {
            return false;
        }

        /*
         * Fully-uppercase document values are
         * strong OCR candidates.
         */
        if (
            strtoupper(
                $value
            ) === $value
        ) {
            return true;
        }

        /*
         * Otherwise require mostly sensible
         * Title-Case words. This rejects examples
         * from the real OCR such as:
         *
         * dadl wv
         * la bily oYglia Hola slo
         */
        $words =
            preg_split(
                '/\s+/',
                trim(
                    $value
                )
            ) ?: [];

        $total = 0;
        $good = 0;

        foreach (
            $words
            as $word
        ) {
            $word =
                trim(
                    $word,
                    ".,&'()/-"
                );

            if ($word === '') {
                continue;
            }

            $total++;

            if (
                preg_match(
                    '/^[A-Z][a-z]+$/',
                    $word
                )
                || preg_match(
                    '/^[A-Z]{2,}$/',
                    $word
                )
                || in_array(
                    strtolower(
                        $word
                    ),
                    [
                        'of',
                        'the',
                        'and',
                        'bin',
                        'bint',
                    ],
                    true
                )
            ) {
                $good++;
            }
        }

        return $total > 0
            && (
                $good / $total
            ) >= 0.70;
    }

    private function validDocumentValue(
        ?string $value,
        int $minimumLetters = 3
    ): bool {
        if ($value === null) {
            return false;
        }

        if (
            preg_match(
                '/\p{Arabic}/u',
                $value
            )
        ) {
            $arabic =
                preg_replace(
                    '/[^\p{Arabic}]/u',
                    '',
                    $value
                )
                ?? '';

            return mb_strlen(
                $arabic,
                'UTF-8'
            ) >= $minimumLetters;
        }

        return $this->validEnglishValue(
            $value,
            $minimumLetters
        );
    }

    private function looksLikeFieldLabel(
        ?string $value
    ): bool {
        if ($value === null) {
            return false;
        }

        $normalized =
            strtolower(
                trim(
                    $value,
                    " \t\n\r:.-"
                )
            );

        return in_array(
            $normalized,
            [
                'nationality',
                'occupation',
                'name',
                'id no',
                'id.no',
                'expiry',
                'passport number',
                'passport expiry',
                'serial no',
                'serial number',
                'residency type',
                'employer',
                'employer / sponsor',
                'sponsor',
            ],
            true
        );
    }

    private function strictCodeAfterLabel(
        string $text,
        array $labels,
        int $minimumLength,
        int $maximumLength
    ): ?string {
        $lines =
            preg_split(
                '/\R/u',
                $this->stripBidi(
                    $text
                )
            ) ?: [];

        foreach ($lines as $line) {
            foreach ($labels as $label) {
                $label =
                    trim(
                        (string) $label
                    );

                if ($label === '') {
                    continue;
                }

                $position =
                    mb_stripos(
                        $line,
                        $label,
                        0,
                        'UTF-8'
                    );

                if ($position === false) {
                    continue;
                }

                $after =
                    mb_substr(
                        $line,
                        $position
                        + mb_strlen(
                            $label,
                            'UTF-8'
                        ),
                        null,
                        'UTF-8'
                    );

                $after =
                    preg_replace(
                        '/^[\s:;#.,\-–—"“”\'()]+/u',
                        '',
                        $after
                    )
                    ?? '';

                $pattern =
                    '/^([A-Z0-9]{'
                    . $minimumLength
                    . ','
                    . $maximumLength
                    . '})(?=[^A-Z0-9]|$)/i';

                if (
                    preg_match(
                        $pattern,
                        $after,
                        $match
                    )
                ) {
                    return strtoupper(
                        $match[1]
                    );
                }
            }
        }

        return null;
    }

    private function normalizeResidencyType(
        string $value
    ): string {
        $value =
            trim(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    $value
                )
                ?? $value
            );

        $compact =
            preg_replace(
                '/[\sـ]+/u',
                '',
                $value
            )
            ?? $value;

        foreach (
            [
                'عمل' =>
                    'WORK',

                'عائلي' =>
                    'FAMILY',

                'عائلية' =>
                    'FAMILY',

                'مستثمر' =>
                    'INVESTOR',

                'طالب' =>
                    'STUDENT',
            ]
            as $arabic => $english
        ) {
            if (
                str_contains(
                    $compact,
                    $arabic
                )
            ) {
                return $english;
            }
        }

        return $value;
    }

    private function stripBidi(
        string $value
    ): string {
        return preg_replace(
            '/[\x{200E}\x{200F}'
            . '\x{202A}-\x{202E}'
            . '\x{2066}-\x{2069}]/u',
            '',
            $value
        ) ?? $value;
    }

    public function frontMissing(
        array $fields
    ): array {
        return $this->missing(
            $fields,
            config(
                'identity_documents.'
                . 'qatar_id.front.required',
                []
            )
        );
    }

    public function backMissing(
        array $fields
    ): array {
        return $this->missing(
            $fields,
            config(
                'identity_documents.'
                . 'qatar_id.back.required',
                []
            )
        );
    }

    public function looksLikeFront(
        array $fields,
        string $text
    ): bool {
        if (
            !empty(
                $fields[
                    'qatar_id_number'
                ]
            )
        ) {
            return true;
        }

        $text =
            $this->normalizeDigits(
                $text
            );

        $hasQid =
            preg_match(
                '/(?<!\d)\d{11}(?!\d)/',
                $text
            ) === 1;

        /*
         * QID_STRONG_FRONT_SIGNAL_V6
         *
         * A real Qatar ID front may be read mainly in
         * Arabic. If a valid 11-digit QID is present
         * together with a Qatar-ID-specific English or
         * Arabic signal, this is strong enough to treat
         * the document as a Qatar ID front.
         *
         * Do not require name/nationality/DOB labels
         * before extracting the QID itself.
         */
        if (
            $hasQid
            && preg_match(
                '/(?:'
                . 'STATE\s+OF\s+QATAR'
                . '|QATAR\s+ID'
                . '|\bQID\b'
                . '|ID\.?\s*(?:NO|NUMBER)'
                . '|دولة\s*قطر'
                . '|بطاقة\s*شخصية'
                . '|البطاقة\s*الشخصية'
                . '|الرقم\s*الشخصي'
                . '|الرقم\s*الشخصى'
                . '|رقم\s*البطاقة'
                . ')/iu',
                $text
            ) === 1
        ) {
            return true;
        }

        $score = 0;

        foreach (
            [
                'name',
                'nationality',
                'date_of_birth',
                'qatar_id_expiry_date',
                'occupation',
            ]
            as $field
        ) {
            if (
                $this->hasAnyLabel(
                    $text,
                    $this->labels(
                        'front',
                        $field
                    )
                )
            ) {
                $score++;
            }
        }

        return $hasQid
            && $score >= 2;
    }

    public function looksLikeBack(
        array $fields,
        string $text
    ): bool {
        $strong = 0;

        foreach (
            [
                'document_serial_number',
                'residency_type',
                'employer',
            ]
            as $field
        ) {
            if (
                !empty(
                    $fields[$field]
                )
                || $this->hasAnyLabel(
                    $text,
                    $this->labels(
                        'back',
                        $field
                    )
                )
            ) {
                $strong++;
            }
        }

        $passportSignals = 0;

        foreach (
            [
                'passport_number',
                'passport_expiry_date',
            ]
            as $field
        ) {
            if (
                !empty(
                    $fields[$field]
                )
                || $this->hasAnyLabel(
                    $text,
                    $this->labels(
                        'back',
                        $field
                    )
                )
            ) {
                $passportSignals++;
            }
        }

        return (
            $strong >= 2
            || (
                $strong >= 1
                && $passportSignals >= 1
            )
        );
    }

    private function labels(
        string $side,
        string $field
    ): array {
        return config(
            'identity_documents.'
            . 'qatar_id.'
            . $side
            . '.fields.'
            . $field,
            []
        );
    }

    private function fillTextField(
        array &$fields,
        string $field,
        string $side,
        string $text
    ): void {
        $detected =
            $this->value(
                $text,
                $this->labels(
                    $side,
                    $field
                )
            );

        if ($detected !== null) {
            $fields[$field] =
                $detected;
        }
    }

    private function fillDateField(
        array &$fields,
        string $field,
        string $side,
        string $text
    ): void {
        $detected =
            $this->value(
                $text,
                $this->labels(
                    $side,
                    $field
                )
            );

        $date =
            $this->date(
                $detected
            );

        if ($date !== null) {
            $fields[$field] =
                $date;
        }
    }

    private function qidNumber(
        array $fields,
        string $text
    ): ?string {
        $existing =
            (string) (
                $fields[
                    'qatar_id_number'
                ]
                ?? ''
            );

        if (
            preg_match(
                '/(?<!\d)(\d{11})(?!\d)/',
                $this->normalizeDigits(
                    $existing
                ),
                $match
            )
        ) {
            return $match[1];
        }

        $labelled =
            $this->value(
                $text,
                $this->labels(
                    'front',
                    'qatar_id_number'
                )
            );

        if (
            $labelled !== null
            && preg_match(
                '/(?<!\d)(\d{11})(?!\d)/',
                $this->normalizeDigits(
                    $labelled
                ),
                $match
            )
        ) {
            return $match[1];
        }

        if (
            preg_match(
                '/(?<!\d)(\d{11})(?!\d)/',
                $this->normalizeDigits(
                    $text
                ),
                $match
            )
        ) {
            return $match[1];
        }

        return null;
    }

    private function value(
        string $text,
        array $labels
    ): ?string {
        if ($labels === []) {
            return null;
        }

        $lines =
            preg_split(
                '/\R/u',
                $this->normalizeDigits(
                    $text
                )
            ) ?: [];

        foreach (
            $lines
            as $index => $line
        ) {
            $line =
                trim(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $line
                    )
                    ?? $line
                );

            if ($line === '') {
                continue;
            }

            foreach (
                $labels
                as $label
            ) {
                $position =
                    mb_stripos(
                        $line,
                        $label,
                        0,
                        'UTF-8'
                    );

                if ($position === false) {
                    continue;
                }

                $before =
                    mb_substr(
                        $line,
                        0,
                        $position,
                        'UTF-8'
                    );

                $after =
                    mb_substr(
                        $line,
                        $position
                        + mb_strlen(
                            $label,
                            'UTF-8'
                        ),
                        null,
                        'UTF-8'
                    );

                $after =
                    $this->clean(
                        $after
                    );

                $before =
                    $this->clean(
                        $before
                    );

                /*
                 * English OCR normally puts value
                 * after label. RTL OCR may put the
                 * value before the Arabic label.
                 */
                if ($after !== null) {
                    return $after;
                }

                if ($before !== null) {
                    return $before;
                }

                foreach (
                    [
                        $index + 1,
                        $index + 2,
                        $index - 1,
                    ]
                    as $near
                ) {
                    if (
                        !isset(
                            $lines[$near]
                        )
                    ) {
                        continue;
                    }

                    $candidate =
                        $this->clean(
                            $lines[$near]
                        );

                    if (
                        $candidate !== null
                        && !$this->looksLikeLabel(
                            $candidate
                        )
                    ) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    private function clean(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            trim(
                preg_replace(
                    '/^[\s:：\-–—|]+|[\s:：\-–—|]+$/u',
                    '',
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $value
                    )
                    ?? $value
                )
            );

        if (
            $value === ''
            || mb_strlen(
                $value,
                'UTF-8'
            ) > 160
        ) {
            return null;
        }

        return $value;
    }

    private function looksLikeLabel(
        string $value
    ): bool {
        foreach (
            [
                'front',
                'back',
            ]
            as $side
        ) {
            $fields =
                config(
                    'identity_documents.'
                    . 'qatar_id.'
                    . $side
                    . '.fields',
                    []
                );

            foreach (
                $fields
                as $labels
            ) {
                foreach (
                    $labels
                    as $label
                ) {
                    if (
                        mb_stripos(
                            $value,
                            $label,
                            0,
                            'UTF-8'
                        ) !== false
                    ) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function hasAnyLabel(
        string $text,
        array $labels
    ): bool {
        foreach (
            $labels
            as $label
        ) {
            if (
                mb_stripos(
                    $text,
                    $label,
                    0,
                    'UTF-8'
                ) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    private function structuredDateOfBirth(
        string $text
    ): ?string {
        $text =
            $this->normalizeDigits(
                $text
            );

        $lines =
            preg_split(
                '/\R/u',
                $text
            ) ?: [];

        /*
         * Highest confidence:
         * a date on the same OCR line as the
         * Arabic "date of birth" label.
         *
         * This survives common English OCR damage
         * such as D.O.B -> D.0.B / 0.0.8.
         */
        $arabicCandidates = [];

        foreach (
            $lines
            as $line
        ) {
            if (
                mb_stripos(
                    $line,
                    'تاريخ الميلاد',
                    0,
                    'UTF-8'
                ) === false
            ) {
                continue;
            }

            foreach (
                $this->datesFromText(
                    $line
                )
                as $date
            ) {
                $arabicCandidates[$date] =
                    true;
            }
        }

        $arabicDates =
            array_keys(
                $arabicCandidates
            );

        if (
            count(
                $arabicDates
            ) === 1
        ) {
            return $arabicDates[0];
        }

        /*
         * Next confidence:
         * normal configured DOB labels.
         */
        $labelCandidates = [];

        $labels =
            $this->labels(
                'front',
                'date_of_birth'
            );

        foreach (
            $lines
            as $index => $line
        ) {
            $matched = false;

            foreach (
                $labels
                as $label
            ) {
                if (
                    mb_stripos(
                        $line,
                        $label,
                        0,
                        'UTF-8'
                    ) !== false
                ) {
                    $matched =
                        true;

                    break;
                }
            }

            if (!$matched) {
                continue;
            }

            $searchLines = [
                $line,
            ];

            if (
                isset(
                    $lines[
                        $index + 1
                    ]
                )
            ) {
                $searchLines[] =
                    $lines[
                        $index + 1
                    ];
            }

            foreach (
                $searchLines
                as $searchLine
            ) {
                foreach (
                    $this->datesFromText(
                        $searchLine
                    )
                    as $date
                ) {
                    $labelCandidates[
                        $date
                    ] = true;
                }
            }
        }

        $labelDates =
            array_keys(
                $labelCandidates
            );

        if (
            count(
                $labelDates
            ) === 1
        ) {
            return $labelDates[0];
        }

        /*
         * OCR-confusion fallback.
         *
         * Examples:
         * D.O.B
         * D.0.B
         * D.O.8
         * 0.0.8
         */
        $ocrLabelCandidates = [];

        foreach (
            $lines
            as $index => $line
        ) {
            if (
                !preg_match(
                    '/(?:^|[^A-Z0-9])'
                    . '[D0O]'
                    . '[.\s]*'
                    . '[O0]'
                    . '[.\s]*'
                    . '[B8]'
                    . '\s*[:：]?/iu',
                    $line
                )
            ) {
                continue;
            }

            $searchLines = [
                $line,
            ];

            if (
                isset(
                    $lines[
                        $index + 1
                    ]
                )
            ) {
                $searchLines[] =
                    $lines[
                        $index + 1
                    ];
            }

            foreach (
                $searchLines
                as $searchLine
            ) {
                foreach (
                    $this->datesFromText(
                        $searchLine
                    )
                    as $date
                ) {
                    $ocrLabelCandidates[
                        $date
                    ] = true;
                }
            }
        }

        $ocrLabelDates =
            array_keys(
                $ocrLabelCandidates
            );

        if (
            count(
                $ocrLabelDates
            ) === 1
        ) {
            return $ocrLabelDates[0];
        }

        /*
         * Conflicting OCR variants remain blank
         * unless the Arabic field label resolves
         * the conflict above.
         */
        return null;
    }

    private function datesFromText(
        string $text
    ): array {
        $text =
            $this->normalizeDigits(
                $text
            );

        preg_match_all(
            '/(?<!\d)(?:'
            . '\d{1,2}[\/.\-]\d{1,2}[\/.\-]\d{4}'
            . '|'
            . '\d{4}[\/.\-]\d{1,2}[\/.\-]\d{1,2}'
            . ')(?!\d)/u',
            $text,
            $matches
        );

        $dates = [];

        foreach (
            $matches[0] ?? []
            as $raw
        ) {
            $date =
                $this->date(
                    $raw
                );

            if (
                $date !== null
                && $date
                    <= date('Y-m-d')
            ) {
                $dates[$date] =
                    true;
            }
        }

        return array_keys(
            $dates
        );
    }

    private function fallbackDateOfBirth(
        string $text,
        ?string $knownExpiry
    ): ?string {
        $text =
            $this->normalizeDigits(
                $text
            );

        $candidates = [];

        preg_match_all(
            '/(?<!\\d)(?:'
            . '\\d{1,2}[\\/.\\-]\\d{1,2}[\\/.\\-]\\d{4}'
            . '|'
            . '\\d{4}[\\/.\\-]\\d{1,2}[\\/.\\-]\\d{1,2}'
            . ')(?!\\d)/u',
            $text,
            $matches
        );

        foreach (
            $matches[0] ?? []
            as $raw
        ) {
            $date =
                $this->date(
                    $raw
                );

            if (
                $date === null
                || $date
                    === $knownExpiry
            ) {
                continue;
            }

            /*
             * DOB must not be in the future.
             */
            if (
                $date
                > date('Y-m-d')
            ) {
                continue;
            }

            $candidates[$date] =
                true;
        }

        $dates =
            array_keys(
                $candidates
            );

        /*
         * Best case: after excluding QID expiry,
         * exactly one valid past date remains.
         */
        if (
            count($dates)
            === 1
        ) {
            return $dates[0];
        }

        /*
         * If OCR produced duplicates/noise but
         * multiple valid dates remain, choose only
         * when one date is clearly the oldest.
         *
         * This is appropriate for the QID front
         * structure and avoids inventing a value
         * when confidence is low.
         */
        if (
            count($dates)
            > 1
        ) {
            sort(
                $dates,
                SORT_STRING
            );

            $oldest =
                $dates[0];

            $second =
                $dates[1];

            $oldestYear =
                (int) substr(
                    $oldest,
                    0,
                    4
                );

            $secondYear =
                (int) substr(
                    $second,
                    0,
                    4
                );

            if (
                $secondYear
                - $oldestYear
                >= 5
            ) {
                return $oldest;
            }
        }

        return null;
    }

    private function date(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            $this->normalizeDigits(
                $value
            );

        if (
            preg_match(
                '/(?<!\d)(\d{1,2})'
                . '[\/.\-]'
                . '(\d{1,2})'
                . '[\/.\-]'
                . '(\d{4})(?!\d)/',
                $value,
                $match
            )
        ) {
            $day =
                (int) $match[1];

            $month =
                (int) $match[2];

            $year =
                (int) $match[3];

            if (
                checkdate(
                    $month,
                    $day,
                    $year
                )
            ) {
                return sprintf(
                    '%04d-%02d-%02d',
                    $year,
                    $month,
                    $day
                );
            }
        }

        if (
            preg_match(
                '/(?<!\d)(\d{4})'
                . '[\/.\-]'
                . '(\d{1,2})'
                . '[\/.\-]'
                . '(\d{1,2})(?!\d)/',
                $value,
                $match
            )
        ) {
            $year =
                (int) $match[1];

            $month =
                (int) $match[2];

            $day =
                (int) $match[3];

            if (
                checkdate(
                    $month,
                    $day,
                    $year
                )
            ) {
                return sprintf(
                    '%04d-%02d-%02d',
                    $year,
                    $month,
                    $day
                );
            }
        }

        return null;
    }

    private function gender(
        string $value
    ): ?string {
        $value =
            mb_strtolower(
                trim($value),
                'UTF-8'
            );

        if (
            str_starts_with(
                $value,
                'm'
            )
            || str_contains(
                $value,
                'male'
            )
            || str_contains(
                $value,
                'ذكر'
            )
        ) {
            return 'M';
        }

        if (
            str_starts_with(
                $value,
                'f'
            )
            || str_contains(
                $value,
                'female'
            )
            || str_contains(
                $value,
                'أنث'
            )
        ) {
            return 'F';
        }

        if (
            str_starts_with(
                $value,
                'x'
            )
        ) {
            return 'X';
        }

        return null;
    }

    private function normalizeDigits(
        string $value
    ): string {
        return strtr(
            $value,
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
    }

    private function missing(
        array $fields,
        array $required
    ): array {
        $missing = [];

        foreach (
            $required
            as $field
        ) {
            if (
                !isset(
                    $fields[$field]
                )
                || trim(
                    (string) $fields[$field]
                ) === ''
            ) {
                $missing[] =
                    $field;
            }
        }

        return $missing;
    }
}
