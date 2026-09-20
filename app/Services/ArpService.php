<?php

namespace App\Services;

use App\Models\Client;
use RuntimeException;
use RouterOS\Client as RouterClient;
use RouterOS\Query;

class ArpService
{
    protected function api(
        Client $client
    ): RouterClient {
        $router = $client->router;

        if (!$router) {
            throw new RuntimeException(
                'Router is missing for ARP operation.'
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

        $existing = $this->findId(
            $api,
            $client
        );

        if ($existing) {
            return $existing;
        }

        $interface = trim(
            (string) (
                $client
                    ->router
                    ?->client_interface
                ?: $client
                    ->ipRange
                    ?->interface
                ?: ''
            )
        );

        if ($interface === '') {
            throw new RuntimeException(
                'IP Range MikroTik interface is missing.'
            );
        }

        $api->query(
            (new Query('/ip/arp/add'))
                ->equal(
                    'address',
                    $client->ip_address
                )
                ->equal(
                    'mac-address',
                    $client->mac_address
                )
                ->equal(
                    'interface',
                    $interface
                )
                ->equal(
                    'comment',
                    $client->client_code
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
                'ARP entry was created but its MikroTik ID could not be verified.'
            );
        }

        return $id;
    }

    public function update(
        Client $client
    ): void {
        $api = $this->api($client);

        $id = $this->resolveId(
            $api,
            $client
        );

        if (!$id) {
            throw new RuntimeException(
                'ARP entry could not be found on MikroTik.'
            );
        }

        $interface = trim(
            (string) (
                $client
                    ->router
                    ?->client_interface
                ?: $client
                    ->ipRange
                    ?->interface
                ?: ''
            )
        );

        if ($interface === '') {
            throw new RuntimeException(
                'IP Range MikroTik interface is missing.'
            );
        }

        $api->query(
            (new Query('/ip/arp/set'))
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
                    'interface',
                    $interface
                )
                ->equal(
                    'comment',
                    $client->client_code
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
            (new Query('/ip/arp/set'))
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
                'ARP entry is missing on MikroTik.'
            );
        }

        $api->query(
            (new Query('/ip/arp/set'))
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
         * Missing entry is already equivalent
         * to successful removal.
         */
        $id = $this->findId(
            $api,
            $client
        );

        if (!$id) {
            return;
        }

        $api->query(
            (new Query('/ip/arp/remove'))
                ->equal('.id', $id)
        )->read();
    }

    private function resolveId(
        RouterClient $api,
        Client $client
    ): ?string {
        if ($client->mikrotik_arp_id) {
            return $client
                ->mikrotik_arp_id;
        }

        return $this->findId(
            $api,
            $client
        );
    }

    /*
     * ROUTEROS_ARP_ID_RECOVERY_V2
     *
     * Prefer the MikroPanel comment, then safely
     * recover a static entry by exact IP + MAC.
     *
     * Dynamic ARP rows are never adopted and
     * foreign manual entries are never modified.
     */
    private function findId(
        RouterClient $api,
        Client $client
    ): ?string {
        $code =
            trim(
                (string)
                $client->client_code
            );

        if ($code !== '') {
            $rows =
                $api->query(
                    (new Query(
                        '/ip/arp/print'
                    ))
                        ->where(
                            'comment',
                            $code
                        )
                )->read();

            foreach ($rows as $row) {
                if (
                    trim(
                        (string) (
                            $row['comment']
                            ?? ''
                        )
                    ) === $code
                    && !empty(
                        $row['.id']
                    )
                ) {
                    return (string)
                        $row['.id'];
                }
            }
        }

        $address =
            trim(
                (string)
                $client->ip_address
            );

        $wantedMac =
            strtoupper(
                trim(
                    (string)
                    $client->mac_address
                )
            );

        if (
            $address === ''
            || $wantedMac === ''
        ) {
            return null;
        }

        $rows =
            $api->query(
                (new Query(
                    '/ip/arp/print'
                ))
                    ->where(
                        'address',
                        $address
                    )
            )->read();

        $staticCollision =
            false;

        foreach ($rows as $row) {
            if (
                trim(
                    (string) (
                        $row['address']
                        ?? ''
                    )
                ) !== $address
            ) {
                continue;
            }

            $dynamic =
                strtolower(
                    trim(
                        (string) (
                            $row['dynamic']
                            ?? 'false'
                        )
                    )
                );

            if (
                in_array(
                    $dynamic,
                    [
                        'true',
                        'yes',
                        '1',
                    ],
                    true
                )
            ) {
                continue;
            }

            $rowMac =
                strtoupper(
                    trim(
                        (string) (
                            $row['mac-address']
                            ?? ''
                        )
                    )
                );

            if (
                $rowMac !== ''
                && $rowMac
                    !== $wantedMac
            ) {
                $staticCollision =
                    true;

                continue;
            }

            if (
                $rowMac
                !== $wantedMac
            ) {
                continue;
            }

            $comment =
                trim(
                    (string) (
                        $row['comment']
                        ?? ''
                    )
                );

            if (
                $comment !== ''
                && $comment !== $code
            ) {
                throw new RuntimeException(
                    'ARP ownership collision: '
                    . $address
                    . ' / '
                    . $wantedMac
                    . ' already exists with foreign comment "'
                    . $comment
                    . '".'
                );
            }

            if (
                !empty(
                    $row['.id']
                )
            ) {
                return (string)
                    $row['.id'];
            }
        }

        if ($staticCollision) {
            throw new RuntimeException(
                'ARP IP collision: '
                . $address
                . ' already exists as a static entry with another MAC address.'
            );
        }

        return null;
    }
}
