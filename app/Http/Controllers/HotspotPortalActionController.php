<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionHotspotVoucher;
use App\Models\HotspotServer;
use App\Models\HotspotVoucher;
use App\Models\Router;
use App\Services\Hotspot\HotspotPortalPackageService;
use App\Services\Hotspot\HotspotRouterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class HotspotPortalActionController extends Controller
{
    /*
     * MAIN_HOTSPOT_PUBLIC_MAC_RESET_V1
     */
    /*
     * MAIN_HOTSPOT_TWO_STEP_MAC_RESET_V1
     *
     * Step 1: inspect
     *   Voucher Code -> current device/session info
     *   -> short-lived confirmation token.
     *
     * Step 2: reset
     *   Voucher Code + confirmation token
     *   -> clear RouterOS MAC + disconnect old session.
     */
    public function resetMac(
        Request $request,
        int $router,
        string $token,
        HotspotPortalPackageService $packages,
        HotspotRouterService $routerService
    ): JsonResponse {
        $routerModel =
            Router::withoutGlobalScopes()
                ->with('zone')
                ->find($router);

        if (
            !$routerModel
            || !$routerModel->zone
            || $routerModel
                ->zone
                ->service_type !== 'hotspot'
        ) {
            return $this->reply(
                'MAC reset request is not available.',
                404
            );
        }

        $expected =
            $packages->resetToken(
                $routerModel
            );

        if (
            !hash_equals(
                $expected,
                strtolower($token)
            )
        ) {
            return $this->reply(
                'MAC reset request is not available.',
                404
            );
        }

        $action =
            strtolower(
                trim(
                    (string)
                    $request->input(
                        'action',
                        'inspect'
                    )
                )
            );

        if (
            !in_array(
                $action,
                [
                    'inspect',
                    'reset',
                ],
                true
            )
        ) {
            return $this->reply(
                'Invalid MAC reset action.',
                422
            );
        }

        $code =
            preg_replace(
                '/\D+/',
                '',
                (string)
                $request->input(
                    'voucher_code',
                    ''
                )
            );

        if (
            !is_string($code)
            || !preg_match(
                '/^[0-9]{6}$/',
                $code
            )
        ) {
            return $this->reply(
                'Enter a valid 6-digit Voucher Code.',
                422
            );
        }

        $ip =
            (string) (
                $request->ip()
                ?: 'unknown'
            );

        $ipHash =
            hash(
                'sha256',
                $ip
            );

        /*
         * Voucher discovery is protected here.
         * The actual reset cannot happen without
         * the short-lived confirmation token.
         */
        if ($action === 'inspect') {
            $ipKey =
                'hotspot-mac-reset:ip:'
                . $routerModel->id
                . ':'
                . $ipHash;

            $voucherKey =
                'hotspot-mac-reset:voucher:'
                . $routerModel->id
                . ':'
                . hash(
                    'sha256',
                    $code
                );

            if (
                RateLimiter::tooManyAttempts(
                    $ipKey,
                    30
                )
            ) {
                return $this->reply(
                    'Too many voucher checks. Try again shortly.',
                    429,
                    [
                        'retry_after' =>
                            RateLimiter::availableIn(
                                $ipKey
                            ),
                    ]
                );
            }

            if (
                RateLimiter::tooManyAttempts(
                    $voucherKey,
                    5
                )
            ) {
                return $this->reply(
                    'Too many checks for this voucher. Try again shortly.',
                    429,
                    [
                        'retry_after' =>
                            RateLimiter::availableIn(
                                $voucherKey
                            ),
                    ]
                );
            }

            RateLimiter::hit(
                $ipKey,
                60
            );

            RateLimiter::hit(
                $voucherKey,
                60
            );
        }

        $serverIds =
            HotspotServer::withoutGlobalScopes()
                ->where(
                    'router_id',
                    $routerModel->id
                )
                ->pluck('id');

        if ($serverIds->isEmpty()) {
            return $this->reply(
                'Voucher could not be verified.',
                422
            );
        }

        $voucher =
            HotspotVoucher::withoutGlobalScopes()
                ->whereNull(
                    'deleted_at'
                )
                ->whereIn(
                    'hotspot_server_id',
                    $serverIds
                )
                ->where(
                    'username',
                    $code
                )
                ->whereNotNull(
                    'sold_at'
                )
                ->whereIn(
                    'status',
                    [
                        'unused',
                        'active',
                    ]
                )
                ->first();

        if (
            !$voucher
            || (
                $voucher->expires_at
                && $voucher
                    ->expires_at
                    ->isPast()
            )
        ) {
            return $this->reply(
                'Voucher could not be verified.',
                422
            );
        }

        if ($action === 'inspect') {
            try {
                $device =
                    $routerService
                        ->voucherDeviceInfo(
                            $voucher
                        );

                $confirmToken =
                    bin2hex(
                        random_bytes(32)
                    );

                $cacheKey =
                    'hotspot-mac-reset-confirm:'
                    . hash(
                        'sha256',
                        $confirmToken
                    );

                \Illuminate\Support\Facades\Cache::put(
                    $cacheKey,
                    [
                        'router_id' =>
                            (int)
                            $routerModel->id,

                        'voucher_id' =>
                            (int)
                            $voucher->id,

                        'ip_hash' =>
                            $ipHash,
                    ],
                    now()->addSeconds(
                        120
                    )
                );

                return $this->reply(
                    'Voucher verified. Review the current device before resetting.',
                    200,
                    [
                        'success' =>
                            true,

                        'action' =>
                            'inspect',

                        'voucher' => [
                            'code' =>
                                $code,

                            'status' =>
                                $voucher->status,

                            'expires_at' =>
                                $voucher
                                    ->expires_at
                                    ?->toIso8601String(),
                        ],

                        'device' =>
                            $device,

                        'confirm_token' =>
                            $confirmToken,

                        'confirm_expires_in' =>
                            120,
                    ]
                );

            } catch (Throwable $exception) {
                report(
                    $exception
                );

                return $this->reply(
                    'Router is temporarily unavailable. Current device information could not be loaded.',
                    503
                );
            }
        }

        $confirmToken =
            strtolower(
                trim(
                    (string)
                    $request->input(
                        'confirm_token',
                        ''
                    )
                )
            );

        if (
            !preg_match(
                '/^[a-f0-9]{64}$/',
                $confirmToken
            )
        ) {
            return $this->reply(
                'Check the voucher again before resetting.',
                409
            );
        }

        $cacheKey =
            'hotspot-mac-reset-confirm:'
            . hash(
                'sha256',
                $confirmToken
            );

        $confirmation =
            \Illuminate\Support\Facades\Cache::get(
                $cacheKey
            );

        if (
            !is_array($confirmation)
            || (int) (
                $confirmation['router_id']
                ?? 0
            ) !== (int) $routerModel->id
            || (int) (
                $confirmation['voucher_id']
                ?? 0
            ) !== (int) $voucher->id
            || !hash_equals(
                (string) (
                    $confirmation['ip_hash']
                    ?? ''
                ),
                $ipHash
            )
        ) {
            return $this->reply(
                'The reset confirmation expired. Check the voucher again.',
                409
            );
        }

        try {
            $routerUserFound =
                $routerService
                    ->resetVoucherMac(
                        $voucher
                    );

            DB::table(
                'hotspot_vouchers'
            )
                ->where(
                    'id',
                    $voucher->id
                )
                ->update([
                    'mac_address' =>
                        null,

                    'updated_at' =>
                        now(),
                ]);

            ProvisionHotspotVoucher::dispatch(
                $voucher->id
            );

            \Illuminate\Support\Facades\Cache::forget(
                $cacheKey
            );

            return $this->reply(
                $routerUserFound
                    ? 'Voucher reset successful. You can now use it on the new device.'
                    : 'Voucher reset accepted. Router synchronization has been queued.',
                200,
                [
                    'success' =>
                        true,

                    'action' =>
                        'reset',
                ]
            );

        } catch (Throwable $exception) {
            report(
                $exception
            );

            return $this->reply(
                'Router is temporarily unavailable. Voucher was not reset.',
                503
            );
        }
    }

    private function reply(
        string $message,
        int $status,
        array $extra = []
    ): JsonResponse {
        return response()
            ->json(
                [
                    ...$extra,

                    'message' =>
                        $message,
                ],
                $status
            )
            ->header(
                'Access-Control-Allow-Origin',
                '*'
            )
            ->header(
                'Cache-Control',
                'no-store, private, max-age=0'
            );
    }
}
