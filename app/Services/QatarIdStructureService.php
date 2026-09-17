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

            $this->fillDateField(
                $fields,
                'date_of_birth',
                'front',
                $text
            );

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
            $this->fillTextField(
                $fields,
                'passport_number',
                'back',
                $text
            );

            if (
                !empty(
                    $fields[
                        'passport_number'
                    ]
                )
            ) {
                $fields[
                    'passport_number'
                ] =
                    strtoupper(
                        preg_replace(
                            '/[^A-Z0-9]/i',
                            '',
                            (string) $fields[
                                'passport_number'
                            ]
                        )
                        ?? ''
                    );
            }

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

        return $fields;
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
