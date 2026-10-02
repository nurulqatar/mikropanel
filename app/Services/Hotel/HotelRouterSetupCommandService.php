<?php

namespace App\Services\Hotel;

use App\Models\Hotel\HotelRouter;
use InvalidArgumentException;

class HotelRouterSetupCommandService
{
    public function generate(
        HotelRouter $router
    ): array {
        $router->loadMissing(
            'hotel'
        );

        [
            $gatewayIp,
            $networkCidr,
            $networkLong,
            $broadcastLong,
        ] =
            $this->network(
                $router
                    ->guest_gateway_cidr
            );

        $poolStart =
            $this->ipLong(
                $router
                    ->guest_pool_start
            );

        $poolEnd =
            $this->ipLong(
                $router
                    ->guest_pool_end
            );

        if (
            $poolStart
                <= $networkLong
            || $poolEnd
                >= $broadcastLong
            || $poolStart
                > $poolEnd
        ) {
            throw new InvalidArgumentException(
                'Guest DHCP pool must be inside the configured subnet.'
            );
        }

        $gatewayLong =
            $this->ipLong(
                $gatewayIp
            );

        if (
            $gatewayLong >= $poolStart
            && $gatewayLong <= $poolEnd
        ) {
            throw new InvalidArgumentException(
                'Guest gateway cannot be inside the DHCP pool.'
            );
        }

        $portalUrl =
            route(
                'hotel.portal.welcome',
                [
                    'hotel' =>
                        $router
                            ->hotel
                            ->slug,
                ]
            );

        $portalHost =
            parse_url(
                $portalUrl,
                PHP_URL_HOST
            );

        if (
            !is_string(
                $portalHost
            )
            || $portalHost === ''
        ) {
            throw new InvalidArgumentException(
                'APP_URL must contain a valid public hostname.'
            );
        }

        $suffix =
            $router
                ->hotel_id
            . '-'
            . $router->id;

        $poolName =
            'mph-pool-'
            . $suffix;

        $dhcpName =
            'mph-dhcp-'
            . $suffix;

        $natComment =
            'MikroPanel-Hotel-'
            . $suffix
            . '-NAT';

        $gardenComment =
            'MikroPanel-Hotel-'
            . $suffix
            . '-Portal';

        $interface =
            $this->quote(
                $router
                    ->guest_interface
            );

        $gatewayCidr =
            $this->quote(
                $router
                    ->guest_gateway_cidr
            );

        $gateway =
            $this->quote(
                $gatewayIp
            );

        $network =
            $this->quote(
                $networkCidr
            );

        $poolRange =
            $this->quote(
                $router
                    ->guest_pool_start
                . '-'
                . $router
                    ->guest_pool_end
            );

        $serverName =
            $this->quote(
                $router
                    ->hotspot_server_name
            );

        $profileName =
            $this->quote(
                $router
                    ->hotspot_profile_name
            );

        $dnsName =
            $this->quote(
                $router
                    ->dns_name
            );

        $portalHostQuoted =
            $this->quote(
                $portalHost
            );

        $comment =
            $this->quote(
                'MikroPanel Hotel '
                . $router
                    ->hotel_id
                . ' Router '
                . $router->id
            );

        $lines = [
            '# MikroPanel Hotel Hotspot Base Setup V1',
            '# Hotel: '
                . $this->plain(
                    $router
                        ->hotel
                        ->name
                ),
            '# Router: '
                . $this->plain(
                    $router
                        ->name
                ),
            '# Review guest interface and subnet before pasting.',
            '# Existing WAN configuration is not changed.',
            '',
            ':put "MikroPanel Hotel Hotspot setup started";',
            '',
            ':if ([:len [/ip pool find where name='
                . $this->quote(
                    $poolName
                )
                . ']] = 0) do={',
            '  /ip pool add name='
                . $this->quote(
                    $poolName
                )
                . ' ranges='
                . $poolRange
                . ';',
            '} else={',
            '  /ip pool set [find where name='
                . $this->quote(
                    $poolName
                )
                . '] ranges='
                . $poolRange
                . ';',
            '}',
            '',
            ':if ([:len [/ip address find where address='
                . $gatewayCidr
                . ' interface='
                . $interface
                . ']] = 0) do={',
            '  /ip address add address='
                . $gatewayCidr
                . ' interface='
                . $interface
                . ' comment='
                . $comment
                . ';',
            '}',
            '',
            ':if ([:len [/ip dhcp-server find where name='
                . $this->quote(
                    $dhcpName
                )
                . ']] = 0) do={',
            '  /ip dhcp-server add name='
                . $this->quote(
                    $dhcpName
                )
                . ' interface='
                . $interface
                . ' address-pool='
                . $this->quote(
                    $poolName
                )
                . ' lease-time=1h disabled=no;',
            '} else={',
            '  /ip dhcp-server set [find where name='
                . $this->quote(
                    $dhcpName
                )
                . '] interface='
                . $interface
                . ' address-pool='
                . $this->quote(
                    $poolName
                )
                . ' disabled=no;',
            '}',
            '',
            ':if ([:len [/ip dhcp-server network find where address='
                . $network
                . ']] = 0) do={',
            '  /ip dhcp-server network add address='
                . $network
                . ' gateway='
                . $gateway
                . ' dns-server=1.1.1.1,8.8.8.8 comment='
                . $comment
                . ';',
            '}',
            '',
            '/ip dns set allow-remote-requests=yes;',
            '',
            ':if ([:len [/ip hotspot profile find where name='
                . $profileName
                . ']] = 0) do={',
            '  /ip hotspot profile add name='
                . $profileName
                . ' hotspot-address='
                . $gateway
                . ' dns-name='
                . $dnsName
                . ' html-directory=hotspot login-by=http-chap,http-pap,cookie;',
            '} else={',
            '  /ip hotspot profile set [find where name='
                . $profileName
                . '] hotspot-address='
                . $gateway
                . ' dns-name='
                . $dnsName
                . ' html-directory=hotspot login-by=http-chap,http-pap,cookie;',
            '}',
            '',
            ':if ([:len [/ip hotspot find where name='
                . $serverName
                . ']] = 0) do={',
            '  /ip hotspot add name='
                . $serverName
                . ' interface='
                . $interface
                . ' address-pool='
                . $this->quote(
                    $poolName
                )
                . ' profile='
                . $profileName
                . ' disabled=no;',
            '} else={',
            '  /ip hotspot set [find where name='
                . $serverName
                . '] interface='
                . $interface
                . ' address-pool='
                . $this->quote(
                    $poolName
                )
                . ' profile='
                . $profileName
                . ' disabled=no;',
            '}',
            '',
            ':if ([:len [/ip firewall nat find where comment='
                . $this->quote(
                    $natComment
                )
                . ']] = 0) do={',
            '  /ip firewall nat add chain=srcnat src-address='
                . $network
                . ' action=masquerade comment='
                . $this->quote(
                    $natComment
                )
                . ';',
            '}',
            '',
            ':if ([:len [/ip hotspot walled-garden find where comment='
                . $this->quote(
                    $gardenComment
                )
                . ']] = 0) do={',
            '  /ip hotspot walled-garden add server='
                . $serverName
                . ' dst-host='
                . $portalHostQuoted
                . ' action=allow comment='
                . $this->quote(
                    $gardenComment
                )
                . ';',
            '}',
            '',
            ':put "MikroPanel Hotel Hotspot base setup completed";',
            ':put "Portal: '
                . $this->plain(
                    $portalUrl
                )
                . '";',
            '',
            '# Phase 3B will add the captive redirect template',
            '# and automatically synchronize guest vouchers.',
        ];

        return [
            'script' =>
                implode(
                    PHP_EOL,
                    $lines
                ),

            'portal_url' =>
                $portalUrl,

            'portal_host' =>
                $portalHost,

            'network_cidr' =>
                $networkCidr,

            'gateway_ip' =>
                $gatewayIp,
        ];
    }

