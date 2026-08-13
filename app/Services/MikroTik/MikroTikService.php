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

            $identity =
                $this->queryFirst(
                    '/system/identity/print',
                    'name'
                );

            $routerboard =
                $this->queryFirst(
                    '/system/routerboard/print',
                    implode(',', [
                        'model',
                        'factory-firmware',
                        'current-firmware',
                        'upgrade-firmware',
                    ])
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

                'checked_at' =>
                    now()->toISOString(),

                'identity' =>
                    $identity['name']
                    ?? null,

                'version' =>
                    $resource['version']
                    ?? null,

                'board_name' =>
                    $resource['board-name']
                    ?? $routerboard['model']
                    ?? null,

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
