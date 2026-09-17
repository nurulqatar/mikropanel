<?php

namespace App\Http\Controllers;

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
        IdentityDocumentClassifier $classifier
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
