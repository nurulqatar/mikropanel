<?php

namespace App\Services\Hotspot;

use App\Models\HotspotDeviceReset;
use App\Models\HotspotVoucher;
use App\Models\Router;
use Illuminate\Support\Facades\DB;

class HotspotSelfDeviceResetService
{
    /*
     * HOTSPOT_SELF_DEVICE_RESET_V2
     *
     * Policy:
     * - 10 minute cooldown after successful/partial reset.
     * - Maximum 3 successful/partial resets in rolling 24 hours.
     * - Processing reset blocks concurrent reset attempts.
     * - New MAC is captured later from HotspotVoucher.mac_address
     *   after the existing RouterOS auto-bind engine observes login.
     */

    public const COOLDOWN_MINUTES = 10;

    public const DAILY_LIMIT = 3;

    public const PROCESSING_LOCK_MINUTES = 5;

    public function policy(
        HotspotVoucher $voucher
    ): array {
        return $this->policyForVoucher(
            (int) $voucher->id,
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
                 * Voucher row lock serializes reset creation
                 * for the same voucher.
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
                        (int)
                        $lockedVoucher->id,
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
        int $voucherId,
        \Illuminate\Support\Carbon $now
    ): array {
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
                    self::COOLDOWN_MINUTES,

                'daily_limit' =>
                    self::DAILY_LIMIT,

                'used_today' =>
                    $this->successfulCount(
                        $voucherId,
                        $now
                    ),

                'remaining_today' =>
                    max(
                        0,
                        self::DAILY_LIMIT
                        - $this->successfulCount(
                            $voucherId,
                            $now
                        )
                    ),
            ];
        }

        $successfulCount =
            $this->successfulCount(
                $voucherId,
                $now
            );

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

        if ($latest) {
            $base =
                $latest->completed_at
                ?: $latest->created_at;

            $cooldownUntil =
                $base
                    ->copy()
                    ->addMinutes(
                        self::COOLDOWN_MINUTES
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
                        self::COOLDOWN_MINUTES,

                    'daily_limit' =>
                        self::DAILY_LIMIT,

                    'used_today' =>
                        $successfulCount,

                    'remaining_today' =>
                        max(
                            0,
                            self::DAILY_LIMIT
                            - $successfulCount
                        ),
                ];
            }
        }

        if (
            $successfulCount
            >= self::DAILY_LIMIT
        ) {
            $oldest =
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
                    ->where(
                        'created_at',
                        '>=',
                        $now
                            ->copy()
                            ->subDay()
                    )
                    ->oldest(
                        'created_at'
                    )
                    ->first();

            $retryAfter =
                3600;

            if ($oldest) {
                $retryAfter =
                    max(
                        1,
                        $now->diffInSeconds(
                            $oldest
                                ->created_at
                                ->copy()
                                ->addDay(),
                            false
                        )
                    );
            }

            return [
                'allowed' =>
                    false,

                'message' =>
                    'Device reset limit reached for this voucher. Maximum 3 resets are allowed within 24 hours.',

                'retry_after' =>
                    $retryAfter,

                'cooldown_minutes' =>
                    self::COOLDOWN_MINUTES,

                'daily_limit' =>
                    self::DAILY_LIMIT,

                'used_today' =>
                    $successfulCount,

                'remaining_today' =>
                    0,
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
                self::COOLDOWN_MINUTES,

            'daily_limit' =>
                self::DAILY_LIMIT,

            'used_today' =>
                $successfulCount,

            'remaining_today' =>
                max(
                    0,
                    self::DAILY_LIMIT
                    - $successfulCount
                ),
        ];
    }

    private function successfulCount(
        int $voucherId,
        \Illuminate\Support\Carbon $now
    ): int {
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
                $now
                    ->copy()
                    ->subDay()
            )
            ->count();
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
