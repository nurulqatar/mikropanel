<?php

namespace App\Services;

use App\Models\Client;
use RouterOS\Client as RouterClient;
use RouterOS\Query;

class DhcpLeaseService
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

        $query = (new Query('/ip/dhcp-server/lease/add'))
            ->equal('address', $client->ip_address)
            ->equal('mac-address', $client->mac_address)
            ->equal('comment', $client->client_code)
            ->equal('disabled', 'false');

        $api->query($query)->read();

        $leases = $api->query(
            (new Query('/ip/dhcp-server/lease/print'))
                ->where('address', $client->ip_address)
        )->read();

        return $leases[0]['.id'] ?? null;
    }

    public function update(Client $client): void
    {
        if (!$client->mikrotik_lease_id) {
            return;
        }

        $api = $this->api($client);

        $api->query(
            (new Query('/ip/dhcp-server/lease/set'))
                ->equal('.id', $client->mikrotik_lease_id)
                ->equal('address', $client->ip_address)
                ->equal('mac-address', $client->mac_address)
                ->equal('comment', $client->client_code)
        )->read();
    }

    public function disable(Client $client): void
    {
        if (!$client->mikrotik_lease_id) {
            return;
        }

        $api = $this->api($client);

        $api->query(
            (new Query('/ip/dhcp-server/lease/set'))
                ->equal('.id', $client->mikrotik_lease_id)
                ->equal('disabled', 'yes')
        )->read();
    }

    public function enable(Client $client): void
    {
        if (!$client->mikrotik_lease_id) {
            return;
        }

        $api = $this->api($client);

        $api->query(
            (new Query('/ip/dhcp-server/lease/set'))
                ->equal('.id', $client->mikrotik_lease_id)
                ->equal('disabled', 'no')
        )->read();
    }

    public function remove(Client $client): void
    {
        if (!$client->mikrotik_lease_id) {
            return;
        }

        $api = $this->api($client);

        $api->query(
            (new Query('/ip/dhcp-server/lease/remove'))
                ->equal('.id', $client->mikrotik_lease_id)
        )->read();
    }
}
