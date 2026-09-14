<?php

namespace App\Services\Hotspot;

use App\Models\HotspotPlan;
use App\Models\HotspotServer;
use App\Models\HotspotSession;
use App\Models\HotspotVoucher;
use App\Models\Router;
use Carbon\Carbon;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class HotspotRouterService
{
    public function discover(
        Router $router
    ): array {
        $api = $this->api($router);

        $servers = $api->query(
            (new Query('/ip/hotspot/print'))
                ->equal(
                    '.proplist',
                    implode(',', [
                        '.id',
                        'name',
                        'interface',
                        'address-pool',
                        'profile',
                        'disabled',
                        'invalid',
                    ])
                )
        )->read();

        /*
         * PROFILE_DNS_DISCOVERY
         */
        $profiles = $api->query(
            (new Query(
                '/ip/hotspot/profile/print'
            ))
                ->equal(
                    '.proplist',
                    'name,dns-name'
                )
        )->read();

        $dnsByProfile = [];

        foreach ($profiles as $profile) {
            if (
                !isset(
                    $profile['name']
                )
            ) {
                continue;
            }

            $dnsByProfile[
                $profile['name']
            ] =
                $profile[
                    'dns-name'
                ] ?? null;
        }

        foreach ($servers as &$server) {
            $profileName =
                $server[
                    'profile'
                ] ?? null;

            $server[
                '_dns_name'
            ] =
                $profileName
                    ? (
                        $dnsByProfile[
                            $profileName
                        ] ?? null
                    )
                    : null;
        }

        unset($server);

        return $servers;
    }

    public function provisionVoucher(
        HotspotVoucher $voucher
    ): string {
        $voucher->loadMissing([
            'server.router',
            'plan',
        ]);

        $server = $voucher->server;
        $plan = $voucher->plan;

        if (
            !$server
            || !$server->router
            || !$plan
        ) {
            throw new \RuntimeException(
                'Hotspot voucher configuration is incomplete.'
            );
        }

        $api = $this->api(
            $server->router
        );

        $profileId = $this->ensureProfile(
            $api,
            $plan
        );

        $profileName =
            $plan->mikrotikProfileName();

        $existing = $api->query(
            (new Query(
                '/ip/hotspot/user/print'
            ))
                ->where(
                    'name',
                    $voucher->username
                )
                ->equal(
                    '.proplist',
                    '.id,name'
                )
        )->read();

        if ($existing !== []) {
            $id = $existing[0]['.id'];

            $query = (new Query(
                '/ip/hotspot/user/set'
            ))
                ->equal('.id', $id)
                ->equal(
                    'password',
                    $voucher->password
                )
                ->equal(
                    'profile',
                    $profileName
                )
                ->equal(
                    'server',
                    $server->mikrotik_name
                )
                ->equal(
                    'disabled',
                    'no'
                )
                ->equal(
                    'comment',
                    'MikroPanel Voucher #'
                    . $voucher->id
                );

            if (
                $plan->mac_binding
                && $voucher->mac_address
            ) {
                $query->equal(
                    'mac-address',
                    $voucher->mac_address
                );
            }

            $api->query($query)->read();

            return $id;
        }

        $query = (new Query(
            '/ip/hotspot/user/add'
        ))
            ->equal(
                'name',
                $voucher->username
            )
            ->equal(
                'password',
                $voucher->password
            )
            ->equal(
                'profile',
                $profileName
            )
            ->equal(
                'server',
                $server->mikrotik_name
            )
            ->equal(
                'disabled',
                'no'
            )
            ->equal(
                'comment',
                'MikroPanel Voucher #'
                . $voucher->id
            );

        $query->equal(
            'mac-address',
            $plan->mac_binding
                && $voucher->mac_address
                    ? strtoupper(
                        $voucher->mac_address
                    )
                    : '00:00:00:00:00:00'
        );

        $api->query($query)->read();

        $created = $api->query(
            (new Query(
                '/ip/hotspot/user/print'
            ))
                ->where(
                    'name',
                    $voucher->username
                )
                ->equal(
                    '.proplist',
                    '.id'
                )
        )->read();

        if ($created === []) {
            throw new \RuntimeException(
                'MikroTik Hotspot user was not found after creation.'
            );
        }

        return $created[0]['.id'];
    }

    public function suspendVoucher(
        HotspotVoucher $voucher
    ): void {
        $voucher->loadMissing(
            'server.router'
        );

        if (
            !$voucher->server
            || !$voucher->server->router
        ) {
            return;
        }

        $api = $this->api(
            $voucher->server->router
        );

        $users = $api->query(
            (new Query(
                '/ip/hotspot/user/print'
            ))
                ->where(
                    'name',
                    $voucher->username
                )
                ->equal(
                    '.proplist',
                    '.id'
                )
        )->read();

        foreach ($users as $user) {
            if (!isset($user['.id'])) {
                continue;
            }

            $api->query(
                (new Query(
                    '/ip/hotspot/user/set'
                ))
                    ->equal(
                        '.id',
                        $user['.id']
                    )
                    ->equal(
                        'disabled',
                        'yes'
                    )
            )->read();
        }

        $this->disconnectUsername(
            $api,
            $voucher->username
        );
    }

    public function activateVoucher(
        HotspotVoucher $voucher
    ): void {
        $voucher->loadMissing(
            'server.router'
        );

        if (
            !$voucher->server
            || !$voucher->server->router
        ) {
            throw new \RuntimeException(
                'Hotspot server is unavailable.'
            );
        }

        $api = $this->api(
            $voucher->server->router
        );

        $users = $api->query(
            (new Query(
                '/ip/hotspot/user/print'
            ))
                ->where(
                    'name',
                    $voucher->username
                )
                ->equal(
                    '.proplist',
                    '.id'
                )
        )->read();

        foreach ($users as $user) {
            if (!isset($user['.id'])) {
                continue;
            }

            $api->query(
                (new Query(
                    '/ip/hotspot/user/set'
                ))
                    ->equal(
                        '.id',
                        $user['.id']
                    )
                    ->equal(
                        'disabled',
                        'no'
                    )
            )->read();
        }
    }

    public function syncServer(
        HotspotServer $server
    ): array {
        $server->loadMissing('router');

        if (!$server->router) {
            throw new \RuntimeException(
                'Router is missing.'
            );
        }

        $api = $this->api(
            $server->router
        );

        $users = $api->query(
            (new Query(
                '/ip/hotspot/user/print'
            ))
                ->where(
                    'server',
                    $server->mikrotik_name
                )
                ->equal(
                    '.proplist',
                    implode(',', [
                        '.id',
                        'name',
                        'disabled',
                    ])
                )
        )->read();

        $active = $api->query(
            (new Query(
                '/ip/hotspot/active/print'
            ))
                ->where(
                    'server',
                    $server->mikrotik_name
                )
                ->equal(
                    '.proplist',
                    implode(',', [
                        '.id',
                        'user',
                        'address',
                        'mac-address',
                        'login-by',
                        'uptime',
                        'bytes-in',
                        'bytes-out',
                    ])
                )
        )->read();

        $seenSessionIds = [];

        foreach ($active as $row) {
            $username =
                $row['user'] ?? null;

            $activeId =
                $row['.id'] ?? null;

            if (
                !$username
                || !$activeId
            ) {
                continue;
            }

            $seenSessionIds[] =
                $activeId;

            $voucher = HotspotVoucher::query()
                ->where(
                    'hotspot_server_id',
                    $server->id
                )
                ->where(
                    'username',
                    $username
                )
                ->first();

            if ($voucher) {
                $now = Carbon::now(
                    'Asia/Qatar'
                );

                /*
                 * AUTO_BIND_HOTSPOT_MAC
                 *
                 * First successful login binds the
                 * observed device MAC when the plan
                 * requires MAC binding.
                 */
                $voucher->loadMissing(
                    'plan'
                );

                $observedMac = strtoupper(
                    (string) (
                        $row[
                            'mac-address'
                        ] ?? ''
                    )
                );

                if (
                    $voucher->plan
                    && $voucher
                        ->plan
                        ->mac_binding
                    && $observedMac !== ''
                ) {
                    if (
                        !$voucher
                            ->mac_address
                    ) {
                        $voucher->forceFill([
                            'mac_address' =>
                                $observedMac,
                        ])->save();

                        $usersForMac =
                            $api->query(
                                (new Query(
                                    '/ip/hotspot/user/print'
                                ))
                                    ->where(
                                        'name',
                                        $voucher
                                            ->username
                                    )
                                    ->equal(
                                        '.proplist',
                                        '.id'
                                    )
                            )->read();

                        foreach (
                            $usersForMac
                            as $hotspotUser
                        ) {
                            if (
                                !isset(
                                    $hotspotUser[
                                        '.id'
                                    ]
                                )
                            ) {
                                continue;
                            }

                            $api->query(
                                (new Query(
                                    '/ip/hotspot/user/set'
                                ))
                                    ->equal(
                                        '.id',
                                        $hotspotUser[
                                            '.id'
                                        ]
                                    )
                                    ->equal(
                                        'mac-address',
                                        $observedMac
                                    )
                            )->read();
                        }

                    } elseif (
                        strtoupper(
                            $voucher
                                ->mac_address
                        ) !== $observedMac
                    ) {
                        /*
                         * Bound voucher being used
                         * by another MAC: drop the
                         * current session immediately.
                         */
                        $api->query(
                            (new Query(
                                '/ip/hotspot/active/remove'
                            ))
                                ->equal(
                                    '.id',
                                    $activeId
                                )
                        )->read();

                        continue;
                    }
                }

                if (!$voucher->activated_at) {
                    $voucher->loadMissing(
                        'plan'
                    );

                    $expiry = $now
                        ->copy()
                        ->addSeconds(
                            $voucher
                                ->plan
                                ->validitySeconds()
                        );

                    $voucher->forceFill([
                        'activated_at' =>
                            $now,

                        'expires_at' =>
                            $expiry,

                        'status' =>
                            'active',
                    ]);
                }

                $voucher->forceFill([
                    'last_login_at' =>
                        $now,

                    'bytes_in' =>
                        max(
                            0,
                            (int) (
                                $row[
                                    'bytes-in'
                                ] ?? 0
                            )
                        ),

                    'bytes_out' =>
                        max(
                            0,
                            (int) (
                                $row[
                                    'bytes-out'
                                ] ?? 0
                            )
                        ),
                ])->save();
            }

            HotspotSession::query()
                ->updateOrCreate(
                    [
                        'hotspot_server_id' =>
                            $server->id,

                        'mikrotik_active_id' =>
                            $activeId,
                    ],
                    [
                        'hotspot_voucher_id' =>
                            $voucher?->id,

                        'username' =>
                            $username,

                        'mac_address' =>
                            $row[
                                'mac-address'
                            ] ?? null,

                        'address' =>
                            $row[
                                'address'
                            ] ?? null,

                        'login_by' =>
                            $row[
                                'login-by'
                            ] ?? null,

                        'uptime_seconds' =>
                            $this->durationToSeconds(
                                $row[
                                    'uptime'
                                ] ?? '0s'
                            ),

                        'bytes_in' =>
                            max(
                                0,
                                (int) (
                                    $row[
                                        'bytes-in'
                                    ] ?? 0
                                )
                            ),

                        'bytes_out' =>
                            max(
                                0,
                                (int) (
                                    $row[
                                        'bytes-out'
                                    ] ?? 0
                                )
                            ),

                        'active' => true,

                        'last_seen_at' =>
                            now(),

                        'ended_at' =>
                            null,
                    ]
                );
        }

        $ending = HotspotSession::query()
            ->where(
                'hotspot_server_id',
                $server->id
            )
            ->where(
                'active',
                true
            );

        if ($seenSessionIds !== []) {
            $ending->whereNotIn(
                'mikrotik_active_id',
                $seenSessionIds
            );
        }

        $ending->update([
            'active' => false,
            'ended_at' => now(),
        ]);

        $server->forceFill([
            'connected' => true,
            'users_count' =>
                count($users),

            'active_sessions_count' =>
                count($active),

            'last_synced_at' =>
                now(),

            'last_error' =>
                null,
        ])->save();

        return [
            'users' => count($users),
            'active' => count($active),
        ];
    }

    public function disconnectSession(
        HotspotSession $session
    ): void {
        $session->loadMissing(
            'server.router'
        );

        if (
            !$session->server
            || !$session->server->router
        ) {
            throw new \RuntimeException(
                'Hotspot server/router is unavailable.'
            );
        }

        $api = $this->api(
            $session->server->router
        );

        /*
         * Prefer current RouterOS active ID.
         * If it disappeared already, operation
         * is considered complete.
         */
        $current = $api->query(
            (new Query(
                '/ip/hotspot/active/print'
            ))
                ->where(
                    '.id',
                    $session->mikrotik_active_id
                )
                ->equal(
                    '.proplist',
                    '.id'
                )
        )->read();

        foreach ($current as $row) {
            if (!isset($row['.id'])) {
                continue;
            }

            $api->query(
                (new Query(
                    '/ip/hotspot/active/remove'
                ))
                    ->equal(
                        '.id',
                        $row['.id']
                    )
            )->read();
        }
    }

    private function ensureProfile(
        Client $api,
        HotspotPlan $plan
    ): string {
        $name =
            $plan->mikrotikProfileName();

        $profiles = $api->query(
            (new Query(
                '/ip/hotspot/user/profile/print'
            ))
                ->where(
                    'name',
                    $name
                )
                ->equal(
                    '.proplist',
                    '.id,name'
                )
        )->read();

        $query = $profiles !== []
            ? (new Query(
                '/ip/hotspot/user/profile/set'
            ))->equal(
                '.id',
                $profiles[0]['.id']
            )
            : (new Query(
                '/ip/hotspot/user/profile/add'
            ))->equal(
                'name',
                $name
            );

        $query->equal(
            'shared-users',
            (string) max(
                1,
                $plan->shared_users
            )
        );

        if ($plan->rate_limit) {
            $query->equal(
                'rate-limit',
                $plan->rate_limit
            );
        }

        if (
            $plan->idle_timeout_minutes
        ) {
            $query->equal(
                'idle-timeout',
                $plan
                    ->idle_timeout_minutes
                . 'm'
            );
        }

        if (
            $plan
                ->keepalive_timeout_minutes
        ) {
            $query->equal(
                'keepalive-timeout',
                $plan
                    ->keepalive_timeout_minutes
                . 'm'
            );
        }

        $api->query($query)->read();

        return $profiles[0]['.id']
            ?? $name;
    }

    private function disconnectUsername(
        Client $api,
        string $username
    ): void {
        $sessions = $api->query(
            (new Query(
                '/ip/hotspot/active/print'
            ))
                ->where(
                    'user',
                    $username
                )
                ->equal(
                    '.proplist',
                    '.id'
                )
        )->read();

        foreach ($sessions as $session) {
            if (!isset($session['.id'])) {
                continue;
            }

            $api->query(
                (new Query(
                    '/ip/hotspot/active/remove'
                ))
                    ->equal(
                        '.id',
                        $session['.id']
                    )
            )->read();
        }
    }

    private function api(
        Router $router
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
                    (int) (
                        $router->api_port
                        ?? 8728
                    ),

                'ssl' =>
                    (bool) (
                        $router->use_ssl
                        ?? false
                    ),

                'timeout' => 3,
                'attempts' => 1,
                'delay' => 0,
            ])
        );
    }

    private function durationToSeconds(
        string $value
    ): int {
        if (
            trim($value) === ''
            || $value === '0s'
        ) {
            return 0;
        }

        preg_match_all(
            '/(\d+)(w|d|h|m|s)/',
            $value,
            $matches,
            PREG_SET_ORDER
        );

        $seconds = 0;

        foreach ($matches as $match) {
            $number = (int) $match[1];

            $seconds += match (
                $match[2]
            ) {
                'w' =>
                    $number * 604800,

                'd' =>
                    $number * 86400,

                'h' =>
                    $number * 3600,

                'm' =>
                    $number * 60,

                default =>
                    $number,
            };
        }

        return $seconds;
    }
}
