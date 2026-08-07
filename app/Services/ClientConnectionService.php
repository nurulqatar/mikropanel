<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Log;
use RouterOS\Client as RouterClient;
use RouterOS\Query;
use Throwable;

class ClientConnectionService
{
    public function syncAll(): array
    {
        $stats = [
            'online' => 0,
            'offline' => 0,
            'updated' => 0,
            'failed' => 0,
        ];

        $clients = Client::query()
            ->with('router')
            ->get()
            ->groupBy('router_id');

        foreach ($clients as $routerClients) {
            $router = $routerClients
                ->first()
                ?->router;

            if (!$router || !$router->enabled) {
                foreach ($routerClients as $client) {
                    $this->saveStatus(
                        $client,
                        false,
                        $stats
                    );
                }

                continue;
            }

            try {
                $api = new RouterClient([
                    'host' => $router->host,
                    'user' => $router->username,
                    'pass' => $router->password,
                    'port' => (int) (
                        $router->api_port ?? 8728
                    ),
                    'ssl' => (bool) $router->use_ssl,
                    'timeout' => 10,
                ]);

                $leases = $api->query(
                    new Query(
                        '/ip/dhcp-server/lease/print'
                    )
                )->read();

                $leaseStatusByMac = [];

                foreach ($leases as $lease) {
                    $mac = $this->normalizeMac(
                        $lease['mac-address']
                            ?? $lease[
                                'active-mac-address'
                            ]
                            ?? null
                    );

                    if (!$mac) {
                        continue;
                    }

                    $leaseStatusByMac[$mac] =
                        strtolower(
                            (string) (
                                $lease['status']
                                ?? 'waiting'
                            )
                        );
                }

                foreach ($routerClients as $client) {
                    $mac = $this->normalizeMac(
                        $client->mac_address
                    );

                    $status = $mac
                        ? (
                            $leaseStatusByMac[$mac]
                            ?? 'waiting'
                        )
                        : 'waiting';

                    $online =
                        $client->enabled
                        && $status === 'bound';

                    $this->saveStatus(
                        $client,
                        $online,
                        $stats
                    );
                }
            } catch (Throwable $exception) {
                $stats['failed'] +=
                    $routerClients->count();

                Log::error(
                    'Client connection sync failed.',
                    [
                        'router_id' => $router->id,
                        'message' =>
                            $exception->getMessage(),
                    ]
                );
            }
        }

        return $stats;
    }

    private function saveStatus(
        Client $client,
        bool $online,
        array &$stats
    ): void {
        if ((bool) $client->connected !== $online) {
            $client->updateQuietly([
                'connected' => $online,
            ]);

            $stats['updated']++;
        }

        if ($online) {
            $stats['online']++;
        } else {
            $stats['offline']++;
        }
    }

    private function normalizeMac(
        mixed $mac
    ): ?string {
        $normalized = strtoupper(
            preg_replace(
                '/[^A-Fa-f0-9]/',
                '',
                (string) $mac
            )
        );

        return strlen($normalized) === 12
            ? $normalized
            : null;
    }
}
