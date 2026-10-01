<?php

namespace App\Services\MikroTik;

use App\Models\Router;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class MikroTikService
{
    protected ?Client $client = null;

    protected ?string $clientKey = null;

    protected ?string $lastError = null;

    protected array|Router|null $activeRouter = null;

    protected bool $connectionReused = false;

    protected int $queryCount = 0;

    protected static array $clientPool = [];

    /*
     * Identity and RouterBoard metadata rarely change.
     * Keep them in the long-lived queue worker for
     * one hour instead of querying RouterOS every minute.
     */
    protected static array $staticMetadataCache = [];

    public function connect(
        array|Router $router
    ): bool {
        $this->activeRouter = $router;
        $this->connectionReused = false;

        try {
            $credentials =
                $this->credentials($router);

            $key = hash(
                'sha256',
                implode('|', [
                    $credentials['host'],
                    $credentials['api_port'],
                    $credentials['username'],
                    $credentials['use_ssl']
                        ? '1'
                        : '0',
                    $credentials['password'],
                ])
            );

            if (
                isset(self::$clientPool[$key])
                && $this->socketAlive(
                    self::$clientPool[$key]
                )
            ) {
                $this->client =
                    self::$clientPool[$key];

                $this->clientKey = $key;
                $this->lastError = null;
                $this->connectionReused = true;

                return true;
            }

            unset(
                self::$clientPool[$key]
            );

            $config = new Config([
                'host' =>
                    $credentials['host'],

                'user' =>
                    $credentials['username'],

                'pass' =>
                    $credentials['password'],

                'port' =>
                    $credentials['api_port'],

                'ssl' =>
                    $credentials['use_ssl'],

                'timeout' => 3,
                'socket_timeout' => 5,
                'attempts' => 1,
                'delay' => 0,

                'socket_options' => [
                    'tcp_nodelay' => true,
                ],
            ]);

            $client =
                new Client($config);

            self::$clientPool[$key] =
                $client;

            $this->client = $client;
            $this->clientKey = $key;
            $this->lastError = null;

            return true;

        } catch (Throwable $exception) {
            $this->forgetCurrentClient();

            $this->lastError =
                $exception->getMessage();

            return false;
        }
    }

    public function test(): array
    {
        try {
            if (!$this->client) {
                return [
                    'success' => false,
                    'message' =>
                        'MikroTik connection is not initialized.',
                ];
            }

            return [
                'success' => true,

                'data' =>
                    $this->queryFirst(
                        '/system/resource/print',
                        'version,uptime,cpu-load'
                    ),
            ];

        } catch (Throwable $exception) {
            return [
                'success' => false,

                'message' =>
                    $exception->getMessage(),
            ];
        }
    }

    /*
     * Full consolidated read-only telemetry.
     *
     * One persistent RouterOS connection supplies:
     * - health
     * - DHCP connection status
     * - ARP count
     * - Queue count
     * - Queue usage counters
     */
    public function telemetry(
        array|Router $router
    ): array {
        $startedAt = microtime(true);

        $this->queryCount = 0;

        if (!$this->connect($router)) {
            return [
                'success' => false,

                'message' =>
                    $this->lastError
                    ?? 'Unable to connect to MikroTik API.',

                'latency_ms' => null,

                'sync_duration_ms' =>
                    (int) round(
                        (
                            microtime(true)
                            - $startedAt
                        ) * 1000
                    ),

                'routeros_query_count' =>
                    $this->queryCount,

                'connection_reused' =>
                    false,

                'leases' => [],
                'queues' => [],

                'checked_at' =>
                    now()->toISOString(),
            ];
        }

        try {
            $latencyStartedAt =
                microtime(true);

            $resource =
                $this->queryFirst(
                    '/system/resource/print',
                    implode(',', [
                        'version',
                        'board-name',
                        'architecture-name',
                        'platform',
                        'uptime',
                        'cpu-load',
                        'cpu-count',
                        'free-memory',
                        'total-memory',
                    ])
                );

            $apiLatencyMs =
                (int) round(
                    (
                        microtime(true)
                        - $latencyStartedAt
                    ) * 1000
                );

            if (empty($resource)) {
                throw new \RuntimeException(
                    'MikroTik returned no system resource information.'
                );
            }

            $staticMetadata =
                $this->staticMetadata(
                    $router
                );

            $dhcpServer = null;

            if (
                $router instanceof Router
                && !empty(
                    $router->dhcp_server
                )
            ) {
                $dhcpServer =
                    trim(
                        (string)
                        $router->dhcp_server
                    );
            }

            $leasesRead =
                $this->readRowsSafe(
                    '/ip/dhcp-server/lease/print',
                    implode(',', [
                        '.id',
                        'mac-address',
                        'active-mac-address',
                        'status',
                    ]),
                    $dhcpServer !== ''
                        && $dhcpServer !== null
                            ? [
                                'server',
                                $dhcpServer,
                            ]
                            : null
                );

            $arpRead =
                $this->readRowsSafe(
                    '/ip/arp/print',
                    '.id'
                );

            $queuesRead =
                $this->readRowsSafe(
                    '/queue/simple/print',
                    implode(',', [
                        '.id',
                        'name',
                        'bytes',
                        'disabled',
                        'invalid',
                    ])
                );

            $leases =
                $leasesRead['rows'];

            $arpRows =
                $arpRead['rows'];

            $queues =
                $queuesRead['rows'];

            $duration =
                (int) round(
                    (
                        microtime(true)
                        - $startedAt
                    ) * 1000
                );

            return [
                'success' => true,

                'message' =>
                    'MikroTik telemetry synchronized successfully.',

                'latency_ms' =>
                    $apiLatencyMs,

                'sync_duration_ms' =>
                    $duration,

                'routeros_query_count' =>
                    $this->queryCount,

                'connection_reused' =>
                    $this->connectionReused,

                'static_metadata_cached' =>
                    (bool) (
                        $staticMetadata[
                            'cached'
                        ]
                        ?? false
                    ),

                'checked_at' =>
                    now()->toISOString(),

                'identity' =>
                    $staticMetadata[
                        'identity'
                    ]
                    ?? (
                        $router instanceof Router
                            ? $router->identity
                            : null
                    ),

                'version' =>
                    $resource['version']
                    ?? null,

                'board_name' =>
                    $resource['board-name']
                    ?? $staticMetadata[
                        'board_name'
                    ]
                    ?? (
                        $router instanceof Router
                            ? $router->board_name
                            : null
                    ),

                'architecture' =>
                    $resource[
                        'architecture-name'
                    ]
                    ?? null,

                'platform' =>
                    $resource['platform']
                    ?? null,

                'uptime' =>
                    $resource['uptime']
                    ?? null,

                'cpu_load' =>
                    isset(
                        $resource['cpu-load']
                    )
                        ? (int)
                            $resource['cpu-load']
                        : null,

                'cpu_count' =>
                    isset(
                        $resource['cpu-count']
                    )
                        ? (int)
                            $resource['cpu-count']
                        : null,

                'free_memory' =>
                    isset(
                        $resource['free-memory']
                    )
                        ? (int)
                            $resource['free-memory']
                        : null,

                'total_memory' =>
                    isset(
                        $resource['total-memory']
                    )
                        ? (int)
                            $resource['total-memory']
                        : null,

                'factory_firmware' =>
                    $staticMetadata[
                        'factory_firmware'
                    ]
                    ?? null,

                'current_firmware' =>
                    $staticMetadata[
                        'current_firmware'
                    ]
                    ?? null,

                'upgrade_firmware' =>
                    $staticMetadata[
                        'upgrade_firmware'
                    ]
                    ?? null,

                'leases_ok' =>
                    $leasesRead['success'],

                'leases_error' =>
                    $leasesRead['error'],

                'arp_ok' =>
                    $arpRead['success'],

                'arp_error' =>
                    $arpRead['error'],

                'queues_ok' =>
                    $queuesRead['success'],

                'queues_error' =>
                    $queuesRead['error'],

                'dhcp_leases_count' =>
                    $leasesRead['success']
                        ? count($leases)
                        : null,

                'arp_entries_count' =>
                    $arpRead['success']
                        ? count($arpRows)
                        : null,

                'simple_queues_count' =>
                    $queuesRead['success']
                        ? count($queues)
                        : null,

                'leases' =>
                    $leases,

                'queues' =>
                    $queues,
            ];

        } catch (Throwable $exception) {
            return [
                'success' => false,

                'message' =>
                    $exception->getMessage(),

                'latency_ms' => null,

                'sync_duration_ms' =>
                    (int) round(
                        (
                            microtime(true)
                            - $startedAt
                        ) * 1000
                    ),

                'routeros_query_count' =>
                    $this->queryCount,

                'connection_reused' =>
                    $this->connectionReused,

                'leases' => [],
                'queues' => [],

                'checked_at' =>
                    now()->toISOString(),
            ];
        }
    }

    /*
     * HOTSPOT_ROUTER_DISCOVERY_PHASE1_V1
     *
     * Read-only discovery for the Hotspot Setup Wizard.
     *
     * Nothing in this method changes RouterOS.
     */
    public function hotspotSetupDiscovery(
        array|Router $router
    ): array {
        $this->queryCount = 0;

        if (!$this->connect($router)) {
            return [
                'success' => false,

                'message' =>
                    $this->lastError
                    ?? 'Unable to connect to MikroTik API.',

                'interfaces' => [],
                'ethernet_interfaces' => [],

                'routeros_query_count' =>
                    $this->queryCount,

                'checked_at' =>
                    now()->toISOString(),
            ];
        }

        try {
            $resource =
                $this->queryFirst(
                    '/system/resource/print',
                    implode(',', [
                        'version',
                        'board-name',
                        'architecture-name',
                        'platform',
                        'uptime',
                    ])
                );

            $identity =
                $this->queryFirst(
                    '/system/identity/print',
                    'name'
                );

            $interfacesRead =
                $this->readRowsSafe(
                    '/interface/print',
                    implode(',', [
                        '.id',
                        'name',
                        'default-name',
                        'type',
                        'mac-address',
                        'running',
                        'disabled',
                        'comment',
                        'mtu',
                        'actual-mtu',
                    ])
                );

            if (
                !(
                    $interfacesRead[
                        'success'
                    ] ?? false
                )
            ) {
                throw new \RuntimeException(
                    'Unable to read MikroTik interfaces: '
                    . (
                        $interfacesRead[
                            'error'
                        ]
                        ?? 'Unknown RouterOS error.'
                    )
                );
            }

            /*
             * HOTSPOT_BRIDGE_SETUP_PHASE2_V2
             */
            $bridgesRead =
                $this->readRowsSafe(
                    '/interface/bridge/print',
                    implode(',', [
                        '.id',
                        'name',
                        'disabled',
                        'comment',
                        'protocol-mode',
                        'mac-address',
                    ])
                );

            $bridgePortsRead =
                $this->readRowsSafe(
                    '/interface/bridge/port/print',
                    implode(',', [
                        '.id',
                        'interface',
                        'bridge',
                        'disabled',
                        'hw',
                        'comment',
                    ])
                );

            $addressesRead =
                $this->readRowsSafe(
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

            $dhcpClientsRead =
                $this->readRowsSafe(
                    '/ip/dhcp-client/print',
                    implode(',', [
                        '.id',
                        'interface',
                        'status',
                        'disabled',
                        'add-default-route',
                        'comment',
                    ])
                );

            /*
             * HOTSPOT_WAN_AUTO_HIDE_V1
             *
             * Detect Internet/WAN interfaces dynamically.
             * No interface name such as ether1 is hardcoded.
             */
            $interfaceListsRead =
                $this->readRowsSafe(
                    '/interface/list/member/print',
                    implode(',', [
                        '.id',
                        'interface',
                        'list',
                        'disabled',
                    ])
                );

            $routesRead =
                $this->readRowsSafe(
                    '/ip/route/print',
                    implode(',', [
                        '.id',
                        'dst-address',
                        'gateway',
                        'immediate-gw',
                        'routing-table',
                        'active',
                        'disabled',
                        'dynamic',
                        'distance',
                    ])
                );

            /*
             * HOTSPOT_GATEWAY_STEP3_V1
             *
             * Read current Hotspot topology so the
             * wizard can distinguish the actual
             * Hotspot gateway from unrelated IPs on
             * the same bridge.
             */
            $poolsRead =
                $this->readRowsSafe(
                    '/ip/pool/print',
                    implode(',', [
                        '.id',
                        'name',
                        'ranges',
                        'comment',
                    ])
                );

            $dhcpServersRead =
                $this->readRowsSafe(
                    '/ip/dhcp-server/print',
                    implode(',', [
                        '.id',
                        'name',
                        'interface',
                        'address-pool',
                        'lease-time',
                        'disabled',
                        'comment',
                    ])
                );

            $dhcpNetworksRead =
                $this->readRowsSafe(
                    '/ip/dhcp-server/network/print',
                    implode(',', [
                        '.id',
                        'address',
                        'gateway',
                        'dns-server',
                        'domain',
                        'comment',
                    ])
                );

            $hotspotServersRead =
                $this->readRowsSafe(
                    '/ip/hotspot/print',
                    implode(',', [
                        '.id',
                        'name',
                        'interface',
                        'address-pool',
                        'profile',
                        'disabled',
                    ])
                );

            $hotspotProfilesRead =
                $this->readRowsSafe(
                    '/ip/hotspot/profile/print',
                    implode(',', [
                        '.id',
                        'name',
                        'hotspot-address',
                        'dns-name',
                        'html-directory',
                        'login-by',
                        'use-radius',
                    ])
                );

            $bridgesByInterface = [];

            foreach (
                $bridgePortsRead['rows'] ?? []
                as $row
            ) {
                $interface =
                    trim(
                        (string) (
                            $row['interface']
                            ?? ''
                        )
                    );

                if ($interface === '') {
                    continue;
                }

                $bridgesByInterface[
                    $interface
                ] = [
                    'bridge' =>
                        $row['bridge']
                        ?? null,

                    'disabled' =>
                        ($row['disabled'] ?? 'false')
                        === 'true',

                    'hardware_offload' =>
                        ($row['hw'] ?? 'false')
                        === 'true',
                ];
            }

            $addressesByInterface = [];

            foreach (
                $addressesRead['rows'] ?? []
                as $row
            ) {
                $interface =
                    trim(
                        (string) (
                            $row['interface']
                            ?? ''
                        )
                    );

                if ($interface === '') {
                    continue;
                }

                $addressesByInterface[
                    $interface
                ][] = [
                    'address' =>
                        $row['address']
                        ?? null,

                    'network' =>
                        $row['network']
                        ?? null,

                    'dynamic' =>
                        ($row['dynamic'] ?? 'false')
                        === 'true',

                    'disabled' =>
                        ($row['disabled'] ?? 'false')
                        === 'true',
                ];
            }

            $dhcpByInterface = [];

            foreach (
                $dhcpClientsRead['rows'] ?? []
                as $row
            ) {
                $interface =
                    trim(
                        (string) (
                            $row['interface']
                            ?? ''
                        )
                    );

                if ($interface === '') {
                    continue;
                }

                $dhcpByInterface[
                    $interface
                ] = [
                    'status' =>
                        $row['status']
                        ?? null,

                    'disabled' =>
                        ($row['disabled'] ?? 'false')
                        === 'true',

                    'add_default_route' =>
                        ($row['add-default-route']
                            ?? 'false')
                        === 'true',
                ];
            }

            $bridges = [];

            foreach (
                $bridgesRead['rows'] ?? []
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

                $bridges[] = [
                    'id' =>
                        $row['.id']
                        ?? null,

                    'name' =>
                        $name,

                    'disabled' =>
                        ($row['disabled'] ?? 'false')
                        === 'true',

                    'comment' =>
                        $row['comment']
                        ?? null,

                    'protocol_mode' =>
                        $row['protocol-mode']
                        ?? null,

                    'mac_address' =>
                        $row['mac-address']
                        ?? null,
                ];
            }

            usort(
                $bridges,
                fn (array $a, array $b): int =>
                    strnatcasecmp(
                        $a['name'],
                        $b['name']
                    )
            );

            $pools = [];
            $poolsByName = [];

            foreach (
                $poolsRead['rows'] ?? []
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

                $item = [
                    'id' =>
                        $row['.id']
                        ?? null,

                    'name' =>
                        $name,

                    'ranges' =>
                        $row['ranges']
                        ?? null,

                    'comment' =>
                        $row['comment']
                        ?? null,
                ];

                $pools[] = $item;
                $poolsByName[$name] = $item;
            }

            $dhcpServers = [];

            foreach (
                $dhcpServersRead['rows'] ?? []
                as $row
            ) {
                $dhcpServers[] = [
                    'id' =>
                        $row['.id']
                        ?? null,

                    'name' =>
                        $row['name']
                        ?? null,

                    'interface' =>
                        $row['interface']
                        ?? null,

                    'address_pool' =>
                        $row['address-pool']
                        ?? null,

                    'lease_time' =>
                        $row['lease-time']
                        ?? null,

                    'disabled' =>
                        ($row['disabled'] ?? 'false')
                        === 'true',

                    'comment' =>
                        $row['comment']
                        ?? null,
                ];
            }

            $dhcpNetworks = [];

            foreach (
                $dhcpNetworksRead['rows'] ?? []
                as $row
            ) {
                $dhcpNetworks[] = [
                    'id' =>
                        $row['.id']
                        ?? null,

                    'address' =>
                        $row['address']
                        ?? null,

                    'gateway' =>
                        $row['gateway']
                        ?? null,

                    'dns_server' =>
                        $row['dns-server']
                        ?? null,

                    'domain' =>
                        $row['domain']
                        ?? null,

                    'comment' =>
                        $row['comment']
                        ?? null,
                ];
            }

            $hotspotProfiles = [];
            $profilesByName = [];

            foreach (
                $hotspotProfilesRead['rows'] ?? []
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

                $item = [
                    'id' =>
                        $row['.id']
                        ?? null,

                    'name' =>
                        $name,

                    'hotspot_address' =>
                        $row['hotspot-address']
                        ?? null,

                    'dns_name' =>
                        $row['dns-name']
                        ?? null,

                    'html_directory' =>
                        $row['html-directory']
                        ?? null,

                    'login_by' =>
                        $row['login-by']
                        ?? null,

                    'use_radius' =>
                        ($row['use-radius'] ?? 'false')
                        === 'true',
                ];

                $hotspotProfiles[] = $item;
                $profilesByName[$name] = $item;
            }

            $hotspotServers = [];
            $hotspotTopologies = [];

            foreach (
                $hotspotServersRead['rows'] ?? []
                as $row
            ) {
                $serverName =
                    trim(
                        (string) (
                            $row['name']
                            ?? ''
                        )
                    );

                $interface =
                    trim(
                        (string) (
                            $row['interface']
                            ?? ''
                        )
                    );

                $poolName =
                    trim(
                        (string) (
                            $row['address-pool']
                            ?? ''
                        )
                    );

                $profileName =
                    trim(
                        (string) (
                            $row['profile']
                            ?? ''
                        )
                    );

                $server = [
                    'id' =>
                        $row['.id']
                        ?? null,

                    'name' =>
                        $serverName !== ''
                            ? $serverName
                            : null,

                    'interface' =>
                        $interface !== ''
                            ? $interface
                            : null,

                    'address_pool' =>
                        $poolName !== ''
                            ? $poolName
                            : null,

                    'profile' =>
                        $profileName !== ''
                            ? $profileName
                            : null,

                    'disabled' =>
                        ($row['disabled'] ?? 'false')
                        === 'true',
                ];

                $hotspotServers[] =
                    $server;

                $profile =
                    $profilesByName[
                        $profileName
                    ] ?? null;

                $gatewayIp =
                    trim(
                        (string) (
                            $profile[
                                'hotspot_address'
                            ]
                            ?? ''
                        )
                    );

                if (
                    $gatewayIp === '0.0.0.0'
                ) {
                    $gatewayIp = '';
                }

                $dhcpServer = null;

                foreach (
                    $dhcpServers
                    as $candidate
                ) {
                    if (
                        ($candidate['interface']
                            ?? null)
                            !== $interface
                    ) {
                        continue;
                    }

                    if (
                        $poolName !== ''
                        && (
                            $candidate[
                                'address_pool'
                            ] ?? null
                        ) === $poolName
                    ) {
                        $dhcpServer =
                            $candidate;
                        break;
                    }

                    if ($dhcpServer === null) {
                        $dhcpServer =
                            $candidate;
                    }
                }

                $networkRow = null;

                if ($gatewayIp !== '') {
                    foreach (
                        $dhcpNetworks
                        as $candidate
                    ) {
                        if (
                            trim(
                                (string) (
                                    $candidate[
                                        'gateway'
                                    ] ?? ''
                                )
                            ) === $gatewayIp
                        ) {
                            $networkRow =
                                $candidate;
                            break;
                        }
                    }
                }

                if (
                    $gatewayIp === ''
                    && $networkRow
                ) {
                    $gatewayIp =
                        trim(
                            (string) (
                                $networkRow[
                                    'gateway'
                                ] ?? ''
                            )
                        );
                }

                $gatewayCidr = null;

                foreach (
                    $addressesByInterface[
                        $interface
                    ] ?? []
                    as $address
                ) {
                    $cidr =
                        trim(
                            (string) (
                                $address[
                                    'address'
                                ] ?? ''
                            )
                        );

                    if ($cidr === '') {
                        continue;
                    }

                    $ip =
                        explode(
                            '/',
                            $cidr,
                            2
                        )[0];

                    if (
                        $gatewayIp !== ''
                        && $ip === $gatewayIp
                    ) {
                        $gatewayCidr =
                            $cidr;
                        break;
                    }
                }

                $pool =
                    $poolsByName[
                        $poolName
                    ] ?? null;

                $hotspotTopologies[] = [
                    'interface' =>
                        $interface !== ''
                            ? $interface
                            : null,

                    'gateway_ip' =>
                        $gatewayIp !== ''
                            ? $gatewayIp
                            : null,

                    'gateway_cidr' =>
                        $gatewayCidr,

                    'network_cidr' =>
                        $networkRow[
                            'address'
                        ] ?? null,

                    'dns_server' =>
                        $networkRow[
                            'dns_server'
                        ] ?? null,

                    'dhcp_network_id' =>
                        $networkRow[
                            'id'
                        ] ?? null,

                    'pool_name' =>
                        $poolName !== ''
                            ? $poolName
                            : null,

                    'pool_ranges' =>
                        $pool[
                            'ranges'
                        ] ?? null,

                    'dhcp_server' =>
                        $dhcpServer[
                            'name'
                        ] ?? null,

                    'dhcp_lease_time' =>
                        $dhcpServer[
                            'lease_time'
                        ] ?? null,

                    'hotspot_server' =>
                        $serverName !== ''
                            ? $serverName
                            : null,

                    'hotspot_profile' =>
                        $profileName !== ''
                            ? $profileName
                            : null,

                    'dns_name' =>
                        $profile[
                            'dns_name'
                        ] ?? null,

                    'html_directory' =>
                        $profile[
                            'html_directory'
                        ] ?? null,
                ];
            }

            /*
             * Build a set of physical/logical interfaces
             * that RouterOS indicates are WAN/Internet.
             *
             * Sources:
             * 1. RouterOS interface list named WAN.
             * 2. Bound DHCP client that installs default route.
             * 3. Active main-table 0.0.0.0/0 route.
             */
            $wanInterfaces = [];

            foreach (
                $interfaceListsRead['rows'] ?? []
                as $row
            ) {
                $list =
                    strtolower(
                        trim(
                            (string) (
                                $row['list']
                                ?? ''
                            )
                        )
                    );

                $interface =
                    trim(
                        (string) (
                            $row['interface']
                            ?? ''
                        )
                    );

                $disabled =
                    ($row['disabled'] ?? 'false')
                    === 'true';

                if (
                    !$disabled
                    && $list === 'wan'
                    && $interface !== ''
                ) {
                    $wanInterfaces[
                        $interface
                    ] = true;
                }
            }

            foreach (
                $dhcpClientsRead['rows'] ?? []
                as $row
            ) {
                $interface =
                    trim(
                        (string) (
                            $row['interface']
                            ?? ''
                        )
                    );

                $status =
                    strtolower(
                        trim(
                            (string) (
                                $row['status']
                                ?? ''
                            )
                        )
                    );

                $disabled =
                    ($row['disabled'] ?? 'false')
                    === 'true';

                $addsDefaultRoute =
                    ($row['add-default-route']
                        ?? 'false')
                    === 'true';

                if (
                    !$disabled
                    && $interface !== ''
                    && $addsDefaultRoute
                    && in_array(
                        $status,
                        [
                            'bound',
                            'renewing',
                            'rebinding',
                        ],
                        true
                    )
                ) {
                    $wanInterfaces[
                        $interface
                    ] = true;
                }
            }

            foreach (
                $routesRead['rows'] ?? []
                as $row
            ) {
                $destination =
                    trim(
                        (string) (
                            $row['dst-address']
                            ?? ''
                        )
                    );

                $routingTable =
                    trim(
                        (string) (
                            $row['routing-table']
                            ?? 'main'
                        )
                    );

                $active =
                    ($row['active'] ?? 'false')
                    === 'true';

                $disabled =
                    ($row['disabled'] ?? 'false')
                    === 'true';

                if (
                    $destination !== '0.0.0.0/0'
                    || !$active
                    || $disabled
                    || (
                        $routingTable !== ''
                        && $routingTable !== 'main'
                    )
                ) {
                    continue;
                }

                $immediateGateway =
                    trim(
                        (string) (
                            $row['immediate-gw']
                            ?? ''
                        )
                    );

                /*
                 * RouterOS commonly reports:
                 * 192.168.1.1%ether1
                 */
                if (
                    preg_match(
                        '/%([^%]+)$/',
                        $immediateGateway,
                        $matches
                    )
                ) {
                    $interface =
                        trim(
                            (string)
                            $matches[1]
                        );

                    if ($interface !== '') {
                        $wanInterfaces[
                            $interface
                        ] = true;
                    }
                }

                /*
                 * Interface gateways may also be
                 * represented directly by name.
                 */
                $gateway =
                    trim(
                        (string) (
                            $row['gateway']
                            ?? ''
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
                    $wanInterfaces[
                        $gateway
                    ] = true;
                }
            }

            $interfaces = [];

            foreach (
                $interfacesRead['rows'] ?? []
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

                $type =
                    strtolower(
                        trim(
                            (string) (
                                $row['type']
                                ?? ''
                            )
                        )
                    );

                $defaultName =
                    trim(
                        (string) (
                            $row['default-name']
                            ?? ''
                        )
                    );

                $physicalEthernet =
                    $type === 'ether'
                    || str_starts_with(
                        strtolower($defaultName),
                        'ether'
                    )
                    || str_starts_with(
                        strtolower($defaultName),
                        'sfp'
                    );

                $bridge =
                    $bridgesByInterface[
                        $name
                    ] ?? null;

                $addresses =
                    $addressesByInterface[
                        $name
                    ] ?? [];

                $dhcpClient =
                    $dhcpByInterface[
                        $name
                    ] ?? null;

                /*
                 * Caution is informational only.
                 * Phase 1 never changes the port.
                 */
                $inUse =
                    $bridge !== null
                    || $addresses !== []
                    || $dhcpClient !== null;

                $isWan =
                    isset(
                        $wanInterfaces[
                            $name
                        ]
                    );

                $interfaces[] = [
                    'id' =>
                        $row['.id']
                        ?? null,

                    'name' =>
                        $name,

                    'default_name' =>
                        $defaultName !== ''
                            ? $defaultName
                            : null,

                    'type' =>
                        $type !== ''
                            ? $type
                            : null,

                    'mac_address' =>
                        $row['mac-address']
                        ?? null,

                    'running' =>
                        ($row['running'] ?? 'false')
                        === 'true',

                    'disabled' =>
                        ($row['disabled'] ?? 'false')
                        === 'true',

                    'comment' =>
                        $row['comment']
                        ?? null,

                    'mtu' =>
                        isset($row['mtu'])
                            ? (int) $row['mtu']
                            : null,

                    'actual_mtu' =>
                        isset(
                            $row['actual-mtu']
                        )
                            ? (int)
                                $row['actual-mtu']
                            : null,

                    'physical_ethernet' =>
                        $physicalEthernet,

                    'is_wan' =>
                        $isWan,

                    'existing_bridge' =>
                        $bridge[
                            'bridge'
                        ] ?? null,

                    'bridge_port' =>
                        $bridge,

                    'ip_addresses' =>
                        $addresses,

                    'dhcp_client' =>
                        $dhcpClient,

                    'in_use' =>
                        $inUse,

                    'caution' =>
                        $physicalEthernet
                        && $inUse,
                ];
            }

            usort(
                $interfaces,
                function (
                    array $a,
                    array $b
                ): int {
                    if (
                        $a['physical_ethernet']
                        !== $b['physical_ethernet']
                    ) {
                        return
                            $a['physical_ethernet']
                                ? -1
                                : 1;
                    }

                    return strnatcasecmp(
                        (string) $a['name'],
                        (string) $b['name']
                    );
                }
            );

            $ethernet =
                array_values(
                    array_filter(
                        $interfaces,
                        fn (array $row): bool =>
                            (bool)
                            $row[
                                'physical_ethernet'
                            ]
                            && !(
                                $row[
                                    'is_wan'
                                ]
                                ?? false
                            )
                    )
                );

            $hiddenWanCount =
                count(
                    array_filter(
                        $interfaces,
                        fn (array $row): bool =>
                            (bool) (
                                $row[
                                    'physical_ethernet'
                                ]
                                ?? false
                            )
                            && (bool) (
                                $row[
                                    'is_wan'
                                ]
                                ?? false
                            )
                    )
                );

            return [
                'success' => true,

                'message' =>
                    'RouterOS connected. Interfaces discovered in read-only mode.',

                'identity' =>
                    $identity['name']
                    ?? (
                        $router instanceof Router
                            ? $router->identity
                            : null
                    ),

                'version' =>
                    $resource['version']
                    ?? null,

                'board_name' =>
                    $resource['board-name']
                    ?? (
                        $router instanceof Router
                            ? $router->board_name
                            : null
                    ),

                'architecture' =>
                    $resource[
                        'architecture-name'
                    ] ?? null,

                'platform' =>
                    $resource['platform']
                    ?? null,

                'uptime' =>
                    $resource['uptime']
                    ?? null,

                'interfaces' =>
                    $interfaces,

                'ethernet_interfaces' =>
                    $ethernet,

                'bridges' =>
                    $bridges,

                'ip_pools' =>
                    $pools,

                'dhcp_servers' =>
                    $dhcpServers,

                'dhcp_networks' =>
                    $dhcpNetworks,

                'hotspot_servers' =>
                    $hotspotServers,

                'hotspot_profiles' =>
                    $hotspotProfiles,

                'hotspot_topologies' =>
                    $hotspotTopologies,

                /*
                 * Only the count is exposed to the UI.
                 * WAN port names are intentionally not
                 * rendered in the setup selection.
                 */
                'hidden_wan_count' =>
                    $hiddenWanCount,

                'wan_detection' => [
                    'interface_list_ok' =>
                        (bool) (
                            $interfaceListsRead[
                                'success'
                            ] ?? false
                        ),

                    'routes_ok' =>
                        (bool) (
                            $routesRead[
                                'success'
                            ] ?? false
                        ),

                    'dhcp_clients_ok' =>
                        (bool) (
                            $dhcpClientsRead[
                                'success'
                            ] ?? false
                        ),
                ],

                'bridge_ports_read_ok' =>
                    (bool) (
                        $bridgePortsRead[
                            'success'
                        ] ?? false
                    ),

                'addresses_read_ok' =>
                    (bool) (
                        $addressesRead[
                            'success'
                        ] ?? false
                    ),

                'dhcp_clients_read_ok' =>
                    (bool) (
                        $dhcpClientsRead[
                            'success'
                        ] ?? false
                    ),

                'routeros_query_count' =>
                    $this->queryCount,

                'checked_at' =>
                    now()->toISOString(),
            ];

        } catch (Throwable $exception) {
            return [
                'success' => false,

                'message' =>
                    $exception->getMessage(),

                'interfaces' => [],
                'ethernet_interfaces' => [],

                'routeros_query_count' =>
                    $this->queryCount,

                'checked_at' =>
                    now()->toISOString(),
            ];
        }
    }

    /*
     * HOTSPOT_BRIDGE_SETUP_PHASE2_V2
     *
     * Safe Hotspot bridge setup.
     *
     * Important:
     * - WAN is discovered again before write.
     * - WAN ports are rejected backend-side.
     * - Ports are never silently moved from another bridge.
     * - Existing selected bridge members are no-op.
     */
    public function applyHotspotBridge(
        Router $router,
        string $mode,
        string $bridgeName,
        array $ports
    ): array {
        $mode =
            strtolower(
                trim($mode)
            );

        if (
            !in_array(
                $mode,
                [
                    'existing',
                    'new',
                ],
                true
            )
        ) {
            throw new \RuntimeException(
                'Invalid bridge setup mode.'
            );
        }

        $bridgeName =
            trim($bridgeName);

        if ($bridgeName === '') {
            throw new \RuntimeException(
                'Bridge name is required.'
            );
        }

        if (
            strlen($bridgeName) > 100
            || preg_match(
                '/[\x00-\x1F\x7F]/',
                $bridgeName
            )
        ) {
            throw new \RuntimeException(
                'Bridge name contains invalid characters.'
            );
        }

        $ports =
            array_values(
                array_unique(
                    array_filter(
                        array_map(
                            fn ($value) =>
                                trim(
                                    (string)
                                    $value
                                ),
                            $ports
                        ),
                        fn ($value) =>
                            $value !== ''
                    )
                )
            );

        if ($ports === []) {
            throw new \RuntimeException(
                'Select at least one Hotspot LAN port.'
            );
        }

        /*
         * Fresh RouterOS state immediately before
         * any configuration write.
         */
        $before =
            $this->hotspotSetupDiscovery(
                $router
            );

        if (
            !(
                $before['success']
                ?? false
            )
        ) {
            throw new \RuntimeException(
                $before['message']
                ?? 'Unable to inspect MikroTik before bridge setup.'
            );
        }

        $selectable = [];

        foreach (
            $before[
                'ethernet_interfaces'
            ] ?? []
            as $interface
        ) {
            $name =
                $interface['name']
                ?? null;

            if ($name) {
                $selectable[
                    $name
                ] = $interface;
            }
        }

        /*
         * Detected WAN ports are already excluded
         * from ethernet_interfaces. Therefore a
         * submitted WAN interface cannot pass here.
         */
        foreach ($ports as $port) {
            if (
                !isset(
                    $selectable[
                        $port
                    ]
                )
            ) {
                throw new \RuntimeException(
                    "Port {$port} is not available for Hotspot bridge use."
                );
            }

            if (
                $selectable[
                    $port
                ]['is_wan']
                ?? false
            ) {
                throw new \RuntimeException(
                    "Port {$port} is an Internet/WAN interface and cannot be used."
                );
            }
        }

        $bridges = [];

        foreach (
            $before['bridges']
            ?? []
            as $bridge
        ) {
            $name =
                $bridge['name']
                ?? null;

            if ($name) {
                $bridges[
                    $name
                ] = $bridge;
            }
        }

        $bridgeExists =
            isset(
                $bridges[
                    $bridgeName
                ]
            );

        if (
            $mode === 'existing'
            && !$bridgeExists
        ) {
            throw new \RuntimeException(
                "Existing bridge {$bridgeName} was not found."
            );
        }

        if (
            $mode === 'new'
            && $bridgeExists
        ) {
            throw new \RuntimeException(
                "Bridge {$bridgeName} already exists. Choose Use Existing Bridge."
            );
        }

        /*
         * Never silently steal/move a port.
         */
        foreach ($ports as $port) {
            $currentBridge =
                $selectable[
                    $port
                ]['existing_bridge']
                ?? null;

            if (
                $mode === 'existing'
                && $currentBridge
                && $currentBridge
                    !== $bridgeName
            ) {
                throw new \RuntimeException(
                    "Port {$port} already belongs to bridge {$currentBridge}. It was not moved."
                );
            }

            if (
                $mode === 'new'
                && $currentBridge
            ) {
                throw new \RuntimeException(
                    "Port {$port} already belongs to bridge {$currentBridge}. It was not moved."
                );
            }
        }

        if (!$this->connect($router)) {
            throw new \RuntimeException(
                $this->lastError
                ?? 'Unable to connect to MikroTik API.'
            );
        }

        $createdBridge = false;
        $addedPorts = [];

        try {
            if ($mode === 'new') {
                $this->readQuery(
                    (new Query(
                        '/interface/bridge/add'
                    ))
                        ->equal(
                            'name',
                            $bridgeName
                        )
                        ->equal(
                            'comment',
                            'MikroPanel Hotspot Wizard'
                        )
                );

                $createdBridge = true;
            }

            foreach ($ports as $port) {
                $currentBridge =
                    $selectable[
                        $port
                    ]['existing_bridge']
                    ?? null;

                /*
                 * Already belongs to requested bridge:
                 * no RouterOS write needed.
                 */
                if (
                    $currentBridge
                    === $bridgeName
                ) {
                    continue;
                }

                $this->readQuery(
                    (new Query(
                        '/interface/bridge/port/add'
                    ))
                        ->equal(
                            'interface',
                            $port
                        )
                        ->equal(
                            'bridge',
                            $bridgeName
                        )
                        ->equal(
                            'comment',
                            'MikroPanel Hotspot Wizard'
                        )
                );

                $addedPorts[] =
                    $port;
            }

            /*
             * Verify resulting RouterOS state.
             */
            $after =
                $this->hotspotSetupDiscovery(
                    $router
                );

            if (
                !(
                    $after['success']
                    ?? false
                )
            ) {
                throw new \RuntimeException(
                    'Bridge was applied but RouterOS verification failed.'
                );
            }

            $verified = [];

            foreach (
                $after[
                    'interfaces'
                ] ?? []
                as $interface
            ) {
                $name =
                    $interface['name']
                    ?? null;

                if (
                    $name
                    && in_array(
                        $name,
                        $ports,
                        true
                    )
                    && (
                        $interface[
                            'existing_bridge'
                        ] ?? null
                    ) === $bridgeName
                ) {
                    $verified[] =
                        $name;
                }
            }

            sort($verified);

            $expected = $ports;
            sort($expected);

            if ($verified !== $expected) {
                throw new \RuntimeException(
                    'RouterOS bridge verification did not match the selected ports.'
                );
            }

            return [
                'success' => true,

                'bridge' =>
                    $bridgeName,

                'mode' =>
                    $mode,

                'ports' =>
                    $ports,

                'added_ports' =>
                    $addedPorts,

                'reused_ports' =>
                    array_values(
                        array_diff(
                            $ports,
                            $addedPorts
                        )
                    ),
            ];

        } catch (Throwable $exception) {
            /*
             * Best effort rollback of only changes
             * introduced by this operation.
             */
            foreach (
                array_reverse(
                    $addedPorts
                )
                as $port
            ) {
                try {
                    $rows =
                        $this->readRowsSafe(
                            '/interface/bridge/port/print',
                            '.id,interface,bridge,comment',
                            [
                                'interface',
                                $port,
                            ]
                        );

                    foreach (
                        $rows['rows']
                            ?? []
                        as $row
                    ) {
                        if (
                            ($row['bridge']
                                ?? null)
                                !== $bridgeName
                            || !isset(
                                $row['.id']
                            )
                        ) {
                            continue;
                        }

                        $this->readQuery(
                            (new Query(
                                '/interface/bridge/port/remove'
                            ))
                                ->equal(
                                    '.id',
                                    $row['.id']
                                )
                        );
                    }

                } catch (Throwable) {
                    // Best effort only.
                }
            }

            if ($createdBridge) {
                try {
                    $rows =
                        $this->readRowsSafe(
                            '/interface/bridge/print',
                            '.id,name',
                            [
                                'name',
                                $bridgeName,
                            ]
                        );

                    foreach (
                        $rows['rows']
                            ?? []
                        as $row
                    ) {
                        if (
                            ($row['name']
                                ?? null)
                                !== $bridgeName
                            || !isset(
                                $row['.id']
                            )
                        ) {
                            continue;
                        }

                        $this->readQuery(
                            (new Query(
                                '/interface/bridge/remove'
                            ))
                                ->equal(
                                    '.id',
                                    $row['.id']
                                )
                        );
                    }

                } catch (Throwable) {
                    // Best effort only.
                }
            }

            throw new \RuntimeException(
                'Hotspot bridge setup failed. MikroPanel attempted to roll back only its new changes: '
                . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /*
     * HOTSPOT_GATEWAY_STEP3_V1
     *
     * Existing mode is verification-only.
     * New mode adds only one IP address to the
     * selected Hotspot bridge after overlap checks.
     */
    public function applyHotspotGateway(
        Router $router,
        string $mode,
        string $bridgeName,
        string $gatewayCidr
    ): array {
        $mode =
            strtolower(
                trim($mode)
            );

        if (
            !in_array(
                $mode,
                [
                    'existing',
                    'new',
                ],
                true
            )
        ) {
            throw new \RuntimeException(
                'Invalid gateway setup mode.'
            );
        }

        $bridgeName =
            trim($bridgeName);

        $gatewayCidr =
            trim($gatewayCidr);

        if (
            $bridgeName === ''
            || $gatewayCidr === ''
        ) {
            throw new \RuntimeException(
                'Bridge and gateway CIDR are required.'
            );
        }

        $before =
            $this->hotspotSetupDiscovery(
                $router
            );

        if (
            !(
                $before['success']
                ?? false
            )
        ) {
            throw new \RuntimeException(
                $before['message']
                ?? 'Unable to inspect MikroTik.'
            );
        }

        $bridgeExists = false;

        foreach (
            $before['bridges']
                ?? []
            as $bridge
        ) {
            if (
                ($bridge['name'] ?? null)
                === $bridgeName
            ) {
                $bridgeExists = true;
                break;
            }
        }

        if (!$bridgeExists) {
            throw new \RuntimeException(
                "Bridge {$bridgeName} was not found."
            );
        }

        $target =
            $this->ipv4CidrInfo(
                $gatewayCidr
            );

        /*
         * Existing Hotspot configuration:
         * verify exact RouterOS topology only.
         * No write.
         */
        if ($mode === 'existing') {
            foreach (
                $before[
                    'hotspot_topologies'
                ] ?? []
                as $topology
            ) {
                if (
                    ($topology[
                        'interface'
                    ] ?? null)
                        === $bridgeName
                    && ($topology[
                        'gateway_cidr'
                    ] ?? null)
                        === $gatewayCidr
                ) {
                    return [
                        'success' => true,

                        'mode' =>
                            'existing',

                        'bridge' =>
                            $bridgeName,

                        'gateway_cidr' =>
                            $gatewayCidr,

                        'gateway_ip' =>
                            $target[
                                'ip'
                            ],

                        'network_cidr' =>
                            $target[
                                'network_cidr'
                            ],

                        'prefix' =>
                            $target[
                                'prefix'
                            ],

                        'routeros_write' =>
                            false,

                        'topology' =>
                            $topology,
                    ];
                }
            }

            throw new \RuntimeException(
                'The selected existing Hotspot gateway could not be verified on this bridge.'
            );
        }

        /*
         * New gateway must not overlap an existing
         * subnet anywhere else on this router.
         */
        $exactExisting = false;

        foreach (
            $before[
                'interfaces'
            ] ?? []
            as $interface
        ) {
            $interfaceName =
                $interface['name']
                ?? null;

            foreach (
                $interface[
                    'ip_addresses'
                ] ?? []
                as $address
            ) {
                $existingCidr =
                    trim(
                        (string) (
                            $address[
                                'address'
                            ] ?? ''
                        )
                    );

                if ($existingCidr === '') {
                    continue;
                }

                try {
                    $existing =
                        $this->ipv4CidrInfo(
                            $existingCidr,
                            true
                        );
                } catch (Throwable) {
                    continue;
                }

                if (
                    $interfaceName
                        === $bridgeName
                    && $existingCidr
                        === $gatewayCidr
                ) {
                    $exactExisting = true;
                    continue;
                }

                $overlap =
                    max(
                        $target[
                            'network_long'
                        ],
                        $existing[
                            'network_long'
                        ]
                    )
                    <= min(
                        $target[
                            'broadcast_long'
                        ],
                        $existing[
                            'broadcast_long'
                        ]
                    );

                if ($overlap) {
                    throw new \RuntimeException(
                        "Gateway subnet {$target['network_cidr']} overlaps existing {$existingCidr} on {$interfaceName}."
                    );
                }
            }
        }

        if ($exactExisting) {
            return [
                'success' => true,

                'mode' =>
                    'new',

                'bridge' =>
                    $bridgeName,

                'gateway_cidr' =>
                    $gatewayCidr,

                'gateway_ip' =>
                    $target['ip'],

                'network_cidr' =>
                    $target[
                        'network_cidr'
                    ],

                'prefix' =>
                    $target['prefix'],

                'routeros_write' =>
                    false,

                'already_present' =>
                    true,
            ];
        }

        if (!$this->connect($router)) {
            throw new \RuntimeException(
                $this->lastError
                ?? 'Unable to connect to MikroTik API.'
            );
        }

        $comment =
            'MIKROPANEL:HOTSPOT:GATEWAY:ROUTER-'
            . $router->id;

        $created = false;

        try {
            $this->readQuery(
                (new Query(
                    '/ip/address/add'
                ))
                    ->equal(
                        'address',
                        $gatewayCidr
                    )
                    ->equal(
                        'interface',
                        $bridgeName
                    )
                    ->equal(
                        'comment',
                        $comment
                    )
            );

            $created = true;

            $after =
                $this->hotspotSetupDiscovery(
                    $router
                );

            $verified = false;

            foreach (
                $after[
                    'interfaces'
                ] ?? []
                as $interface
            ) {
                if (
                    ($interface['name']
                        ?? null)
                        !== $bridgeName
                ) {
                    continue;
                }

                foreach (
                    $interface[
                        'ip_addresses'
                    ] ?? []
                    as $address
                ) {
                    if (
                        ($address[
                            'address'
                        ] ?? null)
                        === $gatewayCidr
                    ) {
                        $verified = true;
                        break 2;
                    }
                }
            }

            if (!$verified) {
                throw new \RuntimeException(
                    'Gateway IP verification failed.'
                );
            }

            return [
                'success' => true,

                'mode' =>
                    'new',

                'bridge' =>
                    $bridgeName,

                'gateway_cidr' =>
                    $gatewayCidr,

                'gateway_ip' =>
                    $target['ip'],

                'network_cidr' =>
                    $target[
                        'network_cidr'
                    ],

                'prefix' =>
                    $target['prefix'],

                'broadcast' =>
                    $target[
                        'broadcast'
                    ],

                'routeros_write' =>
                    true,
            ];

        } catch (Throwable $exception) {
            if ($created) {
                try {
                    $rows =
                        $this->readRowsSafe(
                            '/ip/address/print',
                            '.id,address,interface,comment',
                            [
                                'interface',
                                $bridgeName,
                            ]
                        );

                    foreach (
                        $rows['rows']
                            ?? []
                        as $row
                    ) {
                        if (
                            ($row['address']
                                ?? null)
                                !== $gatewayCidr
                            || ($row['comment']
                                ?? null)
                                !== $comment
                            || !isset(
                                $row['.id']
                            )
                        ) {
                            continue;
                        }

                        $this->readQuery(
                            (new Query(
                                '/ip/address/remove'
                            ))
                                ->equal(
                                    '.id',
                                    $row['.id']
                                )
                        );
                    }

                } catch (Throwable) {
                    // Best effort rollback only.
                }
            }

            throw new \RuntimeException(
                'Hotspot gateway setup failed. MikroPanel attempted to roll back only its new IP address: '
                . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /*
     * HOTSPOT_DHCP_STEP4_V1
     *
     * Generates safe defaults from the gateway subnet.
     * Network, gateway and broadcast are excluded.
     */
    public function hotspotDhcpSuggestion(
        string $gatewayCidr,
        int $routerId
    ): array {
        $info =
            $this->ipv4CidrInfo(
                $gatewayCidr
            );

        $gatewayLong =
            $info['ip_long'];

        $firstUsable =
            $info['network_long']
            + 1;

        $lastUsable =
            $info['broadcast_long']
            - 1;

        $ranges = [];

        if (
            $firstUsable
            <= $gatewayLong - 1
        ) {
            $ranges[] =
                long2ip(
                    $firstUsable
                )
                . '-'
                . long2ip(
                    $gatewayLong - 1
                );
        }

        if (
            $gatewayLong + 1
            <= $lastUsable
        ) {
            $ranges[] =
                long2ip(
                    $gatewayLong + 1
                )
                . '-'
                . long2ip(
                    $lastUsable
                );
        }

        if ($ranges === []) {
            throw new \RuntimeException(
                'This subnet has no usable client IP after reserving the gateway.'
            );
        }

        return [
            'network_cidr' =>
                $info[
                    'network_cidr'
                ],

            'gateway_ip' =>
                $info['ip'],

            'pool_name' =>
                'mp-hs-pool-'
                . $routerId,

            'pool_ranges' =>
                implode(
                    ',',
                    $ranges
                ),

            'dhcp_server' =>
                'mp-hs-dhcp-'
                . $routerId,

            'lease_time' =>
                '30m',

            'dns_server' =>
                $info['ip'],
        ];
    }

    /*
     * Existing mode verifies only.
     *
     * New mode safely creates:
     * - IP pool
     * - DHCP server
     * - DHCP network
     *
     * Any existing unrelated object with the same
     * name/network but conflicting values blocks setup.
     */
    public function applyHotspotDhcp(
        Router $router,
        string $mode,
        string $bridgeName,
        string $gatewayCidr,
        string $poolName,
        string $poolRanges,
        string $dhcpServerName,
        string $leaseTime
    ): array {
        $mode =
            strtolower(
                trim($mode)
            );

        if (
            !in_array(
                $mode,
                [
                    'existing',
                    'new',
                ],
                true
            )
        ) {
            throw new \RuntimeException(
                'Invalid DHCP setup mode.'
            );
        }

        $bridgeName =
            trim($bridgeName);

        $gatewayCidr =
            trim($gatewayCidr);

        $poolName =
            trim($poolName);

        $poolRanges =
            trim($poolRanges);

        $dhcpServerName =
            trim($dhcpServerName);

        $leaseTime =
            trim($leaseTime);

        if (
            $bridgeName === ''
            || $gatewayCidr === ''
            || $poolName === ''
            || $poolRanges === ''
            || $dhcpServerName === ''
            || $leaseTime === ''
        ) {
            throw new \RuntimeException(
                'Bridge, gateway, pool and DHCP fields are required.'
            );
        }

        foreach (
            [
                'Pool name' =>
                    $poolName,

                'DHCP server name' =>
                    $dhcpServerName,
            ]
            as $label => $value
        ) {
            if (
                strlen($value) > 100
                || preg_match(
                    '/[\x00-\x1F\x7F]/',
                    $value
                )
            ) {
                throw new \RuntimeException(
                    "{$label} contains invalid characters."
                );
            }
        }

        if (
            !preg_match(
                '/^[0-9]+[smhdw](?:[0-9]+[smhdw])*$/i',
                $leaseTime
            )
        ) {
            throw new \RuntimeException(
                'DHCP lease time is invalid. Example: 30m, 1h or 1d.'
            );
        }

        $gateway =
            $this->ipv4CidrInfo(
                $gatewayCidr
            );

        $normalizedRanges =
            $this->validateHotspotPoolRanges(
                $poolRanges,
                $gateway
            );

        $before =
            $this->hotspotSetupDiscovery(
                $router
            );

        if (
            !(
                $before['success']
                ?? false
            )
        ) {
            throw new \RuntimeException(
                $before['message']
                ?? 'Unable to inspect MikroTik.'
            );
        }

        $bridgeExists = false;

        foreach (
            $before['bridges']
                ?? []
            as $bridge
        ) {
            if (
                ($bridge['name']
                    ?? null)
                    === $bridgeName
            ) {
                $bridgeExists = true;
                break;
            }
        }

        if (!$bridgeExists) {
            throw new \RuntimeException(
                "Bridge {$bridgeName} was not found."
            );
        }

        /*
         * Verify gateway still exists on this bridge.
         */
        $gatewayExists = false;

        foreach (
            $before['interfaces']
                ?? []
            as $interface
        ) {
            if (
                ($interface['name']
                    ?? null)
                    !== $bridgeName
            ) {
                continue;
            }

            foreach (
                $interface[
                    'ip_addresses'
                ] ?? []
                as $address
            ) {
                if (
                    ($address['address']
                        ?? null)
                        === $gatewayCidr
                ) {
                    $gatewayExists = true;
                    break 2;
                }
            }
        }

        if (!$gatewayExists) {
            throw new \RuntimeException(
                "Gateway {$gatewayCidr} is not configured on bridge {$bridgeName}."
            );
        }

        /*
         * EXISTING MODE = no RouterOS writes.
         */
        if ($mode === 'existing') {
            foreach (
                $before[
                    'hotspot_topologies'
                ] ?? []
                as $topology
            ) {
                if (
                    ($topology['interface']
                        ?? null)
                        !== $bridgeName
                    || ($topology[
                        'gateway_cidr'
                    ] ?? null)
                        !== $gatewayCidr
                    || ($topology[
                        'pool_name'
                    ] ?? null)
                        !== $poolName
                    || ($topology[
                        'pool_ranges'
                    ] ?? null)
                        !== $normalizedRanges
                    || ($topology[
                        'dhcp_server'
                    ] ?? null)
                        !== $dhcpServerName
                    || ($topology[
                        'network_cidr'
                    ] ?? null)
                        !== $gateway[
                            'network_cidr'
                        ]
                ) {
                    continue;
                }

                return [
                    'success' => true,

                    'mode' =>
                        'existing',

                    'bridge' =>
                        $bridgeName,

                    'gateway_cidr' =>
                        $gatewayCidr,

                    'network_cidr' =>
                        $gateway[
                            'network_cidr'
                        ],

                    'pool_name' =>
                        $poolName,

                    'pool_ranges' =>
                        $normalizedRanges,

                    'dhcp_server' =>
                        $dhcpServerName,

                    'lease_time' =>
                        $topology[
                            'dhcp_lease_time'
                        ]
                        ?? $leaseTime,

                    'routeros_write' =>
                        false,

                    'topology' =>
                        $topology,
                ];
            }

            throw new \RuntimeException(
                'Existing DHCP/Pool configuration could not be verified against the selected Hotspot gateway.'
            );
        }

        /*
         * NEW MODE conflict checks.
         */
        $poolExists = null;

        foreach (
            $before['ip_pools']
                ?? []
            as $pool
        ) {
            if (
                ($pool['name']
                    ?? null)
                    === $poolName
            ) {
                $poolExists =
                    $pool;
                break;
            }
        }

        if (
            $poolExists
            && trim(
                (string) (
                    $poolExists[
                        'ranges'
                    ] ?? ''
                )
            ) !== $normalizedRanges
        ) {
            throw new \RuntimeException(
                "IP pool {$poolName} already exists with different ranges."
            );
        }

        $dhcpExists = null;

        foreach (
            $before[
                'dhcp_servers'
            ] ?? []
            as $server
        ) {
            if (
                ($server['name']
                    ?? null)
                    === $dhcpServerName
            ) {
                $dhcpExists =
                    $server;
                break;
            }
        }

        if (
            $dhcpExists
            && (
                ($dhcpExists[
                    'interface'
                ] ?? null)
                    !== $bridgeName
                || ($dhcpExists[
                    'address_pool'
                ] ?? null)
                    !== $poolName
            )
        ) {
            throw new \RuntimeException(
                "DHCP server {$dhcpServerName} already exists with different settings."
            );
        }

        $networkExists = null;

        foreach (
            $before[
                'dhcp_networks'
            ] ?? []
            as $network
        ) {
            if (
                ($network['address']
                    ?? null)
                    === $gateway[
                        'network_cidr'
                    ]
            ) {
                $networkExists =
                    $network;
                break;
            }
        }

        if (
            $networkExists
            && trim(
                (string) (
                    $networkExists[
                        'gateway'
                    ] ?? ''
                )
            ) !== $gateway['ip']
        ) {
            throw new \RuntimeException(
                "DHCP network {$gateway['network_cidr']} already exists with a different gateway."
            );
        }

        if (!$this->connect($router)) {
            throw new \RuntimeException(
                $this->lastError
                ?? 'Unable to connect to MikroTik API.'
            );
        }

        $tag =
            'MIKROPANEL:HOTSPOT:DHCP:ROUTER-'
            . $router->id;

        $createdPool = false;
        $createdDhcp = false;
        $createdNetwork = false;

        try {
            if (!$poolExists) {
                $this->readQuery(
                    (new Query(
                        '/ip/pool/add'
                    ))
                        ->equal(
                            'name',
                            $poolName
                        )
                        ->equal(
                            'ranges',
                            $normalizedRanges
                        )
                        ->equal(
                            'comment',
                            $tag
                        )
                );

                $createdPool = true;
            }

            if (!$dhcpExists) {
                $this->readQuery(
                    (new Query(
                        '/ip/dhcp-server/add'
                    ))
                        ->equal(
                            'name',
                            $dhcpServerName
                        )
                        ->equal(
                            'interface',
                            $bridgeName
                        )
                        ->equal(
                            'address-pool',
                            $poolName
                        )
                        ->equal(
                            'lease-time',
                            $leaseTime
                        )
                        ->equal(
                            'disabled',
                            'false'
                        )
                        ->equal(
                            'comment',
                            $tag
                        )
                );

                $createdDhcp = true;
            }

            if (!$networkExists) {
                $this->readQuery(
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
                            $tag
                        )
                );

                $createdNetwork = true;
            }

            $after =
                $this->hotspotSetupDiscovery(
                    $router
                );

            if (
                !(
                    $after['success']
                    ?? false
                )
            ) {
                throw new \RuntimeException(
                    'DHCP configuration was applied but verification failed.'
                );
            }

            $verifiedPool = false;
            $verifiedDhcp = false;
            $verifiedNetwork = false;

            foreach (
                $after['ip_pools']
                    ?? []
                as $pool
            ) {
                if (
                    ($pool['name']
                        ?? null)
                        === $poolName
                    && trim(
                        (string) (
                            $pool['ranges']
                            ?? ''
                        )
                    ) === $normalizedRanges
                ) {
                    $verifiedPool = true;
                    break;
                }
            }

            foreach (
                $after[
                    'dhcp_servers'
                ] ?? []
                as $server
            ) {
                if (
                    ($server['name']
                        ?? null)
                        === $dhcpServerName
                    && ($server[
                        'interface'
                    ] ?? null)
                        === $bridgeName
                    && ($server[
                        'address_pool'
                    ] ?? null)
                        === $poolName
                    && !(
                        $server[
                            'disabled'
                        ] ?? true
                    )
                ) {
                    $verifiedDhcp = true;
                    break;
                }
            }

            foreach (
                $after[
                    'dhcp_networks'
                ] ?? []
                as $network
            ) {
                if (
                    ($network['address']
                        ?? null)
                        === $gateway[
                            'network_cidr'
                        ]
                    && trim(
                        (string) (
                            $network[
                                'gateway'
                            ] ?? ''
                        )
                    ) === $gateway['ip']
                ) {
                    $verifiedNetwork = true;
                    break;
                }
            }

            if (
                !$verifiedPool
                || !$verifiedDhcp
                || !$verifiedNetwork
            ) {
                throw new \RuntimeException(
                    'RouterOS DHCP verification did not match the requested configuration.'
                );
            }

            return [
                'success' => true,

                'mode' =>
                    'new',

                'bridge' =>
                    $bridgeName,

                'gateway_cidr' =>
                    $gatewayCidr,

                'network_cidr' =>
                    $gateway[
                        'network_cidr'
                    ],

                'pool_name' =>
                    $poolName,

                'pool_ranges' =>
                    $normalizedRanges,

                'dhcp_server' =>
                    $dhcpServerName,

                'lease_time' =>
                    $leaseTime,

                'routeros_write' =>
                    (
                        $createdPool
                        || $createdDhcp
                        || $createdNetwork
                    ),
            ];

        } catch (Throwable $exception) {
            /*
             * Reverse only objects created by
             * this operation.
             */
            if ($createdNetwork) {
                $this->removeRouterOsRowsBestEffort(
                    '/ip/dhcp-server/network/print',
                    '/ip/dhcp-server/network/remove',
                    'address',
                    $gateway[
                        'network_cidr'
                    ],
                    $tag
                );
            }

            if ($createdDhcp) {
                $this->removeRouterOsRowsBestEffort(
                    '/ip/dhcp-server/print',
                    '/ip/dhcp-server/remove',
                    'name',
                    $dhcpServerName,
                    $tag
                );
            }

            if ($createdPool) {
                $this->removeRouterOsRowsBestEffort(
                    '/ip/pool/print',
                    '/ip/pool/remove',
                    'name',
                    $poolName,
                    $tag
                );
            }

            throw new \RuntimeException(
                'Hotspot DHCP setup failed. MikroPanel attempted to roll back only its new DHCP objects: '
                . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /*
     * Backward compatible health method.
     */
    public function inspect(
        array|Router $router
    ): array {
        $result =
            $this->telemetry($router);

        unset(
            $result['leases'],
            $result['queues']
        );

        return $result;
    }

    protected function staticMetadata(
        array|Router $router
    ): array {
        $cacheKey =
            $this->clientKey
            ?? null;

        if (
            $cacheKey !== null
            && isset(
                self::$staticMetadataCache[
                    $cacheKey
                ]
            )
        ) {
            $cached =
                self::$staticMetadataCache[
                    $cacheKey
                ];

            if (
                isset(
                    $cached['refreshed_at']
                )
                && (
                    time()
                    - (int)
                        $cached['refreshed_at']
                ) < 3600
            ) {
                return array_merge(
                    $cached['data'],
                    [
                        'cached' => true,
                    ]
                );
            }

            unset(
                self::$staticMetadataCache[
                    $cacheKey
                ]
            );
        }

        $identityRead =
            $this->readRowsSafe(
                '/system/identity/print',
                'name'
            );

        $routerboardRead =
            $this->readRowsSafe(
                '/system/routerboard/print',
                implode(',', [
                    'model',
                    'factory-firmware',
                    'current-firmware',
                    'upgrade-firmware',
                ])
            );

        $identity =
            $identityRead['rows'][0]
            ?? [];

        $routerboard =
            $routerboardRead['rows'][0]
            ?? [];

        $data = [
            'identity' =>
                $identity['name']
                ?? (
                    $router instanceof Router
                        ? $router->identity
                        : null
                ),

            'board_name' =>
                $routerboard['model']
                ?? (
                    $router instanceof Router
                        ? $router->board_name
                        : null
                ),

            'factory_firmware' =>
                $routerboard[
                    'factory-firmware'
                ]
                ?? null,

            'current_firmware' =>
                $routerboard[
                    'current-firmware'
                ]
                ?? null,

            'upgrade_firmware' =>
                $routerboard[
                    'upgrade-firmware'
                ]
                ?? null,
        ];

        /*
         * Only cache a completely successful static
         * metadata read. Partial errors are retried
         * on the next cycle.
         */
        if (
            $cacheKey !== null
            && (
                $identityRead['success']
                ?? false
            )
            && (
                $routerboardRead['success']
                ?? false
            )
        ) {
            self::$staticMetadataCache[
                $cacheKey
            ] = [
                'refreshed_at' =>
                    time(),

                'data' =>
                    $data,
            ];
        }

        return array_merge(
            $data,
            [
                'cached' => false,
            ]
        );
    }

    /*
     * IPv4 CIDR parser used by the Hotspot wizard.
     */
    protected function ipv4CidrInfo(
        string $cidr,
        bool $allowHostBoundary = false
    ): array {
        $cidr =
            trim($cidr);

        if (
            !preg_match(
                '/^([^\/]+)\/(\d{1,2})$/',
                $cidr,
                $matches
            )
        ) {
            throw new \RuntimeException(
                'Gateway must be entered as IPv4/CIDR, for example 10.20.0.1/21.'
            );
        }

        $ip =
            trim(
                $matches[1]
            );

        $prefix =
            (int)
            $matches[2];

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
                'Gateway IPv4/CIDR is invalid. Prefix must be /1 through /30.'
            );
        }

        $ipLong =
            ip2long($ip);

        if ($ipLong === false) {
            throw new \RuntimeException(
                'Gateway IPv4 address is invalid.'
            );
        }

        $ipLong =
            $ipLong & 0xFFFFFFFF;

        $mask =
            (
                0xFFFFFFFF
                << (32 - $prefix)
            ) & 0xFFFFFFFF;

        $network =
            $ipLong
            & $mask;

        $broadcast =
            $network
            | (
                (~$mask)
                & 0xFFFFFFFF
            );

        if (
            !$allowHostBoundary
            && (
                $ipLong === $network
                || $ipLong === $broadcast
            )
        ) {
            throw new \RuntimeException(
                'Gateway IP cannot be the subnet network or broadcast address.'
            );
        }

        return [
            'ip' =>
                $ip,

            'prefix' =>
                $prefix,

            'ip_long' =>
                $ipLong,

            'network_long' =>
                $network,

            'broadcast_long' =>
                $broadcast,

            'network' =>
                long2ip(
                    $network
                ),

            'broadcast' =>
                long2ip(
                    $broadcast
                ),

            'network_cidr' =>
                long2ip(
                    $network
                )
                . '/'
                . $prefix,
        ];
    }

    /*
     * Validate MikroTik IP pool ranges against the
     * selected gateway subnet.
     */
    protected function validateHotspotPoolRanges(
        string $ranges,
        array $gateway
    ): string {
        $segments =
            array_values(
                array_filter(
                    array_map(
                        'trim',
                        explode(
                            ',',
                            $ranges
                        )
                    ),
                    fn ($value) =>
                        $value !== ''
                )
            );

        if ($segments === []) {
            throw new \RuntimeException(
                'Client IP pool is empty.'
            );
        }

        $normalized = [];
        $used = [];

        foreach ($segments as $segment) {
            if (
                str_contains(
                    $segment,
                    '-'
                )
            ) {
                [
                    $start,
                    $end,
                ] = array_map(
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
                throw new \RuntimeException(
                    "Invalid pool range: {$segment}"
                );
            }

            $startLong =
                ip2long(
                    $start
                );

            $endLong =
                ip2long(
                    $end
                );

            $startLong &=
                0xFFFFFFFF;

            $endLong &=
                0xFFFFFFFF;

            if (
                $startLong
                > $endLong
            ) {
                throw new \RuntimeException(
                    "Pool range starts after it ends: {$segment}"
                );
            }

            if (
                $startLong
                    <= $gateway[
                        'network_long'
                    ]
                || $endLong
                    >= $gateway[
                        'broadcast_long'
                    ]
            ) {
                throw new \RuntimeException(
                    "Pool range {$segment} is outside the usable hosts of {$gateway['network_cidr']}."
                );
            }

            if (
                $gateway[
                    'ip_long'
                ] >= $startLong
                && $gateway[
                    'ip_long'
                ] <= $endLong
            ) {
                throw new \RuntimeException(
                    "Pool range {$segment} contains the gateway {$gateway['ip']}."
                );
            }

            foreach ($used as $prior) {
                $overlap =
                    max(
                        $startLong,
                        $prior[0]
                    )
                    <= min(
                        $endLong,
                        $prior[1]
                    );

                if ($overlap) {
                    throw new \RuntimeException(
                        'Client IP pool ranges overlap each other.'
                    );
                }
            }

            $used[] = [
                $startLong,
                $endLong,
            ];

            $normalized[] =
                $start === $end
                    ? $start
                    : $start
                        . '-'
                        . $end;
        }

        return implode(
            ',',
            $normalized
        );
    }

    /*
     * Best-effort rollback helper for only
     * MikroPanel-tagged rows.
     */
    protected function removeRouterOsRowsBestEffort(
        string $printPath,
        string $removePath,
        string $field,
        string $value,
        string $comment
    ): void {
        try {
            $rows =
                $this->readRowsSafe(
                    $printPath,
                    '.id,'
                    . $field
                    . ',comment',
                    [
                        $field,
                        $value,
                    ]
                );

            foreach (
                $rows['rows'] ?? []
                as $row
            ) {
                if (
                    ($row[$field]
                        ?? null)
                        !== $value
                    || ($row['comment']
                        ?? null)
                        !== $comment
                    || !isset(
                        $row['.id']
                    )
                ) {
                    continue;
                }

                $this->readQuery(
                    (new Query(
                        $removePath
                    ))
                        ->equal(
                            '.id',
                            $row['.id']
                        )
                );
            }

        } catch (Throwable) {
            // Best effort only.
        }
    }

    protected function queryFirst(
        string $path,
        ?string $properties = null
    ): array {
        $query =
            new Query($path);

        if ($properties) {
            $query->equal(
                '.proplist',
                $properties
            );
        }

        $result =
            $this->readQuery($query);

        return $result[0] ?? [];
    }

    protected function readRowsSafe(
        string $path,
        string $properties,
        ?array $filter = null
    ): array {
        try {
            $query =
                (new Query($path))
                    ->equal(
                        '.proplist',
                        $properties
                    );

            if (
                $filter !== null
                && count($filter) === 2
            ) {
                $query->where(
                    $filter[0],
                    $filter[1]
                );
            }

            return [
                'success' => true,

                'rows' =>
                    $this->readQuery(
                        $query
                    ),

                'error' => null,
            ];

        } catch (Throwable $exception) {
            return [
                'success' => false,
                'rows' => [],
                'error' =>
                    $exception->getMessage(),
            ];
        }
    }

    protected function readQuery(
        Query $query
    ): array {
        $lastException = null;

        for (
            $attempt = 0;
            $attempt < 2;
            $attempt++
        ) {
            try {
                if (!$this->client) {
                    if (
                        $this->activeRouter === null
                        || !$this->connect(
                            $this->activeRouter
                        )
                    ) {
                        throw new \RuntimeException(
                            $this->lastError
                            ?? 'MikroTik API connection is not initialized.'
                        );
                    }
                }

                $this->queryCount++;

                return $this->client
                    ->query($query)
                    ->read();

            } catch (Throwable $exception) {
                $lastException =
                    $exception;

                $this->lastError =
                    $exception->getMessage();

                $this->forgetCurrentClient();

                if (
                    $attempt === 0
                    && $this->activeRouter
                        !== null
                ) {
                    $this->connect(
                        $this->activeRouter
                    );

                    continue;
                }
            }
        }

        throw $lastException
            ?? new \RuntimeException(
                'RouterOS query failed.'
            );
    }

    protected function socketAlive(
        Client $client
    ): bool {
        try {
            $socket =
                $client->getSocket();

            return is_resource($socket)
                && !feof($socket);

        } catch (Throwable) {
            return false;
        }
    }

    protected function forgetCurrentClient(): void
    {
        if ($this->clientKey !== null) {
            unset(
                self::$clientPool[
                    $this->clientKey
                ]
            );
        }

        $this->client = null;
        $this->clientKey = null;
        $this->connectionReused = false;
    }

    protected function credentials(
        array|Router $router
    ): array {
        if ($router instanceof Router) {
            return [
                'host' =>
                    $router->host,

                'username' =>
                    $router->username,

                'password' =>
                    $router->password,

                'api_port' =>
                    (int) (
                        $router->api_port
                        ?? 8728
                    ),

                'use_ssl' =>
                    (bool) (
                        $router->use_ssl
                        ?? false
                    ),
            ];
        }

        return [
            'host' =>
                $router['host'],

            'username' =>
                $router['username'],

            'password' =>
                $router['password'],

            'api_port' =>
                (int) (
                    $router['api_port']
                    ?? 8728
                ),

            'use_ssl' =>
                (bool) (
                    $router['use_ssl']
                    ?? false
                ),
        ];
    }
}
