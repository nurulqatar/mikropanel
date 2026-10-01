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
