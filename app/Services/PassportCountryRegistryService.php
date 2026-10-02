<?php

namespace App\Services;

use RuntimeException;

class PassportCountryRegistryService
{
    private ?array $data = null;

    public function all(): array
    {
        return $this->load()['profiles'];
    }

    public function count(): int
    {
        return count(
            $this->all()
        );
    }

    public function profile(
        ?string $code
    ): array {
        $code =
            strtoupper(
                trim(
                    (string) $code
                )
            );

        $profiles =
            $this->all();

        if (
            $code !== ''
            && isset(
                $profiles[$code]
            )
        ) {
            return $profiles[$code];
        }

        /*
         * Unknown/new issuing codes still use
         * the global ICAO TD3 structure.
         */
        return [
            'code' =>
                $code !== ''
                    ? $code
                    : 'UNK',

            'alpha2' =>
                null,

            'country' =>
                'Unknown / ICAO compatible',

            'document_category' =>
                'passport',

            'document_type' =>
                'ordinary',

            'mrz_format' =>
                'TD3',

            'structure' =>
                $this->defaultStructure(),

            'labels' =>
                $this->defaultLabels(),

            'place_aliases' =>
                [],

            'authority_aliases' =>
                [],
        ];
    }

    private function load(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }

        $path =
            resource_path(
                'passports/'.
                'ordinary-passport-profiles.json'
            );

        if (!is_file($path)) {
            throw new RuntimeException(
                'Passport country registry is missing.'
            );
        }

        $decoded =
            json_decode(
                file_get_contents(
                    $path
                ),
                true
            );

        if (
            !is_array($decoded)
            || !isset(
                $decoded['profiles']
            )
            || !is_array(
                $decoded['profiles']
            )
        ) {
            throw new RuntimeException(
                'Passport country registry is invalid.'
            );
        }

        $this->data =
            $decoded;

        return $this->data;
    }

    private function defaultStructure(): array
    {
        return [
            'passport_number' =>
                'MRZ_TD3',

            'surname' =>
                'MRZ_TD3',

            'given_names' =>
                'MRZ_TD3',

            'client_name' =>
                'MRZ_TD3',

            'nationality' =>
                'MRZ_TD3',

            'date_of_birth' =>
                'MRZ_TD3',

            'gender' =>
                'MRZ_TD3',

            'passport_expiry_date' =>
                'MRZ_TD3',

            'issuing_country' =>
                'MRZ_TD3',

            'passport_issue_date' =>
                'VIZ',

            'place_of_birth' =>
                'VIZ',

            'issuing_authority' =>
                'VIZ',
        ];
    }

    private function defaultLabels(): array
    {
        return [
            'place_of_birth' => [
                'Place of Birth',
                'Birth Place',
                'Lieu de naissance',
                'Lugar de nacimiento',
            ],

            'issue_date' => [
                'Date of Issue',
                'Issue Date',
                'Date of Issuance',
                'Date Issued',
                'Date de délivrance',
                'Fecha de expedición',
                'Fecha de emisión',
            ],

            'issuing_authority' => [
                'Issuing Authority',
                'Issuing Office',
                'Authority',
                'Passport Authority',
                'Issued By',
                'Autorité',
                'Autorité de délivrance',
                'Autoridad',
                'Autoridad expedidora',
            ],
        ];
    }
}