    private function network(
        string $cidr
    ): array {
        $parts =
            explode(
                '/',
                trim($cidr),
                2
            );

        if (
            count($parts) !== 2
            || !filter_var(
                $parts[0],
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4
            )
        ) {
            throw new InvalidArgumentException(
                'Guest gateway must be a valid IPv4 CIDR.'
            );
        }

        $prefix =
            filter_var(
                $parts[1],
                FILTER_VALIDATE_INT
            );

        if (
            $prefix === false
            || $prefix < 8
            || $prefix > 30
        ) {
            throw new InvalidArgumentException(
                'Guest subnet prefix must be between /8 and /30.'
            );
        }

        $ipLong =
            $this->ipLong(
                $parts[0]
            );

        $mask =
            (
                0xFFFFFFFF
                << (
                    32
                    - $prefix
                )
            ) & 0xFFFFFFFF;

        $networkLong =
            $ipLong
            & $mask;

        $broadcastLong =
            $networkLong
            | (
                ~$mask
                & 0xFFFFFFFF
            );

        return [
            $parts[0],

            long2ip(
                $networkLong
            )
                . '/'
                . $prefix,

            $networkLong,

            $broadcastLong,
        ];
    }

    private function ipLong(
        string $ip
    ): int {
        if (
            !filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4
            )
        ) {
            throw new InvalidArgumentException(
                'A valid IPv4 address is required.'
            );
        }

        return (int)
            sprintf(
                '%u',
                ip2long($ip)
            );
    }

    private function quote(
        string $value
    ): string {
        return '"'
            . str_replace(
                [
                    '\\',
                    '"',
                    "\r",
                    "\n",
                ],
                [
                    '\\\\',
                    '\\"',
                    '',
                    '',
                ],
                $value
            )
            . '"';
    }

    private function plain(
        string $value
    ): string {
        return str_replace(
            [
                '"',
                "\r",
                "\n",
            ],
            [
                "'",
                '',
                ' ',
            ],
            $value
        );
    }
}
