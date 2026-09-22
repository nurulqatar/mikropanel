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

        /*
         * Two rate-limit layers:
         *
         * - router + source IP
         * - router + voucher code
         *
         * The voucher code itself is never stored
         * in the rate-limit key in plain text.
         */
        $ip =
            (string) (
                $request->ip()
                ?: 'unknown'
            );

        $ipKey =
            'hotspot-mac-reset:ip:'
            . $routerModel->id
            . ':'
            . hash(
                'sha256',
                $ip
            );

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
                'Too many reset attempts. Try again shortly.',
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
                'Too many reset attempts for this voucher. Try again shortly.',
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

        $serverIds =
            HotspotServer::withoutGlobalScopes()
                ->where(
                    'router_id',
                    $routerModel->id
                )
                ->pluck('id');

        if ($serverIds->isEmpty()) {
            return $this->reply(
                'Voucher could not be reset.',
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

        /*
         * Keep the public response generic so the
         * endpoint is not useful for voucher discovery.
         */
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
                'Voucher could not be reset.',
                422
            );
        }

        try {
            /*
             * First clear RouterOS and disconnect
             * any currently active session.
             *
             * If the router cannot be reached,
             * the database remains unchanged.
             */
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

            /*
             * Always queue normal provisioning as a
             * convergence pass. This also handles the
             * unusual case where the RouterOS user did
             * not exist yet.
             */
            ProvisionHotspotVoucher::dispatch(
                $voucher->id
            );

            return $this->reply(
                $routerUserFound
                    ? 'MAC reset successful. You can now use this voucher on the new device.'
                    : 'MAC reset accepted. Router synchronization has been queued.',
                200,
                [
                    'success' => true,
                ]
            );

        } catch (Throwable $exception) {
            report(
                $exception
            );

            return $this->reply(
                'Router is temporarily unavailable. Please try again.',
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
