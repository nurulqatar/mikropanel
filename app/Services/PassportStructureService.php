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
            $this->passportHolderName(
                $text
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

        /*
         * These fields MUST be populated only from
         * their visible printed passport labels.
         *
         * Never preserve generic OCR/MRZ values here.
         */
        unset(
            $fields['name'],
            $fields['passport_issue_date'],
            $fields['place_of_birth'],
            $fields['issuing_country']
        );

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

        $printedIssuingCountry =
            $this->extractPrintedIssuingCountry(
                $text
            );

        if (
            $printedIssuingCountry !== null
        ) {
            $fields['issuing_country'] =
                $printedIssuingCountry;
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

    private function passportHolderName(
        string $text
    ): ?string {
        /*
         * IMPORTANT:
         *
         * Do NOT use MRZ for Client Name.
         *
         * Client Name =
         *   Given Name(s) + Surname
         *
         * directly from the printed bio-page fields.
         */

        $given =
            $this->printedNameValue(
                $text,
                [
                    'Given Names',
                    'Given Name',
                    'Given Name(s)',
                    'Given names',
                    'Forenames',
                    'Forename',
                    'First Names',
                    'First Name',

                    'Prénoms',
                    'Prenoms',

                    'Nombre',
                    'Nombres',

                    'Vornamen',

                    'Nome',
                    'Nomi',
                    'Prenome',

                    /*
                     * Frequent OCR variations.
                     */
                    'Given Nane',
                    'Given Nanes',
                    'Given Nam',
                ]
            );

        $surname =
            $this->printedNameValue(
                $text,
                [
                    'Surname',
                    'Family Name',
                    'Last Name',

                    'Nom de famille',

                    'Apellido',
                    'Apellidos',

                    'Familienname',
                    'Nachname',

                    'Cognome',
                    'Apelido',

                    /*
                     * Frequent OCR variations.
                     */
                    'Surnane',
                    'Surnarne',
                    'Summame',
                    'Surmame',
                ]
            );

        /*
         * Both printed components are required.
         * If OCR cannot read one of them, return null
         * rather than silently substituting MRZ text.
         */
        if (
            $given === null
            || $surname === null
        ) {
            return null;
        }

        return trim(
            $given
            . ' '
            . $surname
        );
    }

    private function mrzNameParts(
        string $value
    ): array {
        $parts =
            explode(
                '<<',
                $value,
                2
            );

        return [
            'surname' =>
                $this->mrzNamePart(
                    $parts[0]
                    ?? ''
                ),

            'given' =>
                $this->mrzNamePart(
                    $parts[1]
                    ?? ''
                ),
        ];
    }

    private function printedNameValue(
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
            $lines as $index => $line
        ) {
            $label =
                $this->matchedPrintedLabel(
                    $line,
                    $labels
                );

            if ($label === null) {
                continue;
            }

            /*
             * SAME LINE FIRST:
             *
             * Given Names: MD NURUL
             * Surname: ISLAM
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
                        " \t:;/|-.—_"
                    );

                $value =
                    $this
                        ->cleanPrintedPersonName(
                            $after
                        );

                if ($value !== null) {
                    return $value;
                }
            }

            /*
             * NEXT LINE:
             *
             * Given Names
             * MD NURUL
             */
            for (
                $step = 1;
                $step <= 3;
                $step++
            ) {
                $candidate =
                    $lines[
                        $index + $step
                    ]
                    ?? null;

                if ($candidate === null) {
                    break;
                }

                $candidate =
                    trim($candidate);

                if ($candidate === '') {
                    continue;
                }

                if (
                    $this
                        ->looksLikePassportFieldLabel(
                            $candidate
                        )
                ) {
                    break;
                }

                $value =
                    $this
                        ->cleanPrintedPersonName(
                            $candidate
                        );

                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function cleanPrintedPersonName(
        string $value
    ): ?string {
        $value =
            preg_replace(
                '/^[^\p{L}]+/u',
                '',
                trim($value)
            )
            ?? '';

        $value =
            preg_replace(
                '/[^\p{L}\p{M}\s\'\-]+$/u',
                '',
                $value
            )
            ?? '';

        $value =
            preg_replace(
                '/\s+/u',
                ' ',
                trim($value)
            )
            ?? '';

        if (
            $value === ''
            || mb_strlen($value) > 100
            || preg_match(
                '/\d/u',
                $value
            )
        ) {
            return null;
        }

        if (
            preg_match(
                '/\b(?:'
                . 'PASSPORT'
                . '|NATIONALITY'
                . '|DATE'
                . '|BIRTH'
                . '|SEX'
                . '|GENDER'
                . '|AUTHORITY'
                . '|EXPIR'
                . '|COUNTRY'
                . ')\b/iu',
                $value
            )
        ) {
            return null;
        }

        return mb_strtoupper(
            $value
        );
    }

    private function looksLikePassportFieldLabel(
        string $value
    ): bool {
        $value =
            trim($value);

        if ($value === '') {
            return false;
        }

        /*
         * Worldwide ordinary-passport field boundary
         * detector.
         *
         * This is used only to know where the current
         * printed field ends and the next field begins.
         */
        return preg_match(
            '/(?:'

            /*
             * English / common ICAO captions
             */
            . 'passport(?:\s+(?:number|no))?'
            . '|document\s+(?:number|no)'
            . '|country\s+code'
            . '|issuing\s+country'
            . '|country\s+of\s+(?:issue|issuance)'
            . '|issuing\s+state'
            . '|state\s+of\s+issue'
            . '|surname'
            . '|family\s+name'
            . '|last\s+name'
            . '|given\s+names?'
            . '|first\s+names?'
            . '|forenames?'
            . '|nationality'
            . '|personal\s+(?:number|no)'
            . '|date.{0,12}birth'
            . '|birth.{0,12}date'
            . '|place.{0,18}birth'
            . '|birth.{0,18}place'
            . '|date.{0,12}issue'
            . '|issue.{0,12}date'
            . '|date.{0,12}expir'
            . '|expir(?:y|ation)'
            . '|sex'
            . '|gender'
            . '|authority'
            . '|signature'

            /*
             * French
             */
            . '|nom\s+de\s+famille'
            . '|pr[ée]noms?'
            . '|nationalit[ée]'
            . '|lieu.{0,18}naissance'
            . '|date.{0,18}naissance'
            . '|date.{0,18}d[ée]livrance'
            . '|date.{0,18}expiration'
            . '|pays.{0,18}[ée]metteur'
            . '|pays.{0,18}d[ée]livrance'

            /*
             * Spanish
             */
            . '|apellidos?'
            . '|nombres?'
            . '|nacionalidad'
            . '|lugar.{0,18}nacimiento'
            . '|fecha.{0,18}nacimiento'
            . '|fecha.{0,18}expedici[oó]n'
            . '|fecha.{0,18}emisi[oó]n'
            . '|fecha.{0,18}caducidad'
            . '|pa[ií]s.{0,18}emisor'

            /*
             * German
             */
            . '|familienname'
            . '|nachname'
            . '|vornamen?'
            . '|staatsangeh[oö]rigkeit'
            . '|geburtsort'
            . '|geburtsdatum'
            . '|ausstellungsdatum'
            . '|ausstellender\s+staat'
            . '|g[üu]ltig\s+bis'

            /*
             * Italian
             */
            . '|cognome'
            . '|nomi?'
            . '|nazionalit[àa]'
            . '|luogo.{0,18}nascita'
            . '|data.{0,18}nascita'
            . '|data.{0,18}rilascio'
            . '|paese.{0,18}rilascio'
            . '|data.{0,18}scadenza'

            /*
             * Portuguese
             */
            . '|apelido'
            . '|nome'
            . '|nacionalidade'
            . '|local.{0,18}nascimento'
            . '|data.{0,18}nascimento'
            . '|data.{0,18}emiss[aã]o'
            . '|pa[ií]s.{0,18}emiss[aã]o'
            . '|data.{0,18}validade'

            . ')/iu',
            $value
        ) === 1;
    }

    private function mrzName(
        string $value
    ): ?string {
        $parts =
            $this->mrzNameParts(
                $value
            );

        $name =
            trim(
                implode(
                    ' ',
                    array_filter(
                        [
                            $parts['given']
                                ?? '',
                            $parts['surname']
                                ?? '',
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

    private function matchedPrintedLabel(
        string $line,
        array $labels
    ): ?string {
        usort(
            $labels,
            fn (
                string $a,
                string $b
            ) =>
                mb_strlen($b)
                <=>
                mb_strlen($a)
        );

        foreach ($labels as $label) {
            $offset = 0;

            while (true) {
                $position =
                    mb_stripos(
                        $line,
                        $label,
                        $offset
                    );

                if ($position === false) {
                    break;
                }

                $before =
                    $position > 0
                        ? mb_substr(
                            $line,
                            $position - 1,
                            1
                        )
                        : '';

                $afterPosition =
                    $position
                    + mb_strlen(
                        $label
                    );

                $after =
                    $afterPosition
                        < mb_strlen($line)
                        ? mb_substr(
                            $line,
                            $afterPosition,
                            1
                        )
                        : '';

                $beforeIsWord =
                    $before !== ''
                    && preg_match(
                        '/[\p{L}\p{N}]/u',
                        $before
                    );

                $afterIsWord =
                    $after !== ''
                    && preg_match(
                        '/[\p{L}\p{N}]/u',
                        $after
                    );

                if (
                    !$beforeIsWord
                    && !$afterIsWord
                ) {
                    return $label;
                }

                $offset =
                    $position + 1;
            }
        }

        return null;
    }

    private function directPrintedCandidates(
        string $text,
        array $labels,
        int $followingLines = 4
    ): array {
        $lines =
            preg_split(
                '/\R/u',
                $text
            )
            ?: [];

        $result = [];

        foreach (
            $lines as $index => $line
        ) {
            $label =
                $this->matchedPrintedLabel(
                    $line,
                    $labels
                );

            if ($label === null) {
                continue;
            }

            /*
             * SAME-LINE VALUE FIRST.
             *
             * Place of Birth: NEW SOUTH WALES
             * Issuing Country: Australia
             * Date of Issue: 10 JAN 2025
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
                        " \t:;/|-.—_"
                    );

                if (
                    $after !== ''
                    && !$this
                        ->looksLikePassportFieldLabel(
                            $after
                        )
                ) {
                    $result[] =
                        $after;
                }
            }

            /*
             * If value is not on the same line,
             * follow the printed caption downward.
             *
             * Stop immediately when the next passport
             * field caption begins.
             */
            for (
                $step = 1;
                $step <= $followingLines;
                $step++
            ) {
                $candidate =
                    $lines[
                        $index + $step
                    ]
                    ?? null;

                if ($candidate === null) {
                    break;
                }

                $candidate =
                    trim($candidate);

                if ($candidate === '') {
                    continue;
                }

                if (
                    $this
                        ->looksLikePassportFieldLabel(
                            $candidate
                        )
                ) {
                    break;
                }

                $result[] =
                    $candidate;
            }
        }

        return array_values(
            array_unique(
                $result
            )
        );
    }

    private function extractPrintedIssuingCountry(
        string $text
    ): ?string {
        /*
         * Only visible printed country/country-code
         * fields are accepted here.
         *
         * MRZ issuer code is NOT used.
         */
        $labels = [
            'Issuing Country',
            'Country of Issue',
            'Country of Issuance',
            'Issuing State',
            'State of Issue',
            'Country Code',

            'Pays émetteur',
            'Pays emetteur',
            'Pays de délivrance',
            'Pays de delivrance',

            'País emisor',
            'Pais emisor',
            'País de expedición',
            'Pais de expedicion',

            'Ausstellender Staat',
            'Ausstellungsstaat',

            'Paese di rilascio',
            'Stato di rilascio',

            'País de emissão',
            'Pais de emissao',
        ];

        $candidates =
            $this->directPrintedCandidates(
                $text,
                $labels,
                4
            );

        foreach ($candidates as $candidate) {
            $country =
                $this->countryFromPrintedCandidate(
                    $candidate
                );

            if ($country !== null) {
                return $country;
            }
        }

        return null;
    }

    private function countryFromPrintedCandidate(
        string $value
    ): ?string {
        $value =
            preg_replace(
                '/\s+/u',
                ' ',
                trim($value)
            )
            ?? '';

        if ($value === '') {
            return null;
        }

        $profiles =
            $this->registry->all();

        /*
         * Printed ISO/ICAO alpha-3 country code,
         * e.g. BGD, QAT, IND, USA.
         */
        preg_match_all(
            '/(?<![A-Z])([A-Z]{3})(?![A-Z])/u',
            mb_strtoupper($value),
            $matches
        );

        foreach (
            $matches[1] ?? []
            as $code
        ) {
            if (
                isset(
                    $profiles[$code]
                )
            ) {
                $country =
                    trim(
                        (string) (
                            $profiles[$code][
                                'country'
                            ]
                            ?? ''
                        )
                    );

                if ($country !== '') {
                    return $country;
                }
            }
        }

        /*
         * Printed alpha-2 country code.
         */
        preg_match_all(
            '/(?<![A-Z])([A-Z]{2})(?![A-Z])/u',
            mb_strtoupper($value),
            $matches2
        );

        foreach (
            $matches2[1] ?? []
            as $alpha2
        ) {
            foreach (
                $profiles as $profile
            ) {
                if (
                    strtoupper(
                        (string) (
                            $profile['alpha2']
                            ?? ''
                        )
                    )
                    !== $alpha2
                ) {
                    continue;
                }

                $country =
                    trim(
                        (string) (
                            $profile['country']
                            ?? ''
                        )
                    );

                if ($country !== '') {
                    return $country;
                }
            }
        }

        /*
         * Full printed country name.
         */
        foreach (
            $profiles as $profile
        ) {
            $country =
                trim(
                    (string) (
                        $profile['country']
                        ?? ''
                    )
                );

            if ($country === '') {
                continue;
            }

            if (
                mb_stripos(
                    $value,
                    $country
                ) !== false
            ) {
                return $country;
            }
        }

        return null;
    }

    private function extractIssueDate(
        string $text,
        array $labels
    ): ?string {
        /*
         * Explicit printed issue-date captions.
         */
        $directLabels = array_values(
            array_unique(
                array_merge(
                    [
                        'Passport Issue Date',
                        'Date of Issue',
                        'Issue Date',
                        'Date Issued',
                        'Date of Issuance',

                        'Date de délivrance',
                        'Date de delivrance',

                        'Fecha de expedición',
                        'Fecha de expedicion',
                        'Fecha de emisión',
                        'Fecha de emision',

                        'Ausstellungsdatum',

                        'Data di rilascio',

                        'Data de emissão',
                        'Data de emissao',
                    ],
                    $labels
                )
            )
        );

        $candidates =
            $this->directPrintedCandidates(
                $text,
                $directLabels,
                4
            );

        foreach ($candidates as $candidate) {
            $date =
                $this->findPrintedDate(
                    $candidate
                );

            if ($date !== null) {
                return $date;
            }
        }

        /*
         * Nothing found under the printed issue-date
         * caption: leave it empty.
         *
         * Do NOT infer from MRZ.
         */
        return null;
    }

    private function extractPlaceOfBirth(
        string $text,
        array $labels
    ): ?string {
        $directLabels =
            array_values(
                array_unique(
                    array_merge(
                        [
                            'Place of Birth',
                            'Birth Place',

                            'Lieu de naissance',

                            'Lugar de nacimiento',

                            'Geburtsort',

                            'Luogo di nascita',

                            'Local de nascimento',
                        ],
                        $labels
                    )
                )
            );

        $candidates =
            $this->directPrintedCandidates(
                $text,
                $directLabels,
                4
            );

        foreach (
            $candidates
            as $candidate
        ) {
            $candidate =
                $this->cleanVizLine(
                    $candidate
                );

            /*
             * Remove Sex ONLY when M/F/X is a
             * standalone prefix token.
             */
            $candidate =
                preg_replace(
                    '/^[MFX]'
                    . '(?=[^\p{L}\p{N}]|$)'
                    . '(?:[^\p{L}\p{N}]+\s*)?'
                    . '/iu',
                    '',
                    $candidate
                )
                ?? $candidate;

            /*
             * Remove trailing numeric personal /
             * internal serial value while preserving
             * all place-name words.
             */
            $candidate =
                preg_replace(
                    '/\s*[\(\[]?'
                    . '\d{4,}'
                    . '.*$/u',
                    '',
                    $candidate
                )
                ?? $candidate;

            $candidate =
                trim(
                    $candidate,
                    " \t\n\r\0\x0B.,;:|>-_<"
                );

            if (
                $candidate === ''
                || $this
                    ->looksLikePassportFieldLabel(
                        $candidate
                    )
                || $this->findPrintedDate(
                    $candidate
                ) !== null
                || str_contains(
                    $candidate,
                    '<<'
                )
                || !$this->validVizText(
                    $candidate,
                    2,
                    120
                )
            ) {
                continue;
            }

            return mb_strtoupper(
                $candidate
            );
        }

        return null;
    }

    private function placeFromWindow(
        string $window
    ): ?string {
        $lines =
            preg_split(
                '/\R/u',
                $window
            )
            ?: [];

        $parts = [];

        foreach ($lines as $line) {
            $line =
                $this->cleanVizLine(
                    $line
                );

            if ($line === '') {
                continue;
            }

            $line =
                preg_replace(
                    '/^[MFX]\s*[\W_]*/iu',
                    '',
                    $line
                )
                ?? $line;

            /*
             * Remove trailing serial/personal number,
             * not the place words before it.
             */
            $line =
                preg_replace(
                    '/\s*[\(\[]?'
                    . '\d{4,}'
                    . '.*$/u',
                    '',
                    $line
                )
                ?? $line;

            $line =
                trim(
                    $line,
                    " \t\n\r\0\x0B.,;:|>-_<"
                );

            if ($line === '') {
                continue;
            }

            if (
                $this->looksLikePassportFieldLabel(
                    $line
                )
                || $this->findPrintedDate(
                    $line
                ) !== null
                || str_contains(
                    $line,
                    '<<'
                )
            ) {
                break;
            }

            if (
                !$this->validVizText(
                    $line,
                    2,
                    120
                )
            ) {
                if ($parts !== []) {
                    break;
                }

                continue;
            }

            $parts[] =
                $line;

            /*
             * Preserve wrapped place names.
             */
            if (count($parts) >= 2) {
                break;
            }
        }

        if ($parts === []) {
            return null;
        }

        $place =
            preg_replace(
                '/\s+/u',
                ' ',
                implode(
                    ' ',
                    $parts
                )
            )
            ?? '';

        $place =
            trim($place);

        return $place !== ''
            ? mb_strtoupper(
                $place
            )
            : null;
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

        $result =
            preg_replace(
                '/\s+/u',
                ' ',
                trim($value)
            )
            ?? '';

        foreach (
            $aliases
            as $from => $to
        ) {
            $from =
                trim(
                    (string) $from
                );

            if ($from === '') {
                continue;
            }

            $result =
                preg_replace(
                    '/(?<!\p{L})'
                    . preg_quote(
                        $from,
                        '/'
                    )
                    . '(?!\p{L})/iu',
                    (string) $to,
                    $result
                )
                ?? $result;
        }

        $result =
            preg_replace(
                '/\s+/u',
                ' ',
                trim($result)
            )
            ?? '';

        return $result !== ''
            ? $result
            : null;
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
