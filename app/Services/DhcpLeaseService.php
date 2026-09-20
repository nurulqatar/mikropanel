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

        $server =
            $this->resolveServer(
                $api,
                $client
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

        $result =
            $api->query(
                $query
            )->read();

        $this->assertNoRouterOsError(
            $result,
            'create DHCP lease'
        );

        $id =
            $this->createdId(
                $result
            )
            ?: $this->findId(
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

        $server =
            $this->resolveServer(
                $api,
                $client
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

        $result =
            $api->query(
                $query
            )->read();

        $this->assertNoRouterOsError(
            $result,
            'update DHCP lease'
        );
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

    /*
     * ROUTEROS_DHCP_SERVER_RESOLUTION_V3
     *
     * A saved RouterOS server name may become
     * stale after router reconfiguration.
     *
     * First validate the configured name.
     * If missing, safely resolve the active DHCP
     * server attached to the client's interface.
     */
    private function resolveServer(
        RouterClient $api,
        Client $client
    ): string {
        $configured =
            trim(
                (string) (
                    $client
                        ->router
                        ?->dhcp_server
                    ?? ''
                )
            );

        if ($configured !== '') {
            $rows =
                $api->query(
                    (new Query(
                        '/ip/dhcp-server/print'
                    ))
                        ->where(
                            'name',
                            $configured
                        )
                )->read();

            $this->assertNoRouterOsError(
                $rows,
                'read DHCP servers'
            );

            foreach ($rows as $row) {
                if (
                    (string) (
                        $row['name']
                        ?? ''
                    ) === $configured
                    && !$this->truthy(
                        $row['disabled']
                        ?? false
                    )
                    && !$this->truthy(
                        $row['invalid']
                        ?? false
                    )
                ) {
                    return $configured;
                }
            }
        }

        $interface =
            trim(
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
                'MikroTik client interface is missing; DHCP server cannot be resolved.'
            );
        }

        $rows =
            $api->query(
                (new Query(
                    '/ip/dhcp-server/print'
                ))
                    ->where(
                        'interface',
                        $interface
                    )
            )->read();

        $this->assertNoRouterOsError(
            $rows,
            'resolve DHCP server by interface'
        );

        $matches = [];

        foreach ($rows as $row) {
            if (
                (string) (
                    $row['interface']
                    ?? ''
                ) !== $interface
            ) {
                continue;
            }

            if (
                $this->truthy(
                    $row['disabled']
                    ?? false
                )
                || $this->truthy(
                    $row['invalid']
                    ?? false
                )
            ) {
                continue;
            }

            $name =
                trim(
                    (string) (
                        $row['name']
                        ?? ''
                    )
                );

            if ($name !== '') {
                $matches[] = $name;
            }
        }

        $matches =
            array_values(
                array_unique(
                    $matches
                )
            );

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) === 0) {
            throw new RuntimeException(
                'No active DHCP server was found on MikroTik interface '
                . $interface
                . '.'
            );
        }

        throw new RuntimeException(
            'Multiple active DHCP servers were found on MikroTik interface '
            . $interface
            . '; select the intended DHCP server in the router settings.'
        );
    }

    private function createdId(
        mixed $value
    ): ?string {
        if (!is_array($value)) {
            return null;
        }

        foreach ($value as $key => $item) {
            if (
                $key === 'ret'
                && is_scalar($item)
                && trim(
                    (string) $item
                ) !== ''
            ) {
                return (string) $item;
            }

            if (is_array($item)) {
                $found =
                    $this->createdId(
                        $item
                    );

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function assertNoRouterOsError(
        mixed $value,
        string $operation
    ): void {
        $message =
            $this->routerOsMessage(
                $value
            );

        if ($message === null) {
            return;
        }

        throw new RuntimeException(
            'RouterOS failed to '
            . $operation
            . ': '
            . $message
        );
    }

    private function routerOsMessage(
        mixed $value
    ): ?string {
        if (!is_array($value)) {
            return null;
        }

        foreach ($value as $key => $item) {
            if (
                $key === 'message'
                && is_scalar($item)
            ) {
                $message =
                    trim(
                        (string) $item
                    );

                if ($message !== '') {
                    return $message;
                }
            }

            if (is_array($item)) {
                $found =
                    $this->routerOsMessage(
                        $item
                    );

                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function truthy(
        mixed $value
    ): bool {
        if ($value === true) {
            return true;
        }

        return in_array(
            strtolower(
                trim(
                    (string) $value
                )
            ),
            [
                'true',
                'yes',
                '1',
            ],
            true
        );
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

    /*
     * ROUTEROS_DHCP_ID_RECOVERY_V2
     *
     * RouterOS filtering by comment is not
     * reliable enough to be the only identity
     * check after an add operation.
     *
     * Ownership order:
     * 1. Exact MikroPanel client comment.
     * 2. Exact IP + MAC with empty/same comment.
     *
     * Never adopt a foreign lease with another
     * non-empty comment or another MAC.
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
                        '/ip/dhcp-server/lease/print'
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
                    '/ip/dhcp-server/lease/print'
                ))
                    ->where(
                        'address',
                        $address
                    )
            )->read();

        $ipCollision =
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
                $ipCollision =
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
                    'DHCP ownership collision: '
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

        if ($ipCollision) {
            throw new RuntimeException(
                'DHCP IP collision: '
                . $address
                . ' already exists with another MAC address.'
            );
        }

        return null;
    }
}
