<?php

namespace App\Services;

use App\Models\Client;
use RuntimeException;
use RouterOS\Client as RouterClient;
use RouterOS\Query;

class DhcpLeaseService
{
    protected function api(
        Client $client
    ): RouterClient {
        $router = $client->router;

        if (!$router) {
            throw new RuntimeException(
                'Router is missing for DHCP operation.'
            );
        }

        return new RouterClient([
            'host' => $router->host,
            'user' => $router->username,
            'pass' => $router->password,
            'port' =>
                (int) (
                    $router->api_port
                    ?? 8728
                ),
            'ssl' =>
                (bool) $router->use_ssl,
            'timeout' => 10,
        ]);
    }

    public function create(
        Client $client
    ): string {
        $api = $this->api($client);

        $server = trim(
            (string) (
                $client
                    ->router
                    ?->dhcp_server
                ?? ''
            )
        );

        $existing = $this->findId(
            $api,
            $client
        );

        if ($existing) {
            return $existing;
        }

        $query = (new Query(
            '/ip/dhcp-server/lease/add'
        ))
            ->equal(
                'address',
                $client->ip_address
            )
            ->equal(
                'mac-address',
                $client->mac_address
            )
            ->equal(
                'comment',
                $client->client_code
            )
            ->equal(
                'disabled',
                'false'
            );

        if ($server !== '') {
            $query = $query->equal(
                'server',
                $server
            );
        }

        $api->query(
            $query
        )->read();

        $id = $this->findId(
            $api,
            $client
        );

        if (!$id) {
            throw new RuntimeException(
                'DHCP lease was created but its MikroTik ID could not be verified.'
            );
        }

        return $id;
    }

    public function update(
        Client $client
    ): void {
        $api = $this->api($client);

        $server = trim(
            (string) (
                $client
                    ->router
                    ?->dhcp_server
                ?? ''
            )
        );

        $id = $this->resolveId(
            $api,
            $client
        );

        if (!$id) {
            throw new RuntimeException(
                'DHCP lease could not be found on MikroTik.'
            );
        }

        $query = (new Query(
            '/ip/dhcp-server/lease/set'
        ))
            ->equal('.id', $id)
            ->equal(
                'address',
                $client->ip_address
            )
            ->equal(
                'mac-address',
                $client->mac_address
            )
            ->equal(
                'comment',
                $client->client_code
            );

        if ($server !== '') {
            $query = $query->equal(
                'server',
                $server
            );
        }

        $api->query(
            $query
        )->read();
    }

    public function disable(
        Client $client
    ): void {
        $api = $this->api($client);

        $id = $this->resolveId(
            $api,
            $client
        );

        if (!$id) {
            return;
        }

        $api->query(
            (new Query(
                '/ip/dhcp-server/lease/set'
            ))
                ->equal('.id', $id)
                ->equal(
                    'disabled',
                    'yes'
                )
        )->read();
    }

    public function enable(
        Client $client
    ): void {
        $api = $this->api($client);

        $id = $this->resolveId(
            $api,
            $client
        );

        if (!$id) {
            throw new RuntimeException(
                'DHCP lease is missing on MikroTik.'
            );
        }

        $api->query(
            (new Query(
                '/ip/dhcp-server/lease/set'
            ))
                ->equal('.id', $id)
                ->equal(
                    'disabled',
                    'no'
                )
        )->read();
    }

    public function remove(
        Client $client
    ): void {
        $api = $this->api($client);

        /*
         * Search the Router instead of blindly
         * trusting a possibly stale saved .id.
         */
        $id = $this->findId(
            $api,
            $client
        );

        if (!$id) {
            return;
        }

        $api->query(
            (new Query(
                '/ip/dhcp-server/lease/remove'
            ))
                ->equal('.id', $id)
        )->read();
    }

    private function resolveId(
        RouterClient $api,
        Client $client
    ): ?string {
        if ($client->mikrotik_lease_id) {
            return $client
                ->mikrotik_lease_id;
        }

        return $this->findId(
            $api,
            $client
        );
    }

    private function findId(
        RouterClient $api,
        Client $client
    ): ?string {
        $rows = $api->query(
            (new Query(
                '/ip/dhcp-server/lease/print'
            ))
                ->where(
                    'comment',
                    $client->client_code
                )
        )->read();

        foreach ($rows as $row) {
            if (
                ($row['comment'] ?? null)
                    === $client->client_code
            ) {
                return $row['.id']
                    ?? null;
            }
        }

        return null;
    }
}
