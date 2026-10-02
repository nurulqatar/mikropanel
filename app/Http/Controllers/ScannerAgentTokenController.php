<?php

namespace App\Http\Controllers;

use App\Services\ScannerAgentTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ScannerAgentTokenController extends Controller
{
    public function __invoke(
        Request $request,
        ScannerAgentTokenService $tokens,
    ): JsonResponse {
        $user =
            $request->user();

        abort_if(
            $user === null,
            401,
        );

        /*
         * Do not use a hard-coded APP_URL here.
         *
         * The token follows the CURRENT panel
         * scheme + host + port.
         */
        $origin = rtrim(
            $request->getSchemeAndHttpHost(),
            '/',
        );

        $response =
            response()->json(
                $tokens->issue(
                    $origin,
                    $user->getAuthIdentifier(),
                ),
            );

        $response->headers->set(
            'Cache-Control',
            'no-store, private',
        );

        $response->headers->set(
            'Pragma',
            'no-cache',
        );

        return $response;
    }
}
