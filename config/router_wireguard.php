<?php

return [
    /*
     * RESELLER_ROUTER_WIREGUARD_V1
     *
     * This tunnel is only for MikroPanel management.
     * It is intentionally NOT a default-route VPN.
     */
    'interface' =>
        env(
            'ROUTER_WG_INTERFACE',
            'wg0'
        ),

    'endpoint_host' =>
        env(
            'ROUTER_WG_ENDPOINT_HOST',
            '144.172.88.43'
        ),

    'endpoint_port' =>
        (int) env(
            'ROUTER_WG_ENDPOINT_PORT',
            51820
        ),

    'server_tunnel_ip' =>
        env(
            'ROUTER_WG_SERVER_IP',
            '10.10.10.1'
        ),

    /*
     * 10.10.10.1 = server
     * 10.10.10.2 = existing reserved peer
     * 10.10.10.10-254 = MikroPanel router peers
     */
    'client_prefix' =>
        '10.10.10.',

    'client_start' =>
        10,

    'client_end' =>
        254,

    'mikrotik_interface' =>
        'mp-wg',

    'persistent_keepalive' =>
        25,

    'fresh_handshake_seconds' =>
        180,

    'helper' =>
        '/usr/local/sbin/mikropanel-wireguard-peer',
];
