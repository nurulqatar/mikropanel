<?php

namespace App\Http\Controllers;

use App\Services\ClientIdentityOcrService;
use App\Services\IdentityDocumentClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientIdentityScanController extends Controller
{
    public function __invoke(
        Request $request,
        ClientIdentityOcrService $ocr,
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

        $result['document'] =
            $classifier->classify(
                $result
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
