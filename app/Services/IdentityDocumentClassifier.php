<?php

namespace App\Services;

class IdentityDocumentClassifier
{
    public function __construct(
        private readonly
        QatarIdStructureService $qatarId
    ) {
    }

    public function classify(
        array $scan
    ): array {
        $fields =
            $scan['fields']
            ?? [];

        $text =
            (string) (
                $scan['raw_text']
                ?? ''
            );

        $front =
            $this->qatarId
                ->looksLikeFront(
                    $fields,
                    $text
                );

        $back =
            $this->qatarId
                ->looksLikeBack(
                    $fields,
                    $text
                );

        if ($front || $back) {
            $frontMissing =
                $this->qatarId
                    ->frontMissing(
                        $fields
                    );

            $backMissing =
                $this->qatarId
                    ->backMissing(
                        $fields
                    );

            if ($front && $back) {
                return [
                    'type' =>
                        'qatar_id',

                    'label' =>
                        'Qatar ID',

                    'side' =>
                        'both',

                    'needs_back' =>
                        false,

                    'side_complete' =>
                        $frontMissing === []
                        && $backMissing === [],

                    'missing_fields' =>
                        array_values(
                            array_unique([
                                ...$frontMissing,
                                ...$backMissing,
                            ])
                        ),

                    'confidence' =>
                        'high',
                ];
            }

            if ($front) {
                return [
                    'type' =>
                        'qatar_id',

                    'label' =>
                        'Qatar ID',

                    'side' =>
                        'front',

                    'needs_back' =>
                        true,

                    'side_complete' =>
                        $frontMissing === [],

                    'missing_fields' =>
                        $frontMissing,

                    'confidence' =>
                        'high',
                ];
            }

            return [
                'type' =>
                    'qatar_id',

                'label' =>
                    'Qatar ID',

                'side' =>
                    'back',

                'needs_back' =>
                    false,

                'side_complete' =>
                    $backMissing === [],

                'missing_fields' =>
                    $backMissing,

                'confidence' =>
                    'high',
            ];
        }

        $identityType =
            strtolower(
                trim(
                    (string) (
                        $fields[
                            'identity_type'
                        ]
                        ?? ''
                    )
                )
            );

        $passportNumber =
            trim(
                (string) (
                    $fields[
                        'passport_number'
                    ]
                    ?? ''
                )
            );

        $looksMrz =
            preg_match(
                '/(?:^|\R)P[A-Z0-9<]?'
                . '[A-Z0-9<]{30,}/m',
                strtoupper(
                    $text
                )
            ) === 1;

        if (
            $identityType
            === 'passport'
            || $looksMrz
            || (
                $passportNumber !== ''
                && (
                    !empty(
                        $fields[
                            'passport_expiry_date'
                        ]
                    )
                    || !empty(
                        $fields[
                            'date_of_birth'
                        ]
                    )
                    || !empty(
                        $fields[
                            'nationality'
                        ]
                    )
                )
            )
        ) {
            return [
                'type' =>
                    'ordinary_passport',

                'label' =>
                    'Ordinary Passport',

                'side' =>
                    'bio',

                'needs_back' =>
                    false,

                'side_complete' =>
                    true,

                'missing_fields' =>
                    [],

                'confidence' =>
                    $looksMrz
                        ? 'verified_mrz'
                        : 'high',
            ];
        }

        return [
            'type' =>
                'unknown',

            'label' =>
                'Unknown document',

            'side' =>
                null,

            'needs_back' =>
                false,

            'side_complete' =>
                false,

            'missing_fields' =>
                [],

            'confidence' =>
                'low',
        ];
    }
}
