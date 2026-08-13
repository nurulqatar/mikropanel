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

    /**
     * Persistent per-worker RouterOS client pool.
     *
     * Laravel queue workers are long-lived processes, so these
     * connections may be reused across background jobs.
     */
    protected static array $clientPool = [];

    public function connect(array|Router $router): bool
    {
        $this->activeRouter = $router;

        try {
            $credentials = $this->credentials($router);

            $key = hash(
                'sha256',
                implode('|', [
                    $credentials['host'],
                    $credentials['api_port'],
                    $credentials['username'],
                    $credentials['use_ssl'] ? '1' : '0',
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

                return true;
            }

            unset(self::$clientPool[$key]);

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

            $client = new Client($config);

            self::$clientPool[$key] = $client;

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

            $result = $this->queryFirst(
                '/system/resource/print',
                'version,uptime,cpu-load'
            );

            return [
                'success' => true,
                'data' => $result,
            ];

        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' =>
                    $exception->getMessage(),
            ];
        }
    }

    /**
     * Optimized RouterOS health snapshot.
     *
     * api_latency_ms:
     *   lightweight RouterOS query response time.
     *
     * sync_duration_ms:
     *   total snapshot collection duration.
     */
    public function inspect(
        array|Router $router
    ): array {
        $startedAt = microtime(true);

        if (!$this->connect($router)) {
            return [
                'success' => false,
                'message' =>
                    $this->lastError
                    ?? 'Unable to connect to MikroTik API.',

                'latency_ms' => null,
                'sync_duration_ms' =>
                    (int) round(
                        (microtime(true) - $startedAt)
                        * 1000
                    ),

                'checked_at' =>
                    now()->toISOString(),
            ];
        }

        try {
            /*
             * First query is deliberately lightweight.
             * Its duration becomes API latency.
             */
            $queryStartedAt = microtime(true);

            $resource = $this->queryFirst(
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

            $apiLatencyMs = (int) round(
                (microtime(true) - $queryStartedAt)
                * 1000
            );

            if (empty($resource)) {
                throw new \RuntimeException(
                    'MikroTik returned no system resource information.'
                );
            }

            $identity = $this->queryFirst(
                '/system/identity/print',
                'name'
            );

            $routerboard = $this->queryFirst(
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
                && !empty($router->dhcp_server)
            ) {
                $dhcpServer =
                    $router->dhcp_server;
            }

            /*
             * For counts we only request .id.
             * This avoids downloading every property
             * from every DHCP/ARP/Queue entry.
             */
            $dhcpLeasesCount =
                $this->safeCount(
                    '/ip/dhcp-server/lease/print',
                    $dhcpServer
                        ? ['server', $dhcpServer]
                        : null
                );

            $arpEntriesCount =
                $this->safeCount(
                    '/ip/arp/print'
                );

            $simpleQueuesCount =
                $this->safeCount(
                    '/queue/simple/print'
                );

            $syncDurationMs = (int) round(
                (microtime(true) - $startedAt)
                * 1000
            );

            return [
                'success' => true,

                'message' =>
                    'MikroTik API connected successfully.',

                'latency_ms' =>
                    $apiLatencyMs,

                'sync_duration_ms' =>
                    $syncDurationMs,

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
                    $resource['architecture-name']
                    ?? null,

                'platform' =>
                    $resource['platform']
                    ?? null,

                'uptime' =>
                    $resource['uptime']
                    ?? null,

                'cpu_load' =>
                    isset($resource['cpu-load'])
                        ? (int) $resource['cpu-load']
                        : null,

                'cpu_count' =>
                    isset($resource['cpu-count'])
                        ? (int) $resource['cpu-count']
                        : null,

                'free_memory' =>
                    isset($resource['free-memory'])
                        ? (int) $resource['free-memory']
                        : null,

                'total_memory' =>
                    isset($resource['total-memory'])
                        ? (int) $resource['total-memory']
                        : null,

                'factory_firmware' =>
                    $routerboard['factory-firmware']
                    ?? null,

                'current_firmware' =>
                    $routerboard['current-firmware']
                    ?? null,

                'upgrade_firmware' =>
                    $routerboard['upgrade-firmware']
                    ?? null,

                'dhcp_leases_count' =>
                    $dhcpLeasesCount,

                'arp_entries_count' =>
                    $arpEntriesCount,

                'simple_queues_count' =>
                    $simpleQueuesCount,
            ];

        } catch (Throwable $exception) {
            return [
                'success' => false,

                'message' =>
                    $exception->getMessage(),

                'latency_ms' => null,

                'sync_duration_ms' =>
                    (int) round(
                        (microtime(true) - $startedAt)
                        * 1000
                    ),

                'checked_at' =>
                    now()->toISOString(),
            ];
        }
    }

    protected function queryFirst(
        string $path,
        ?string $properties = null
    ): array {
        $query = new Query($path);

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

    protected function safeCount(
        string $path,
        ?array $filter = null
    ): int {
        try {
            $query =
                (new Query($path))
                    ->equal(
                        '.proplist',
                        '.id'
                    );

            if (
                $filter
                && count($filter) === 2
            ) {
                $query->where(
                    $filter[0],
                    $filter[1]
                );
            }

            return count(
                $this->readQuery($query)
            );

        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * Read a query and reconnect one time if a
     * persistent socket became stale.
     */
    protected function readQuery(
        Query $query
    ): array {
        $lastException = null;

        for ($attempt = 0; $attempt < 2; $attempt++) {
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

                return $this->client
                    ->query($query)
                    ->read();

            } catch (Throwable $exception) {
                $lastException = $exception;

                $this->lastError =
                    $exception->getMessage();

                $this->forgetCurrentClient();

                if (
                    $attempt === 0
                    && $this->activeRouter !== null
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
            $socket = $client->getSocket();

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
