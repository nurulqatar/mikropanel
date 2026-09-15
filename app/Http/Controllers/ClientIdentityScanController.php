<?php

namespace App\Http\Controllers;

use App\Services\ClientIdentityOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientIdentityScanController extends Controller
{
    public function __invoke(
        Request $request,
        ClientIdentityOcrService $ocr
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

        return response()
            ->json(
                $ocr->scan(
                    $validated[
                        'document'
                    ]
                )
            )
            ->header(
                'Cache-Control',
                'no-store, private'
            );
    }
}
