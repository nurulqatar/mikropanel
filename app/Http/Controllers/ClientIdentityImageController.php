<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientIdentityImageController extends Controller
{
    public function __invoke(
        Request $request,
        Client $client,
        string $kind
    ): StreamedResponse {
        $user =
            $request->user();

        abort_unless(
            $user
            && (
                $user->isAdmin()
                || $user->isSuperAdmin()
                || $user->hasAnyPermission([
                    'clients.view',
                    'clients.create',
                    'clients.edit',
                ])
            ),
            403
        );

        $columns = [
            'qid-front' =>
                'qatar_id_front_image_path',

            'qid-back' =>
                'qatar_id_back_image_path',

            'passport' =>
                'passport_image_path',

            'face' =>
                'profile_image_path',
        ];

        abort_unless(
            isset(
                $columns[
                    $kind
                ]
            ),
            404
        );

        $path =
            $client->getAttribute(
                $columns[
                    $kind
                ]
            );

        abort_unless(
            is_string(
                $path
            )
            && $path !== ''
            && Storage::disk('local')
                ->exists(
                    $path
                ),
            404
        );

        return Storage::disk('local')
            ->response(
                $path,
                $kind . '.webp',
                [
                    'Content-Type' =>
                        'image/webp',

                    'Cache-Control' =>
                        'private, max-age=300',
                ]
            );
    }

    public function preview(
        Request $request,
        string $token
    ): StreamedResponse {
        $user =
            $request->user();

        abort_unless(
            $user
            && (
                $user->isAdmin()
                || $user->isSuperAdmin()
                || $user->hasAnyPermission([
                    'clients.view',
                    'clients.create',
                    'clients.edit',
                ])
            ),
            403
        );

        abort_unless(
            preg_match(
                '/^[0-9a-f]{8}-'
                . '[0-9a-f]{4}-'
                . '[1-5][0-9a-f]{3}-'
                . '[89ab][0-9a-f]{3}-'
                . '[0-9a-f]{12}$/i',
                $token
            ) === 1,
            404
        );

        $path =
            'identity-scans/tmp/'
            . $token
            . '.webp';

        abort_unless(
            Storage::disk('local')
                ->exists(
                    $path
                ),
            404
        );

        return Storage::disk('local')
            ->response(
                $path,
                'identity-preview.webp',
                [
                    'Content-Type' =>
                        'image/webp',

                    'Cache-Control' =>
                        'no-store, private',
                ]
            );
    }


    public function previewFace(
        Request $request,
        string $token
    ): StreamedResponse {
        $user =
            $request->user();

        abort_unless(
            $user
            && (
                $user->isAdmin()
                || $user->isSuperAdmin()
                || $user->hasAnyPermission([
                    'clients.view',
                    'clients.create',
                    'clients.edit',
                ])
            ),
            403
        );

        abort_unless(
            preg_match(
                '/^[0-9a-f]{8}-'
                . '[0-9a-f]{4}-'
                . '[1-5][0-9a-f]{3}-'
                . '[89ab][0-9a-f]{3}-'
                . '[0-9a-f]{12}$/i',
                $token
            ) === 1,
            404
        );

        $path =
            'identity-scans/tmp/'
            . $token
            . '-face.webp';

        abort_unless(
            Storage::disk('local')
                ->exists(
                    $path
                ),
            404
        );

        return Storage::disk('local')
            ->response(
                $path,
                'client-face.webp',
                [
                    'Content-Type' =>
                        'image/webp',

                    'Cache-Control' =>
                        'no-store, private',
                ]
            );
    }

}
