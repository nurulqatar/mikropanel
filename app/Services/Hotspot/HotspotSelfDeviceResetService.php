<?php

namespace App\Services\Hotspot;

use App\Models\HotspotDeviceReset;
use App\Models\HotspotVoucher;
use App\Models\ResellerSetting;
use App\Models\Router;
use Illuminate\Support\Facades\DB;

class HotspotSelfDeviceResetService
{
    /*
     * HOTSPOT_SELF_DEVICE_RESET_MONTHLY_POLICY_V3
     *
     * Default company policy:
     * - Maximum 5 successful/partial resets per calendar month.
     * - 10 minute cooldown after successful/partial reset.
     *
     * Both values are reseller/company settings and can
     * be changed from the Router Health control panel.
     */

    public const DEFAULT_MONTHLY_LIMIT = 5;

    public const DEFAULT_COOLDOWN_MINUTES = 10;

    public const PROCESSING_LOCK_MINUTES = 5;

    public const MONTHLY_LIMIT_KEY =
        'hotspot_device_reset_monthly_limit';

    public const COOLDOWN_KEY =
        'hotspot_device_reset_cooldown_minutes';

    public function monthlyLimit(
        HotspotVoucher $voucher
    ): int {
        $resellerId =
            $this->resellerId(
                $voucher
            );

        if (!$resellerId) {
            return self::DEFAULT_MONTHLY_LIMIT;
        }

        $value =
            (int)
            ResellerSetting::getValue(
                $resellerId,
                self::MONTHLY_LIMIT_KEY,
                self::DEFAULT_MONTHLY_LIMIT
            );

        return max(
            1,
            min(
                100,
                $value
            )
        );
    }

    public function cooldownMinutes(
        HotspotVoucher $voucher
    ): int {
        $resellerId =
            $this->resellerId(
                $voucher
            );

        if (!$resellerId) {
            return self::DEFAULT_COOLDOWN_MINUTES;
        }

        $value =
            (int)
            ResellerSetting::getValue(
                $resellerId,
                self::COOLDOWN_KEY,
                self::DEFAULT_COOLDOWN_MINUTES
            );

        return max(
            0,
            min(
                1440,
                $value
            )
        );
    }

    public function policy(
        HotspotVoucher $voucher
    ): array {
        return $this->policyForVoucher(
            $voucher,
            now()
        );
    }

    public function beginReset(
        HotspotVoucher $voucher,
        Router $requestedRouter,
        ?string $oldMac,
        string $ipHash
    ): array {
        return DB::transaction(
            function () use (
                $voucher,
                $requestedRouter,
                $oldMac,
                $ipHash
            ): array {
                /*
                 * Lock the voucher row so two requests
                 * cannot create resets simultaneously.
                 */
                $lockedVoucher =
                    HotspotVoucher::withoutGlobalScopes()
                        ->where(
                            'id',
                            $voucher->id
                        )
                        ->lockForUpdate()
                        ->first();

                if (!$lockedVoucher) {
                    return [
                        'allowed' =>
                            false,

                        'message' =>
                            'Voucher could not be verified.',

                        'retry_after' =>
                            0,

                        'audit' =>
                            null,
                    ];
                }

                $policy =
                    $this->policyForVoucher(
                        $lockedVoucher,
                        now()
                    );

                if (
                    !(
                        $policy[
                            'allowed'
                        ] ?? false
                    )
                ) {
                    return [
                        ...$policy,

                        'audit' =>
                            null,
                    ];
                }

                $oldMac =
                    $this->normaliseMac(
                        $oldMac
                    );

                $audit =
                    HotspotDeviceReset::query()
                        ->create([
                            'reseller_id' =>
                                $lockedVoucher
                                    ->reseller_id,

                            'zone_id' =>
                                $lockedVoucher
                                    ->zone_id,

                            'hotspot_voucher_id' =>
                                $lockedVoucher
                                    ->id,

                            'requested_router_id' =>
                                $requestedRouter
                                    ->id,

                            'old_mac_address' =>
                                $oldMac,

                            'new_mac_address' =>
                                null,

                            'status' =>
                                'processing',

                            'request_ip_hash' =>
                                $ipHash,

                            'started_at' =>
                                now(),
                        ]);

                return [
                    ...$policy,

                    'allowed' =>
                        true,

                    'audit' =>
                        $audit,
                ];
            }
        );
    }

