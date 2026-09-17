<?php

namespace App\Services;

class IdentityDocumentClassifier
{
    public function classify(
        array $scan
    ): array {
        $fields =
            $scan['fields']
            ?? [];

        $qid =
            trim(
                (string) (
                    $fields['qatar_id_number']
                    ?? ''
                )
            );

        $identityType =
            strtolower(
                trim(
                    (string) (
                        $fields['identity_type']
                        ?? ''
                    )
                )
            );

        /*
         * Qatar ID front is the strongest signal.
         */
        if ($qid !== '') {
            return [
                'type' => 'qatar_id',
                'label' => 'Qatar ID',
                'side' => 'front',
                'needs_back' => true,
                'confidence' => 'high',
            ];
        }

        /*
         * Qatar ID back does not necessarily repeat
         * the QID number. Detect its characteristic
         * residence-card fields.
         */
        $backScore = 0;

        foreach (
            [
                'document_serial_number',
                'residency_type',
                'employer',
            ]
            as $field
        ) {
            if (
                trim(
                    (string) (
                        $fields[$field]
                        ?? ''
                    )
                ) !== ''
            ) {
                $backScore += 2;
            }
        }

        foreach (
            [
                'passport_number',
                'passport_expiry_date',
            ]
            as $field
        ) {
            if (
                trim(
                    (string) (
                        $fields[$field]
                        ?? ''
                    )
                ) !== ''
            ) {
                $backScore++;
            }
        }

        if ($backScore >= 3) {
            return [
                'type' => 'qatar_id',
                'label' => 'Qatar ID',
                'side' => 'back',
                'needs_back' => false,
                'confidence' => 'high',
            ];
        }

        /*
         * Ordinary passport.
         */
        $passportNumber =
            trim(
                (string) (
                    $fields['passport_number']
                    ?? ''
                )
            );

        if (
            $identityType === 'passport'
            || $passportNumber !== ''
        ) {
            return [
                'type' => 'ordinary_passport',
                'label' => 'Ordinary Passport',
                'side' => 'bio',
                'needs_back' => false,
                'confidence' => 'high',
            ];
        }

        return [
            'type' => 'unknown',
            'label' => 'Unknown document',
            'side' => null,
            'needs_back' => false,
            'confidence' => 'low',
        ];
    }
}
