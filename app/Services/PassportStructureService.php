<?php

namespace App\Services;

use DateTimeImmutable;
use DateTimeZone;

class PassportStructureService
{
    public function augment(
        array $fields,
        string $text
    ): array {
        /*
         * Only ordinary/personal passport bio pages.
         * Never promote diplomatic/service/special/
         * emergency/refugee travel documents here.
         */
        if ($this->isExcludedDocument($text)) {
            return $fields;
        }

        $td3 = $this->findTd3($text);

        if ($td3 === null) {
            return $fields;
        }

        $line1 = $td3['line1'];
        $line2 = $td3['line2'];

        $passportNumber =
            rtrim(
                substr(
                    $line2,
                    0,
                    9
                ),
                '<'
            );

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

        $birthDate =
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

        $expiryDate =
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
         * A validated MRZ is the authoritative source
         * for the machine-readable passport fields.
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

        if ($birthDate !== null) {
            $fields['date_of_birth'] =
                $birthDate;
        }

        if ($gender !== null) {
            $fields['gender'] =
                $gender;
        }

        if ($expiryDate !== null) {
            $fields['passport_expiry_date'] =
                $expiryDate;

            $fields['document_expiry_date'] =
                $expiryDate;
        }

        /*
         * Prefer an already-good printed nationality
         * over the three-letter MRZ code.
         */
        /*
         * Validated MRZ always wins over generic OCR
         * for nationality and issuing country.
         */
        if ($nationality !== null) {
            $fields['nationality'] =
                $nationality;
        }

        if ($issuer !== null) {
            $fields['issuing_country'] =
                $issuer;
        }

        /*
         * These values are not encoded in TD3 MRZ,
         * so read them from the visible bio page.
         */
        $fields =
            $this->augmentPrintedFields(
                $fields,
                $text
            );

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
            . '|TRAVEL\s+DOCUMENT'
            . '|LAISSEZ[\s\-]*PASSER'
            . ')\b/iu',
            $text
        ) === 1;
    }

    private function findTd3(
        string $text
    ): ?array {
        $lines = [];

        foreach (
            preg_split(
                '/\R/u',
                strtoupper($text)
            ) ?: []
            as $rawLine
        ) {
            $line =
                preg_replace(
                    '/[^A-Z0-9<]/',
                    '',
                    $rawLine
                )
                ?? '';

            if (
                strlen($line)
                >= 35
            ) {
                $lines[] = $line;
            }
        }

        $best = null;

        for (
            $i = 0;
            $i < count($lines) - 1;
            $i++
        ) {
            foreach (
                $this->line1Candidates(
                    $lines[$i]
                )
                as $line1
            ) {
                if (
                    !str_starts_with(
                        $line1,
                        'P'
                    )
                ) {
                    continue;
                }

                foreach (
                    $this->line2Candidates(
                        $lines[$i + 1]
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
                        || $score >
                            $best['score']
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

        return $best;
    }

    private function line1Candidates(
        string $line
    ): array {
        $positions = [];

        $offset = 0;

        while (
            (
                $position =
                    strpos(
                        $line,
                        'P',
                        $offset
                    )
            ) !== false
        ) {
            $positions[] =
                $position;

            $offset =
                $position + 1;
        }

        if ($positions === []) {
            return [];
        }

        $result = [];

        foreach ($positions as $position) {
            $candidate =
                substr(
                    $line,
                    $position,
                    44
                );

            if (
                strlen($candidate)
                < 40
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
        $result = [];

        $length =
            strlen($line);

        if ($length < 40) {
            return [];
        }

        if ($length <= 44) {
            $result[] =
                str_pad(
                    $line,
                    44,
                    '<'
                );
        } else {
            $max =
                min(
                    $length - 44,
                    16
                );

            for (
                $start = 0;
                $start <= $max;
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
                    fn (string $candidate) =>
                        $this->normalizeLine2(
                            $candidate
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

        /*
         * OCR commonly confuses these letters/digits.
         * Correct only positions which must be numeric.
         */
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
            42,
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

        /*
         * Nationality must be alphabetic.
         */
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

        $passportCheck =
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
            $passportCheck === null
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
            ) !== $passportCheck
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
            ) !== $birthCheck
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
            ) !== $expiryCheck
        ) {
            return null;
        }

        $score = 30;

        $compositeCheck =
            $this->digit(
                $line[43]
            );

        if (
            $compositeCheck !== null
        ) {
            $composite =
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
                    $composite
                ) === $compositeCheck
            ) {
                $score += 10;
            }
        }

        $passport =
            rtrim(
                substr(
                    $line,
                    0,
                    9
                ),
                '<'
            );

        if (
            $passport !== ''
        ) {
            $score += 3;
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
            $score += 2;
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

        $length =
            strlen($value);

        for (
            $i = 0;
            $i < $length;
            $i++
        ) {
            $character =
                $value[$i];

            if (
                $character === '<'
            ) {
                $number = 0;
            } elseif (
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
                trim(
                    str_replace(
                        '<',
                        '',
                        $value
                    )
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

        $name =
            trim(
                implode(
                    ' ',
                    array_filter(
                        [
                            $given,
                            $surname,
                        ],
                        fn ($part) =>
                            $part !== ''
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
            str_replace(
                '<',
                ' ',
                $value
            );

        $value =
            preg_replace(
                '/\s+/',
                ' ',
                trim($value)
            )
            ?? '';

        return trim($value);
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
            $years = [
                1900 + $yy,
                2000 + $yy,
            ];

            $valid = [];

            foreach ($years as $year) {
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
                        ),
                        new DateTimeZone(
                            'UTC'
                        )
                    );

                if ($date > $today) {
                    continue;
                }

                $age =
                    $date
                        ->diff(
                            $today
                        )
                        ->y;

                if ($age > 120) {
                    continue;
                }

                $valid[] = $date;
            }

            if ($valid === []) {
                return null;
            }

            usort(
                $valid,
                fn (
                    DateTimeImmutable $a,
                    DateTimeImmutable $b
                ) =>
                    $b <=> $a
            );

            return $valid[0]
                ->format(
                    'Y-m-d'
                );
        }

        $years = [
            1900 + $yy,
            2000 + $yy,
            2100 + $yy,
        ];

        $valid = [];

        foreach ($years as $year) {
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
                    ),
                    new DateTimeZone(
                        'UTC'
                    )
                );

            $distance =
                abs(
                    (int) $today
                        ->diff(
                            $date
                        )
                        ->format(
                            '%r%a'
                        )
                );

            if (
                $year
                >= ((int) $today->format('Y') - 20)
                && $year
                <= ((int) $today->format('Y') + 20)
            ) {
                $distance -= 100000;
            }

            $valid[] = [
                'date' =>
                    $date,
                'distance' =>
                    $distance,
            ];
        }

        if ($valid === []) {
            return null;
        }

        usort(
            $valid,
            fn (array $a, array $b) =>
                $a['distance']
                <=>
                $b['distance']
        );

        return $valid[0]['date']
            ->format(
                'Y-m-d'
            );
    }

    private function augmentPrintedFields(
        array $fields,
        string $text
    ): array {
        /*
         * Prefer the visible printed holder name
         * when both surname and given names can be
         * read cleanly.
         *
         * Otherwise keep the validated MRZ name.
         */
        $printedName =
            $this->printedPassportName(
                $text
            );

        if ($printedName !== null) {
            $fields['name'] =
                $printedName;
        }

        $placeOfBirth =
            $this->textNearLabels(
                $text,
                [
                    'Place of Birth',
                    'Birth Place',
                    'Place of birth',
                    'Lieu de naissance',
                ],
                'place'
            );

        if ($placeOfBirth !== null) {
            $fields[
                'place_of_birth'
            ] = $placeOfBirth;
        }

        $issueDate =
            $this->dateNearLabels(
                $text,
                [
                    'Date of Issue',
                    'Issue Date',
                    'Date of Issuance',
                    'Date Issued',
                    'Passport Issue',
                    'Date de délivrance',
                ]
            );

        if ($issueDate !== null) {
            $fields[
                'passport_issue_date'
            ] = $issueDate;
        }

        $authority =
            $this->textNearLabels(
                $text,
                [
                    'Issuing Authority',
                    'Passport Authority',
                    'Issuing Office',
                    'Issued By',
                    'Authority',
                    'Autorité',
                ],
                'authority'
            );

        if ($authority !== null) {
            $fields[
                'issuing_authority'
            ] = $authority;
        }

        /*
         * Nationality and issuing country stay
         * authoritative from validated TD3 MRZ.
         * Printed OCR must not replace them.
         */

        return $fields;
    }

    private function printedPassportName(
        string $text
    ): ?string {
        $surname =
            $this->textNearLabels(
                $text,
                [
                    'Surname',
                    'Family Name',
                    'Last Name',
                    'Nom',
                ],
                'name'
            );

        $givenNames =
            $this->textNearLabels(
                $text,
                [
                    'Given Names',
                    'Given Name',
                    'First Names',
                    'First Name',
                    'Forenames',
                    'Prénoms',
                    'Prenoms',
                ],
                'name'
            );

        if (
            $surname !== null
            && $givenNames !== null
        ) {
            $name =
                trim(
                    $givenNames
                    . ' '
                    . $surname
                );

            if (
                $this->validPersonName(
                    $name
                )
            ) {
                return $name;
            }
        }

        $fullName =
            $this->textNearLabels(
                $text,
                [
                    'Full Name',
                    'Name of Holder',
                    'Holder Name',
                ],
                'name'
            );

        if (
            $fullName !== null
            && $this->validPersonName(
                $fullName
            )
        ) {
            return $fullName;
        }

        return null;
    }

    private function textNearLabels(
        string $text,
        array $labels,
        string $kind = 'generic'
    ): ?string {
        $lines =
            preg_split(
                '/\R/u',
                $text
            )
            ?: [];

        $candidates = [];

        foreach (
            $lines as $index => $rawLine
        ) {
            $line =
                $this->normalizePrintedLine(
                    $rawLine
                );

            if ($line === '') {
                continue;
            }

            foreach ($labels as $label) {
                /*
                 * A nearby authority VALUE may itself
                 * start with "Passport Authority":
                 *
                 *   PASSPORT AUTHORITY STOCKHOLM
                 *
                 * Do not reinterpret that as another
                 * label unless punctuation follows the
                 * words "Passport Authority".
                 */
                if (
                    $kind === 'authority'
                    && mb_strtolower(
                        trim($label)
                    ) === 'passport authority'
                    && preg_match(
                        '/^PASSPORT\\s+AUTHORITY\\s+.+$/iu',
                        $line
                    )
                    && !preg_match(
                        '/^PASSPORT\\s+AUTHORITY\\s*[:;|\\/-]/iu',
                        $line
                    )
                ) {
                    continue;
                }

                $pattern =
                    '/(?<![\p{L}\p{N}])'
                    . preg_quote(
                        $label,
                        '/'
                    )
                    . '(?![\p{L}\p{N}])/iu';

                if (
                    !preg_match(
                        $pattern,
                        $line,
                        $match,
                        PREG_OFFSET_CAPTURE
                    )
                ) {
                    continue;
                }

                $matched =
                    $match[0][0];

                $offset =
                    $match[0][1];

                /*
                 * Do not interpret a value such as:
                 *
                 *   PASSPORT AUTHORITY STOCKHOLM
                 *
                 * as another generic "Authority"
                 * label. In that case the whole line
                 * is the authority value belonging to
                 * the preceding Issuing Authority label.
                 */
                if (
                    $kind === 'authority'
                    && mb_strtolower(
                        trim($label)
                    ) === 'authority'
                    && trim(
                        substr(
                            $line,
                            0,
                            $offset
                        )
                    ) !== ''
                ) {
                    continue;
                }

                $after =
                    substr(
                        $line,
                        $offset
                        + strlen(
                            $matched
                        )
                    );

                $after =
                    preg_replace(
                        '/^[\s:;|\/\-]+/u',
                        '',
                        $after
                    )
                    ?? '';

                $sameLine =
                    $this->cleanStructuredValue(
                        $after,
                        $kind
                    );

                if ($sameLine !== null) {
                    $candidates[] = [
                        'value' =>
                            $sameLine,
                        'score' =>
                            120,
                    ];
                }

                /*
                 * Passport layouts often put the
                 * English label on one line and
                 * its value on the next line.
                 */
                for (
                    $step = 1;
                    $step <= 4;
                    $step++
                ) {
                    $nextIndex =
                        $index
                        + $step;

                    if (
                        !isset(
                            $lines[
                                $nextIndex
                            ]
                        )
                    ) {
                        break;
                    }

                    $next =
                        $this->normalizePrintedLine(
                            $lines[
                                $nextIndex
                            ]
                        );

                    if ($next === '') {
                        continue;
                    }

                    if (
                        $kind === 'authority'
                        && preg_match(
                            '/^PASSPORT\\s+AUTHORITY\\s+.+$/iu',
                            $next
                        )
                        && !preg_match(
                            '/^PASSPORT\\s+AUTHORITY\\s*[:;|\\/-]/iu',
                            $next
                        )
                    ) {
                        $candidate =
                            $this->cleanStructuredValue(
                                $next,
                                $kind
                            );

                        if ($candidate !== null) {
                            $candidates[] = [
                                'value' =>
                                    $candidate,
                                'score' =>
                                    125
                                    - ($step * 2),
                            ];
                        }

                        break;
                    }

                    if (
                        $this->looksLikePassportLabel(
                            $next
                        )
                    ) {
                        break;
                    }

                    $candidate =
                        $this->cleanStructuredValue(
                            $next,
                            $kind
                        );

                    if ($candidate === null) {
                        continue;
                    }

                    $candidates[] = [
                        'value' =>
                            $candidate,
                        'score' =>
                            110
                            - ($step * 5),
                    ];

                    /*
                     * First clean nearby value is
                     * normally the value paired with
                     * the current label.
                     */
                    break;
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        /*
         * Prefer candidates found consistently across
         * both passport OCR passes.
         */
        foreach (
            $candidates as $i => $candidate
        ) {
            $normalized =
                $this->comparisonValue(
                    $candidate[
                        'value'
                    ]
                );

            foreach (
                $candidates as $j => $other
            ) {
                if ($i === $j) {
                    continue;
                }

                $otherNormalized =
                    $this->comparisonValue(
                        $other[
                            'value'
                        ]
                    );

                if (
                    $normalized !== ''
                    && $normalized
                        === $otherNormalized
                ) {
                    $candidates[$i][
                        'score'
                    ] += 30;
                }
            }

            if (
                $kind === 'name'
                || $kind === 'place'
            ) {
                if (
                    preg_match(
                        '/^[\p{L}\p{M}\s\'\-,.]+$/u',
                        $candidate[
                            'value'
                        ]
                    )
                ) {
                    $candidates[$i][
                        'score'
                    ] += 10;
                }
            }
        }

        usort(
            $candidates,
            function (
                array $a,
                array $b
            ): int {
                if (
                    $a['score']
                    === $b['score']
                ) {
                    return mb_strlen(
                        $b['value']
                    )
                    <=>
                    mb_strlen(
                        $a['value']
                    );
                }

                return $b['score']
                    <=>
                    $a['score'];
            }
        );

        return $candidates[0][
            'value'
        ];
    }

    private function dateNearLabels(
        string $text,
        array $labels
    ): ?string {
        $lines =
            preg_split(
                '/\R/u',
                $text
            )
            ?: [];

        foreach (
            $lines as $index => $rawLine
        ) {
            $line =
                $this->normalizePrintedLine(
                    $rawLine
                );

            foreach ($labels as $label) {
                $pattern =
                    '/(?<![\p{L}\p{N}])'
                    . preg_quote(
                        $label,
                        '/'
                    )
                    . '(?![\p{L}\p{N}])/iu';

                if (
                    !preg_match(
                        $pattern,
                        $line
                    )
                ) {
                    continue;
                }

                $window = [
                    $line,
                ];

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
                        $window[] =
                            $this->normalizePrintedLine(
                                $lines[
                                    $index
                                    + $step
                                ]
                            );
                    }
                }

                foreach ($window as $candidate) {
                    $date =
                        $this->printedDate(
                            $candidate
                        );

                    if ($date !== null) {
                        return $date;
                    }
                }

                $date =
                    $this->printedDate(
                        implode(
                            ' ',
                            $window
                        )
                    );

                if ($date !== null) {
                    return $date;
                }
            }
        }

        return null;
    }

    private function normalizePrintedLine(
        string $value
    ): string {
        return trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $value
            )
            ?? ''
        );
    }

    private function cleanStructuredValue(
        string $value,
        string $kind
    ): ?string {
        $value =
            $this->normalizePrintedLine(
                $value
            );

        $value =
            trim(
                $value,
                " \t\n\r\0\x0B:;|/"
            );

        if (
            $value === ''
            || mb_strlen(
                $value
            ) < 2
            || mb_strlen(
                $value
            ) > 100
        ) {
            return null;
        }

        if (
            substr_count(
                $value,
                '<'
            ) >= 2
        ) {
            return null;
        }

        /*
         * In many passports the actual authority value
         * begins with words such as "Passport Authority".
         * It is a value when additional office/location
         * text follows it.
         */
        if (
            $kind === 'authority'
            && preg_match(
                '/^PASSPORT\\s+AUTHORITY(?:\\s+.+)?$/iu',
                $value
            )
        ) {
            return $value;
        }

        if (
            $this->looksLikePassportLabel(
                $value
            )
        ) {
            return null;
        }

        if ($kind === 'name') {
            $value =
                preg_replace(
                    '/[^\p{L}\p{M}\s\'\-]/u',
                    ' ',
                    $value
                )
                ?? '';

            $value =
                $this->normalizePrintedLine(
                    $value
                );

            return $this->validPersonName(
                $value
            )
                ? $value
                : null;
        }

        if ($kind === 'place') {
            $value =
                preg_replace(
                    '/[^\p{L}\p{M}\s\'\-,.]/u',
                    ' ',
                    $value
                )
                ?? '';

            $value =
                $this->normalizePrintedLine(
                    $value
                );

            if (
                mb_strlen(
                    $value
                ) < 2
            ) {
                return null;
            }
        }

        return $value;
    }

    private function validPersonName(
        string $value
    ): bool {
        if (
            mb_strlen(
                $value
            ) < 3
            || mb_strlen(
                $value
            ) > 100
        ) {
            return false;
        }

        if (
            preg_match(
                '/\d/u',
                $value
            )
        ) {
            return false;
        }

        return preg_match(
            '/\p{L}/u',
            $value
        ) === 1;
    }

    private function looksLikePassportLabel(
        string $value
    ): bool {
        return preg_match(
            '/^(?:'
            . 'SURNAME'
            . '|FAMILY\s+NAME'
            . '|LAST\s+NAME'
            . '|GIVEN\s+NAMES?'
            . '|FIRST\s+NAMES?'
            . '|FORENAMES?'
            . '|FULL\s+NAME'
            . '|NAME\s+OF\s+HOLDER'
            . '|NATIONALITY'
            . '|DATE\s+OF\s+BIRTH'
            . '|PLACE\s+OF\s+BIRTH'
            . '|BIRTH\s+PLACE'
            . '|SEX'
            . '|GENDER'
            . '|DATE\s+OF\s+ISSUE'
            . '|ISSUE\s+DATE'
            . '|DATE\s+OF\s+EXPIRY'
            . '|EXPIRY\s+DATE'
            . '|PASSPORT'
            . '|DOCUMENT'
            . '|ISSUING\s+AUTHORITY'
            . '|PASSPORT\s+AUTHORITY'
            . '|AUTHORITY'
            . '|ISSUING\s+COUNTRY'
            . '|COUNTRY\s+OF\s+ISSUE'
            . ')\b/iu',
            trim(
                $value
            )
        ) === 1;
    }

    private function comparisonValue(
        string $value
    ): string {
        return mb_strtoupper(
            preg_replace(
                '/[^\p{L}\p{N}]+/u',
                '',
                $value
            )
            ?? ''
        );
    }

    private function labelValue(
        string $text,
        array $labels
    ): ?string {
        $lines =
            preg_split(
                '/\R/u',
                $text
            )
            ?: [];

        $count =
            count($lines);

        foreach (
            $lines as $index => $rawLine
        ) {
            $line =
                preg_replace(
                    '/\s+/u',
                    ' ',
                    trim($rawLine)
                )
                ?? '';

            if ($line === '') {
                continue;
            }

            foreach ($labels as $label) {
                $position =
                    mb_stripos(
                        $line,
                        $label
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
                            $label
                        )
                    );

                $after =
                    ltrim(
                        trim($after),
                        ":;-|/ "
                    );

                $clean =
                    $this->cleanPrintedValue(
                        $after
                    );

                if ($clean !== null) {
                    return $clean;
                }

                for (
                    $next = $index + 1;
                    $next < min(
                        $count,
                        $index + 3
                    );
                    $next++
                ) {
                    $candidate =
                        $this->cleanPrintedValue(
                            $lines[$next]
                        );

                    if (
                        $candidate !== null
                    ) {
                        return $candidate;
                    }
                }
            }
        }

        return null;
    }

    private function cleanPrintedValue(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            preg_replace(
                '/\s+/u',
                ' ',
                trim($value)
            )
            ?? '';

        $value =
            trim(
                $value,
                " \t\n\r\0\x0B:;|-"
            );

        if (
            $value === ''
            || mb_strlen(
                $value
            ) < 2
        ) {
            return null;
        }

        if (
            substr_count(
                $value,
                '<'
            ) >= 2
        ) {
            return null;
        }

        if (
            preg_match(
                '/^(?:'
                . 'NAME'
                . '|SURNAME'
                . '|NATIONALITY'
                . '|DATE\s+OF'
                . '|PLACE\s+OF'
                . '|PASSPORT'
                . '|SEX'
                . '|AUTHORITY'
                . ')$/iu',
                $value
            )
        ) {
            return null;
        }

        return mb_substr(
            $value,
            0,
            150
        );
    }

    private function printedDate(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            strtoupper(
                trim($value)
            );

        $patterns = [
            '/\b\d{4}[-\/.]\d{1,2}[-\/.]\d{1,2}\b/',
            '/\b\d{1,2}[-\/.]\d{1,2}[-\/.]\d{4}\b/',
            '/\b\d{1,2}\s+[A-Z]{3,9}\s+\d{4}\b/',
        ];

        $candidate = null;

        foreach ($patterns as $pattern) {
            if (
                preg_match(
                    $pattern,
                    $value,
                    $match
                )
            ) {
                $candidate =
                    $match[0];

                break;
            }
        }

        if ($candidate === null) {
            return null;
        }

        $formats = [
            'Y-m-d',
            'Y/m/d',
            'Y.m.d',
            'd-m-Y',
            'd/m/Y',
            'd.m.Y',
            'd M Y',
            'd F Y',
        ];

        foreach ($formats as $format) {
            $date =
                DateTimeImmutable::createFromFormat(
                    '!' . $format,
                    $candidate,
                    new DateTimeZone(
                        'UTC'
                    )
                );

            if (!$date) {
                continue;
            }

            $errors =
                DateTimeImmutable::getLastErrors();

            if (
                $errors !== false
                && (
                    $errors[
                        'warning_count'
                    ] > 0
                    || $errors[
                        'error_count'
                    ] > 0
                )
            ) {
                continue;
            }

            return $date->format(
                'Y-m-d'
            );
        }

        return null;
    }
}
