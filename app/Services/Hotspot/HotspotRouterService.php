<?php

namespace App\Services\Hotspot;

use App\Jobs\ProvisionHotspotVoucher;
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
    public function __construct(
        private readonly HotspotBillingService $billing
    ) {
    }

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

        /*
         * HOTSPOT_FIRST_LOGIN_AUTO_SALE_V2
         */
        $expectedComment =
            'MikroPanel Voucher #'
            . $voucher->id;

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
                    '.id,name,comment'
                )
        )->read();

        if ($existing !== []) {
            foreach ($existing as $row) {
                if (
                    (string) (
                        $row['comment']
                        ?? ''
                    ) !== $expectedComment
                ) {
                    throw new \RuntimeException(
                        'Hotspot username conflict: '
                        . $voucher->username
                    );
                }
            }

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
                    'false'
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
                'false'
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


    public function deleteVoucherFromRouter(
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
                'Hotspot server/router is unavailable.'
            );
        }

        $api = $this->api(
            $voucher->server->router
        );

        $this->disconnectUsername(
            $api,
            $voucher->username
        );

        $expectedComment =
            'MikroPanel Voucher #'
            . $voucher->id;

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
                    '.id,name,comment'
                )
        )->read();

        foreach ($users as $user) {
            if (!isset($user['.id'])) {
                continue;
            }

            if (
                (string) (
                    $user['comment']
                    ?? ''
                ) !== $expectedComment
            ) {
                throw new \RuntimeException(
                    'Refusing to remove unmanaged Hotspot user '
                    . $voucher->username
                );
            }

            $api->query(
                (new Query(
                    '/ip/hotspot/user/remove'
                ))
                    ->equal(
                        '.id',
                        $user['.id']
                    )
            )->read();
        }
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
                        'true'
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
                        'false'
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

        /*
         * AUTO_HOTSPOT_PORTAL_ACCESS_V1
         *
         * Every normal Hotspot sync refreshes the
         * portal hostname and server IPv4 fallback.
         */
        $portalHost =
            parse_url(
                (string)
                config(
                    'app.url'
                ),
                PHP_URL_HOST
            );

        if (
            is_string(
                $portalHost
            )
            && trim(
                $portalHost
            ) !== ''
        ) {
            try {
                $this
                    ->ensurePortalHostAccess(
                        $server->router,
                        $portalHost
                    );

            } catch (\Throwable $exception) {
                report(
                    $exception
                );
            }
        }

        $api = $this->api(
            $server->router
        );

        /*
         * HOTSPOT_PLAN_AUTO_PROFILE_V2
         */
        HotspotPlan::withoutGlobalScopes()
            ->where(
                'enabled',
                true
            )
            ->when(
                $server->reseller_id !== null,
                fn ($query) =>
                    $query->where(
                        'reseller_id',
                        $server->reseller_id
                    ),
                fn ($query) =>
                    $query->whereNull(
                        'reseller_id'
                    )
            )
            ->orderBy('id')
            ->each(
                function (
                    HotspotPlan $plan
                ) use ($api): void {
                    $this->ensureProfile(
                        $api,
                        $plan
                    );
                }
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
                    'username',
                    $username
                )
                ->where(
                    function ($query) use ($server): void {
                        if ($server->zone_id) {
                            $query->where(
                                'zone_id',
                                $server->zone_id
                            );
                            return;
                        }

                        $query->where(
                            'hotspot_server_id',
                            $server->id
                        );
                    }
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

                        ProvisionHotspotVoucher::dispatch(
                            $voucher->id
                        );

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

                $voucher->loadMissing(
                    'plan'
                );

                if (!$voucher->plan) {
                    throw new \RuntimeException(
                        'Hotspot voucher plan is missing.'
                    );
                }

                /*
                 * HOTSPOT_SELLER_FIRST_LOGIN_PAID_V1
                 *
                 * First actual RouterOS session starts the
                 * validity and records a fully-paid sale.
                 */
                if (!$voucher->activated_at) {
                    $sessionUptime =
                        $this->durationToSeconds(
                            $row['uptime']
                            ?? '0s'
                        );

                    $firstLoginAt =
                        $now
                            ->copy()
                            ->subSeconds(
                                max(
                                    0,
                                    $sessionUptime
                                )
                            );

                    $expiry =
                        $firstLoginAt
                            ->copy()
                            ->addSeconds(
                                $voucher
                                    ->plan
                                    ->validitySeconds()
                            );

                    $this->billing
                        ->recordFirstLoginPaidSale(
                            $voucher,
                            $firstLoginAt,
                            $expiry
                        );

                    $voucher->refresh();
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

    /*
     * MAIN_HOTSPOT_PUBLIC_MAC_RESET_V1
     *
     * Allow the MikroPanel reset endpoint before
     * Hotspot authentication.
     *
     * RouterOS creates dynamic destination entries
     * for dst-host in walled-garden ip.
     */
    /*
     * MAIN_HOTSPOT_PUBLIC_MAC_RESET_V2
     *
     * Primary:
     *   APP_URL hostname.
     *
     * Fallback:
     *   Current resolved IPv4 A record(s).
     *
     * Only MikroPanel-owned fallback IP rules
     * are removed when DNS changes.
     */
    public function ensurePortalHostAccess(
        Router $router,
        string $host
    ): array {
        $host =
            strtolower(
                rtrim(
                    trim(
                        $host
                    ),
                    '.'
                )
            );

        if (
            $host === ''
            || !preg_match(
                '/^[a-z0-9.-]+$/',
                $host
            )
        ) {
            throw new \RuntimeException(
                'Invalid portal host.'
            );
        }

        $api =
            $this->api(
                $router
            );

        $rows =
            $api->query(
                (new Query(
                    '/ip/hotspot/walled-garden/ip/print'
                ))
                    ->equal(
                        '.proplist',
                        implode(',', [
                            '.id',
                            'dst-host',
                            'dst-address',
                            'action',
                            'disabled',
                            'comment',
                        ])
                    )
            )->read();

        $hostComment =
            'MikroPanel Portal MAC Reset';

        $ipComment =
            'MikroPanel Portal MAC Reset IP';

        $hostReady = false;
        $hostAdded = false;

        foreach ($rows as $row) {
            $rowHost =
                strtolower(
                    rtrim(
                        trim(
                            (string) (
                                $row[
                                    'dst-host'
                                ]
                                ?? ''
                            )
                        ),
                        '.'
                    )
                );

            if ($rowHost !== $host) {
                continue;
            }

            $disabled =
                in_array(
                    strtolower(
                        (string) (
                            $row[
                                'disabled'
                            ]
                            ?? 'false'
                        )
                    ),
                    [
                        'true',
                        'yes',
                        '1',
                    ],
                    true
                );

            $action =
                strtolower(
                    (string) (
                        $row[
                            'action'
                        ]
                        ?? 'accept'
                    )
                );

            if (
                !$disabled
                && $action === 'accept'
            ) {
                $hostReady = true;
                break;
            }

            if (
                (string) (
                    $row[
                        'comment'
                    ]
                    ?? ''
                ) === $hostComment
                && isset(
                    $row['.id']
                )
            ) {
                $api->query(
                    (new Query(
                        '/ip/hotspot/walled-garden/ip/set'
                    ))
                        ->equal(
                            '.id',
                            $row['.id']
                        )
                        ->equal(
                            'action',
                            'accept'
                        )
                        ->equal(
                            'disabled',
                            'false'
                        )
                )->read();

                $hostReady = true;
                break;
            }
        }

        if (!$hostReady) {
            $api->query(
                (new Query(
                    '/ip/hotspot/walled-garden/ip/add'
                ))
                    ->equal(
                        'dst-host',
                        $host
                    )
                    ->equal(
                        'action',
                        'accept'
                    )
                    ->equal(
                        'comment',
                        $hostComment
                    )
            )->read();

            $hostAdded = true;
        }

        $resolved = [];

        if (
            filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4
            )
        ) {
            $resolved[] =
                $host;

        } else {
            $dnsRows =
                @dns_get_record(
                    $host,
                    DNS_A
                );

            if (is_array($dnsRows)) {
                foreach ($dnsRows as $dnsRow) {
                    $ip =
                        trim(
                            (string) (
                                $dnsRow['ip']
                                ?? ''
                            )
                        );

                    if (
                        filter_var(
                            $ip,
                            FILTER_VALIDATE_IP,
                            FILTER_FLAG_IPV4
                        )
                    ) {
                        $resolved[] =
                            $ip;
                    }
                }
            }

            if ($resolved === []) {
                $fallback =
                    @gethostbynamel(
                        $host
                    );

                if (is_array($fallback)) {
                    foreach ($fallback as $ip) {
                        if (
                            filter_var(
                                $ip,
                                FILTER_VALIDATE_IP,
                                FILTER_FLAG_IPV4
                            )
                        ) {
                            $resolved[] =
                                $ip;
                        }
                    }
                }
            }
        }

        $resolved =
            array_values(
                array_unique(
                    $resolved
                )
            );

        sort(
            $resolved
        );

        /*
         * Protection against an unexpectedly huge
         * DNS pool.
         */
        $resolved =
            array_slice(
                $resolved,
                0,
                8
            );

        $managed = [];

        foreach ($rows as $row) {
            if (
                (string) (
                    $row[
                        'comment'
                    ]
                    ?? ''
                ) !== $ipComment
            ) {
                continue;
            }

            $address =
                trim(
                    (string) (
                        $row[
                            'dst-address'
                        ]
                        ?? ''
                    )
                );

            if ($address === '') {
                continue;
            }

            $ip =
                explode(
                    '/',
                    $address,
                    2
                )[0];

            if (
                filter_var(
                    $ip,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4
                )
            ) {
                $managed[
                    $ip
                ] = $row;
            }
        }

        $ipAdded = [];
        $ipRemoved = [];

        foreach ($resolved as $ip) {
            if (
                isset(
                    $managed[$ip]
                )
            ) {
                $row =
                    $managed[$ip];

                $disabled =
                    in_array(
                        strtolower(
                            (string) (
                                $row[
                                    'disabled'
                                ]
                                ?? 'false'
                            )
                        ),
                        [
                            'true',
                            'yes',
                            '1',
                        ],
                        true
                    );

                if (
                    $disabled
                    && isset(
                        $row['.id']
                    )
                ) {
                    $api->query(
                        (new Query(
                            '/ip/hotspot/walled-garden/ip/set'
                        ))
                            ->equal(
                                '.id',
                                $row['.id']
                            )
                            ->equal(
                                'action',
                                'accept'
                            )
                            ->equal(
                                'disabled',
                                'false'
                            )
                    )->read();
                }

                continue;
            }

            $api->query(
                (new Query(
                    '/ip/hotspot/walled-garden/ip/add'
                ))
                    ->equal(
                        'dst-address',
                        $ip . '/32'
                    )
                    ->equal(
                        'action',
                        'accept'
                    )
                    ->equal(
                        'comment',
                        $ipComment
                    )
            )->read();

            $ipAdded[] =
                $ip;
        }

        /*
         * If DNS fails completely, keep existing
         * fallback rules rather than deleting them.
         */
        if ($resolved !== []) {
            foreach (
                $managed
                as $ip => $row
            ) {
                if (
                    in_array(
                        $ip,
                        $resolved,
                        true
                    )
                ) {
                    continue;
                }

                if (
                    !isset(
                        $row['.id']
                    )
                ) {
                    continue;
                }

                $api->query(
                    (new Query(
                        '/ip/hotspot/walled-garden/ip/remove'
                    ))
                        ->equal(
                            '.id',
                            $row['.id']
                        )
                )->read();

                $ipRemoved[] =
                    $ip;
            }
        }

        return [
            'host' =>
                $host,

            'host_added' =>
                $hostAdded,

            'resolved_ipv4' =>
                $resolved,

            'ip_added' =>
                $ipAdded,

            'ip_removed' =>
                $ipRemoved,
        ];
    }

    /*
     * Clear the RouterOS Hotspot user's permanent
     * MAC restriction and terminate old sessions.
     *
     * No database write is performed here.
     */
    /*
     * MAIN_HOTSPOT_VOUCHER_DEVICE_INFO_V1
     *
     * Read the current RouterOS Hotspot user and
     * active session without changing anything.
     */
    public function voucherDeviceInfo(
        HotspotVoucher $voucher
    ): array {
        $server =
            HotspotServer::withoutGlobalScopes()
                ->find(
                    $voucher
                        ->hotspot_server_id
                );

        if (!$server) {
            throw new \RuntimeException(
                'Hotspot server was not found.'
            );
        }

        $router =
            Router::withoutGlobalScopes()
                ->find(
                    $server->router_id
                );

        if (!$router) {
            throw new \RuntimeException(
                'Hotspot router was not found.'
            );
        }

        $api =
            $this->api(
                $router
            );

        $users =
            $api->query(
                (new Query(
                    '/ip/hotspot/user/print'
                ))
                    ->where(
                        'name',
                        $voucher->username
                    )
                    ->equal(
                        '.proplist',
                        '.id,name,mac-address,disabled'
                    )
            )->read();

        $activeRows =
            $api->query(
                (new Query(
                    '/ip/hotspot/active/print'
                ))
                    ->where(
                        'user',
                        $voucher->username
                    )
                    ->equal(
                        '.proplist',
                        '.id,user,address,mac-address,login-by,uptime,server'
                    )
            )->read();

        $user =
            $users[0]
            ?? [];

        $active =
            $activeRows[0]
            ?? [];

        $mac =
            trim(
                (string) (
                    $active['mac-address']
                    ?? $user['mac-address']
                    ?? $voucher->mac_address
                    ?? ''
                )
            );

        if (
            $mac === ''
            || $mac
                === '00:00:00:00:00:00'
        ) {
            $mac = null;
        }

        return [
            'online' =>
                $active !== [],

            'mac_address' =>
                $mac
                    ? strtoupper($mac)
                    : null,

            'ip_address' =>
                $active['address']
                ?? null,

            'login_by' =>
                $active['login-by']
                ?? null,

            'uptime' =>
                $active['uptime']
                ?? null,

            'hotspot_server' =>
                $active['server']
                ?? $server->mikrotik_name
                ?? $server->name,

            'router_name' =>
                $router->name,

            'router_user_found' =>
                $user !== [],
        ];
    }

    public function resetVoucherMac(
        HotspotVoucher $voucher
    ): bool {
        $server =
            HotspotServer::withoutGlobalScopes()
                ->find(
                    $voucher
                        ->hotspot_server_id
                );

        if (!$server) {
            throw new \RuntimeException(
                'Hotspot server was not found.'
            );
        }

        $router =
            Router::withoutGlobalScopes()
                ->find(
                    $server->router_id
                );

        if (!$router) {
            throw new \RuntimeException(
                'Hotspot router was not found.'
            );
        }

        $api =
            $this->api(
                $router
            );

        $users =
            $api->query(
                (new Query(
                    '/ip/hotspot/user/print'
                ))
                    ->where(
                        'name',
                        $voucher->username
                    )
                    ->equal(
                        '.proplist',
                        '.id,name,mac-address'
                    )
            )->read();

        foreach (
            $users
            as $user
        ) {
            $id =
                $user['.id']
                ?? null;

            if (!$id) {
                continue;
            }

            $api->query(
                (new Query(
                    '/ip/hotspot/user/set'
                ))
                    ->equal(
                        '.id',
                        $id
                    )
                    ->equal(
                        'mac-address',
                        '00:00:00:00:00:00'
                    )
            )->read();
        }

        /*
         * Release the old connected device so the
         * same voucher may authenticate elsewhere.
         */
        $this->disconnectUsername(
            $api,
            $voucher->username
        );

        return $users !== [];
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
