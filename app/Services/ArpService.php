<?php

namespace App\Services;

use App\Models\Client;
use RouterOS\Client as RouterClient;
use RouterOS\Query;

class ArpService
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
        $api = $this->api($client);

        $api->query(
            (new Query('/ip/arp/add'))
                ->equal('address', $client->ip_address)
                ->equal('mac-address', $client->mac_address)
                ->equal('interface', $client->ipRange->interface)
                ->equal('comment', $client->client_code)
                ->equal('disabled', 'false')
        )->read();

        $arp = $api->query(
            (new Query('/ip/arp/print'))
                ->where('address', $client->ip_address)
        )->read();

        return $arp[0]['.id'] ?? null;
    }

    public function update(Client $client): void
    {
        if (!$client->mikrotik_arp_id) {
            return;
        }

        $api = $this->api($client);

        $api->query(
            (new Query('/ip/arp/set'))
                ->equal('.id', $client->mikrotik_arp_id)
                ->equal('address', $client->ip_address)
                ->equal('mac-address', $client->mac_address)
                ->equal('interface', $client->ipRange->interface)
                ->equal('comment', $client->client_code)
        )->read();
    }

    public function disable(Client $client): void
    {
        if (!$client->mikrotik_arp_id) {
            return;
        }

        $this->api($client)->query(
            (new Query('/ip/arp/set'))
                ->equal('.id', $client->mikrotik_arp_id)
                ->equal('disabled', 'yes')
        )->read();
    }

    public function enable(Client $client): void
    {
        if (!$client->mikrotik_arp_id) {
            return;
        }

        $this->api($client)->query(
            (new Query('/ip/arp/set'))
                ->equal('.id', $client->mikrotik_arp_id)
                ->equal('disabled', 'no')
        )->read();
    }

    public function remove(Client $client): void
    {
        if (!$client->mikrotik_arp_id) {
            return;
        }

        $this->api($client)->query(
            (new Query('/ip/arp/remove'))
                ->equal('.id', $client->mikrotik_arp_id)
        )->read();
    }
}
