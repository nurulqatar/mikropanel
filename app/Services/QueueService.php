<?php

namespace App\Services;

use App\Models\Client;
use RuntimeException;
use RouterOS\Client as RouterClient;
use RouterOS\Query;

class QueueService
{
    protected function api(
        Client $client
    ): RouterClient {
        $router = $client->router;

        if (!$router) {
            throw new RuntimeException(
                'Router is missing for Queue operation.'
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
        $package = $client->package;

        if (!$package) {
            throw new RuntimeException(
                'Package is missing for Queue operation.'
            );
        }

        $api = $this->api($client);

        $existing = $this->findId(
            $api,
            $client
        );

        if ($existing) {
            return $existing;
        }

        $maxLimit =
            "{$package->speed_upload}M/"
            . "{$package->speed_download}M";

        $api->query(
            (new Query('/queue/simple/add'))
                ->equal(
                    'name',
                    $client->client_code
                )
                ->equal(
                    'target',
                    $client->ip_address
                    . '/32'
                )
                ->equal(
                    'max-limit',
                    $maxLimit
                )
                ->equal(
                    'comment',
                    $client->name
                )
                ->equal(
                    'disabled',
                    'false'
                )
        )->read();

        $id = $this->findId(
            $api,
            $client
        );

        if (!$id) {
            throw new RuntimeException(
                'Simple Queue was created but its MikroTik ID could not be verified.'
            );
        }

        return $id;
    }

    public function update(
        Client $client
    ): void {
        $package = $client->package;

        if (!$package) {
            throw new RuntimeException(
                'Package is missing for Queue operation.'
            );
        }

        $api = $this->api($client);

        $id = $this->resolveId(
            $api,
            $client
        );

        if (!$id) {
            throw new RuntimeException(
                'Simple Queue could not be found on MikroTik.'
            );
        }

        $maxLimit =
            "{$package->speed_upload}M/"
            . "{$package->speed_download}M";

        $api->query(
            (new Query('/queue/simple/set'))
                ->equal('.id', $id)
                ->equal(
                    'target',
                    $client->ip_address
                    . '/32'
                )
                ->equal(
                    'max-limit',
                    $maxLimit
                )
                ->equal(
                    'comment',
                    $client->name
                )
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
                '/queue/simple/set'
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
                'Simple Queue is missing on MikroTik.'
            );
        }

        $api->query(
            (new Query(
                '/queue/simple/set'
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
         * Find by stable queue name so archive
         * retries remain safe after partial work.
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
                '/queue/simple/remove'
            ))
                ->equal('.id', $id)
        )->read();
    }

    private function resolveId(
        RouterClient $api,
        Client $client
    ): ?string {
        if ($client->mikrotik_queue_id) {
            return $client
                ->mikrotik_queue_id;
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
                '/queue/simple/print'
            ))
                ->where(
                    'name',
                    $client->client_code
                )
        )->read();

        foreach ($rows as $row) {
            if (
                ($row['name'] ?? null)
                    === $client->client_code
            ) {
                return $row['.id']
                    ?? null;
            }
        }

        return null;
    }
}