    public function complete(
        HotspotDeviceReset $audit,
        string $status,
        array $result = [],
        ?string $failureMessage = null
    ): void {
        if (
            !in_array(
                $status,
                [
                    'success',
                    'partial',
                    'failed',
                ],
                true
            )
        ) {
            throw new \InvalidArgumentException(
                'Invalid reset completion status.'
            );
        }

        $audit->forceFill([
            'status' =>
                $status,

            'routers_total' =>
                (int) (
                    $result[
                        'routers'
                    ] ?? 0
                ),

            'routers_succeeded' =>
                (int) (
                    $result[
                        'reset'
                    ] ?? 0
                ),

            'routers_failed' =>
                (int) (
                    $result[
                        'failed'
                    ] ?? 0
                ),

            'failure_message' =>
                $failureMessage,

            'result_json' =>
                $result,

            'completed_at' =>
                now(),
        ])->save();
    }

    public function captureRebinds(): int
    {
        $updated = 0;

        $rows =
            HotspotDeviceReset::query()
                ->whereIn(
                    'status',
                    [
                        'success',
                        'partial',
                    ]
                )
                ->whereNull(
                    'new_mac_address'
                )
                ->orderByDesc('id')
                ->limit(500)
                ->get();

        foreach ($rows as $audit) {
            $voucher =
                HotspotVoucher::withoutGlobalScopes()
                    ->find(
                        $audit
                            ->hotspot_voucher_id
                    );

            if (!$voucher) {
                continue;
            }

            $mac =
                $this->normaliseMac(
                    $voucher
                        ->mac_address
                );

            if (!$mac) {
                continue;
            }

            $audit->forceFill([
                'new_mac_address' =>
                    $mac,

                'rebound_at' =>
                    now(),
            ])->save();

            $updated++;
        }

        return $updated;
    }

