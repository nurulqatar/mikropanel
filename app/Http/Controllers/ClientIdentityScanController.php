<?php

namespace App\Http\Controllers;

use App\Services\ClientIdentityImageService;
use App\Services\ClientIdentityOcrService;
use App\Services\IdentityDocumentClassifier;
use App\Services\QatarIdStructureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientIdentityScanController extends Controller
{
    public function __invoke(
        Request $request,
        ClientIdentityOcrService $ocr,
        QatarIdStructureService $qatarId,
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

        $result['document'] =
            $classifier->classify([
                'fields' =>
                    $result[
                        'fields'
                    ],

                'raw_text' =>
                    $rawText,
            ]);

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
