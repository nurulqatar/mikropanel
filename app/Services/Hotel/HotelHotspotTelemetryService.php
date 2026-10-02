<?php

namespace App\Services\Hotel;

use App\Models\Hotel\HotelHotspotSession;
use App\Models\Hotel\HotelRouter;
use App\Models\Hotel\HotelVoucher;
use Illuminate\Support\Str;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class HotelHotspotTelemetryService
{
    public function syncAll(): array
    {
        $result = [
            'routers' => 0,
            'online' => 0,
            'sessions_seen' => 0,
            'failures' => 0,
            'errors' => [],
        ];

        $routers =
            HotelRouter::query()
                ->where(
                    'enabled',
                    true
                )
                ->orderBy('id')
                ->get();

        foreach ($routers as $router) {
            $result['routers']++;

            try {
                $stats =
                    $this->syncRouter(
                        $router
                    );

                $result['online'] +=
                    $stats['online'];

                $result['sessions_seen'] +=
                    $stats['sessions_seen'];
            } catch (Throwable $e) {
                $result['failures']++;

                $result['errors'][] = [
                    'router_id' =>
                        $router->id,

                    'name' =>
                        $router->name,

                    'error' =>
                        $this->safeMessage(
                            $e,
                            $router
                        ),
                ];
            }
        }

        return $result;
    }

    public function syncRouter(
        HotelRouter $router
    ): array {
        if (!$router->enabled) {
            return [
                'online' => 0,
                'sessions_seen' => 0,
            ];
        }

        $rows =
            $this->client($router)
                ->query(
                    new Query(
                        '/ip/hotspot/active/print'
                    )
                )
                ->read();

        $seenIds = [];
        $voucherIds = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $username =
                trim(
                    (string) (
                        $row['user']
                        ?? ''
                    )
                );

            if ($username === '') {
                continue;
            }

            $routerSessionId =
                $this->stringValue(
                    $row['.id']
                    ?? null
                );

            $ip =
                $this->stringValue(
                    $row['address']
                    ?? null
                );

            $mac =
                $this->stringValue(
                    $row[
                        'mac-address'
                    ]
                    ?? null
                );

            $sessionQuery =
                HotelHotspotSession::query()
                    ->where(
                        'hotel_router_id',
                        $router->id
                    )
                    ->where(
                        'username',
                        $username
                    )
                    ->where(
                        'is_online',
                        true
                    );

            if ($routerSessionId) {
                $sessionQuery->where(
                    'router_session_id',
                    $routerSessionId
                );
            } else {
                $sessionQuery
                    ->where(
                        'ip_address',
                        $ip
                    )
                    ->where(
                        'mac_address',
                        $mac
                    );
            }

            $session =
                $sessionQuery
                    ->latest('id')
                    ->first();

            if (!$session) {
                $session =
                    new HotelHotspotSession([
                        'session_key' =>
                            (string)
                            Str::uuid(),

                        'started_at' =>
                            now(),
                    ]);
            }

            $voucher =
                HotelVoucher::query()
                    ->where(
                        'hotel_id',
                        $router->hotel_id
                    )
                    ->where(
                        'username',
                        $username
                    )
                    ->first();

            $session->fill([
                'hotel_id' =>
                    $router->hotel_id,

                'hotel_router_id' =>
                    $router->id,

                'hotel_voucher_id' =>
                    $voucher?->id,

                'router_session_id' =>
                    $routerSessionId,

                'username' =>
                    $username,

                'ip_address' =>
                    $ip,

                'mac_address' =>
                    $mac,

                'server_name' =>
                    $this->stringValue(
                        $row['server']
                        ?? null
                    ),

                'login_by' =>
                    $this->stringValue(
                        $row['login-by']
                        ?? null
                    ),

                'uptime' =>
                    $this->stringValue(
                        $row['uptime']
                        ?? null
                    ),

                'session_time_left' =>
                    $this->stringValue(
                        $row[
                            'session-time-left'
                        ]
                        ?? null
                    ),

                'bytes_in' =>
                    max(
                        0,
                        (int) (
                            $row['bytes-in']
                            ?? 0
                        )
                    ),

                'bytes_out' =>
                    max(
                        0,
                        (int) (
                            $row['bytes-out']
                            ?? 0
                        )
                    ),

                'is_online' =>
                    true,

                'last_seen_at' =>
                    now(),

                'ended_at' =>
                    null,
            ]);

            $session->save();

            $seenIds[] =
                $session->id;

            if ($voucher) {
                $voucherIds[] =
                    $voucher->id;

                $changes = [
                    'activated_at' =>
                        $voucher
                            ->activated_at
                        ?? now(),

                    'last_login_at' =>
                        now(),
                ];

                if (
                    $voucher->expires_at
                    && $voucher
                        ->expires_at
                        ->lte(now())
                ) {
                    $changes['status'] =
                        'expired';
                } elseif (
                    $voucher->status
                    === 'unused'
                ) {
                    $changes['status'] =
                        'active';
                }

                $voucher
                    ->forceFill(
                        $changes
                    )
                    ->save();
            }
        }

        $closing =
            HotelHotspotSession::query()
                ->where(
                    'hotel_router_id',
                    $router->id
                )
                ->where(
                    'is_online',
                    true
                );

        if ($seenIds !== []) {
            $closing->whereNotIn(
                'id',
                $seenIds
            );
        }

        $closingVoucherIds =
            (clone $closing)
                ->whereNotNull(
                    'hotel_voucher_id'
                )
                ->pluck(
                    'hotel_voucher_id'
                )
                ->all();

        $closing->update([
            'is_online' =>
                false,

            'last_seen_at' =>
                now(),

            'ended_at' =>
                now(),
        ]);

        $voucherIds =
            array_values(
                array_unique([
                    ...$voucherIds,
                    ...$closingVoucherIds,
                ])
            );

        foreach ($voucherIds as $id) {
            $this->updateVoucherUsage(
                (int) $id
            );
        }

        return [
            'online' =>
                count($seenIds),

            'sessions_seen' =>
                count($seenIds),
        ];
    }

    private function updateVoucherUsage(
        int $voucherId
    ): void {
        $voucher =
            HotelVoucher::query()
                ->find(
                    $voucherId
                );

        if (!$voucher) {
            return;
        }

        $totals =
            HotelHotspotSession::query()
                ->where(
                    'hotel_voucher_id',
                    $voucher->id
                )
                ->selectRaw(
                    '
                    COALESCE(
                        SUM(bytes_in),
                        0
                    ) AS total_in,
                    COALESCE(
                        SUM(bytes_out),
                        0
                    ) AS total_out
                    '
                )
                ->first();

        $voucher->forceFill([
            'bytes_in' =>
                (int) (
                    $totals
                        ?->total_in
                    ?? 0
                ),

            'bytes_out' =>
                (int) (
                    $totals
                        ?->total_out
                    ?? 0
                ),
        ])->save();
    }

    private function client(
        HotelRouter $router
    ): Client {
        return new Client(
            new Config([
                'host' =>
                    $router->host,

                'user' =>
                    $router->username,

                'pass' =>
                    $router->password,

                'port' =>
                    $router->api_port,

                'ssl' =>
                    (bool)
                    $router->use_ssl,

                'timeout' =>
                    5,

                'socket_timeout' =>
                    5,

                'attempts' =>
                    1,

                'delay' =>
                    0,
            ])
        );
    }

    private function stringValue(
        mixed $value
    ): ?string {
        $value =
            trim(
                (string) $value
            );

        return $value !== ''
            ? mb_substr(
                $value,
                0,
                191
            )
            : null;
    }

    private function safeMessage(
        Throwable $e,
        HotelRouter $router
    ): string {
        $message =
            trim(
                $e->getMessage()
            );

        $password =
            (string)
            $router->password;

        if ($password !== '') {
            $message =
                str_replace(
                    $password,
                    '[hidden]',
                    $message
                );
        }

        return mb_substr(
            $message !== ''
                ? $message
                : 'MikroTik telemetry failed.',
            0,
            500
        );
    }
}