    private function policyForVoucher(
        HotspotVoucher $voucher,
        \Illuminate\Support\Carbon $now
    ): array {
        $voucherId =
            (int)
            $voucher->id;

        $monthlyLimit =
            $this->monthlyLimit(
                $voucher
            );

        $cooldownMinutes =
            $this->cooldownMinutes(
                $voucher
            );

        $usedThisMonth =
            $this->successfulCountThisMonth(
                $voucherId,
                $now
            );

        $processing =
            HotspotDeviceReset::query()
                ->where(
                    'hotspot_voucher_id',
                    $voucherId
                )
                ->where(
                    'status',
                    'processing'
                )
                ->where(
                    'created_at',
                    '>=',
                    $now
                        ->copy()
                        ->subMinutes(
                            self::PROCESSING_LOCK_MINUTES
                        )
                )
                ->latest('id')
                ->first();

        if ($processing) {
            $until =
                $processing
                    ->created_at
                    ->copy()
                    ->addMinutes(
                        self::PROCESSING_LOCK_MINUTES
                    );

            return [
                'allowed' =>
                    false,

                'message' =>
                    'A device reset is already being processed. Please try again shortly.',

                'retry_after' =>
                    max(
                        1,
                        $now->diffInSeconds(
                            $until,
                            false
                        )
                    ),

                'cooldown_minutes' =>
                    $cooldownMinutes,

                'monthly_limit' =>
                    $monthlyLimit,

                'used_this_month' =>
                    $usedThisMonth,

                'remaining_this_month' =>
                    max(
                        0,
                        $monthlyLimit
                        - $usedThisMonth
                    ),
            ];
        }

        $latest =
            HotspotDeviceReset::query()
                ->where(
                    'hotspot_voucher_id',
                    $voucherId
                )
                ->whereIn(
                    'status',
                    [
                        'success',
                        'partial',
                    ]
                )
                ->latest(
                    'completed_at'
                )
                ->latest('id')
                ->first();

        if (
            $latest
            && $cooldownMinutes > 0
        ) {
            $base =
                $latest->completed_at
                ?: $latest->created_at;

            $cooldownUntil =
                $base
                    ->copy()
                    ->addMinutes(
                        $cooldownMinutes
                    );

            if (
                $cooldownUntil
                    ->isFuture()
            ) {
                return [
                    'allowed' =>
                        false,

                    'message' =>
                        'This voucher was recently reset. Please wait before changing device again.',

                    'retry_after' =>
                        max(
                            1,
                            $now->diffInSeconds(
                                $cooldownUntil,
                                false
                            )
                        ),

                    'cooldown_minutes' =>
                        $cooldownMinutes,

                    'monthly_limit' =>
                        $monthlyLimit,

                    'used_this_month' =>
                        $usedThisMonth,

                    'remaining_this_month' =>
                        max(
                            0,
                            $monthlyLimit
                            - $usedThisMonth
                        ),
                ];
            }
        }

        if (
            $usedThisMonth
            >= $monthlyLimit
        ) {
            $nextMonth =
                $now
                    ->copy()
                    ->addMonthNoOverflow()
                    ->startOfMonth();

            return [
                'allowed' =>
                    false,

                'message' =>
                    'Monthly device reset limit reached for this voucher. Maximum '
                    . $monthlyLimit
                    . ' reset(s) are allowed per calendar month.',

                'retry_after' =>
                    max(
                        1,
                        $now->diffInSeconds(
                            $nextMonth,
                            false
                        )
                    ),

                'cooldown_minutes' =>
                    $cooldownMinutes,

                'monthly_limit' =>
                    $monthlyLimit,

                'used_this_month' =>
                    $usedThisMonth,

                'remaining_this_month' =>
                    0,

                'resets_again_at' =>
                    $nextMonth
                        ->toIso8601String(),
            ];
        }

        return [
            'allowed' =>
                true,

            'message' =>
                'Device reset is available.',

            'retry_after' =>
                0,

            'cooldown_minutes' =>
                $cooldownMinutes,

            'monthly_limit' =>
                $monthlyLimit,

            'used_this_month' =>
                $usedThisMonth,

            'remaining_this_month' =>
                max(
                    0,
                    $monthlyLimit
                    - $usedThisMonth
                ),
        ];
    }

    private function successfulCountThisMonth(
        int $voucherId,
        \Illuminate\Support\Carbon $now
    ): int {
        $start =
            $now
                ->copy()
                ->startOfMonth();

        $end =
            $now
                ->copy()
                ->addMonthNoOverflow()
                ->startOfMonth();

        return HotspotDeviceReset::query()
            ->where(
                'hotspot_voucher_id',
                $voucherId
            )
            ->whereIn(
                'status',
                [
                    'success',
                    'partial',
                ]
            )
            ->where(
                'created_at',
                '>=',
                $start
            )
            ->where(
                'created_at',
                '<',
                $end
            )
            ->count();
    }

    private function resellerId(
        HotspotVoucher $voucher
    ): ?int {
        if (
            (int) (
                $voucher
                    ->reseller_id
                ?? 0
            ) > 0
        ) {
            return (int)
                $voucher
                    ->reseller_id;
        }

        $voucher->loadMissing(
            'server'
        );

        $resellerId =
            (int) (
                $voucher
                    ->server
                    ?->reseller_id
                ?? 0
            );

        return $resellerId > 0
            ? $resellerId
            : null;
    }

    private function normaliseMac(
        mixed $value
    ): ?string {
        $mac =
            strtoupper(
                trim(
                    (string)
                    $value
                )
            );

        if (
            !preg_match(
                '/^[0-9A-F]{2}(?::[0-9A-F]{2}){5}$/',
                $mac
            )
        ) {
            return null;
        }

        if (
            $mac
            === '00:00:00:00:00:00'
        ) {
            return null;
        }

        return $mac;
    }
}
