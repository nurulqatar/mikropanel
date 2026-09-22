<?php

namespace App\Services;

use App\Models\Router;
use App\Models\RouterWireGuardPeer;
use Illuminate\Support\Carbon;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class RouterWireGuardService
{
    /*
     * RESELLER_ROUTER_WIREGUARD_V1
     */

    public function snapshot(
        Router $router
    ): array {
        $peer =
            RouterWireGuardPeer::query()
                ->where(
                    'router_id',
                    $router->id
                )
                ->first();

        $status =
            $this->emptyStatus();

        if (
            $peer
            && $peer->active
        ) {
            try {
                $status =
                    $this->readStatus(
                        $peer
                    );

            } catch (Throwable $exception) {
                $status['error'] =
                    $exception->getMessage();
            }
        }

        return [
            'peer' =>
                $peer
                    ? $this->peerData(
                        $peer
                    )
                    : null,

            'status' =>
                $status,

            'command' =>
                $peer
                && $peer->active
                    ? $this
                        ->mikrotikCommand(
                            $router,
                            $peer
                        )
                    : null,

            'server' => [
                'interface' =>
                    config(
                        'router_wireguard.interface'
                    ),

                'tunnel_ip' =>
                    config(
                        'router_wireguard.server_tunnel_ip'
                    ),

                'endpoint' =>
                    config(
                        'router_wireguard.endpoint_host'
                    )
                    . ':'
                    . config(
                        'router_wireguard.endpoint_port'
                    ),

                'routing' =>
                    'management-only',
            ],
        ];
    }

    public function create(
        Router $router
    ): RouterWireGuardPeer {
        $existing =
            RouterWireGuardPeer::query()
                ->where(
                    'router_id',
                    $router->id
                )
                ->first();

        if (
            $existing
            && $existing->active
        ) {
            return $existing;
        }

        $clientIp =
            $existing?->client_ip
            ?: $this->allocateClientIp();

        [
            $privateKey,
            $publicKey,
        ] =
            $this->generateKeyPair();

        $serverPublicKey =
            $this->serverPublicKey();

        $endpointHost =
            trim(
                (string)
                config(
                    'router_wireguard.endpoint_host'
                )
            );

        $endpointPort =
            (int)
            config(
                'router_wireguard.endpoint_port'
            );

        if (
            $endpointHost === ''
            || $endpointPort < 1
            || $endpointPort > 65535
        ) {
            throw new RuntimeException(
                'WireGuard endpoint configuration is invalid.'
            );
        }

        $this->helper([
            'add',
            $publicKey,
            $clientIp,
        ]);

        try {
            $resellerId =
                $this->routerResellerId(
                    $router
                );

            $peer =
                RouterWireGuardPeer::query()
                    ->updateOrCreate(
                        [
                            'router_id' =>
                                $router->id,
                        ],
                        [
                            'reseller_id' =>
                                $resellerId,

                            'server_interface' =>
                                (string)
                                config(
                                    'router_wireguard.interface'
                                ),

                            'server_public_key' =>
                                $serverPublicKey,

                            'endpoint_host' =>
                                $endpointHost,

                            'endpoint_port' =>
                                $endpointPort,

                            'client_private_key' =>
                                $privateKey,

                            'client_public_key' =>
                                $publicKey,

                            'client_ip' =>
                                $clientIp,

                            'active' =>
                                true,

                            'provisioned_at' =>
                                now(),

                            'revoked_at' =>
                                null,

                            'last_handshake_at' =>
                                null,

                            'rx_bytes' =>
                                0,

                            'tx_bytes' =>
                                0,

                            'last_endpoint' =>
                                null,

                            'last_status_check_at' =>
                                null,
                        ]
                    );

            return $peer->refresh();

        } catch (Throwable $exception) {
            $this->safeRemove(
                $publicKey
            );

            throw $exception;
        }
    }

    public function rotate(
        Router $router
    ): RouterWireGuardPeer {
        $peer =
            $this->peerForRouter(
                $router
            );

        if (!$peer->active) {
            return $this->create(
                $router
            );
        }

        $oldPublicKey =
            $peer->client_public_key;

        [
            $privateKey,
            $publicKey,
        ] =
            $this->generateKeyPair();

        /*
         * Add the new peer first.
         * Existing connection remains available until
         * the new server peer is successfully installed.
         */
        $this->helper([
            'add',
            $publicKey,
            $peer->client_ip,
        ]);

        try {
            $this->safeRemove(
                $oldPublicKey
            );

            $peer->forceFill([
                'server_public_key' =>
                    $this->serverPublicKey(),

                'endpoint_host' =>
                    (string)
                    config(
                        'router_wireguard.endpoint_host'
                    ),

                'endpoint_port' =>
                    (int)
                    config(
                        'router_wireguard.endpoint_port'
                    ),

                'client_private_key' =>
                    $privateKey,

                'client_public_key' =>
                    $publicKey,

                'active' =>
                    true,

                'provisioned_at' =>
                    now(),

                'revoked_at' =>
                    null,

                'last_handshake_at' =>
                    null,

                'rx_bytes' =>
                    0,

                'tx_bytes' =>
                    0,

                'last_endpoint' =>
                    null,

                'last_status_check_at' =>
                    null,
            ])->save();

            return $peer->refresh();

        } catch (Throwable $exception) {
            $this->safeRemove(
                $publicKey
            );

            /*
             * Best-effort restoration of the old peer.
             */
            try {
                $this->helper([
                    'add',
                    $oldPublicKey,
                    $peer->client_ip,
                ]);
            } catch (Throwable) {
                //
            }

            throw $exception;
        }
    }

    public function revoke(
        Router $router
    ): void {
        $peer =
            $this->peerForRouter(
                $router
            );

        if ($peer->active) {
            $this->safeRemove(
                $peer->client_public_key
            );
        }

        if (
            $peer->previous_router_host
            && $router->host
                === $peer->client_ip
        ) {
            $router->forceFill([
                'host' =>
                    $peer
                        ->previous_router_host,
            ])->save();
        }

        $peer->forceFill([
            'active' =>
                false,

            'revoked_at' =>
                now(),

            'last_status_check_at' =>
                now(),
        ])->save();
    }

    public function refreshStatus(
        Router $router
    ): array {
        $peer =
            $this->peerForRouter(
                $router
            );

        $status =
            $this->readStatus(
                $peer
            );

        $peer->forceFill([
            'last_handshake_at' =>
                $status[
                    'handshake_at'
                ],

            'rx_bytes' =>
                $status['rx_bytes'],

            'tx_bytes' =>
                $status['tx_bytes'],

            'last_endpoint' =>
                $status['endpoint'],

            'last_status_check_at' =>
                now(),
        ])->save();

        return $status;
    }

    public function activateVpnHost(
        Router $router
    ): Router {
        $peer =
            $this->peerForRouter(
                $router
            );

        if (!$peer->active) {
            throw new RuntimeException(
                'WireGuard peer is revoked.'
            );
        }

        $status =
            $this->refreshStatus(
                $router
            );

        if (!$status['connected']) {
            throw new RuntimeException(
                'WireGuard has no recent handshake. Paste the MikroTik command first and check the connection again.'
            );
        }

        $port =
            (int)
            $router->api_port;

        $target =
            sprintf(
                'tcp://%s:%d',
                $peer->client_ip,
                $port
            );

        $errno = 0;
        $error = '';

        $socket =
            @stream_socket_client(
                $target,
                $errno,
                $error,
                4
            );

        if (!is_resource($socket)) {
            throw new RuntimeException(
                'WireGuard is connected, but MikroTik API port '
                . $port
                . ' is not reachable through the tunnel.'
            );
        }

        fclose($socket);

        if (
            !$peer->previous_router_host
            && $router->host
                !== $peer->client_ip
        ) {
            $peer->forceFill([
                'previous_router_host' =>
                    $router->host,
            ])->save();
        }

        $router->forceFill([
            'host' =>
                $peer->client_ip,
        ])->save();

        return $router->refresh();
    }

    public function mikrotikCommand(
        Router $router,
        RouterWireGuardPeer $peer
    ): string {
        $interface =
            (string)
            config(
                'router_wireguard.mikrotik_interface',
                'mp-wg'
            );

        $serverIp =
            (string)
            config(
                'router_wireguard.server_tunnel_ip'
            );

        $keepalive =
            (int)
            config(
                'router_wireguard.persistent_keepalive',
                25
            );

        $apiPort =
            (int)
            $router->api_port;

        /*
         * No 0.0.0.0/0.
         * Only the MikroPanel server tunnel IP is
         * directed through this peer.
         */
        return implode(
            "\n",
            [
                ':local mpIf "'
                    . $interface
                    . '";',

                '/ip/firewall/filter/remove [find where comment="MikroPanel VPN API"];',

                '/ip/firewall/filter/remove [find where comment="MikroPanel VPN Ping"];',

                '/ip/address/remove [find where comment="MikroPanel VPN"];',

                '/interface/wireguard/remove [find where comment="MikroPanel VPN"];',

                '/interface/wireguard/add name=$mpIf mtu=1420 private-key="'
                    . $peer->client_private_key
                    . '" comment="MikroPanel VPN";',

                '/ip/address/add address="'
                    . $peer->client_ip
                    . '/32" network="'
                    . $serverIp
                    . '" interface=$mpIf comment="MikroPanel VPN";',

                '/interface/wireguard/peers/add interface=$mpIf public-key="'
                    . $peer->server_public_key
                    . '" endpoint-address="'
                    . $peer->endpoint_host
                    . '" endpoint-port='
                    . $peer->endpoint_port
                    . ' allowed-address="'
                    . $serverIp
                    . '/32" persistent-keepalive='
                    . $keepalive
                    . 's comment="MikroPanel Server";',

                '/ip/firewall/filter/add chain=input action=accept in-interface=$mpIf src-address="'
                    . $serverIp
                    . '" protocol=tcp dst-port='
                    . $apiPort
                    . ' comment="MikroPanel VPN API";',

                '/ip/firewall/filter/move [find where comment="MikroPanel VPN API"] 0;',

                '/ip/firewall/filter/add chain=input action=accept in-interface=$mpIf src-address="'
                    . $serverIp
                    . '" protocol=icmp comment="MikroPanel VPN Ping";',

                '/ip/firewall/filter/move [find where comment="MikroPanel VPN Ping"] 0;',
            ]
        );
    }

    private function peerForRouter(
        Router $router
    ): RouterWireGuardPeer {
        $peer =
            RouterWireGuardPeer::query()
                ->where(
                    'router_id',
                    $router->id
                )
                ->first();

        if (!$peer) {
            throw new RuntimeException(
                'Create the WireGuard VPN first.'
            );
        }

        return $peer;
    }

    private function allocateClientIp(): string
    {
        $prefix =
            (string)
            config(
                'router_wireguard.client_prefix'
            );

        $start =
            (int)
            config(
                'router_wireguard.client_start'
            );

        $end =
            (int)
            config(
                'router_wireguard.client_end'
            );

        $used = [];

        foreach (
            RouterWireGuardPeer::query()
                ->pluck('client_ip')
            as $ip
        ) {
            $used[(string) $ip] =
                true;
        }

        $live =
            preg_split(
                '/\s+/',
                trim(
                    $this->helper([
                        'used-ips',
                    ])
                )
            )
            ?: [];

        foreach ($live as $cidr) {
            $ip =
                explode(
                    '/',
                    $cidr,
                    2
                )[0];

            if ($ip !== '') {
                $used[$ip] =
                    true;
            }
        }

        for (
            $last = $start;
            $last <= $end;
            $last++
        ) {
            $candidate =
                $prefix
                . $last;

            if (
                !isset(
                    $used[$candidate]
                )
            ) {
                return $candidate;
            }
        }

        throw new RuntimeException(
            'No free MikroPanel WireGuard IP remains.'
        );
    }

    private function generateKeyPair(): array
    {
        $privateProcess =
            new Process([
                '/usr/bin/wg',
                'genkey',
            ]);

        $privateProcess
            ->setTimeout(5);

        $privateProcess->run();

        if (
            !$privateProcess
                ->isSuccessful()
        ) {
            throw new RuntimeException(
                'WireGuard private key generation failed.'
            );
        }

        $privateKey =
            trim(
                $privateProcess
                    ->getOutput()
            );

        $publicProcess =
            new Process([
                '/usr/bin/wg',
                'pubkey',
            ]);

        $publicProcess
            ->setInput(
                $privateKey
                . "\n"
            );

        $publicProcess
            ->setTimeout(5);

        $publicProcess->run();

        if (
            !$publicProcess
                ->isSuccessful()
        ) {
            throw new RuntimeException(
                'WireGuard public key generation failed.'
            );
        }

        $publicKey =
            trim(
                $publicProcess
                    ->getOutput()
            );

        if (
            $privateKey === ''
            || $publicKey === ''
        ) {
            throw new RuntimeException(
                'WireGuard keypair is empty.'
            );
        }

        return [
            $privateKey,
            $publicKey,
        ];
    }

    private function serverPublicKey(): string
    {
        $key =
            trim(
                $this->helper([
                    'server-public-key',
                ])
            );

        if ($key === '') {
            throw new RuntimeException(
                'Server WireGuard public key is unavailable.'
            );
        }

        return $key;
    }

    private function readStatus(
        RouterWireGuardPeer $peer
    ): array {
        if (!$peer->active) {
            return $this->emptyStatus();
        }

        $output =
            trim(
                $this->helper([
                    'status',
                    $peer
                        ->client_public_key,
                ])
            );

        if ($output === '') {
            return [
                ...$this->emptyStatus(),
                'present' =>
                    false,
            ];
        }

        $parts =
            explode(
                "\t",
                $output
            );

        $endpoint =
            $parts[0]
            ?? null;

        $allowed =
            $parts[1]
            ?? null;

        $handshake =
            (int) (
                $parts[2]
                ?? 0
            );

        $rx =
            (int) (
                $parts[3]
                ?? 0
            );

        $tx =
            (int) (
                $parts[4]
                ?? 0
            );

        $handshakeAt =
            $handshake > 0
                ? Carbon::createFromTimestamp(
                    $handshake
                )
                : null;

        $age =
            $handshakeAt
                ? max(
                    0,
                    now()
                        ->diffInSeconds(
                            $handshakeAt
                        )
                )
                : null;

        /*
         * diffInSeconds can be signed depending on
         * Carbon version; normalize explicitly.
         */
        if ($handshakeAt) {
            $age =
                abs(
                    now()
                        ->getTimestamp()
                    - $handshakeAt
                        ->getTimestamp()
                );
        }

        $fresh =
            (int)
            config(
                'router_wireguard.fresh_handshake_seconds',
                180
            );

        return [
            'present' =>
                true,

            'connected' =>
                $handshake > 0
                && $age !== null
                && $age <= $fresh,

            'endpoint' =>
                $endpoint
                && $endpoint !== '(none)'
                    ? $endpoint
                    : null,

            'allowed_ips' =>
                $allowed,

            'handshake_at' =>
                $handshakeAt,

            'handshake_age_seconds' =>
                $age,

            'rx_bytes' =>
                $rx,

            'tx_bytes' =>
                $tx,

            'error' =>
                null,
        ];
    }

    private function emptyStatus(): array
    {
        return [
            'present' =>
                false,

            'connected' =>
                false,

            'endpoint' =>
                null,

            'allowed_ips' =>
                null,

            'handshake_at' =>
                null,

            'handshake_age_seconds' =>
                null,

            'rx_bytes' =>
                0,

            'tx_bytes' =>
                0,

            'error' =>
                null,
        ];
    }

    private function peerData(
        RouterWireGuardPeer $peer
    ): array {
        return [
            'id' =>
                $peer->id,

            'client_public_key' =>
                $peer
                    ->client_public_key,

            'client_ip' =>
                $peer->client_ip,

            'active' =>
                $peer->active,

            'provisioned_at' =>
                $peer
                    ->provisioned_at
                    ?->toIso8601String(),

            'revoked_at' =>
                $peer
                    ->revoked_at
                    ?->toIso8601String(),

            'last_handshake_at' =>
                $peer
                    ->last_handshake_at
                    ?->toIso8601String(),

            'rx_bytes' =>
                $peer->rx_bytes,

            'tx_bytes' =>
                $peer->tx_bytes,

            'last_endpoint' =>
                $peer->last_endpoint,

            'previous_router_host' =>
                $peer
                    ->previous_router_host,
        ];
    }

    private function routerResellerId(
        Router $router
    ): ?int {
        $router->loadMissing(
            'zone:id,reseller_id'
        );

        $direct =
            $router->getAttribute(
                'reseller_id'
            );

        if ($direct) {
            return (int) $direct;
        }

        return $router->zone
            ?->reseller_id
            ? (int)
                $router
                    ->zone
                    ->reseller_id
            : null;
    }

    private function helper(
        array $arguments
    ): string {
        $helper =
            (string)
            config(
                'router_wireguard.helper'
            );

        $process =
            new Process(
                array_merge(
                    [
                        'sudo',
                        '-n',
                        $helper,
                    ],
                    $arguments
                )
            );

        $process->setTimeout(
            12
        );

        $process->run();

        if (
            !$process
                ->isSuccessful()
        ) {
            throw new RuntimeException(
                trim(
                    $process
                        ->getErrorOutput()
                    ?: $process
                        ->getOutput()
                    ?: 'WireGuard helper failed.'
                )
            );
        }

        return $process
            ->getOutput();
    }

    private function safeRemove(
        string $publicKey
    ): void {
        try {
            $this->helper([
                'remove',
                $publicKey,
            ]);
        } catch (Throwable) {
            //
        }
    }
}
