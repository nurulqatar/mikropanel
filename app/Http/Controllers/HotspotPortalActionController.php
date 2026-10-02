<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionHotspotVoucher;
use App\Models\HotspotServer;
use App\Models\HotspotVoucher;
use App\Models\Router;
use App\Services\CompanyBrandingService;
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
    /*
     * HOTSPOT_SELF_DEVICE_RESET_V2
     *
     * Public captive-portal device reset:
     * - HMAC router token
     * - 6-digit sold voucher verification
     * - IP/voucher rate limits
     * - 120 second one-time confirmation
     * - 10 minute cooldown
     * - max 3 successful resets / rolling 24h
     * - zone-wide RouterOS MAC/session release
     * - audit history
     * - normal provisioning queue convergence
     */
    /*
     * MAIN_HOTSPOT_PUBLIC_BRANDING_V1
     *
     * Only non-sensitive Company display
     * branding is returned.
     */
    public function branding(
        int $router,
        string $token,
        HotspotPortalPackageService $packages,
        CompanyBrandingService $branding
    ): JsonResponse {
        $routerModel =
            Router::withoutGlobalScopes()
                ->with([
                    'zone:id,reseller_id,service_type',
                ])
                ->find(
                    $router
                );

        if (
            !$routerModel
            || !$routerModel->zone
            || $routerModel
                ->zone
                ->service_type !== 'hotspot'
        ) {
            return response()
                ->json(
                    [
                        'message' =>
                            'Portal branding is unavailable.',
                    ],
                    404
                )
                ->header(
                    'Access-Control-Allow-Origin',
                    '*'
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
            return response()
                ->json(
                    [
                        'message' =>
                            'Portal branding is unavailable.',
                    ],
                    404
                )
                ->header(
                    'Access-Control-Allow-Origin',
                    '*'
                );
        }

        $company =
            $branding->forResellerId(
                $routerModel
                    ->zone
                    ->reseller_id
                    ? (int)
                        $routerModel
                            ->zone
                            ->reseller_id
                    : null
            );

        return response()
            ->json([
                'company_name' =>
                    trim(
                        (string) (
                            $company[
                                'company_name'
                            ]
                            ?? ''
                        )
                    ),

                'company_slogan' =>
                    trim(
                        (string) (
                            $company[
                                'company_slogan'
                            ]
                            ?? ''
                        )
                    ),
            ])
            ->header(
                'Access-Control-Allow-Origin',
                '*'
            )
            ->header(
                'Cache-Control',
                'no-store, max-age=0'
            );
    }

    public function resetMac(
        Request $request,
        int $router,
        string $token,
        HotspotPortalPackageService $packages,
        HotspotRouterService $routerService,
        \App\Services\Hotspot\HotspotZoneVoucherService $zoneService,
        \App\Services\Hotspot\HotspotSelfDeviceResetService $resetService
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
                'Device reset request is not available.',
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
                'Device reset request is not available.',
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
                'Invalid device reset action.',
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

        /*
         * Do not store a reversible/plain IP value
         * in reset confirmation or audit state.
         */
        $ipHash =
            hash_hmac(
                'sha256',
                $ip,
                (string)
                config('app.key')
            );

        if ($action === 'inspect') {
            $ipKey =
                'hotspot-mac-reset:ip:'
                . $routerModel->id
                . ':'
                . $ipHash;

            $voucherKey =
                'hotspot-mac-reset:voucher:'
                . $routerModel->zone_id
                . ':'
                . hash_hmac(
                    'sha256',
                    $code,
                    (string)
                    config('app.key')
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

        /*
         * Zone ownership is the primary boundary.
         * Legacy vouchers without zone_id may still
         * match the current router's Hotspot server.
         */
        $serverIds =
            HotspotServer::withoutGlobalScopes()
                ->where(
                    'router_id',
                    $routerModel->id
                )
                ->where(
                    'zone_id',
                    $routerModel->zone_id
                )
                ->pluck('id');

        $voucher =
            HotspotVoucher::withoutGlobalScopes()
                ->whereNull(
                    'deleted_at'
                )
                ->where(
                    function ($query) use (
                        $routerModel,
                        $serverIds
                    ): void {
                        $query->where(
                            'zone_id',
                            $routerModel->zone_id
                        );

                        if (
                            !$serverIds
                                ->isEmpty()
                        ) {
                            $query->orWhere(
                                function ($legacy) use (
                                    $serverIds
                                ): void {
                                    $legacy
                                        ->whereNull(
                                            'zone_id'
                                        )
                                        ->whereIn(
                                            'hotspot_server_id',
                                            $serverIds
                                        );
                                }
                            );
                        }
                    }
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

        /*
         * Extra tenant guard for vouchers that already
         * carry reseller ownership.
         */
        if (
            $voucher->reseller_id
            && $routerModel
                ->zone
                ->reseller_id
            && (int)
                $voucher
                    ->reseller_id
                !== (int)
                    $routerModel
                        ->zone
                        ->reseller_id
        ) {
            return $this->reply(
                'Voucher could not be verified.',
                422
            );
        }

        if ($action === 'inspect') {
            $policy =
                $resetService
                    ->policy(
                        $voucher
                    );

            if (
                !(
                    $policy[
                        'allowed'
                    ] ?? false
                )
            ) {
                return $this->reply(
                    $policy[
                        'message'
                    ]
                    ?? 'Device reset is temporarily unavailable.',
                    429,
                    [
                        'retry_after' =>
                            (int) (
                                $policy[
                                    'retry_after'
                                ] ?? 0
                            ),

                        'reset_policy' =>
                            $policy,
                    ]
                );
            }

            try {
                $device =
                    $zoneService
                        ->voucherDeviceInfoAcrossZone(
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

                        'zone_id' =>
                            (int)
                            $routerModel
                                ->zone_id,

                        'voucher_id' =>
                            (int)
                            $voucher->id,

                        'ip_hash' =>
                            $ipHash,

                        'old_mac' =>
                            $device[
                                'mac_address'
                            ]
                            ?? $voucher
                                ->mac_address,
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

                        'reset_policy' =>
                            $policy,

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

        /*
         * Pull makes confirmation strictly one-time.
         * Even a failed RouterOS attempt requires a
         * fresh voucher inspection.
         */
        $confirmation =
            \Illuminate\Support\Facades\Cache::pull(
                $cacheKey
            );

        if (
            !is_array($confirmation)
            || (int) (
                $confirmation[
                    'router_id'
                ] ?? 0
            ) !== (int)
                $routerModel->id
            || (int) (
                $confirmation[
                    'zone_id'
                ] ?? 0
            ) !== (int)
                $routerModel->zone_id
            || (int) (
                $confirmation[
                    'voucher_id'
                ] ?? 0
            ) !== (int)
                $voucher->id
            || !hash_equals(
                (string) (
                    $confirmation[
                        'ip_hash'
                    ] ?? ''
                ),
                $ipHash
            )
        ) {
            return $this->reply(
                'The reset confirmation expired. Check the voucher again.',
                409
            );
        }

        $begin =
            $resetService
                ->beginReset(
                    $voucher,
                    $routerModel,
                    $confirmation[
                        'old_mac'
                    ]
                    ?? $voucher
                        ->mac_address,
                    $ipHash
                );

        if (
            !(
                $begin[
                    'allowed'
                ] ?? false
            )
        ) {
            return $this->reply(
                $begin[
                    'message'
                ]
                ?? 'Device reset is temporarily unavailable.',
                429,
                [
                    'retry_after' =>
                        (int) (
                            $begin[
                                'retry_after'
                            ] ?? 0
                        ),
                ]
            );
        }

        /** @var \App\Models\HotspotDeviceReset $audit */
        $audit =
            $begin['audit'];

        try {
            $zoneResult =
                $zoneService
                    ->resetVoucherMacAcrossZone(
                        $voucher
                    );

            if (
                (int) (
                    $zoneResult[
                        'reset'
                    ] ?? 0
                ) < 1
            ) {
                $resetService
                    ->complete(
                        $audit,
                        'failed',
                        $zoneResult,
                        'No online router accepted the device reset.'
                    );

                return $this->reply(
                    'Router is temporarily unavailable. Voucher was not reset.',
                    503
                );
            }

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

            /*
             * Existing zone voucher sync engine now
             * reprovisions the MAC-free voucher.
             *
             * On next login the existing
             * AUTO_BIND_HOTSPOT_MAC logic binds the
             * newly observed device automatically.
             */
            ProvisionHotspotVoucher::dispatch(
                $voucher->id
            );

            $partial =
                (int) (
                    $zoneResult[
                        'failed'
                    ] ?? 0
                ) > 0;

            $resetService
                ->complete(
                    $audit,
                    $partial
                        ? 'partial'
                        : 'success',
                    $zoneResult,
                    $partial
                        ? 'One or more offline routers will converge through the normal provisioning queue.'
                        : null
                );

            return $this->reply(
                $partial
                    ? 'Device reset successful. The new device can now log in. Offline routers will synchronize automatically after reconnect.'
                    : 'Device reset successful. Connect the new device and log in with the same voucher.',
                200,
                [
                    'success' =>
                        true,

                    'action' =>
                        'reset',

                    'zone_sync' => [
                        'routers' =>
                            (int) (
                                $zoneResult[
                                    'routers'
                                ] ?? 0
                            ),

                        'reset' =>
                            (int) (
                                $zoneResult[
                                    'reset'
                                ] ?? 0
                            ),

                        'failed' =>
                            (int) (
                                $zoneResult[
                                    'failed'
                                ] ?? 0
                            ),
                    ],

                    'cooldown_minutes' =>
                        $resetService
                            ->cooldownMinutes(
                                $voucher
                            ),

                    'monthly_limit' =>
                        $resetService
                            ->monthlyLimit(
                                $voucher
                            ),
                ]
            );

        } catch (Throwable $exception) {
            try {
                $resetService
                    ->complete(
                        $audit,
                        'failed',
                        [],
                        $exception
                            ->getMessage()
                    );
            } catch (Throwable) {
                // Preserve the original reset error.
            }

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
