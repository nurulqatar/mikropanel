<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

class PassportStructureService
{
    public function __construct(
        private PassportCountryRegistryService $registry
    ) {
    }

    public function augment(
        array $fields,
        string $text
    ): array {
        if (
            $this->isExcludedDocument(
                $text
            )
        ) {
            return $fields;
        }

        $td3 =
            $this->findTd3(
                $text
            );

        if ($td3 === null) {
            return $fields;
        }

        $line1 =
            $td3['line1'];

        $line2 =
            $td3['line2'];

        $issuer =
            $this->alphaCode(
                substr(
                    $line1,
                    2,
                    3
                )
            );

        $nationality =
            $this->alphaCode(
                substr(
                    $line2,
                    10,
                    3
                )
            );

        $profile =
            $this->registry
                ->profile(
                    $issuer
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

        $dateOfBirth =
            $this->mrzDate(
                substr(
                    $line2,
                    13,
                    6
                ),
                true
            );

        $gender =
            strtoupper(
                substr(
                    $line2,
                    20,
                    1
                )
            );

        if (
            !in_array(
                $gender,
                [
                    'M',
                    'F',
                    'X',
                ],
                true
            )
        ) {
            $gender = null;
        }

        $expiry =
            $this->mrzDate(
                substr(
                    $line2,
                    21,
                    6
                ),
                false
            );

        $name =
            $this->mrzName(
                substr(
                    $line1,
                    5
                )
            );

        /*
         * ==========================================
         * AUTHORITATIVE TD3 MRZ FIELDS
         * ==========================================
         *
         * Printed OCR must never overwrite these.
         */

        $fields['identity_type'] =
            'passport';

        if ($passportNumber !== '') {
            $fields['passport_number'] =
                $passportNumber;

            $fields['identity_number'] =
                $passportNumber;
        }

        if ($name !== null) {
            $fields['name'] =
                $name;
        }

        if ($nationality !== null) {
            $fields['nationality'] =
                $nationality;
        }

        if ($dateOfBirth !== null) {
            $fields['date_of_birth'] =
                $dateOfBirth;
        }

        if ($gender !== null) {
            $fields['gender'] =
                $gender;
        }

        if ($expiry !== null) {
            $fields[
                'passport_expiry_date'
            ] = $expiry;

            $fields[
                'document_expiry_date'
            ] = $expiry;
        }

        if ($issuer !== null) {
            $fields['issuing_country'] =
                $issuer;
        }

        /*
         * ==========================================
         * VIZ / PRINTED FIELDS
         * ==========================================
         */

        $labels =
            $profile['labels']
            ?? [];

        $issueDate =
            $this->extractIssueDate(
                $text,
                $labels[
                    'issue_date'
                ]
                ?? []
            );

        if ($issueDate !== null) {
            $fields[
                'passport_issue_date'
            ] = $issueDate;
        }

        $place =
            $this->extractPlaceOfBirth(
                $text,
                $labels[
                    'place_of_birth'
                ]
                ?? []
            );

        $place =
            $this->applyAlias(
                $place,
                $profile[
                    'place_aliases'
                ]
                ?? []
            );

        if ($place !== null) {
            $fields[
                'place_of_birth'
            ] = $place;
        }

        $authority =
            $this->extractAuthority(
                $text,
                $labels[
                    'issuing_authority'
                ]
                ?? []
            );

        $authority =
            $this->applyAlias(
                $authority,
                $profile[
                    'authority_aliases'
                ]
                ?? []
            );

        if ($authority !== null) {
            $fields[
                'issuing_authority'
            ] = $authority;
        }

        return $fields;
    }

    public function isExcludedDocument(
        string $text
    ): bool {
        return preg_match(
            '/\b(?:'
            . 'DIPLOMATIC\s+PASSPORT'
            . '|DIPLOMATIQUE'
            . '|SERVICE\s+PASSPORT'
            . '|OFFICIAL\s+PASSPORT'
            . '|SPECIAL\s+PASSPORT'
            . '|EMERGENCY\s+PASSPORT'
            . '|REFUGEE\s+TRAVEL'
            . '|REFUGEE\s+DOCUMENT'
            . '|LAISSEZ[\s\-]*PASSER'
            . ')\b/iu',
            $text
        ) === 1;
    }

    private function findTd3(
        string $text
    ): ?array {
        $rawLines =
            preg_split(
                '/\R/u',
                strtoupper($text)
            )
            ?: [];

        $lines = [];

        foreach ($rawLines as $raw) {
            $line =
                preg_replace(
                    '/[^A-Z0-9<]/',
                    '',
                    $raw
                )
                ?? '';

            if (
                strlen($line)
                >= 30
            ) {
                $lines[] =
                    $line;
            }
        }

        $best = null;

        for (
            $i = 0;
            $i < count($lines);
            $i++
        ) {
            if (
                !str_contains(
                    $lines[$i],
                    'P'
                )
            ) {
                continue;
            }

            foreach (
                $this->line1Candidates(
                    $lines[$i]
                )
                as $line1
            ) {
                for (
                    $j = $i + 1;
                    $j <= min(
                        $i + 3,
                        count($lines) - 1
                    );
                    $j++
                ) {
                    foreach (
                        $this->line2Candidates(
                            $lines[$j]
                        )
                        as $line2
                    ) {
                        $score =
                            $this->validateLine2(
                                $line2
                            );

                        if ($score === null) {
                            continue;
                        }

                        if (
                            $best === null
                            || $score
                                > $best[
                                    'score'
                                ]
                        ) {
                            $best = [
                                'line1' =>
                                    $this->normalizeLine1(
                                        $line1
                                    ),

                                'line2' =>
                                    $line2,

                                'score' =>
                                    $score,
                            ];
                        }
                    }
                }
            }
        }

        return $best;
    }

    private function line1Candidates(
        string $line
    ): array {
        $result = [];

        for (
            $offset = 0;
            $offset < strlen($line);
            $offset++
        ) {
            if (
                $line[$offset]
                !== 'P'
            ) {
                continue;
            }

            $candidate =
                substr(
                    $line,
                    $offset,
                    44
                );

            if (
                strlen($candidate)
                < 35
            ) {
                continue;
            }

            $candidate =
                str_pad(
                    $candidate,
                    44,
                    '<'
                );

            $result[] =
                substr(
                    $candidate,
                    0,
                    44
                );
        }

        return array_values(
            array_unique(
                $result
            )
        );
    }

    private function line2Candidates(
        string $line
    ): array {
        $length =
            strlen($line);

        if ($length < 40) {
            return [];
        }

        $result = [];

        if ($length <= 44) {
            $result[] =
                str_pad(
                    $line,
                    44,
                    '<'
                );
        } else {
            for (
                $start = 0;
                $start <= min(
                    20,
                    $length - 44
                );
                $start++
            ) {
                $result[] =
                    substr(
                        $line,
                        $start,
                        44
                    );
            }
        }

        return array_values(
            array_unique(
                array_map(
                    fn (string $value) =>
                        $this->normalizeLine2(
                            $value
                        ),
                    $result
                )
            )
        );
    }

    private function normalizeLine1(
        string $line
    ): string {
        $line =
            str_pad(
                substr(
                    $line,
                    0,
                    44
                ),
                44,
                '<'
            );

        /*
         * Country + holder name positions should be
         * alphabetic. Fix common OCR digit confusion.
         */
        $map = [
            '0' => 'O',
            '1' => 'I',
            '2' => 'Z',
            '5' => 'S',
            '6' => 'G',
            '8' => 'B',
        ];

        for (
            $i = 2;
            $i < 44;
            $i++
        ) {
            if (
                isset(
                    $map[
                        $line[$i]
                    ]
                )
            ) {
                $line[$i] =
                    $map[
                        $line[$i]
                    ];
            }
        }

        return $line;
    }

    private function normalizeLine2(
        string $line
    ): string {
        $line =
            str_pad(
                substr(
                    $line,
                    0,
                    44
                ),
                44,
                '<'
            );

        $digitMap = [
            'O' => '0',
            'Q' => '0',
            'D' => '0',
            'I' => '1',
            'L' => '1',
            'Z' => '2',
            'S' => '5',
            'G' => '6',
            'B' => '8',
        ];

        $numericPositions = [
            9,
            13, 14, 15, 16, 17, 18,
            19,
            21, 22, 23, 24, 25, 26,
            27,
            43,
        ];

        foreach (
            $numericPositions
            as $position
        ) {
            if (
                isset(
                    $digitMap[
                        $line[$position]
                    ]
                )
            ) {
                $line[$position] =
                    $digitMap[
                        $line[$position]
                    ];
            }
        }

        $alphaMap = [
            '0' => 'O',
            '1' => 'I',
            '2' => 'Z',
            '5' => 'S',
            '6' => 'G',
            '8' => 'B',
        ];

        foreach (
            [10, 11, 12]
            as $position
        ) {
            if (
                isset(
                    $alphaMap[
                        $line[$position]
                    ]
                )
            ) {
                $line[$position] =
                    $alphaMap[
                        $line[$position]
                    ];
            }
        }

        return $line;
    }

    private function validateLine2(
        string $line
    ): ?int {
        if (
            strlen($line)
            !== 44
        ) {
            return null;
        }

        $documentCheck =
            $this->digit(
                $line[9]
            );

        $birthCheck =
            $this->digit(
                $line[19]
            );

        $expiryCheck =
            $this->digit(
                $line[27]
            );

        if (
            $documentCheck === null
            || $birthCheck === null
            || $expiryCheck === null
        ) {
            return null;
        }

        if (
            $this->checkDigit(
                substr(
                    $line,
                    0,
                    9
                )
            )
            !== $documentCheck
        ) {
            return null;
        }

        if (
            $this->checkDigit(
                substr(
                    $line,
                    13,
                    6
                )
            )
            !== $birthCheck
        ) {
            return null;
        }

        if (
            $this->checkDigit(
                substr(
                    $line,
                    21,
                    6
                )
            )
            !== $expiryCheck
        ) {
            return null;
        }

        $score = 30;

        $composite =
            $this->digit(
                $line[43]
            );

        if ($composite !== null) {
            $data =
                substr(
                    $line,
                    0,
                    10
                )
                . substr(
                    $line,
                    13,
                    7
                )
                . substr(
                    $line,
                    21,
                    22
                );

            if (
                $this->checkDigit(
                    $data
                )
                === $composite
            ) {
                $score += 10;
            }
        }

        if (
            preg_match(
                '/^[A-Z]{3}$/',
                substr(
                    $line,
                    10,
                    3
                )
            )
        ) {
            $score += 5;
        }

        return $score;
    }

    private function checkDigit(
        string $value
    ): int {
        $weights = [
            7,
            3,
            1,
        ];

        $sum = 0;

        for (
            $i = 0;
            $i < strlen($value);
            $i++
        ) {
            $char =
                $value[$i];

            if ($char === '<') {
                $number = 0;
            } elseif (
                ctype_digit($char)
            ) {
                $number =
                    (int) $char;
            } else {
                $number =
                    ord($char)
                    - ord('A')
                    + 10;
            }

            $sum +=
                $number
                * $weights[
                    $i % 3
                ];
        }

        return $sum % 10;
    }

    private function digit(
        string $value
    ): ?int {
        return ctype_digit(
            $value
        )
            ? (int) $value
            : null;
    }

    private function alphaCode(
        string $value
    ): ?string {
        $value =
            strtoupper(
                str_replace(
                    '<',
                    '',
                    trim($value)
                )
            );

        return preg_match(
            '/^[A-Z]{3}$/',
            $value
        )
            ? $value
            : null;
    }

    private function mrzName(
        string $value
    ): ?string {
        $parts =
            explode(
                '<<',
                $value,
                2
            );

        $surname =
            $this->mrzNamePart(
                $parts[0]
                ?? ''
            );

        $given =
            $this->mrzNamePart(
                $parts[1]
                ?? ''
            );

        /*
         * Client-facing name:
         * Given Names + Surname.
         */
        $name =
            trim(
                implode(
                    ' ',
                    array_filter(
                        [
                            $given,
                            $surname,
                        ]
                    )
                )
            );

        return $name !== ''
            ? $name
            : null;
    }

    private function mrzNamePart(
        string $value
    ): string {
        $value =
            preg_replace(
                '/<+/',
                ' ',
                $value
            )
            ?? '';

        $value =
            preg_replace(
                '/\s+/',
                ' ',
                trim($value)
            )
            ?? '';

        /*
         * Only valid MRZ letters/spaces survive.
         */
        $value =
            preg_replace(
                '/[^A-Z\s\'\-]/',
                '',
                strtoupper($value)
            )
            ?? '';

        return trim(
            preg_replace(
                '/\s+/',
                ' ',
                $value
            )
            ?? ''
        );
    }

    private function mrzDate(
        string $value,
        bool $birth
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

        $today =
            new DateTimeImmutable(
                'today',
                new DateTimeZone(
                    'UTC'
                )
            );

        if ($birth) {
            foreach (
                [
                    2000 + $yy,
                    1900 + $yy,
                ]
                as $year
            ) {
                if (
                    !checkdate(
                        $month,
                        $day,
                        $year
                    )
                ) {
                    continue;
                }

                $date =
                    new DateTimeImmutable(
                        sprintf(
                            '%04d-%02d-%02d',
                            $year,
                            $month,
                            $day
                        )
                    );

                if ($date > $today) {
                    continue;
                }

                if (
                    $date
                        ->diff(
                            $today
                        )
                        ->y
                    <= 120
                ) {
                    return $date
                        ->format(
                            'Y-m-d'
                        );
                }
            }

            return null;
        }

        $currentYear =
            (int) $today
                ->format('Y');

        $candidates = [];

        foreach (
            [
                1900 + $yy,
                2000 + $yy,
                2100 + $yy,
            ]
            as $year
        ) {
            if (
                !checkdate(
                    $month,
                    $day,
                    $year
                )
            ) {
                continue;
            }

            if (
                $year
                    < $currentYear - 20
                || $year
                    > $currentYear + 20
            ) {
                continue;
            }

            $candidates[] =
                new DateTimeImmutable(
                    sprintf(
                        '%04d-%02d-%02d',
                        $year,
                        $month,
                        $day
                    )
                );
        }

        if ($candidates === []) {
            return null;
        }

        usort(
            $candidates,
            fn (
                DateTimeImmutable $a,
                DateTimeImmutable $b
            ) =>
                abs(
                    $today
                        ->diff($a)
                        ->days
                )
                <=>
                abs(
                    $today
                        ->diff($b)
                        ->days
                )
        );

        return $candidates[0]
            ->format(
                'Y-m-d'
            );
    }

    private function extractIssueDate(
        string $text,
        array $labels
    ): ?string {
        foreach (
            $this->windowsForLabels(
                $text,
                $labels
            )
            as $window
        ) {
            $date =
                $this->findPrintedDate(
                    $window
                );

            if ($date !== null) {
                return $date;
            }
        }

        return null;
    }

    private function extractPlaceOfBirth(
        string $text,
        array $labels
    ): ?string {
        foreach (
            $this->windowsForLabels(
                $text,
                $labels
            )
            as $window
        ) {
            $lines =
                preg_split(
                    '/\R/u',
                    $window
                )
                ?: [];

            foreach ($lines as $line) {
                $line =
                    $this->cleanVizLine(
                        $line
                    );

                /*
                 * Remove sex marker when both fields
                 * share one visual row.
                 */
                $line =
                    preg_replace(
                        '/^[MFX]\s*[\W_]*/i',
                        '',
                        $line
                    )
                    ?? $line;

                /*
                 * Remove common trailing personal /
                 * internal numeric identifiers.
                 */
                $line =
                    preg_replace(
                        '/[\(\[]?\d{4,}.*$/',
                        '',
                        $line
                    )
                    ?? $line;

                $line =
                    trim(
                        $line,
                        " \t\n\r\0\x0B.,;:|>-_<"
                    );

                if (
                    $this->validVizText(
                        $line,
                        2,
                        80
                    )
                    && !$this->containsAnyLabel(
                        $line,
                        $labels
                    )
                ) {
                    return strtoupper(
                        $line
                    );
                }
            }
        }

        return null;
    }

    private function extractAuthority(
        string $text,
        array $labels
    ): ?string {
        foreach (
            $this->windowsForLabels(
                $text,
                $labels
            )
            as $window
        ) {
            $lines =
                preg_split(
                    '/\R/u',
                    $window
                )
                ?: [];

            foreach ($lines as $line) {
                $line =
                    $this->cleanVizLine(
                        $line
                    );

                /*
                 * A combined line may begin with
                 * Date of Issue value followed by
                 * Issuing Authority value.
                 */
                $line =
                    preg_replace(
                        '/^\s*'
                        . '\d{1,2}'
                        . '[\s\-\/.]+'.
                        '[A-Z]{3,9}'
                        . '[\s\-\/.]+'.
                        '\d{2,4}'
                        . '[\s.,;:|-]*/iu',
                        '',
                        $line
                    )
                    ?? $line;

                $line =
                    trim(
                        $line,
                        " \t\n\r\0\x0B.,;:|"
                    );

                if (
                    $this->validVizText(
                        $line,
                        2,
                        100
                    )
                    && !$this->containsAnyLabel(
                        $line,
                        $labels
                    )
                    && !$this->findPrintedDate(
                        $line
                    )
                ) {
                    return strtoupper(
                        $line
                    );
                }
            }
        }

        return null;
    }

    private function windowsForLabels(
        string $text,
        array $labels
    ): array {
        $lines =
            preg_split(
                '/\R/u',
                $text
            )
            ?: [];

        $windows = [];

        foreach (
            $lines as $index => $line
        ) {
            foreach ($labels as $label) {
                if (
                    mb_stripos(
                        $line,
                        $label
                    ) === false
                ) {
                    continue;
                }

                $parts = [];

                /*
                 * Same line text after label.
                 */
                $position =
                    mb_stripos(
                        $line,
                        $label
                    );

                if ($position !== false) {
                    $after =
                        mb_substr(
                            $line,
                            $position
                            + mb_strlen(
                                $label
                            )
                        );

                    $after =
                        trim(
                            $after,
                            " \t:;|/-"
                        );

                    if ($after !== '') {
                        $parts[] =
                            $after;
                    }
                }

                /*
                 * Then nearby visual lines.
                 */
                for (
                    $step = 1;
                    $step <= 4;
                    $step++
                ) {
                    if (
                        isset(
                            $lines[
                                $index
                                + $step
                            ]
                        )
                    ) {
                        $parts[] =
                            $lines[
                                $index
                                + $step
                            ];
                    }
                }

                if ($parts !== []) {
                    $windows[] =
                        implode(
                            "\n",
                            $parts
                        );
                }
            }
        }

        return $windows;
    }

    private function containsAnyLabel(
        string $value,
        array $labels
    ): bool {
        foreach ($labels as $label) {
            if (
                mb_stripos(
                    $value,
                    $label
                ) !== false
            ) {
                return true;
            }
        }

        return false;
    }

    private function cleanVizLine(
        string $value
    ): string {
        $value =
            preg_replace(
                '/\s+/u',
                ' ',
                trim($value)
            )
            ?? '';

        return trim($value);
    }

    private function validVizText(
        string $value,
        int $min,
        int $max
    ): bool {
        $length =
            mb_strlen($value);

        if (
            $length < $min
            || $length > $max
        ) {
            return false;
        }

        if (
            substr_count(
                $value,
                '<'
            ) >= 2
        ) {
            return false;
        }

        return preg_match(
            '/\p{L}/u',
            $value
        ) === 1;
    }

    private function applyAlias(
        ?string $value,
        array $aliases
    ): ?string {
        if ($value === null) {
            return null;
        }

        $normalized =
            strtoupper(
                preg_replace(
                    '/\s+/u',
                    ' ',
                    trim($value)
                )
                ?? ''
            );

        foreach (
            $aliases
            as $from => $to
        ) {
            if (
                strtoupper(
                    trim(
                        (string) $from
                    )
                )
                === $normalized
            ) {
                return (string) $to;
            }
        }

        return $value;
    }

    private function findPrintedDate(
        string $value
    ): ?string {
        $value =
            strtoupper($value);

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
            'OCT' => 10,
            'NOV' => 11,
            'DEC' => 12,

            'JANV' => 1,
            'FEVR' => 2,
            'FÉVR' => 2,
            'MARS' => 3,
            'AVR' => 4,
            'MAI' => 5,
            'JUIN' => 6,
            'JUIL' => 7,
            'AOUT' => 8,
            'AOÛT' => 8,
            'SEPT' => 9,
            'OCT' => 10,
            'NOV' => 11,
            'DEC' => 12,
            'DÉC' => 12,

            'ENE' => 1,
            'FEB' => 2,
            'MAR' => 3,
            'ABR' => 4,
            'MAY' => 5,
            'JUN' => 6,
            'JUL' => 7,
            'AGO' => 8,
            'SEP' => 9,
            'OCT' => 10,
            'NOV' => 11,
            'DIC' => 12,
        ];

        if (
            preg_match(
                '/\b'
                . '(\d{1,2})'
                . '\s+'
                . '([A-ZÉÛ]{3,5})'
                . '\s+'
                . '(\d{4})'
                . '\b/u',
                $value,
                $match
            )
        ) {
            $month =
                $months[
                    $match[2]
                ]
                ?? null;

            if (
                $month !== null
                && checkdate(
                    $month,
                    (int) $match[1],
                    (int) $match[3]
                )
            ) {
                return sprintf(
                    '%04d-%02d-%02d',
                    (int) $match[3],
                    $month,
                    (int) $match[1]
                );
            }
        }

        foreach (
            [
                '/\b(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})\b/',
                '/\b(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})\b/',
            ]
            as $index => $pattern
        ) {
            if (
                !preg_match(
                    $pattern,
                    $value,
                    $match
                )
            ) {
                continue;
            }

            if ($index === 0) {
                $year =
                    (int) $match[1];

                $month =
                    (int) $match[2];

                $day =
                    (int) $match[3];
            } else {
                $day =
                    (int) $match[1];

                $month =
                    (int) $match[2];

                $year =
                    (int) $match[3];
            }

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
}
