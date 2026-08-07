<?php

namespace App\Services;

use App\Models\Client;
use RouterOS\Client as RouterClient;
use RouterOS\Query;

class QueueService
{
    protected function api(Client $client): RouterClient
    {
        $router = $client->router;

        return new RouterClient([
            'host' => $router->host,
            'user' => $router->username,
            'pass' => $router->password,
            'port' => $router->api_port ?? 8728,
        ]);
    }

    public function create(Client $client): ?string
    {
        $package = $client->package;

        $maxLimit = "{$package->speed_upload}M/{$package->speed_download}M";

        $api = $this->api($client);

        $api->query(
            (new Query('/queue/simple/add'))
                ->equal('name', $client->client_code)
                ->equal('target', $client->ip_address . '/32')
                ->equal('max-limit', $maxLimit)
                ->equal('comment', $client->name)
        )->read();

        $queues = $api->query(
            (new Query('/queue/simple/print'))
                ->where('name', $client->client_code)
        )->read();

        return $queues[0]['.id'] ?? null;
    }

    public function update(Client $client): void
    {
        if (!$client->mikrotik_queue_id) {
            return;
        }

        $package = $client->package;

        $maxLimit = "{$package->speed_upload}M/{$package->speed_download}M";

        $this->api($client)->query(
            (new Query('/queue/simple/set'))
                ->equal('.id', $client->mikrotik_queue_id)
                ->equal('target', $client->ip_address . '/32')
                ->equal('max-limit', $maxLimit)
                ->equal('comment', $client->name)
        )->read();
    }

    public function disable(Client $client): void
    {
        if (!$client->mikrotik_queue_id) {
            return;
        }

        $this->api($client)->query(
            (new Query('/queue/simple/set'))
                ->equal('.id', $client->mikrotik_queue_id)
                ->equal('disabled', 'yes')
        )->read();
    }

    public function enable(Client $client): void
    {
        if (!$client->mikrotik_queue_id) {
            return;
        }

        $this->api($client)->query(
            (new Query('/queue/simple/set'))
                ->equal('.id', $client->mikrotik_queue_id)
                ->equal('disabled', 'no')
        )->read();
    }

    public function remove(Client $client): void
    {
        if (!$client->mikrotik_queue_id) {
            return;
        }

        $this->api($client)->query(
            (new Query('/queue/simple/remove'))
                ->equal('.id', $client->mikrotik_queue_id)
        )->read();
    }
}
