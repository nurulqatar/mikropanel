<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Qatar Resident ID / Residence Permit
    |--------------------------------------------------------------------------
    */

    'qatar_id' => [

        'type' =>
            'resident',

        'front' => [

            'required' => [
                'qatar_id_number',
                'name',
                'nationality',
                'date_of_birth',
                'qatar_id_expiry_date',
                'occupation',
            ],

            'fields' => [

                'qatar_id_number' => [
                    'ID.No',
                    'ID No',
                    'ID Number',
                    'Personal No',
                    'Personal Number',
                    'QID',
                    'Qatar ID',
                    'الرقم الشخصي',
                    'رقم البطاقة',
                ],

                'name' => [
                    'Full Name',
                    'Name',
                    'الاسم',
                    'اسم حامل البطاقة',
                ],

                'nationality' => [
                    'Nationality',
                    'الجنسية',
                ],

                'date_of_birth' => [
                    'Date of Birth',
                    'D.O.B',
                    'D.O.B.',
                    'DOB',
                    'تاريخ الميلاد',
                ],

                'qatar_id_expiry_date' => [
                    'QID Expiry',
                    'ID Expiry',
                    'Residency Expiry',
                    'Date of Expiry',
                    'Expiry Date',
                    'Expiry',
                    'تاريخ الانتهاء',
                    'تاريخ انتهاء البطاقة',
                    'تاريخ انتهاء الإقامة',
                ],

                'occupation' => [
                    'Occupation',
                    'Profession',
                    'المهنة',
                ],

                'gender' => [
                    'Gender',
                    'Sex',
                    'الجنس',
                ],
            ],
        ],

        'back' => [

            'required' => [
                'passport_number',
                'passport_expiry_date',
                'document_serial_number',
                'residency_type',
                'employer',
            ],

            'fields' => [

                'passport_number' => [
                    'Passport Number',
                    'Passport No.',
                    'Passport No',
                    'Passport #',
                    'رقم جواز السفر',
                    'رقم الجواز',
                ],

                'passport_expiry_date' => [
                    'Passport Expiry Date',
                    'Passport Expiry',
                    'Passport Date of Expiry',
                    'تاريخ انتهاء جواز السفر',
                    'انتهاء جواز السفر',
                    'تاريخ انتهاء الجواز',
                ],

                'document_serial_number' => [
                    'Serial Number',
                    'Serial No.',
                    'Serial No',
                    'الرقم المسلسل',
                    'الرقم التسلسلي',
                ],

                'residency_type' => [
                    'Residency Type',
                    'Residence Type',
                    'Permit Type',
                    'Permit Class',
                    'نوع الرخصة',
                    'نوع الإقامة',
                ],

                'employer' => [
                    'Employer / Sponsor',
                    'Employer',
                    'Sponsor',
                    'المستقدم',
                    'الكفيل',
                    'صاحب العمل',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Worldwide Ordinary / Personal Passport
    |--------------------------------------------------------------------------
    |
    | ICAO TD3 is the universal primary structure.
    | Country/version layouts may override VIZ extraction later.
    |
    */

    'ordinary_passport' => [

        'scope' =>
            'ordinary_personal_only',

        'standard' =>
            'ICAO Doc 9303 TD3',

        'epassport_bio_page' =>
            true,

        'mrz' => [

            'lines' => 2,

            'line_length' => 44,

            'line1' => [
                'document_code' => [0, 2],
                'issuing_country' => [2, 3],
                'name' => [5, 39],
            ],

            'line2' => [
                'passport_number' => [0, 9],
                'passport_number_check' => [9, 1],
                'nationality' => [10, 3],
                'date_of_birth' => [13, 6],
                'date_of_birth_check' => [19, 1],
                'gender' => [20, 1],
                'passport_expiry_date' => [21, 6],
                'passport_expiry_check' => [27, 1],
            ],
        ],

        /*
         * PRADO-confirmed Qatar ordinary passport
         * versions. The generic TD3 engine does not
         * depend on these versions to read core data.
         */
        'known_versions' => [

            'QAT' => [
                'QAT-AO-01001',
                'QAT-AO-02001',
                'QAT-AO-03001',
            ],
        ],
    ],
];
