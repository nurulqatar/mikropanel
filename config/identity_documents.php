<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Qatar Residency Permit / Qatar ID
    |--------------------------------------------------------------------------
    |
    | Front and back are intentionally treated as one document.
    |
    */

    'qatar_id' => [

        'label' => 'Qatar ID',

        'sides' => [

            'front' => [
                'name',
                'qatar_id_number',
                'qatar_id_expiry_date',
                'nationality',
                'date_of_birth',
                'gender',
                'occupation',
            ],

            'back' => [
                'passport_number',
                'passport_expiry_date',
                'document_serial_number',
                'residency_type',
                'employer',
            ],

        ],

        'front_hints' => [
            'qatar_id_number',
            'qatar_id_expiry_date',
            'occupation',
        ],

        'back_hints' => [
            'passport_number',
            'passport_expiry_date',
            'document_serial_number',
            'residency_type',
            'employer',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Worldwide Ordinary / Personal Passport
    |--------------------------------------------------------------------------
    |
    | Diplomatic, Service, Official and special travel documents are not
    | part of this scanner template registry.
    |
    | ICAO TD3 MRZ remains authoritative for core passport identity.
    |
    */

    'ordinary_passport' => [

        'label' => 'Ordinary Passport',

        'mrz_document_prefix' => 'P',

        'core_mrz_fields' => [
            'name',
            'passport_number',
            'nationality',
            'date_of_birth',
            'gender',
            'passport_expiry_date',
            'issuing_country',
        ],

        'printed_fields' => [
            'passport_issue_date',
            'place_of_birth',
            'issuing_authority',
        ],

    ],

];
