<?php

namespace App\Http\Controllers;

use App\Services\ClientIdentityImageService;
use App\Services\ClientIdentityOcrService;
use App\Services\IdentityDocumentClassifier;
use App\Services\QatarIdStructureService;
use App\Services\PassportStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientIdentityScanController extends Controller
{
    public function __invoke(
        Request $request,
        ClientIdentityOcrService $ocr,
        QatarIdStructureService $qatarId,
        PassportStructureService $passport,
        IdentityDocumentClassifier $classifier,
        ClientIdentityImageService $images
    ): JsonResponse {
        $user =
            $request->user();

        abort_unless(
            $user
            && (
                $user->isAdmin()
                || $user->isSuperAdmin()
                || $user->hasAnyPermission([
                    'clients.create',
                    'clients.edit',
                ])
            ),
            403
        );

        $validated =
            $request->validate([
                'document' => [
                    'required',
                    'file',
                    'max:12288',
                    'mimes:jpg,jpeg,png,webp,pdf',
                ],
            ]);

        $result =
            $ocr->scan(
                $validated[
                    'document'
                ]
            );

        $rawText =
            (string) (
                $result[
                    '_raw_text'
                ]
                ?? ''
            );

        $result['fields'] =
            $qatarId->augment(
                $result[
                    'fields'
                ]
                ?? [],
                $rawText
            );

        $result['fields'] =
            $passport->augment(
                $result[
                    'fields'
                ]
                ?? [],
                $rawText
            );

        /*
         * Ordinary/personal passports only.
         *
         * Diplomatic, service, official, special,
         * emergency, refugee and other travel
         * documents must never be presented as an
         * ordinary customer passport.
         */
        if (
            $passport->isExcludedDocument(
                $rawText
            )
        ) {
            /*
             * Do not leak passport values parsed by
             * the generic OCR layer into the form.
             */
            $result['fields'] = [];

            /*
             * Reuse the classifier's own unknown
             * document response shape so frontend
             * compatibility remains unchanged.
             */
            $result['document'] =
                $classifier->classify([
                    'fields' => [],
                    'raw_text' => '',
                ]);

            $result['document'][
                'reason'
            ] =
                'Only ordinary/personal passports are supported.';
        } else {
            $result['document'] =
                $classifier->classify([
                    'fields' =>
                        $result[
                            'fields'
                        ],

                    'raw_text' =>
                        $rawText,
                ]);
        }

        /*
         * PASSORT_FIELD_WHITELIST_V1
         *
         * A passport scan may only populate fields
         * that actually belong to a passport/client
         * identity record.
         *
         * Phone, MAC, address, package and network
         * information must NEVER come from passport
         * OCR.
         */
        if (
            (
                $result['document']['type']
                ?? null
            )
            === 'ordinary_passport'
        ) {
            $allowedPassportFields = [
                'identity_type',
                'identity_number',
                'name',
                'nationality',
                'date_of_birth',
                'gender',
                'document_expiry_date',
                'passport_number',
                'passport_issue_date',
                'passport_expiry_date',
                'place_of_birth',
                'issuing_country',
                'issuing_authority',
            ];

            $result['fields'] =
                array_intersect_key(
                    $result['fields']
                    ?? [],
                    array_flip(
                        $allowedPassportFields
                    )
                );

            /*
             * Keep generic identity fields synchronized
             * with the structured passport fields.
             */
            if (
                !empty(
                    $result['fields'][
                        'passport_number'
                    ]
                )
            ) {
                $result['fields'][
                    'identity_type'
                ] = 'passport';

                $result['fields'][
                    'identity_number'
                ] =
                    $result['fields'][
                        'passport_number'
                    ];
            }

            if (
                !empty(
                    $result['fields'][
                        'passport_expiry_date'
                    ]
                )
            ) {
                $result['fields'][
                    'document_expiry_date'
                ] =
                    $result['fields'][
                        'passport_expiry_date'
                    ];
            }
        }

        /*
         * OCR uses the original upload. Only after
         * reading it do we create the small permanent
         * candidate image.
         */
        try {
            $imageToken =
                $images->stage(
                    $validated[
                        'document'
                    ]
                );

            $result['image_token'] =
                $imageToken;

            $result['image_preview_url'] =
                route(
                    'clients.identity-scan-preview',
                    [
                        'token' =>
                            $imageToken,
                    ],
                    false
                );


            $documentType =
                $result['document']['type']
                ?? null;

            $documentSide =
                $result['document']['side']
                ?? null;

            $mayUseFace =
                $documentType
                    === 'ordinary_passport'
                || (
                    $documentType
                        === 'qatar_id'
                    && in_array(
                        $documentSide,
                        [
                            'front',
                            'both',
                        ],
                        true
                    )
                );

            $result['face_preview_url'] =
                (
                    $mayUseFace
                    && $images->faceExists(
                        $imageToken
                    )
                )
                    ? route(
                        'clients.identity-face-preview',
                        [
                            'token' =>
                                $imageToken,
                        ],
                        false
                    )
                    : null;
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::warning(
                'Identity image staging failed.',
                [
                    'message' =>
                        $exception->getMessage(),
                ]
            );

            $result['image_token'] =
                null;

            $result['image_preview_url'] =
                null;

            $result['face_preview_url'] =
                null;
        }

        /*
         * Never expose full OCR text containing
         * identity-document data to the browser.
         */
        unset(
            $result[
                '_raw_text'
            ]
        );

        return response()
            ->json(
                $result
            )
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }
}
