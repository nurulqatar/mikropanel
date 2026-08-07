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

    protected ?string $lastError = null;

    public function connect(array|Router $router): bool
    {
        try {
            $credentials = $this->credentials($router);

            $config = new Config([
                'host' => $credentials['host'],
                'user' => $credentials['username'],
                'pass' => $credentials['password'],
                'port' => $credentials['api_port'],
                'ssl' => $credentials['use_ssl'],
                'timeout' => 6,
            ]);

            $this->client = new Client($config);
            $this->lastError = null;

            return true;
        } catch (Throwable $exception) {
            $this->client = null;
            $this->lastError = $exception->getMessage();

            return false;
        }
    }

    /**
     * Existing code compatibility.
     */
    public function test(): array
    {
        try {
            if (!$this->client) {
                return [
                    'success' => false,
                    'message' => 'MikroTik connection is not initialized.',
                ];
            }

            $query = new Query('/system/resource/print');

            $result = $this->client
                ->query($query)
                ->read();

            return [
                'success' => true,
                'data' => $result[0] ?? [],
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
            ];
        }
    }

    /**
     * Complete live RouterOS status and sync information.
     */
    public function inspect(array|Router $router): array
    {
        $startedAt = microtime(true);

        if (!$this->connect($router)) {
            return [
                'success' => false,
                'message' => $this->lastError
                    ?? 'Unable to connect to MikroTik API.',
                'latency_ms' => null,
                'checked_at' => now()->toISOString(),
            ];
        }

        try {
            $resource = $this->queryFirst(
                '/system/resource/print'
            );

            if (empty($resource)) {
                throw new \RuntimeException(
                    'MikroTik returned no system resource information.'
                );
            }

            $identity = $this->queryFirst(
                '/system/identity/print'
            );

            $routerboard = $this->queryFirst(
                '/system/routerboard/print'
            );

            $dhcpLeases = $this->safeQuery(
                '/ip/dhcp-server/lease/print'
            );

            $arpEntries = $this->safeQuery(
                '/ip/arp/print'
            );

            $simpleQueues = $this->safeQuery(
                '/queue/simple/print'
            );

            $latencyMs = (int) round(
                (microtime(true) - $startedAt) * 1000
            );

            return [
                'success' => true,
                'message' => 'MikroTik API connected successfully.',
                'latency_ms' => $latencyMs,
                'checked_at' => now()->toISOString(),

                'identity' => $identity['name'] ?? null,

                'version' => $resource['version'] ?? null,

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

                'cpu_load' => isset($resource['cpu-load'])
                    ? (int) $resource['cpu-load']
                    : null,

                'cpu_count' => isset($resource['cpu-count'])
                    ? (int) $resource['cpu-count']
                    : null,

                'free_memory' => isset($resource['free-memory'])
                    ? (int) $resource['free-memory']
                    : null,

                'total_memory' => isset($resource['total-memory'])
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
                    count($dhcpLeases),

                'arp_entries_count' =>
                    count($arpEntries),

                'simple_queues_count' =>
                    count($simpleQueues),
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'message' => $exception->getMessage(),
                'latency_ms' => null,
                'checked_at' => now()->toISOString(),
            ];
        }
    }

    protected function queryFirst(string $path): array
    {
        if (!$this->client) {
            throw new \RuntimeException(
                'MikroTik API connection is not initialized.'
            );
        }

        $query = new Query($path);

        $result = $this->client
            ->query($query)
            ->read();

        return $result[0] ?? [];
    }

    protected function safeQuery(string $path): array
    {
        try {
            if (!$this->client) {
                return [];
            }

            $query = new Query($path);

            return $this->client
                ->query($query)
                ->read();
        } catch (Throwable) {
            return [];
        }
    }

    protected function credentials(
        array|Router $router
    ): array {
        if ($router instanceof Router) {
            return [
                'host' => $router->host,
                'username' => $router->username,
                'password' => $router->password,
                'api_port' => (int) (
                    $router->api_port ?? 8728
                ),
                'use_ssl' => (bool) (
                    $router->use_ssl ?? false
                ),
            ];
        }

        return [
            'host' => $router['host'],
            'username' => $router['username'],
            'password' => $router['password'],
            'api_port' => (int) (
                $router['api_port'] ?? 8728
            ),
            'use_ssl' => (bool) (
                $router['use_ssl'] ?? false
            ),
        ];
    }
}
