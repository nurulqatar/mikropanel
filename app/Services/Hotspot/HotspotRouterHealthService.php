<?php

namespace App\Services\Hotspot;

use App\Models\HotspotServer;
use App\Models\Router;
use App\Services\MikroTik\MikroTikService;
use Illuminate\Support\Facades\Cache;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class HotspotRouterHealthService
{
    /*
     * HOTSPOT_ROUTER_HEALTH_V2
     *
     * Read:
     * - Smart Import discovery
     * - Internet / VPN / API
     * - Bridge / Gateway
     * - DHCP / Pool / Leases
     * - Hotspot IP Bindings
     * - Hotspot / Profile
     * - DNS / NAT / Portal
     * - Multi-WAN byte counters
     * - Hotspot bridge counters
     * - Active Hotspot user counters
     *
     * Write:
     * - importExisting(): panel DB only
     * - repair(): known repairable RouterOS items only
     */

    public function __construct(
        protected MikroTikService $mikrotik
    ) {
    }

    public function snapshot(
        Router $router
    ): array {
        try {
            $router->loadMissing([
                'zone:id,reseller_id,name,code,service_type',
            ]);

            $discovery =
                $this->mikrotik
                    ->hotspotSetupDiscovery(
                        $router
                    );

            if (
                !(
                    $discovery['success']
                    ?? false
                )
            ) {
                throw new \RuntimeException(
                    $discovery['message']
                    ?? 'RouterOS discovery failed.'
                );
            }

            $api =
                $this->client(
                    $router
                );

            $topology =
                $this->selectTopology(
                    $router,
                    $discovery
                );

            $routes =
                $this->read(
                    $api,
                    '/ip/route/print',
                    implode(',', [
                        '.id',
                        'dst-address',
                        'gateway',
                        'immediate-gw',
                        'routing-table',
                        'distance',
                        'active',
                        'disabled',
                    ])
                );

            $addresses =
                $this->read(
                    $api,
                    '/ip/address/print',
                    implode(',', [
                        '.id',
                        'address',
                        'network',
                        'interface',
                        'disabled',
                        'dynamic',
                        'comment',
                    ])
                );

            $bridges =
                $this->read(
                    $api,
                    '/interface/bridge/print',
                    '.id,name,disabled,running'
                );

            $wireguard =
                $this->safeRead(
                    $api,
                    '/interface/wireguard/print',
                    '.id,name,running,disabled,comment'
                );

            $dnsRows =
                $this->read(
                    $api,
                    '/ip/dns/print',
                    implode(',', [
                        'servers',
                        'dynamic-servers',
                        'allow-remote-requests',
                    ])
                );

            $natRows =
                $this->read(
                    $api,
                    '/ip/firewall/nat/print',
                    implode(',', [
                        '.id',
                        'chain',
                        'action',
                        'src-address',
                        'out-interface',
                        'out-interface-list',
                        'disabled',
                        'comment',
                    ])
                );

            $files =
                $this->read(
                    $api,
                    '/file/print',
                    '.id,name,type,size'
                );

            $leases =
                $this->read(
                    $api,
                    '/ip/dhcp-server/lease/print',
                    implode(',', [
                        '.id',
                        'address',
                        'mac-address',
                        'status',
                        'server',
                        'dynamic',
                        'disabled',
                        'host-name',
                        'comment',
                    ])
                );

            $bindings =
                $this->read(
                    $api,
                    '/ip/hotspot/ip-binding/print',
                    implode(',', [
                        '.id',
                        'mac-address',
                        'address',
                        'server',
                        'type',
                        'disabled',
                        'comment',
                    ])
                );

            $active =
                $this->read(
                    $api,
                    '/ip/hotspot/active/print',
                    implode(',', [
                        '.id',
                        'user',
                        'address',
                        'mac-address',
                        'server',
                        'uptime',
                        'bytes-in',
                        'bytes-out',
                    ])
                );

            $wan =
                $this->detectWan(
                    $api,
                    $routes
                );

            $defaultRoute =
                $this->hasDefaultRoute(
                    $routes
                );

            $internetPing =
                $this->internetPing(
                    $api
                );

            $vpn =
                $this->vpnState(
                    $router,
                    $addresses,
                    $wireguard
                );

            $panelServer =
                HotspotServer::withoutGlobalScopes()
                    ->where(
                        'router_id',
                        $router->id
                    )
                    ->where(
                        'zone_id',
                        $router->zone_id
                    )
                    ->first();

            if (!$topology) {
                return [
                    'online' => true,

                    'checked_at' =>
                        now()->toISOString(),

                    'overall' =>
                        'warning',

                    'topology' =>
                        null,

                    'checks' => [
                        $this->check(
                            'internet',
                            'Internet',
                            $defaultRoute,
                            $internetPing === true
                                ? 'Internet reachable'
                                : (
                                    $defaultRoute
                                        ? 'Default route active'
                                        : 'No active default route'
                                ),
                            false
                        ),

                        $this->check(
                            'vpn',
                            'VPN',
                            $vpn['status'],
                            $vpn['detail'],
                            false
                        ),

                        $this->check(
                            'api',
                            'MikroTik API',
                            true,
                            'Connected',
                            false
                        ),

                        $this->check(
                            'hotspot',
                            'Hotspot',
                            false,
                            'No complete Hotspot topology detected',
                            false
                        ),
                    ],

                    'issues' => [
                        [
                            'key' =>
                                'hotspot_incomplete',

                            'severity' =>
                                'warning',

                            'title' =>
                                'Hotspot setup incomplete',

                            'message' =>
                                'No complete RouterOS Hotspot topology was detected.',

                            'repairable' =>
                                false,
                        ],
                    ],

                    'repairable_count' =>
                        0,

                    'wan' =>
                        $wan,

                    'vpn' =>
                        $vpn,

                    'stats' => [
                        'active_users' =>
                            count($active),

                        'dhcp_leases' =>
                            count($leases),

                        'bound_leases' =>
                            0,

                        'pool_total' =>
                            0,

                        'pool_used' =>
                            0,

                        'pool_usage_percent' =>
                            0,

                        'ip_bindings' =>
                            count($bindings),

                        'binding_conflicts' =>
                            0,
                    ],

                    'binding_conflicts' =>
                        [],

                    'panel_imported' =>
                        false,
                ];
            }

            $bridgeName =
                trim(
                    (string) (
                        $topology[
                            'interface'
                        ] ?? ''
                    )
                );

            $gatewayCidr =
                trim(
                    (string) (
                        $topology[
                            'gateway_cidr'
                        ] ?? ''
                    )
                );

            $gatewayIp =
                trim(
                    (string) (
                        $topology[
                            'gateway_ip'
                        ] ?? ''
                    )
                );

            $networkCidr =
                trim(
                    (string) (
                        $topology[
                            'network_cidr'
                        ] ?? ''
                    )
                );

            if (
                $networkCidr === ''
                && $gatewayCidr !== ''
            ) {
                $networkCidr =
                    $this->cidrInfo(
                        $gatewayCidr
                    )['network_cidr'];
            }

            $poolName =
                trim(
                    (string) (
                        $topology[
                            'pool_name'
                        ] ?? ''
                    )
                );

            $dhcpName =
                trim(
                    (string) (
                        $topology[
                            'dhcp_server'
                        ] ?? ''
                    )
                );

            $hotspotName =
                trim(
                    (string) (
                        $topology[
                            'hotspot_server'
                        ] ?? ''
                    )
                );

            $profileName =
                trim(
                    (string) (
                        $topology[
                            'hotspot_profile'
                        ] ?? ''
                    )
                );

            $dnsName =
                trim(
                    (string) (
                        $topology[
                            'dns_name'
                        ] ?? ''
                    )
                );

            $bridge =
                collect($bridges)
                    ->first(
                        fn ($row) =>
                            ($row['name'] ?? null)
                            === $bridgeName
                    );

            $gatewayOk =
                collect($addresses)
                    ->contains(
                        fn ($row) =>
                            ($row['interface'] ?? null)
                                === $bridgeName
                            && ($row['address'] ?? null)
                                === $gatewayCidr
                            && !$this->truthy(
                                $row['disabled']
                                ?? false
                            )
                    );

            $pool =
                collect(
                    $discovery[
                        'ip_pools'
                    ] ?? []
                )->first(
                    fn ($row) =>
                        ($row['name'] ?? null)
                        === $poolName
                );

            $dhcp =
                collect(
                    $discovery[
                        'dhcp_servers'
                    ] ?? []
                )->first(
                    fn ($row) =>
                        ($row['name'] ?? null)
                        === $dhcpName
                );

            $dhcpNetwork =
                collect(
                    $discovery[
                        'dhcp_networks'
                    ] ?? []
                )->first(
                    fn ($row) =>
                        (
                            $networkCidr !== ''
                            && (
                                $row['address']
                                ?? null
                            ) === $networkCidr
                        )
                        || (
                            $gatewayIp !== ''
                            && trim(
                                (string) (
                                    $row[
                                        'gateway'
                                    ] ?? ''
                                )
                            ) === $gatewayIp
                        )
                );

            $hotspot =
                collect(
                    $discovery[
                        'hotspot_servers'
                    ] ?? []
                )->first(
                    fn ($row) =>
                        ($row['name'] ?? null)
                        === $hotspotName
                );

            $profile =
                collect(
                    $discovery[
                        'hotspot_profiles'
                    ] ?? []
                )->first(
                    fn ($row) =>
                        ($row['name'] ?? null)
                        === $profileName
                );

            $poolRanges =
                trim(
                    (string) (
                        $pool['ranges']
                        ?? $topology[
                            'pool_ranges'
                        ]
                        ?? ''
                    )
                );

            $relevantLeases =
                array_values(
                    array_filter(
                        $leases,
                        fn ($row) =>
                            $dhcpName === ''
                            || (
                                $row['server']
                                ?? null
                            ) === $dhcpName
                    )
                );

            $boundLeases =
                array_values(
                    array_filter(
                        $relevantLeases,
                        fn ($row) =>
                            strtolower(
                                (string) (
                                    $row[
                                        'status'
                                    ] ?? ''
                                )
                            ) === 'bound'
                            && !$this->truthy(
                                $row[
                                    'disabled'
                                ] ?? false
                            )
                    )
                );

            $relevantBindings =
                array_values(
                    array_filter(
                        $bindings,
                        function ($row) use (
                            $hotspotName
                        ) {
                            if (
                                $this->truthy(
                                    $row[
                                        'disabled'
                                    ] ?? false
                                )
                            ) {
                                return false;
                            }

                            $server =
                                trim(
                                    (string) (
                                        $row[
                                            'server'
                                        ] ?? ''
                                    )
                                );

                            return $server === ''
                                || $server === 'all'
                                || (
                                    $hotspotName !== ''
                                    && $server
                                        === $hotspotName
                                );
                        }
                    )
                );

            $bindingConflicts =
                $this->bindingConflicts(
                    $relevantBindings,
                    $boundLeases
                );

            $poolTotal =
                $this->poolCapacity(
                    $poolRanges
                );

            $poolUsed = 0;

            foreach ($boundLeases as $lease) {
                if (
                    $this->ipInPool(
                        (string) (
                            $lease[
                                'address'
                            ] ?? ''
                        ),
                        $poolRanges
                    )
                ) {
                    $poolUsed++;
                }
            }

            $poolPercent =
                $poolTotal > 0
                    ? round(
                        (
                            $poolUsed
                            / $poolTotal
                        ) * 100,
                        1
                    )
                    : 0;

            $dns =
                $dnsRows[0] ?? [];

            $natOk =
                $networkCidr !== ''
                && $this->hasNat(
                    $natRows,
                    $wan,
                    $networkCidr
                );

            $portal =
                $this->portalState(
                    $files,
                    $profile
                );

            $loginBy =
                array_values(
                    array_filter(
                        array_map(
                            'trim',
                            explode(
                                ',',
                                strtolower(
                                    (string) (
                                        $profile[
                                            'login_by'
                                        ]
                                        ?? $profile[
                                            'login-by'
                                        ]
                                        ?? ''
                                    )
                                )
                            )
                        )
                    )
                );

            $loginOk =
                in_array(
                    'cookie',
                    $loginBy,
                    true
                )
                && in_array(
                    'mac-cookie',
                    $loginBy,
                    true
                )
                && (
                    in_array(
                        'http-chap',
                        $loginBy,
                        true
                    )
                    || in_array(
                        'http-pap',
                        $loginBy,
                        true
                    )
                );

            $panelImported =
                $panelServer
                && $panelServer
                    ->mikrotik_name
                    === $hotspotName
                && $panelServer
                    ->interface
                    === $bridgeName;

            $checks = [
                $this->check(
                    'internet',
                    'Internet',
                    $defaultRoute,
                    $internetPing === true
                        ? 'Default route + ping OK'
                        : (
                            $internetPing === false
                                ? 'Default route active; ping no reply'
                                : 'Default route active'
                        ),
                    false
                ),

                $this->check(
                    'vpn',
                    'VPN',
                    $vpn['status'],
                    $vpn['detail'],
                    false
                ),

                $this->check(
                    'api',
                    'MikroTik API',
                    true,
                    'Connected',
                    false
                ),

                $this->check(
                    'bridge',
                    'Hotspot Bridge',
                    $bridge !== null
                    && !$this->truthy(
                        $bridge[
                            'disabled'
                        ] ?? false
                    ),
                    $bridgeName ?: 'Not detected',
                    false
                ),

                $this->check(
                    'gateway',
                    'Gateway IP',
                    $gatewayOk,
                    $gatewayCidr ?: 'Not detected',
                    false
                ),

                $this->check(
                    'dhcp',
                    'DHCP Server',
                    $dhcp !== null
                    && !(
                        $dhcp[
                            'disabled'
                        ] ?? true
                    )
                    && (
                        $dhcp[
                            'interface'
                        ] ?? null
                    ) === $bridgeName
                    && (
                        $dhcp[
                            'address_pool'
                        ] ?? null
                    ) === $poolName,
                    $dhcpName ?: 'Not detected',
                    $dhcp !== null
                ),

                $this->check(
                    'dhcp_network',
                    'DHCP Network',
                    $dhcpNetwork !== null
                    && (
                        $gatewayIp === ''
                        || trim(
                            (string) (
                                $dhcpNetwork[
                                    'gateway'
                                ] ?? ''
                            )
                        ) === $gatewayIp
                    ),
                    $networkCidr ?: 'Not detected',
                    true
                ),

                $this->check(
                    'pool',
                    'DHCP Pool',
                    $pool !== null,
                    $poolName !== ''
                        ? "{$poolName} · {$poolUsed}/{$poolTotal}"
                        : 'Not detected',
                    false
                ),

                $this->check(
                    'ip_binding',
                    'DHCP / IP Bind',
                    $bindingConflicts === [],
                    count(
                        $relevantBindings
                    )
                    . ' bindings · '
                    . count(
                        $boundLeases
                    )
                    . ' bound leases',
                    false
                ),

                $this->check(
                    'hotspot',
                    'Hotspot Server',
                    $hotspot !== null
                    && !(
                        $hotspot[
                            'disabled'
                        ] ?? true
                    ),
                    $hotspotName ?: 'Not detected',
                    $hotspot !== null
                ),

                $this->check(
                    'profile',
                    'Hotspot Profile',
                    $profile !== null,
                    $profileName ?: 'Not detected',
                    false
                ),

                $this->check(
                    'login',
                    'MAC-Cookie Login',
                    $loginOk,
                    $loginBy !== []
                        ? implode(
                            ', ',
                            $loginBy
                        )
                        : 'Login methods unavailable',
                    true
                ),

                $this->check(
                    'dns',
                    'DNS',
                    $this->truthy(
                        $dns[
                            'allow-remote-requests'
                        ] ?? false
                    ),
                    $dnsName ?: 'Hotspot DNS',
                    true
                ),

                $this->check(
                    'nat',
                    'NAT',
                    $natOk,
                    $natOk
                        ? 'Masquerade detected'
                        : 'Hotspot Internet NAT missing',
                    true
                ),

                $this->check(
                    'portal',
                    'Portal',
                    $portal['ok'],
                    $portal['detail'],
                    true
                ),

                $this->check(
                    'panel',
                    'Panel Import',
                    $panelImported,
                    $panelImported
                        ? 'Managed by MikroPanel'
                        : 'Existing Hotspot can be imported',
                    false
                ),
            ];

            $issues =
                $this->makeIssues(
                    $checks,
                    $poolPercent,
                    $bindingConflicts,
                    $internetPing
                );

            return [
                'online' =>
                    true,

                'checked_at' =>
                    now()->toISOString(),

                'overall' =>
                    $this->overall(
                        $issues
                    ),

                'topology' =>
                    $topology,

                'checks' =>
                    $checks,

                'issues' =>
                    $issues,

                'repairable_count' =>
                    count(
                        array_filter(
                            $issues,
                            fn ($issue) =>
                                $issue[
                                    'repairable'
                                ] ?? false
                        )
                    ),

                'wan' =>
                    $wan,

                'vpn' =>
                    $vpn,

                'panel_imported' =>
                    (bool)
                    $panelImported,

                'stats' => [
                    'active_users' =>
                        count($active),

                    'dhcp_leases' =>
                        count(
                            $relevantLeases
                        ),

                    'bound_leases' =>
                        count(
                            $boundLeases
                        ),

                    'pool_total' =>
                        $poolTotal,

                    'pool_used' =>
                        $poolUsed,

                    'pool_usage_percent' =>
                        $poolPercent,

                    'ip_bindings' =>
                        count(
                            $relevantBindings
                        ),

                    'binding_conflicts' =>
                        count(
                            $bindingConflicts
                        ),
                ],

                'binding_conflicts' =>
                    $bindingConflicts,
            ];

        } catch (Throwable $exception) {
            return [
                'online' =>
                    false,

                'checked_at' =>
                    now()->toISOString(),

                'overall' =>
                    'critical',

                'topology' =>
                    null,

                'checks' => [
                    $this->check(
                        'api',
                        'MikroTik API',
                        false,
                        $exception
                            ->getMessage(),
                        false
                    ),
                ],

                'issues' => [
                    [
                        'key' =>
                            'router_offline',

                        'severity' =>
                            'critical',

                        'title' =>
                            'Router Offline / API Unreachable',

                        'message' =>
                            $exception
                                ->getMessage(),

                        'repairable' =>
                            false,
                    ],
                ],

                'repairable_count' =>
                    0,

                'wan' => [
                    'interfaces' =>
                        [],

                    'list_name' =>
                        null,
                ],

                'vpn' => [
                    'status' =>
                        false,

                    'interface' =>
                        null,

                    'detail' =>
                        'Router unreachable',
                ],

                'panel_imported' =>
                    false,

                'stats' => [
                    'active_users' => 0,
                    'dhcp_leases' => 0,
                    'bound_leases' => 0,
                    'pool_total' => 0,
                    'pool_used' => 0,
                    'pool_usage_percent' => 0,
                    'ip_bindings' => 0,
                    'binding_conflicts' => 0,
                ],

                'binding_conflicts' =>
                    [],
            ];
        }
    }

    public function live(
        Router $router
    ): array {
        try {
            $api =
                $this->client(
                    $router
                );

            /*
             * Heavy discovery is cached.
             * 0.5 second polling performs only:
             *
             * 1. /interface/print
             * 2. /ip/hotspot/active/print
             */
            $map =
                Cache::remember(
                    'hotspot-live-map-v2:'
                    . $router->id,
                    30,
                    function () use (
                        $router
                    ) {
                        $discovery =
                            $this->mikrotik
                                ->hotspotSetupDiscovery(
                                    $router
                                );

                        if (
                            !(
                                $discovery[
                                    'success'
                                ] ?? false
                            )
                        ) {
                            throw new \RuntimeException(
                                'Hotspot discovery unavailable.'
                            );
                        }

                        $topology =
                            $this->selectTopology(
                                $router,
                                $discovery
                            );

                        $mapApi =
                            $this->client(
                                $router
                            );

                        $routes =
                            $this->read(
                                $mapApi,
                                '/ip/route/print',
                                implode(',', [
                                    '.id',
                                    'dst-address',
                                    'gateway',
                                    'immediate-gw',
                                    'routing-table',
                                    'active',
                                    'disabled',
                                ])
                            );

                        return [
                            'bridge' =>
                                $topology[
                                    'interface'
                                ] ?? null,

                            'hotspot_server' =>
                                $topology[
                                    'hotspot_server'
                                ] ?? null,

                            'wan' =>
                                $this->detectWan(
                                    $mapApi,
                                    $routes
                                ),
                        ];
                    }
                );

            $interfaceRows =
                $this->read(
                    $api,
                    '/interface/print',
                    implode(',', [
                        '.id',
                        'name',
                        'type',
                        'running',
                        'disabled',
                        'rx-byte',
                        'tx-byte',
                        'rx-packet',
                        'tx-packet',
                        'rx-drop',
                        'tx-drop',
                    ])
                );

            $interfaces = [];

            foreach (
                $interfaceRows
                as $row
            ) {
                $name =
                    trim(
                        (string) (
                            $row['name']
                            ?? ''
                        )
                    );

                if ($name === '') {
                    continue;
                }

                $interfaces[$name] = [
                    'name' =>
                        $name,

                    'type' =>
                        $row['type']
                        ?? null,

                    'running' =>
                        $this->truthy(
                            $row[
                                'running'
                            ] ?? false
                        ),

                    'disabled' =>
                        $this->truthy(
                            $row[
                                'disabled'
                            ] ?? false
                        ),

                    'rx_byte' =>
                        $this->integer(
                            $row[
                                'rx-byte'
                            ] ?? 0
                        ),

                    'tx_byte' =>
                        $this->integer(
                            $row[
                                'tx-byte'
                            ] ?? 0
                        ),

                    'rx_packet' =>
                        $this->integer(
                            $row[
                                'rx-packet'
                            ] ?? 0
                        ),

                    'tx_packet' =>
                        $this->integer(
                            $row[
                                'tx-packet'
                            ] ?? 0
                        ),

                    'rx_drop' =>
                        $this->integer(
                            $row[
                                'rx-drop'
                            ] ?? 0
                        ),

                    'tx_drop' =>
                        $this->integer(
                            $row[
                                'tx-drop'
                            ] ?? 0
                        ),
                ];
            }

            $wanRows = [];

            foreach (
                $map['wan'][
                    'interfaces'
                ] ?? []
                as $name
            ) {
                if (
                    isset(
                        $interfaces[$name]
                    )
                ) {
                    $wanRows[] =
                        $interfaces[$name];
                }
            }

            $bridge =
                (
                    $map['bridge']
                    && isset(
                        $interfaces[
                            $map['bridge']
                        ]
                    )
                )
                    ? $interfaces[
                        $map['bridge']
                    ]
                    : null;

            $activeRows =
                $this->read(
                    $api,
                    '/ip/hotspot/active/print',
                    implode(',', [
                        '.id',
                        'user',
                        'address',
                        'mac-address',
                        'server',
                        'uptime',
                        'bytes-in',
                        'bytes-out',
                    ])
                );

            $clients = [];

            foreach (
                $activeRows
                as $row
            ) {
                $server =
                    trim(
                        (string) (
                            $row[
                                'server'
                            ] ?? ''
                        )
                    );

                if (
                    ($map[
                        'hotspot_server'
                    ] ?? null)
                    && $server !== ''
                    && $server
                        !== $map[
                            'hotspot_server'
                        ]
                ) {
                    continue;
                }

                $clients[] = [
                    'key' =>
                        implode(
                            '|',
                            [
                                $row['user']
                                    ?? '',

                                $row[
                                    'mac-address'
                                ] ?? '',

                                $row['address']
                                    ?? '',
                            ]
                        ),

                    'user' =>
                        $row['user']
                        ?? '-',

                    'address' =>
                        $row['address']
                        ?? '-',

                    'mac_address' =>
                        $row[
                            'mac-address'
                        ] ?? '-',

                    'uptime' =>
                        $row['uptime']
                        ?? '-',

                    /*
                     * Hotspot counters:
                     * bytes-in  = client upload
                     * bytes-out = client download
                     */
                    'bytes_in' =>
                        $this->integer(
                            $row[
                                'bytes-in'
                            ] ?? 0
                        ),

                    'bytes_out' =>
                        $this->integer(
                            $row[
                                'bytes-out'
                            ] ?? 0
                        ),
                ];
            }

            return [
                'online' =>
                    true,

                'timestamp_ms' =>
                    (int)
                    round(
                        microtime(true)
                        * 1000
                    ),

                'bridge_name' =>
                    $map['bridge']
                    ?? null,

                'wan_interfaces' =>
                    $wanRows,

                'bridge' =>
                    $bridge,

                'clients' =>
                    $clients,
            ];

        } catch (Throwable $exception) {
            return [
                'online' =>
                    false,

                'timestamp_ms' =>
                    (int)
                    round(
                        microtime(true)
                        * 1000
                    ),

                'error' =>
                    $exception
                        ->getMessage(),

                'bridge_name' =>
                    null,

                'wan_interfaces' =>
                    [],

                'bridge' =>
                    null,

                'clients' =>
                    [],
            ];
        }
    }

    public function importCandidate(
        Router $router
    ): array {
        $discovery =
            $this->mikrotik
                ->hotspotSetupDiscovery(
                    $router
                );

        if (
            !(
                $discovery['success']
                ?? false
            )
        ) {
            return [
                'found' =>
                    false,

                'can_import' =>
                    false,

                'already_imported' =>
                    false,

                'reason' =>
                    $discovery['message']
                    ?? 'Router unavailable.',

                'candidate' =>
                    null,
            ];
        }

        $complete =
            array_values(
                array_filter(
                    $discovery[
                        'hotspot_topologies'
                    ] ?? [],
                    fn ($row) =>
                        $this->complete(
                            $row
                        )
                )
            );

        $panel =
            HotspotServer::withoutGlobalScopes()
                ->where(
                    'router_id',
                    $router->id
                )
                ->where(
                    'zone_id',
                    $router->zone_id
                )
                ->first();

        if ($panel) {
            foreach (
                $complete
                as $row
            ) {
                if (
                    ($row[
                        'hotspot_server'
                    ] ?? null)
                    === $panel
                        ->mikrotik_name
                ) {
                    return [
                        'found' =>
                            true,

                        'can_import' =>
                            false,

                        'already_imported' =>
                            true,

                        'reason' =>
                            'Existing Hotspot is already managed by MikroPanel.',

                        'candidate' =>
                            $row,
                    ];
                }
            }
        }

        if (
            count($complete)
            === 1
        ) {
            return [
                'found' =>
                    true,

                'can_import' =>
                    true,

                'already_imported' =>
                    false,

                'reason' =>
                    'Existing RouterOS Hotspot found.',

                'candidate' =>
                    $complete[0],
            ];
        }

        if (
            count($complete)
            > 1
        ) {
            return [
                'found' =>
                    true,

                'can_import' =>
                    false,

                'already_imported' =>
                    false,

                'reason' =>
                    'Multiple Hotspot servers detected. Use Advanced Setup to select the correct one.',

                'candidate' =>
                    null,
            ];
        }

        return [
            'found' =>
                false,

            'can_import' =>
                false,

            'already_imported' =>
                false,

            'reason' =>
                'No complete existing Hotspot detected.',

            'candidate' =>
                null,
        ];
    }

    public function importExisting(
        Router $router
    ): HotspotServer {
        $router->loadMissing([
            'zone:id,reseller_id,name,code,service_type',
        ]);

        $state =
            $this->importCandidate(
                $router
            );

        if (
            $state[
                'already_imported'
            ] ?? false
        ) {
            $existing =
                HotspotServer::withoutGlobalScopes()
                    ->where(
                        'router_id',
                        $router->id
                    )
                    ->where(
                        'zone_id',
                        $router->zone_id
                    )
                    ->first();

            if ($existing) {
                return $existing;
            }
        }

        if (
            !(
                $state[
                    'can_import'
                ] ?? false
            )
            || !(
                $state[
                    'candidate'
                ] ?? null
            )
        ) {
            throw new \RuntimeException(
                $state['reason']
                ?? 'Hotspot cannot be imported.'
            );
        }

        /*
         * RouterOS write = NONE.
         * Only panel registration happens here.
         */
        $row =
            $state[
                'candidate'
            ];

        $server =
            HotspotServer::withoutGlobalScopes()
                ->where(
                    'router_id',
                    $router->id
                )
                ->where(
                    'zone_id',
                    $router->zone_id
                )
                ->where(
                    'mikrotik_name',
                    $row[
                        'hotspot_server'
                    ]
                )
                ->first();

        $server ??=
            new HotspotServer();

        $server->fill([
            'reseller_id' =>
                $router
                    ->zone
                    ->reseller_id,

            'zone_id' =>
                $router->zone_id,

            'router_id' =>
                $router->id,

            'name' =>
                $row[
                    'hotspot_server'
                ],

            'mikrotik_name' =>
                $row[
                    'hotspot_server'
                ],

            'interface' =>
                $row[
                    'interface'
                ],

            'address_pool' =>
                $row[
                    'pool_name'
                ],

            'hotspot_profile' =>
                $row[
                    'hotspot_profile'
                ],

            'dns_name' =>
                $row[
                    'dns_name'
                ],

            'enabled' =>
                true,

            'connected' =>
                true,

            'last_synced_at' =>
                now(),

            'last_error' =>
                null,
        ]);

        $server->save();

        Cache::forget(
            'hotspot-live-map-v2:'
            . $router->id
        );

        return $server;
    }

    /*
     * HOTSPOT_ROUTER_SAFE_REPAIR_V3
     *
     * Exact managed topology only.
     * Captive portal remains read-only.
     * Every write made here registers a rollback.
     */
    public function repair(
        Router $router
    ): array {
        $router->loadMissing([
            'zone:id,reseller_id,name,code,service_type',
        ]);

        $before =
            $this->snapshot(
                $router
            );

        if (
            !(
                $before[
                    'online'
                ] ?? false
            )
        ) {
            throw new \RuntimeException(
                'Router is offline.'
            );
        }

        $topology =
            $before[
                'topology'
            ] ?? null;

        if (
            !$topology
            || !$this->complete(
                $topology
            )
        ) {
            throw new \RuntimeException(
                'Complete Hotspot topology is required before automatic repair.'
            );
        }

        $bridge =
            $topology[
                'interface'
            ];

        $gatewayCidr =
            $topology[
                'gateway_cidr'
            ];

        $gateway =
            $this->cidrInfo(
                $gatewayCidr
            );

        $pool =
            $topology[
                'pool_name'
            ];

        $dhcpName =
            $topology[
                'dhcp_server'
            ];

        $hotspotName =
            $topology[
                'hotspot_server'
            ];

        $profileName =
            $topology[
                'hotspot_profile'
            ];

        $dnsName =
            $topology[
                'dns_name'
            ];

        /*
         * Exact MikroPanel ownership guard.
         */
        $managed =
            \App\Models\HotspotServer::withoutGlobalScopes()
                ->where(
                    'router_id',
                    $router->id
                )
                ->where(
                    'zone_id',
                    $router->zone_id
                )
                ->where(
                    'mikrotik_name',
                    $hotspotName
                )
                ->first();

        if (!$managed) {
            throw new \RuntimeException(
                'Automatic repair is blocked because this Hotspot topology is not managed by MikroPanel.'
            );
        }

        $managedChecks = [
            'interface' =>
                $bridge,

            'address_pool' =>
                $pool,

            'hotspot_profile' =>
                $profileName,

            'dns_name' =>
                $dnsName,
        ];

        foreach (
            $managedChecks
            as $field => $expected
        ) {
            $stored =
                trim(
                    (string) (
                        $managed
                            ->getAttribute(
                                $field
                            )
                        ?? ''
                    )
                );

            if (
                $stored !== ''
                && $stored
                    !== trim(
                        (string)
                        $expected
                    )
            ) {
                throw new \RuntimeException(
                    'Automatic repair blocked: managed '
                    . $field
                    . ' does not match the discovered Hotspot topology.'
                );
            }
        }

        $audit =
            \App\Models\HotspotRouterRepairAudit::query()
                ->create([
                    'reseller_id' =>
                        $router
                            ->zone
                            ?->reseller_id,

                    'zone_id' =>
                        $router
                            ->zone_id,

                    'router_id' =>
                        $router
                            ->id,

                    'requested_by' =>
                        auth()->id(),

                    'status' =>
                        'processing',

                    'before_overall' =>
                        $before[
                            'overall'
                        ] ?? null,

                    'actions' =>
                        [],

                    'skipped' =>
                        [],

                    'rollback_actions' =>
                        [],

                    'started_at' =>
                        now(),
                ]);

        $actions = [];
        $skipped = [];
        $rollbacks = [];
        $rollbackActions = [];

        try {
            $api =
                $this->client(
                    $router
                );

            /*
             * Preflight all managed objects before
             * the first RouterOS write.
             */
            $dhcpRows =
                $this->read(
                    $api,
                    '/ip/dhcp-server/print',
                    '.id,name,interface,address-pool,disabled'
                );

            $dhcp =
                $this->find(
                    $dhcpRows,
                    'name',
                    $dhcpName
                );

            if (!$dhcp) {
                $skipped[] =
                    'DHCP server missing — generic repair will not recreate an unknown DHCP server.';

            } elseif (
                (string) (
                    $dhcp[
                        'interface'
                    ] ?? ''
                ) !== (string)
                    $bridge
                || (string) (
                    $dhcp[
                        'address-pool'
                    ] ?? ''
                ) !== (string)
                    $pool
            ) {
                throw new \RuntimeException(
                    'Automatic repair blocked: DHCP server interface/pool does not match managed topology.'
                );
            }

            $networks =
                $this->read(
                    $api,
                    '/ip/dhcp-server/network/print',
                    '.id,address,gateway,dns-server,comment'
                );

            $network =
                $this->find(
                    $networks,
                    'address',
                    $gateway[
                        'network_cidr'
                    ]
                );

            $hotspots =
                $this->read(
                    $api,
                    '/ip/hotspot/print',
                    '.id,name,interface,address-pool,profile,disabled'
                );

            $hotspot =
                $this->find(
                    $hotspots,
                    'name',
                    $hotspotName
                );

            if (!$hotspot) {
                throw new \RuntimeException(
                    'Hotspot server is missing. Generic repair will not recreate an unknown Hotspot server.'
                );
            }

            if (
                (string) (
                    $hotspot[
                        'interface'
                    ] ?? ''
                ) !== (string)
                    $bridge
                || (string) (
                    $hotspot[
                        'address-pool'
                    ] ?? ''
                ) !== (string)
                    $pool
                || (string) (
                    $hotspot[
                        'profile'
                    ] ?? ''
                ) !== (string)
                    $profileName
            ) {
                throw new \RuntimeException(
                    'Automatic repair blocked: Hotspot server does not match managed bridge/pool/profile.'
                );
            }

            $profiles =
                $this->read(
                    $api,
                    '/ip/hotspot/profile/print',
                    '.id,name,hotspot-address,dns-name,html-directory,login-by'
                );

            $profile =
                $this->find(
                    $profiles,
                    'name',
                    $profileName
                );

            if (!$profile) {
                throw new \RuntimeException(
                    'Hotspot profile is missing. Generic repair will not recreate an unknown profile.'
                );
            }

            $profileGateway =
                trim(
                    (string) (
                        $profile[
                            'hotspot-address'
                        ] ?? ''
                    )
                );

            if (
                $profileGateway !== ''
                && $profileGateway
                    !== $gateway['ip']
            ) {
                throw new \RuntimeException(
                    'Automatic repair blocked: Hotspot profile gateway does not match managed topology.'
                );
            }

            $profileDns =
                trim(
                    (string) (
                        $profile[
                            'dns-name'
                        ] ?? ''
                    )
                );

            if (
                $profileDns !== ''
                && $dnsName !== ''
                && $profileDns
                    !== $dnsName
            ) {
                throw new \RuntimeException(
                    'Automatic repair blocked: Hotspot profile DNS name does not match managed topology.'
                );
            }

            $dns =
                $this->read(
                    $api,
                    '/ip/dns/print',
                    'allow-remote-requests'
                );

            $routes =
                $this->read(
                    $api,
                    '/ip/route/print',
                    '.id,dst-address,gateway,immediate-gw,routing-table,active,disabled'
                );

            $wan =
                $this->detectWan(
                    $api,
                    $routes
                );

            $nat =
                $this->read(
                    $api,
                    '/ip/firewall/nat/print',
                    '.id,chain,action,src-address,out-interface,out-interface-list,disabled,comment'
                );

            $files =
                $this->read(
                    $api,
                    '/file/print',
                    '.id,name,type,size'
                );

            $portal =
                $this->portalState(
                    $files,
                    $profile
                );

            if (!$portal['ok']) {
                $skipped[] =
                    'Portal problem detected — generic repair does not modify portal files or html-directory.';
            }

            /*
             * DHCP enable only.
             */
            if (
                $dhcp
                && $this->truthy(
                    $dhcp[
                        'disabled'
                    ] ?? false
                )
            ) {
                $this->write(
                    $api,
                    (new Query(
                        '/ip/dhcp-server/set'
                    ))
                        ->equal(
                            '.id',
                            $dhcp[
                                '.id'
                            ]
                        )
                        ->equal(
                            'disabled',
                            'false'
                        )
                );

                $actions[] =
                    'DHCP server enabled';

                $rollbacks[] = [
                    'label' =>
                        'DHCP server disabled again',

                    'run' =>
                        function () use (
                            $api,
                            $dhcp
                        ): void {
                            $this->write(
                                $api,
                                (new Query(
                                    '/ip/dhcp-server/set'
                                ))
                                    ->equal(
                                        '.id',
                                        $dhcp[
                                            '.id'
                                        ]
                                    )
                                    ->equal(
                                        'disabled',
                                        'true'
                                    )
                            );
                        },
                ];
            }

            /*
             * Missing exact DHCP network.
             */
            if (!$network) {
                $comment =
                    'MIKROPANEL:HEALTH:DHCP:ROUTER-'
                    . $router->id;

                $this->write(
                    $api,
                    (new Query(
                        '/ip/dhcp-server/network/add'
                    ))
                        ->equal(
                            'address',
                            $gateway[
                                'network_cidr'
                            ]
                        )
                        ->equal(
                            'gateway',
                            $gateway['ip']
                        )
                        ->equal(
                            'dns-server',
                            $gateway['ip']
                        )
                        ->equal(
                            'comment',
                            $comment
                        )
                );

                $rows =
                    $this->read(
                        $api,
                        '/ip/dhcp-server/network/print',
                        '.id,address,comment'
                    );

                $created =
                    $this->find(
                        $rows,
                        'comment',
                        $comment
                    );

                if (
                    !$created
                    || empty(
                        $created[
                            '.id'
                        ]
                    )
                ) {
                    throw new \RuntimeException(
                        'DHCP network was created but cannot be identified for rollback.'
                    );
                }

                $id =
                    $created[
                        '.id'
                    ];

                $actions[] =
                    'DHCP network restored';

                $rollbacks[] = [
                    'label' =>
                        'Repair-created DHCP network removed',

                    'run' =>
                        function () use (
                            $api,
                            $id
                        ): void {
                            $this->write(
                                $api,
                                (new Query(
                                    '/ip/dhcp-server/network/remove'
                                ))
                                    ->equal(
                                        '.id',
                                        $id
                                    )
                            );
                        },
                ];
            }

            /*
             * Hotspot enable only.
             */
            if (
                $this->truthy(
                    $hotspot[
                        'disabled'
                    ] ?? false
                )
            ) {
                $this->write(
                    $api,
                    (new Query(
                        '/ip/hotspot/set'
                    ))
                        ->equal(
                            '.id',
                            $hotspot[
                                '.id'
                            ]
                        )
                        ->equal(
                            'disabled',
                            'false'
                        )
                );

                $actions[] =
                    'Hotspot server enabled';

                $rollbacks[] = [
                    'label' =>
                        'Hotspot server disabled again',

                    'run' =>
                        function () use (
                            $api,
                            $hotspot
                        ): void {
                            $this->write(
                                $api,
                                (new Query(
                                    '/ip/hotspot/set'
                                ))
                                    ->equal(
                                        '.id',
                                        $hotspot[
                                            '.id'
                                        ]
                                    )
                                    ->equal(
                                        'disabled',
                                        'true'
                                    )
                            );
                        },
                ];
            }

            /*
             * Required login methods while preserving
             * the complete original value for rollback.
             */
            $originalLogin =
                trim(
                    (string) (
                        $profile[
                            'login-by'
                        ] ?? ''
                    )
                );

            $login =
                array_values(
                    array_unique(
                        array_filter(
                            array_map(
                                'trim',
                                explode(
                                    ',',
                                    strtolower(
                                        $originalLogin
                                    )
                                )
                            )
                        )
                    )
                );

            $loginChanged =
                false;

            foreach (
                [
                    'cookie',
                    'mac-cookie',
                ]
                as $required
            ) {
                if (
                    !in_array(
                        $required,
                        $login,
                        true
                    )
                ) {
                    $login[] =
                        $required;

                    $loginChanged =
                        true;
                }
            }

            if (
                !in_array(
                    'http-chap',
                    $login,
                    true
                )
                && !in_array(
                    'http-pap',
                    $login,
                    true
                )
            ) {
                $login[] =
                    'http-chap';

                $loginChanged =
                    true;
            }

            if ($loginChanged) {
                $this->write(
                    $api,
                    (new Query(
                        '/ip/hotspot/profile/set'
                    ))
                        ->equal(
                            '.id',
                            $profile[
                                '.id'
                            ]
                        )
                        ->equal(
                            'login-by',
                            implode(
                                ',',
                                $login
                            )
                        )
                );

                $actions[] =
                    'Cookie / MAC-cookie login repaired';

                $rollbacks[] = [
                    'label' =>
                        'Original Hotspot login methods restored',

                    'run' =>
                        function () use (
                            $api,
                            $profile,
                            $originalLogin
                        ): void {
                            $this->write(
                                $api,
                                (new Query(
                                    '/ip/hotspot/profile/set'
                                ))
                                    ->equal(
                                        '.id',
                                        $profile[
                                            '.id'
                                        ]
                                    )
                                    ->equal(
                                        'login-by',
                                        $originalLogin
                                    )
                            );
                        },
                ];
            }

            /*
             * DNS.
             */
            $dnsEnabled =
                $this->truthy(
                    $dns[0][
                        'allow-remote-requests'
                    ] ?? false
                );

            if (!$dnsEnabled) {
                $this->write(
                    $api,
                    (new Query(
                        '/ip/dns/set'
                    ))
                        ->equal(
                            'allow-remote-requests',
                            'true'
                        )
                );

                $actions[] =
                    'DNS repaired';

                $rollbacks[] = [
                    'label' =>
                        'DNS remote requests restored to disabled',

                    'run' =>
                        function () use (
                            $api
                        ): void {
                            $this->write(
                                $api,
                                (new Query(
                                    '/ip/dns/set'
                                ))
                                    ->equal(
                                        'allow-remote-requests',
                                        'false'
                                    )
                            );
                        },
                ];
            }

            /*
             * NAT. Only rules created by this repair
             * can later be removed by rollback.
             */
            if (
                !$this->hasNat(
                    $nat,
                    $wan,
                    $gateway[
                        'network_cidr'
                    ]
                )
            ) {
                $createdIds = [];

                if (
                    $wan[
                        'list_name'
                    ]
                ) {
                    $comment =
                        'MIKROPANEL:HEALTH:NAT:ROUTER-'
                        . $router->id;

                    $this->write(
                        $api,
                        (new Query(
                            '/ip/firewall/nat/add'
                        ))
                            ->equal(
                                'chain',
                                'srcnat'
                            )
                            ->equal(
                                'action',
                                'masquerade'
                            )
                            ->equal(
                                'src-address',
                                $gateway[
                                    'network_cidr'
                                ]
                            )
                            ->equal(
                                'out-interface-list',
                                $wan[
                                    'list_name'
                                ]
                            )
                            ->equal(
                                'comment',
                                $comment
                            )
                    );

                    $rows =
                        $this->read(
                            $api,
                            '/ip/firewall/nat/print',
                            '.id,comment'
                        );

                    $created =
                        $this->find(
                            $rows,
                            'comment',
                            $comment
                        );

                    if (
                        !$created
                        || empty(
                            $created[
                                '.id'
                            ]
                        )
                    ) {
                        throw new \RuntimeException(
                            'NAT rule was created but cannot be identified for rollback.'
                        );
                    }

                    $createdIds[] =
                        $created[
                            '.id'
                        ];

                } elseif (
                    $wan[
                        'interfaces'
                    ] !== []
                ) {
                    foreach (
                        $wan[
                            'interfaces'
                        ]
                        as $index => $interface
                    ) {
                        $comment =
                            'MIKROPANEL:HEALTH:NAT:ROUTER-'
                            . $router->id
                            . ':'
                            . ($index + 1);

                        $this->write(
                            $api,
                            (new Query(
                                '/ip/firewall/nat/add'
                            ))
                                ->equal(
                                    'chain',
                                    'srcnat'
                                )
                                ->equal(
                                    'action',
                                    'masquerade'
                                )
                                ->equal(
                                    'src-address',
                                    $gateway[
                                        'network_cidr'
                                    ]
                                )
                                ->equal(
                                    'out-interface',
                                    $interface
                                )
                                ->equal(
                                    'comment',
                                    $comment
                                )
                        );

                        $rows =
                            $this->read(
                                $api,
                                '/ip/firewall/nat/print',
                                '.id,comment'
                            );

                        $created =
                            $this->find(
                                $rows,
                                'comment',
                                $comment
                            );

                        if (
                            !$created
                            || empty(
                                $created[
                                    '.id'
                                ]
                            )
                        ) {
                            throw new \RuntimeException(
                                'NAT rule was created but cannot be identified for rollback.'
                            );
                        }

                        $createdIds[] =
                            $created[
                                '.id'
                            ];
                    }

                } else {
                    $skipped[] =
                        'NAT missing but WAN cannot be detected safely. NAT was not modified.';
                }

                if (
                    $createdIds !== []
                ) {
                    $actions[] =
                        'NAT restored';

                    $rollbacks[] = [
                        'label' =>
                            'Repair-created NAT rule(s) removed',

                        'run' =>
                            function () use (
                                $api,
                                $createdIds
                            ): void {
                                foreach (
                                    array_reverse(
                                        $createdIds
                                    )
                                    as $id
                                ) {
                                    $this->write(
                                        $api,
                                        (new Query(
                                            '/ip/firewall/nat/remove'
                                        ))
                                            ->equal(
                                                '.id',
                                                $id
                                            )
                                    );
                                }
                            },
                    ];
                }
            }

            /*
             * PORTAL WRITE INTENTIONALLY ABSENT.
             */

            Cache::forget(
                'hotspot-live-map-v2:'
                . $router->id
            );

            $after =
                $this->snapshot(
                    $router
                );

            $audit->forceFill([
                'status' =>
                    $actions === []
                        ? 'noop'
                        : 'success',

                'after_overall' =>
                    $after[
                        'overall'
                    ] ?? null,

                'actions' =>
                    $actions,

                'skipped' =>
                    $skipped,

                'rollback_actions' =>
                    [],

                'completed_at' =>
                    now(),
            ])->save();

            return [
                'actions' =>
                    $actions,

                'skipped' =>
                    $skipped,

                'audit_id' =>
                    $audit->id,

                'health' =>
                    $after,
            ];

        } catch (\Throwable $exception) {
            $rollbackFailed =
                false;

            foreach (
                array_reverse(
                    $rollbacks
                )
                as $rollback
            ) {
                try {
                    $rollback[
                        'run'
                    ]();

                    $rollbackActions[] =
                        $rollback[
                            'label'
                        ];

                } catch (\Throwable $rollbackException) {
                    $rollbackFailed =
                        true;

                    $rollbackActions[] =
                        'ROLLBACK FAILED: '
                        . $rollback[
                            'label'
                        ]
                        . ' — '
                        . $rollbackException
                            ->getMessage();
                }
            }

            Cache::forget(
                'hotspot-live-map-v2:'
                . $router->id
            );

            $audit->forceFill([
                'status' =>
                    $rollbackFailed
                        ? 'rollback_failed'
                        : (
                            $rollbackActions !== []
                                ? 'rolled_back'
                                : 'failed'
                        ),

                'actions' =>
                    $actions,

                'skipped' =>
                    $skipped,

                'rollback_actions' =>
                    $rollbackActions,

                'error_message' =>
                    $exception
                        ->getMessage(),

                'completed_at' =>
                    now(),
            ])->save();

            $message =
                $exception
                    ->getMessage();

            if (
                $rollbackActions !== []
            ) {
                $message .=
                    $rollbackFailed
                        ? ' Automatic rollback was attempted but one or more rollback steps failed.'
                        : ' Changes made by this repair attempt were rolled back.';
            }

            throw new \RuntimeException(
                $message,
                0,
                $exception
            );
        }
    }

    protected function selectTopology(
        Router $router,
        array $discovery
    ): ?array {
        $rows =
            $discovery[
                'hotspot_topologies'
            ] ?? [];

        $panel =
            HotspotServer::withoutGlobalScopes()
                ->where(
                    'router_id',
                    $router->id
                )
                ->where(
                    'zone_id',
                    $router->zone_id
                )
                ->first();

        if ($panel) {
            foreach ($rows as $row) {
                if (
                    ($row[
                        'hotspot_server'
                    ] ?? null)
                    === $panel
                        ->mikrotik_name
                ) {
                    return $row;
                }
            }
        }

        foreach ($rows as $row) {
            if (
                $this->complete(
                    $row
                )
            ) {
                return $row;
            }
        }

        return $rows[0]
            ?? null;
    }

    protected function complete(
        array $row
    ): bool {
        foreach (
            [
                'interface',
                'gateway_cidr',
                'pool_name',
                'dhcp_server',
                'hotspot_server',
                'hotspot_profile',
                'dns_name',
            ]
            as $field
        ) {
            if (
                trim(
                    (string) (
                        $row[$field]
                        ?? ''
                    )
                ) === ''
            ) {
                return false;
            }
        }

        return true;
    }

    protected function detectWan(
        Client $api,
        array $routes
    ): array {
        $members =
            $this->safeRead(
                $api,
                '/interface/list/member/print',
                '.id,interface,list,disabled'
            );

        $interfaces = [];
        $listName = null;

        foreach ($members as $row) {
            if (
                $this->truthy(
                    $row['disabled']
                    ?? false
                )
                || strtolower(
                    (string) (
                        $row['list']
                        ?? ''
                    )
                ) !== 'wan'
            ) {
                continue;
            }

            $listName =
                (string)
                $row['list'];

            $name =
                trim(
                    (string) (
                        $row[
                            'interface'
                        ] ?? ''
                    )
                );

            if ($name !== '') {
                $interfaces[] =
                    $name;
            }
        }

        foreach ($routes as $row) {
            if (
                (
                    $row[
                        'dst-address'
                    ] ?? null
                ) !== '0.0.0.0/0'
                || !$this->truthy(
                    $row['active']
                    ?? false
                )
                || $this->truthy(
                    $row['disabled']
                    ?? false
                )
                || (
                    isset(
                        $row[
                            'routing-table'
                        ]
                    )
                    && $row[
                        'routing-table'
                    ] !== 'main'
                )
            ) {
                continue;
            }

            $immediate =
                trim(
                    (string) (
                        $row[
                            'immediate-gw'
                        ] ?? ''
                    )
                );

            if (
                preg_match(
                    '/%([^%]+)$/',
                    $immediate,
                    $matches
                )
            ) {
                $interfaces[] =
                    trim(
                        $matches[1]
                    );

                continue;
            }

            $gateway =
                trim(
                    (string) (
                        $row[
                            'gateway'
                        ] ?? ''
                    )
                );

            if (
                $gateway !== ''
                && !filter_var(
                    $gateway,
                    FILTER_VALIDATE_IP
                )
                && !str_contains(
                    $gateway,
                    ','
                )
            ) {
                $interfaces[] =
                    $gateway;
            }
        }

        return [
            'list_name' =>
                $listName,

            'interfaces' =>
                array_values(
                    array_unique(
                        array_filter(
                            $interfaces
                        )
                    )
                ),
        ];
    }

    protected function hasDefaultRoute(
        array $routes
    ): bool {
        foreach ($routes as $row) {
            if (
                (
                    $row[
                        'dst-address'
                    ] ?? null
                ) === '0.0.0.0/0'
                && $this->truthy(
                    $row['active']
                    ?? false
                )
                && !$this->truthy(
                    $row['disabled']
                    ?? false
                )
                && (
                    !isset(
                        $row[
                            'routing-table'
                        ]
                    )
                    || $row[
                        'routing-table'
                    ] === 'main'
                )
            ) {
                return true;
            }
        }

        return false;
    }

    protected function internetPing(
        Client $api
    ): ?bool {
        try {
            $rows =
                $api->query(
                    (new Query(
                        '/ping'
                    ))
                        ->equal(
                            'address',
                            '1.1.1.1'
                        )
                        ->equal(
                            'count',
                            '1'
                        )
                        ->equal(
                            'interval',
                            '200ms'
                        )
                )->read();

            foreach ($rows as $row) {
                if (
                    isset(
                        $row['time']
                    )
                    || isset(
                        $row['ttl']
                    )
                ) {
                    return true;
                }
            }

            return false;

        } catch (Throwable) {
            return null;
        }
    }

    protected function vpnState(
        Router $router,
        array $addresses,
        array $wireguard
    ): array {
        $host =
            trim(
                (string)
                $router->host
            );

        $interface = null;

        foreach ($addresses as $row) {
            $address =
                trim(
                    (string) (
                        $row[
                            'address'
                        ] ?? ''
                    )
                );

            $ip =
                explode(
                    '/',
                    $address,
                    2
                )[0];

            if ($ip === $host) {
                $interface =
                    $row[
                        'interface'
                    ] ?? null;

                break;
            }
        }

        if (!$interface) {
            return [
                'status' =>
                    null,

                'interface' =>
                    null,

                'detail' =>
                    'VPN management interface not identified',
            ];
        }

        foreach ($wireguard as $row) {
            if (
                ($row['name'] ?? null)
                !== $interface
            ) {
                continue;
            }

            $ok =
                !$this->truthy(
                    $row[
                        'disabled'
                    ] ?? false
                );

            return [
                'status' =>
                    $ok,

                'interface' =>
                    $interface,

                'detail' =>
                    $ok
                        ? "{$interface} connected"
                        : "{$interface} disabled",
            ];
        }

        return [
            'status' =>
                null,

            'interface' =>
                $interface,

            'detail' =>
                "Management path: {$interface}",
        ];
    }

    protected function hasNat(
        array $rows,
        array $wan,
        string $network
    ): bool {
        foreach ($rows as $row) {
            if (
                $this->truthy(
                    $row['disabled']
                    ?? false
                )
                || strtolower(
                    (string) (
                        $row['chain']
                        ?? ''
                    )
                ) !== 'srcnat'
                || strtolower(
                    (string) (
                        $row['action']
                        ?? ''
                    )
                ) !== 'masquerade'
            ) {
                continue;
            }

            $src =
                trim(
                    (string) (
                        $row[
                            'src-address'
                        ] ?? ''
                    )
                );

            if (
                $src !== ''
                && $src !== $network
            ) {
                continue;
            }

            $out =
                trim(
                    (string) (
                        $row[
                            'out-interface'
                        ] ?? ''
                    )
                );

            $list =
                trim(
                    (string) (
                        $row[
                            'out-interface-list'
                        ] ?? ''
                    )
                );

            if (
                $out === ''
                && $list === ''
            ) {
                return true;
            }

            if (
                $wan['list_name']
                && strcasecmp(
                    $list,
                    $wan['list_name']
                ) === 0
            ) {
                return true;
            }

            if (
                $out !== ''
                && in_array(
                    $out,
                    $wan[
                        'interfaces'
                    ],
                    true
                )
            ) {
                return true;
            }
        }

        return false;
    }

    protected function portalState(
        array $files,
        ?array $profile
    ): array {
        if (!$profile) {
            return [
                'ok' => false,
                'detail' =>
                    'Profile missing',
            ];
        }

        $directory =
            trim(
                (string) (
                    $profile[
                        'html_directory'
                    ]
                    ?? $profile[
                        'html-directory'
                    ]
                    ?? ''
                )
            );

        if ($directory === '') {
            return [
                'ok' => false,
                'detail' =>
                    'Portal directory missing',
            ];
        }

        $names =
            array_fill_keys(
                array_map(
                    fn ($row) =>
                        (string) (
                            $row['name']
                            ?? ''
                        ),
                    $files
                ),
                true
            );

        $login =
            isset(
                $names[
                    "{$directory}/login.html"
                ]
            );

        $status =
            isset(
                $names[
                    "{$directory}/status.html"
                ]
            );

        $api =
            isset(
                $names[
                    "{$directory}/api.json"
                ]
            );

        return [
            'ok' =>
                $login
                && $status
                && $api,

            'detail' =>
                "{$directory} · "
                . 'login '
                . ($login ? '✓' : '✕')
                . ' · status '
                . ($status ? '✓' : '✕')
                . ' · api '
                . ($api ? '✓' : '✕'),
        ];
    }

    protected function poolCapacity(
        string $ranges
    ): int {
        $total = 0;

        foreach (
            explode(
                ',',
                $ranges
            )
            as $segment
        ) {
            $segment =
                trim($segment);

            if ($segment === '') {
                continue;
            }

            if (
                str_contains(
                    $segment,
                    '-'
                )
            ) {
                [
                    $start,
                    $end,
                ] =
                    array_map(
                        'trim',
                        explode(
                            '-',
                            $segment,
                            2
                        )
                    );

            } else {
                $start = $segment;
                $end = $segment;
            }

            if (
                !filter_var(
                    $start,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4
                )
                || !filter_var(
                    $end,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4
                )
            ) {
                continue;
            }

            $a =
                $this->ipLong(
                    $start
                );

            $b =
                $this->ipLong(
                    $end
                );

            if ($b >= $a) {
                $total +=
                    $b - $a + 1;
            }
        }

        return $total;
    }

    protected function ipInPool(
        string $ip,
        string $ranges
    ): bool {
        if (
            !filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4
            )
        ) {
            return false;
        }

        $value =
            $this->ipLong(
                $ip
            );

        foreach (
            explode(
                ',',
                $ranges
            )
            as $segment
        ) {
            $segment =
                trim($segment);

            if ($segment === '') {
                continue;
            }

            if (
                str_contains(
                    $segment,
                    '-'
                )
            ) {
                [
                    $start,
                    $end,
                ] =
                    array_map(
                        'trim',
                        explode(
                            '-',
                            $segment,
                            2
                        )
                    );

            } else {
                $start = $segment;
                $end = $segment;
            }

            if (
                !filter_var(
                    $start,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4
                )
                || !filter_var(
                    $end,
                    FILTER_VALIDATE_IP,
                    FILTER_FLAG_IPV4
                )
            ) {
                continue;
            }

            if (
                $value
                    >= $this->ipLong(
                        $start
                    )
                && $value
                    <= $this->ipLong(
                        $end
                    )
            ) {
                return true;
            }
        }

        return false;
    }

    protected function bindingConflicts(
        array $bindings,
        array $leases
    ): array {
        $leasesByIp = [];

        foreach ($leases as $lease) {
            $ip =
                trim(
                    (string) (
                        $lease[
                            'address'
                        ] ?? ''
                    )
                );

            if ($ip === '') {
                continue;
            }

            $leasesByIp[$ip] =
                strtoupper(
                    trim(
                        (string) (
                            $lease[
                                'mac-address'
                            ] ?? ''
                        )
                    )
                );
        }

        $seen = [];
        $conflicts = [];

        foreach ($bindings as $binding) {
            $ip =
                trim(
                    (string) (
                        $binding[
                            'address'
                        ] ?? ''
                    )
                );

            $mac =
                strtoupper(
                    trim(
                        (string) (
                            $binding[
                                'mac-address'
                            ] ?? ''
                        )
                    )
                );

            if ($ip === '') {
                continue;
            }

            if (
                isset(
                    $seen[$ip]
                )
                && $seen[$ip]
                    !== $mac
            ) {
                $conflicts[] = [
                    'address' =>
                        $ip,

                    'binding_mac' =>
                        $mac,

                    'lease_mac' =>
                        $seen[$ip],

                    'reason' =>
                        'Duplicate Hotspot IP binding',
                ];
            }

            $seen[$ip] =
                $mac;

            if (
                isset(
                    $leasesByIp[$ip]
                )
                && $mac !== ''
                && $leasesByIp[$ip]
                    !== ''
                && $leasesByIp[$ip]
                    !== $mac
            ) {
                $conflicts[] = [
                    'address' =>
                        $ip,

                    'binding_mac' =>
                        $mac,

                    'lease_mac' =>
                        $leasesByIp[$ip],

                    'reason' =>
                        'DHCP MAC and Hotspot IP binding MAC do not match',
                ];
            }
        }

        return $conflicts;
    }

    protected function makeIssues(
        array $checks,
        float $poolPercent,
        array $conflicts,
        ?bool $internetPing
    ): array {
        $issues = [];

        foreach ($checks as $check) {
            if (
                $check['status']
                !== false
            ) {
                continue;
            }

            $critical =
                in_array(
                    $check['key'],
                    [
                        'internet',
                        'api',
                        'bridge',
                        'gateway',
                        'dhcp',
                        'hotspot',
                    ],
                    true
                );

            $issues[] = [
                'key' =>
                    $check['key'],

                'severity' =>
                    $critical
                        ? 'critical'
                        : 'warning',

                'title' =>
                    $check['label']
                    . ' Problem',

                'message' =>
                    $check['detail'],

                'repairable' =>
                    $check[
                        'repairable'
                    ],
            ];
        }

        if (
            $poolPercent >= 98
        ) {
            $issues[] = [
                'key' =>
                    'pool_full',

                'severity' =>
                    'critical',

                'title' =>
                    'DHCP Pool Almost Full',

                'message' =>
                    "Pool usage {$poolPercent}%",

                'repairable' =>
                    false,
            ];

        } elseif (
            $poolPercent >= 90
        ) {
            $issues[] = [
                'key' =>
                    'pool_high',

                'severity' =>
                    'warning',

                'title' =>
                    'DHCP Pool Usage High',

                'message' =>
                    "Pool usage {$poolPercent}%",

                'repairable' =>
                    false,
            ];
        }

        if ($conflicts !== []) {
            $issues[] = [
                'key' =>
                    'binding_conflict',

                'severity' =>
                    'warning',

                'title' =>
                    'DHCP / IP Bind Conflict',

                'message' =>
                    count($conflicts)
                    . ' conflict(s) detected.',

                'repairable' =>
                    false,
            ];
        }

        if ($internetPing === false) {
            $issues[] = [
                'key' =>
                    'internet_ping',

                'severity' =>
                    'warning',

                'title' =>
                    'Internet Ping No Reply',

                'message' =>
                    'Default route is active, but 1.1.1.1 did not reply.',

                'repairable' =>
                    false,
            ];
        }

        return $issues;
    }

    protected function overall(
        array $issues
    ): string {
        foreach ($issues as $issue) {
            if (
                ($issue[
                    'severity'
                ] ?? null)
                === 'critical'
            ) {
                return 'critical';
            }
        }

        return $issues === []
            ? 'healthy'
            : 'warning';
    }

    protected function check(
        string $key,
        string $label,
        ?bool $status,
        string $detail,
        bool $repairable
    ): array {
        /*
         * HOTSPOT_PORTAL_GENERIC_REPAIR_DISABLED_V3
         *
         * Portal recovery requires a dedicated
         * backup-aware recovery workflow.
         */
        if ($key === 'portal') {
            $repairable = false;
        }

        return [
            'key' =>
                $key,

            'label' =>
                $label,

            'status' =>
                $status,

            'detail' =>
                $detail,

            'repairable' =>
                $repairable,
        ];
    }

    protected function cidrInfo(
        string $cidr
    ): array {
        if (
            !preg_match(
                '/^([^\/]+)\/(\d{1,2})$/',
                trim($cidr),
                $match
            )
        ) {
            throw new \RuntimeException(
                'Invalid IPv4/CIDR.'
            );
        }

        $ip =
            trim(
                $match[1]
            );

        $prefix =
            (int)
            $match[2];

        if (
            !filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4
            )
            || $prefix < 1
            || $prefix > 30
        ) {
            throw new \RuntimeException(
                'Invalid IPv4/CIDR.'
            );
        }

        $ipLong =
            $this->ipLong(
                $ip
            );

        $mask =
            (
                0xFFFFFFFF
                << (32 - $prefix)
            )
            & 0xFFFFFFFF;

        return [
            'ip' =>
                $ip,

            'network_cidr' =>
                long2ip(
                    $ipLong
                    & $mask
                )
                . '/'
                . $prefix,
        ];
    }

    protected function ipLong(
        string $ip
    ): int {
        $value =
            ip2long(
                $ip
            );

        if ($value === false) {
            throw new \RuntimeException(
                'Invalid IPv4.'
            );
        }

        return (int)
            sprintf(
                '%u',
                $value
            );
    }

    protected function integer(
        mixed $value
    ): int {
        return is_numeric(
            $value
        )
            ? (int)
                $value
            : 0;
    }

    protected function client(
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
                    (int)
                    $router->api_port,

                'ssl' =>
                    (bool)
                    $router->use_ssl,

                'timeout' =>
                    3,

                'socket_timeout' =>
                    5,

                'attempts' =>
                    1,

                'delay' =>
                    0,

                'socket_options' => [
                    'tcp_nodelay' =>
                        true,
                ],
            ])
        );
    }

    protected function read(
        Client $api,
        string $path,
        ?string $properties = null
    ): array {
        $query =
            new Query(
                $path
            );

        if ($properties) {
            $query->equal(
                '.proplist',
                $properties
            );
        }

        return $api
            ->query(
                $query
            )
            ->read();
    }

    protected function safeRead(
        Client $api,
        string $path,
        ?string $properties = null
    ): array {
        try {
            return $this->read(
                $api,
                $path,
                $properties
            );

        } catch (Throwable) {
            return [];
        }
    }

    protected function write(
        Client $api,
        Query $query
    ): array {
        return $api
            ->query(
                $query
            )
            ->read();
    }

    protected function find(
        array $rows,
        string $field,
        string $value
    ): ?array {
        foreach ($rows as $row) {
            if (
                (string) (
                    $row[$field]
                    ?? ''
                ) === $value
            ) {
                return $row;
            }
        }

        return null;
    }

    protected function truthy(
        mixed $value
    ): bool {
        return in_array(
            strtolower(
                trim(
                    (string)
                    $value
                )
            ),
            [
                '1',
                'true',
                'yes',
                'on',
            ],
            true
        );
    }
}
