<?php

namespace App\Services\Hotspot;

use App\Models\Router;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class HotspotWizardFinalizerService
{
    /*
     * HOTSPOT_WIZARD_FINAL_V1
     *
     * Completes:
     * Step 5 - Hotspot server + profile
     * Step 6 - login / cookie / MAC-cookie settings
     * Step 7 - DNS + NAT / Internet
     * Step 8 - company-branded RouterOS portal files
     * Step 9 - complete RouterOS validation
     */

    public function finalize(
        Router $router,
        array $settings
    ): array {
        $settings =
            $this->normalizeSettings(
                $settings
            );

        $api =
            $this->client(
                $router
            );

        $changes = [
            'profile_created' => false,
            'server_created' => false,
            'dns_changed' => false,
            'dns_previous' => null,
            'nat_comments' => [],
            'portal_directory_created' => false,
            'portal_directory' => null,
            'profile_directory_changed' => false,
            'profile_previous_directory' => null,
            'profile_id' => null,
        ];

        try {
            $core =
                $this->ensureHotspotCore(
                    $api,
                    $router,
                    $settings,
                    $changes
                );

            $this->ensureDns(
                $api,
                $changes
            );

            $this->ensureNat(
                $api,
                $router,
                $settings,
                $changes
            );

            $portal =
                $this->installPortal(
                    $api,
                    $router,
                    $settings,
                    $core,
                    $changes
                );

            $settings[
                'portal_directory'
            ] =
                $portal[
                    'directory'
                ];

            $validation =
                $this->inspect(
                    $router,
                    $settings,
                    $api
                );

            if (
                !(
                    $validation[
                        'ready'
                    ] ?? false
                )
            ) {
                $failed =
                    array_keys(
                        array_filter(
                            $validation[
                                'checks'
                            ] ?? [],
                            fn ($value) =>
                                $value !== true
                        )
                    );

                throw new \RuntimeException(
                    'Final Hotspot validation failed: '
                    . implode(
                        ', ',
                        $failed
                    )
                );
            }

            return [
                'success' => true,

                'bridge' =>
                    $settings[
                        'bridge_name'
                    ],

                'gateway_cidr' =>
                    $settings[
                        'gateway_cidr'
                    ],

                'pool_name' =>
                    $settings[
                        'pool_name'
                    ],

                'dhcp_server' =>
                    $settings[
                        'dhcp_server'
                    ],

                'hotspot_server' =>
                    $settings[
                        'hotspot_server'
                    ],

                'hotspot_profile' =>
                    $settings[
                        'hotspot_profile'
                    ],

                'dns_name' =>
                    $settings[
                        'dns_name'
                    ],

                'portal_directory' =>
                    $settings[
                        'portal_directory'
                    ],

                'validation' =>
                    $validation,
            ];

        } catch (Throwable $exception) {
            $this->rollback(
                $api,
                $router,
                $changes
            );

            throw new \RuntimeException(
                'Hotspot final setup failed. '
                . 'MikroPanel attempted to roll back only changes created by this operation: '
                . $exception->getMessage(),
                0,
                $exception
            );
        }
    }

    /*
     * Completely read-only final validation.
     */
    public function inspect(
        Router $router,
        array $settings,
        ?Client $api = null
    ): array {
        $settings =
            $this->normalizeSettings(
                $settings,
                false
            );

        $api ??=
            $this->client(
                $router
            );

        $bridges =
            $this->read(
                $api,
                '/interface/bridge/print',
                '.id,name,disabled'
            );

        $addresses =
            $this->read(
                $api,
                '/ip/address/print',
                '.id,address,interface,disabled'
            );

        $pools =
            $this->read(
                $api,
                '/ip/pool/print',
                '.id,name,ranges'
            );

        $dhcpServers =
            $this->read(
                $api,
                '/ip/dhcp-server/print',
                '.id,name,interface,address-pool,disabled'
            );

        $profiles =
            $this->read(
                $api,
                '/ip/hotspot/profile/print',
                implode(',', [
                    '.id',
                    'name',
                    'hotspot-address',
                    'dns-name',
                    'html-directory',
                    'login-by',
                    'http-cookie-lifetime',
                    'use-radius',
                ])
            );

        $servers =
            $this->read(
                $api,
                '/ip/hotspot/print',
                implode(',', [
                    '.id',
                    'name',
                    'interface',
                    'address-pool',
                    'profile',
                    'disabled',
                ])
            );

        $dnsRows =
            $this->read(
                $api,
                '/ip/dns/print',
                'allow-remote-requests,servers,dynamic-servers'
            );

        $natRows =
            $this->read(
                $api,
                '/ip/firewall/nat/print',
                implode(',', [
                    '.id',
                    'chain',
                    'action',
                    'src-address',
                    'out-interface',
                    'out-interface-list',
                    'disabled',
                    'comment',
                ])
            );

        $files =
            $this->read(
                $api,
                '/file/print',
                '.id,name,type,size'
            );

        $gateway =
            $this->ipv4CidrInfo(
                $settings[
                    'gateway_cidr'
                ]
            );

        $bridge =
            $this->findBy(
                $bridges,
                'name',
                $settings[
                    'bridge_name'
                ]
            );

        $gatewayAddress = null;

        foreach ($addresses as $row) {
            if (
                ($row['interface'] ?? null)
                    === $settings[
                        'bridge_name'
                    ]
                && ($row['address'] ?? null)
                    === $settings[
                        'gateway_cidr'
                    ]
                && !$this->isTrue(
                    $row['disabled']
                    ?? false
                )
            ) {
                $gatewayAddress =
                    $row;
                break;
            }
        }

        $pool =
            $this->findBy(
                $pools,
                'name',
                $settings[
                    'pool_name'
                ]
            );

        $dhcp =
            $this->findBy(
                $dhcpServers,
                'name',
                $settings[
                    'dhcp_server'
                ]
            );

        $profile =
            $this->findBy(
                $profiles,
                'name',
                $settings[
                    'hotspot_profile'
                ]
            );

        $server =
            $this->findBy(
                $servers,
                'name',
                $settings[
                    'hotspot_server'
                ]
            );

        $loginBy =
            array_values(
                array_filter(
                    array_map(
                        'trim',
                        explode(
                            ',',
                            strtolower(
                                (string) (
                                    $profile[
                                        'login-by'
                                    ] ?? ''
                                )
                            )
                        )
                    )
                )
            );

        $loginMethodsOk =
            in_array(
                'cookie',
                $loginBy,
                true
            )
            && in_array(
                'mac-cookie',
                $loginBy,
                true
            )
            && (
                in_array(
                    'http-chap',
                    $loginBy,
                    true
                )
                || in_array(
                    'http-pap',
                    $loginBy,
                    true
                )
            );

        $dns =
            $dnsRows[0]
            ?? [];

        $natOk =
            $this->hasUsableNat(
                $api,
                $natRows,
                $gateway[
                    'network_cidr'
                ]
            );

        $portalDirectory =
            trim(
                (string) (
                    $settings[
                        'portal_directory'
                    ]
                    ?? (
                        $profile[
                            'html-directory'
                        ] ?? ''
                    )
                )
            );

        $loginFile = false;
        $statusFile = false;
        $apiFile = false;

        if ($portalDirectory !== '') {
            foreach ($files as $file) {
                $name =
                    (string) (
                        $file['name']
                        ?? ''
                    );

                if (
                    $name
                    === $portalDirectory
                        . '/login.html'
                ) {
                    $loginFile = true;
                }

                if (
                    $name
                    === $portalDirectory
                        . '/status.html'
                ) {
                    $statusFile = true;
                }

                if (
                    $name
                    === $portalDirectory
                        . '/api.json'
                ) {
                    $apiFile = true;
                }
            }
        }

        $checks = [
            'bridge' =>
                $bridge !== null
                && !$this->isTrue(
                    $bridge[
                        'disabled'
                    ] ?? false
                ),

            'gateway' =>
                $gatewayAddress !== null,

            'pool' =>
                $pool !== null,

            'dhcp' =>
                $dhcp !== null
                && ($dhcp[
                    'interface'
                ] ?? null)
                    === $settings[
                        'bridge_name'
                    ]
                && ($dhcp[
                    'address-pool'
                ] ?? null)
                    === $settings[
                        'pool_name'
                    ]
                && !$this->isTrue(
                    $dhcp[
                        'disabled'
                    ] ?? true
                ),

            'hotspot_profile' =>
                $profile !== null
                && ($profile[
                    'hotspot-address'
                ] ?? null)
                    === $gateway['ip']
                && strtolower(
                    (string) (
                        $profile[
                            'dns-name'
                        ] ?? ''
                    )
                ) === strtolower(
                    $settings[
                        'dns_name'
                    ]
                ),

            'login_methods' =>
                $loginMethodsOk,

            'hotspot_server' =>
                $server !== null
                && ($server[
                    'interface'
                ] ?? null)
                    === $settings[
                        'bridge_name'
                    ]
                && ($server[
                    'address-pool'
                ] ?? null)
                    === $settings[
                        'pool_name'
                    ]
                && ($server[
                    'profile'
                ] ?? null)
                    === $settings[
                        'hotspot_profile'
                    ]
                && !$this->isTrue(
                    $server[
                        'disabled'
                    ] ?? true
                ),

            'dns' =>
                $this->isTrue(
                    $dns[
                        'allow-remote-requests'
                    ] ?? false
                ),

            'nat' =>
                $natOk,

            'portal_profile' =>
                $portalDirectory !== ''
                && ($profile[
                    'html-directory'
                ] ?? null)
                    === $portalDirectory,

            'portal_login' =>
                $loginFile,

            'portal_status' =>
                $statusFile,

            'portal_api' =>
                $apiFile,
        ];

        return [
            'success' => true,

            'ready' =>
                !in_array(
                    false,
                    $checks,
                    true
                ),

            'checks' =>
                $checks,

            'gateway_ip' =>
                $gateway['ip'],

            'network_cidr' =>
                $gateway[
                    'network_cidr'
                ],

            'portal_directory' =>
                $portalDirectory,

            'hotspot_server' =>
                $settings[
                    'hotspot_server'
                ],

            'hotspot_profile' =>
                $settings[
                    'hotspot_profile'
                ],

            'dns_name' =>
                $settings[
                    'dns_name'
                ],
        ];
    }

    protected function ensureHotspotCore(
        Client $api,
        Router $router,
        array $settings,
        array &$changes
    ): array {
        $gateway =
            $this->ipv4CidrInfo(
                $settings[
                    'gateway_cidr'
                ]
            );

        $profiles =
            $this->read(
                $api,
                '/ip/hotspot/profile/print',
                implode(',', [
                    '.id',
                    'name',
                    'hotspot-address',
                    'dns-name',
                    'html-directory',
                    'login-by',
                    'http-cookie-lifetime',
                    'use-radius',
                ])
            );

        $servers =
            $this->read(
                $api,
                '/ip/hotspot/print',
                '.id,name,interface,address-pool,profile,disabled'
            );

        $profile =
            $this->findBy(
                $profiles,
                'name',
                $settings[
                    'hotspot_profile'
                ]
            );

        $server =
            $this->findBy(
                $servers,
                'name',
                $settings[
                    'hotspot_server'
                ]
            );

        if (
            $settings['mode']
            === 'existing'
        ) {
            if (!$profile) {
                throw new \RuntimeException(
                    'Existing Hotspot profile was not found.'
                );
            }

            if (!$server) {
                throw new \RuntimeException(
                    'Existing Hotspot server was not found.'
                );
            }

            if (
                ($profile[
                    'hotspot-address'
                ] ?? null)
                    !== $gateway['ip']
                || strtolower(
                    (string) (
                        $profile[
                            'dns-name'
                        ] ?? ''
                    )
                ) !== strtolower(
                    $settings[
                        'dns_name'
                    ]
                )
            ) {
                throw new \RuntimeException(
                    'Existing Hotspot profile does not match the selected gateway/DNS.'
                );
            }

            if (
                ($server[
                    'interface'
                ] ?? null)
                    !== $settings[
                        'bridge_name'
                    ]
                || ($server[
                    'address-pool'
                ] ?? null)
                    !== $settings[
                        'pool_name'
                    ]
                || ($server[
                    'profile'
                ] ?? null)
                    !== $settings[
                        'hotspot_profile'
                    ]
            ) {
                throw new \RuntimeException(
                    'Existing Hotspot server does not match the selected bridge/pool/profile.'
                );
            }

            $loginBy =
                strtolower(
                    (string) (
                        $profile[
                            'login-by'
                        ] ?? ''
                    )
                );

            if (
                !str_contains(
                    $loginBy,
                    'mac-cookie'
                )
            ) {
                throw new \RuntimeException(
                    'Existing Hotspot profile does not have MAC-cookie login enabled.'
                );
            }

            $changes[
                'profile_id'
            ] =
                $profile['.id']
                ?? null;

            return [
                'profile_id' =>
                    $profile['.id']
                    ?? null,

                'server_id' =>
                    $server['.id']
                    ?? null,

                'profile' =>
                    $profile,

                'server' =>
                    $server,
            ];
        }

        /*
         * NEW / REPAIR-SAFE MODE:
         * Existing same-name objects must match;
         * unrelated objects are never overwritten.
         */
        if ($profile) {
            if (
                ($profile[
                    'hotspot-address'
                ] ?? null)
                    !== $gateway['ip']
                || strtolower(
                    (string) (
                        $profile[
                            'dns-name'
                        ] ?? ''
                    )
                ) !== strtolower(
                    $settings[
                        'dns_name'
                    ]
                )
            ) {
                throw new \RuntimeException(
                    'Hotspot profile name already exists with different settings.'
                );
            }

        } else {
            $this->write(
                $api,
                (new Query(
                    '/ip/hotspot/profile/add'
                ))
                    ->equal(
                        'name',
                        $settings[
                            'hotspot_profile'
                        ]
                    )
                    ->equal(
                        'hotspot-address',
                        $gateway['ip']
                    )
                    ->equal(
                        'dns-name',
                        $settings[
                            'dns_name'
                        ]
                    )
                    ->equal(
                        'login-by',
                        'cookie,http-chap,http-pap,mac-cookie'
                    )
                    ->equal(
                        'http-cookie-lifetime',
                        $settings[
                            'cookie_lifetime'
                        ]
                    )
                    ->equal(
                        'use-radius',
                        'false'
                    )
            );

            $changes[
                'profile_created'
            ] = true;

            $profiles =
                $this->read(
                    $api,
                    '/ip/hotspot/profile/print',
                    '.id,name,hotspot-address,dns-name,html-directory,login-by,http-cookie-lifetime,use-radius'
                );

            $profile =
                $this->findBy(
                    $profiles,
                    'name',
                    $settings[
                        'hotspot_profile'
                    ]
                );

            if (!$profile) {
                throw new \RuntimeException(
                    'New Hotspot profile could not be verified.'
                );
            }
        }

        if ($server) {
            if (
                ($server[
                    'interface'
                ] ?? null)
                    !== $settings[
                        'bridge_name'
                    ]
                || ($server[
                    'address-pool'
                ] ?? null)
                    !== $settings[
                        'pool_name'
                    ]
                || ($server[
                    'profile'
                ] ?? null)
                    !== $settings[
                        'hotspot_profile'
                    ]
            ) {
                throw new \RuntimeException(
                    'Hotspot server name already exists with different settings.'
                );
            }

        } else {
            $this->write(
                $api,
                (new Query(
                    '/ip/hotspot/add'
                ))
                    ->equal(
                        'name',
                        $settings[
                            'hotspot_server'
                        ]
                    )
                    ->equal(
                        'interface',
                        $settings[
                            'bridge_name'
                        ]
                    )
                    ->equal(
                        'address-pool',
                        $settings[
                            'pool_name'
                        ]
                    )
                    ->equal(
                        'profile',
                        $settings[
                            'hotspot_profile'
                        ]
                    )
                    ->equal(
                        'disabled',
                        'false'
                    )
            );

            $changes[
                'server_created'
            ] = true;

            $servers =
                $this->read(
                    $api,
                    '/ip/hotspot/print',
                    '.id,name,interface,address-pool,profile,disabled'
                );

            $server =
                $this->findBy(
                    $servers,
                    'name',
                    $settings[
                        'hotspot_server'
                    ]
                );

            if (!$server) {
                throw new \RuntimeException(
                    'New Hotspot server could not be verified.'
                );
            }
        }

        $changes[
            'profile_id'
        ] =
            $profile['.id']
            ?? null;

        return [
            'profile_id' =>
                $profile['.id']
                ?? null,

            'server_id' =>
                $server['.id']
                ?? null,

            'profile' =>
                $profile,

            'server' =>
                $server,
        ];
    }

    protected function ensureDns(
        Client $api,
        array &$changes
    ): void {
        $rows =
            $this->read(
                $api,
                '/ip/dns/print',
                'allow-remote-requests'
            );

        $current =
            $this->isTrue(
                $rows[0][
                    'allow-remote-requests'
                ] ?? false
            );

        if ($current) {
            return;
        }

        $changes[
            'dns_previous'
        ] = false;

        $this->write(
            $api,
            (new Query(
                '/ip/dns/set'
            ))
                ->equal(
                    'allow-remote-requests',
                    'true'
                )
        );

        $changes[
            'dns_changed'
        ] = true;
    }

    protected function ensureNat(
        Client $api,
        Router $router,
        array $settings,
        array &$changes
    ): void {
        $gateway =
            $this->ipv4CidrInfo(
                $settings[
                    'gateway_cidr'
                ]
            );

        $natRows =
            $this->read(
                $api,
                '/ip/firewall/nat/print',
                implode(',', [
                    '.id',
                    'chain',
                    'action',
                    'src-address',
                    'out-interface',
                    'out-interface-list',
                    'disabled',
                    'comment',
                ])
            );

        if (
            $this->hasUsableNat(
                $api,
                $natRows,
                $gateway[
                    'network_cidr'
                ]
            )
        ) {
            return;
        }

        $wan =
            $this->detectWan(
                $api
            );

        if (
            $wan[
                'list_name'
            ]
        ) {
            $comment =
                'MIKROPANEL:HOTSPOT:NAT:ROUTER-'
                . $router->id
                . ':WAN-LIST';

            $this->write(
                $api,
                (new Query(
                    '/ip/firewall/nat/add'
                ))
                    ->equal(
                        'chain',
                        'srcnat'
                    )
                    ->equal(
                        'action',
                        'masquerade'
                    )
                    ->equal(
                        'src-address',
                        $gateway[
                            'network_cidr'
                        ]
                    )
                    ->equal(
                        'out-interface-list',
                        $wan[
                            'list_name'
                        ]
                    )
                    ->equal(
                        'comment',
                        $comment
                    )
            );

            $changes[
                'nat_comments'
            ][] = $comment;

            return;
        }

        if (
            $wan[
                'interfaces'
            ] === []
        ) {
            throw new \RuntimeException(
                'No active Internet/WAN interface could be detected for NAT.'
            );
        }

        foreach (
            $wan[
                'interfaces'
            ]
            as $index => $interface
        ) {
            $comment =
                'MIKROPANEL:HOTSPOT:NAT:ROUTER-'
                . $router->id
                . ':'
                . ($index + 1);

            $this->write(
                $api,
                (new Query(
                    '/ip/firewall/nat/add'
                ))
                    ->equal(
                        'chain',
                        'srcnat'
                    )
                    ->equal(
                        'action',
                        'masquerade'
                    )
                    ->equal(
                        'src-address',
                        $gateway[
                            'network_cidr'
                        ]
                    )
                    ->equal(
                        'out-interface',
                        $interface
                    )
                    ->equal(
                        'comment',
                        $comment
                    )
            );

            $changes[
                'nat_comments'
            ][] = $comment;
        }
    }

    protected function installPortal(
        Client $api,
        Router $router,
        array $settings,
        array $core,
        array &$changes
    ): array {
        $files =
            $this->read(
                $api,
                '/file/print',
                '.id,name,type,size'
            );

        $hasFlash = false;

        foreach ($files as $row) {
            if (
                ($row['name'] ?? null)
                    === 'flash'
                && (
                    ($row['type'] ?? null)
                    === 'directory'
                )
            ) {
                $hasFlash = true;
                break;
            }
        }

        $directory =
            ($hasFlash
                ? 'flash/'
                : '')
            . 'mp-hotspot-'
            . $router->id;

        $directoryRow =
            $this->findBy(
                $files,
                'name',
                $directory
            );

        if (!$directoryRow) {
            $this->write(
                $api,
                (new Query(
                    '/file/add'
                ))
                    ->equal(
                        'name',
                        $directory
                    )
                    ->equal(
                        'type',
                        'directory'
                    )
            );

            $changes[
                'portal_directory_created'
            ] = true;
        }

        $changes[
            'portal_directory'
        ] = $directory;

        $brand =
            htmlspecialchars(
                $settings[
                    'brand_name'
                ],
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            );

        $dns =
            htmlspecialchars(
                $settings[
                    'dns_name'
                ],
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            );

        $portalFiles = [
            'style.css' =>
                <<<'CSS'
*{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#0f172a;color:#0f172a;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}.card{width:100%;max-width:420px;background:#fff;border-radius:22px;padding:28px;box-shadow:0 25px 70px rgba(0,0,0,.35)}h1{margin:0;font-size:28px}.sub{color:#64748b;margin:8px 0 24px}.field{margin:14px 0}.field label{display:block;font-weight:700;font-size:13px;margin-bottom:6px}.field input{width:100%;padding:13px;border:1px solid #cbd5e1;border-radius:12px;font-size:16px}.btn{width:100%;padding:14px;border:0;border-radius:12px;background:#0891b2;color:#fff;font-size:16px;font-weight:800;cursor:pointer}.msg{padding:10px 12px;border-radius:10px;background:#fef2f2;color:#991b1b;margin:12px 0}.meta{margin-top:18px;color:#64748b;font-size:12px;text-align:center}.stats{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:18px 0}.stat{background:#f8fafc;border-radius:12px;padding:12px}.stat b{display:block;margin-top:4px}
CSS,

            'login.html' =>
                '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
                . $brand
                . '</title><link rel="stylesheet" href="style.css"></head><body><div class="card"><h1>'
                . $brand
                . '</h1><div class="sub">Secure Internet Access</div>$(if error)<div class="msg">$(error)</div>$(endif)<form action="$(link-login-only)" method="post"><input type="hidden" name="dst" value="$(link-orig)"><input type="hidden" name="popup" value="true"><div class="field"><label>Username / Voucher</label><input name="username" value="$(username)" autocomplete="username" required></div><div class="field"><label>Password</label><input name="password" type="password" autocomplete="current-password" required></div><button class="btn" type="submit">Connect to Internet</button></form><div class="meta">'
                . $dns
                . '</div></div></body></html>',

            'status.html' =>
                '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
                . $brand
                . '</title><link rel="stylesheet" href="style.css"></head><body><div class="card"><h1>Connected</h1><div class="sub">'
                . $brand
                . '</div><div class="stats"><div class="stat">User<b>$(username)</b></div><div class="stat">Uptime<b>$(uptime)</b></div><div class="stat">Download<b>$(bytes-out-nice)</b></div><div class="stat">Upload<b>$(bytes-in-nice)</b></div></div><form action="$(link-logout)" method="post"><button class="btn" type="submit">Disconnect</button></form></div></body></html>',

            'logout.html' =>
                '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
                . $brand
                . '</title><link rel="stylesheet" href="style.css"></head><body><div class="card"><h1>Disconnected</h1><div class="sub">'
                . $brand
                . '</div><a href="$(link-login)" style="text-decoration:none"><button class="btn">Login Again</button></a></div></body></html>',

            'error.html' =>
                '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'
                . $brand
                . '</title><link rel="stylesheet" href="style.css"></head><body><div class="card"><h1>Connection Error</h1><div class="msg">$(error)</div><a href="$(link-login)" style="text-decoration:none"><button class="btn">Back to Login</button></a></div></body></html>',

            'redirect.html' =>
                '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0; url=$(link-login)"><title>'
                . $brand
                . '</title></head><body></body></html>',

            'alogin.html' =>
                '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0; url=$(link-redirect)"><title>'
                . $brand
                . '</title></head><body></body></html>',

            'api.json' =>
                '{"captive":$(if logged-in == \'yes\')false$(else)true$(endif),"user-portal-url":"$(link-login-only)","can-extend-session":true}',
        ];

        foreach (
            $portalFiles
            as $filename => $contents
        ) {
            if (
                strlen(
                    $contents
                ) > 58000
            ) {
                throw new \RuntimeException(
                    "Portal file {$filename} is too large for safe RouterOS contents write."
                );
            }

            $this->writeFile(
                $api,
                $directory
                    . '/'
                    . $filename,
                $contents
            );
        }

        $profileId =
            $core[
                'profile_id'
            ];

        if (!$profileId) {
            throw new \RuntimeException(
                'Hotspot profile ID is unavailable for portal activation.'
            );
        }

        $profile =
            $core[
                'profile'
            ];

        $oldDirectory =
            trim(
                (string) (
                    $profile[
                        'html-directory'
                    ] ?? ''
                )
            );

        if (
            $oldDirectory
            !== $directory
        ) {
            $changes[
                'profile_previous_directory'
            ] =
                $oldDirectory;

            $this->write(
                $api,
                (new Query(
                    '/ip/hotspot/profile/set'
                ))
                    ->equal(
                        '.id',
                        $profileId
                    )
                    ->equal(
                        'html-directory',
                        $directory
                    )
            );

            $changes[
                'profile_directory_changed'
            ] = true;
        }

        return [
            'directory' =>
                $directory,

            'files' =>
                array_keys(
                    $portalFiles
                ),
        ];
    }

    protected function writeFile(
        Client $api,
        string $path,
        string $contents
    ): void {
        $files =
            $this->read(
                $api,
                '/file/print',
                '.id,name,type,size'
            );

        $file =
            $this->findBy(
                $files,
                'name',
                $path
            );

        if (!$file) {
            $this->write(
                $api,
                (new Query(
                    '/file/add'
                ))
                    ->equal(
                        'name',
                        $path
                    )
                    ->equal(
                        'type',
                        'file'
                    )
            );

            $files =
                $this->read(
                    $api,
                    '/file/print',
                    '.id,name,type,size'
                );

            $file =
                $this->findBy(
                    $files,
                    'name',
                    $path
                );
        }

        if (
            !$file
            || !isset(
                $file['.id']
            )
        ) {
            throw new \RuntimeException(
                "Unable to create RouterOS file {$path}."
            );
        }

        $this->write(
            $api,
            (new Query(
                '/file/set'
            ))
                ->equal(
                    '.id',
                    $file['.id']
                )
                ->equal(
                    'contents',
                    $contents
                )
        );
    }

    protected function hasUsableNat(
        Client $api,
        array $natRows,
        string $networkCidr
    ): bool {
        $wan =
            $this->detectWan(
                $api
            );

        foreach ($natRows as $row) {
            if (
                $this->isTrue(
                    $row['disabled']
                    ?? false
                )
                || strtolower(
                    (string) (
                        $row['chain']
                        ?? ''
                    )
                ) !== 'srcnat'
                || strtolower(
                    (string) (
                        $row['action']
                        ?? ''
                    )
                ) !== 'masquerade'
            ) {
                continue;
            }

            $src =
                trim(
                    (string) (
                        $row[
                            'src-address'
                        ] ?? ''
                    )
                );

            if (
                $src !== ''
                && $src !== $networkCidr
            ) {
                continue;
            }

            $outList =
                trim(
                    (string) (
                        $row[
                            'out-interface-list'
                        ] ?? ''
                    )
                );

            $outInterface =
                trim(
                    (string) (
                        $row[
                            'out-interface'
                        ] ?? ''
                    )
                );

            if (
                $outList === ''
                && $outInterface === ''
            ) {
                return true;
            }

            if (
                $wan['list_name']
                && strcasecmp(
                    $outList,
                    $wan[
                        'list_name'
                    ]
                ) === 0
            ) {
                return true;
            }

            if (
                $outInterface !== ''
                && in_array(
                    $outInterface,
                    $wan[
                        'interfaces'
                    ],
                    true
                )
            ) {
                return true;
            }
        }

        return false;
    }

    protected function detectWan(
        Client $api
    ): array {
        $members =
            $this->read(
                $api,
                '/interface/list/member/print',
                '.id,interface,list,disabled'
            );

        $routes =
            $this->read(
                $api,
                '/ip/route/print',
                '.id,dst-address,gateway,immediate-gw,routing-table,active,disabled'
            );

        $listName = null;
        $interfaces = [];

        foreach ($members as $row) {
            if (
                $this->isTrue(
                    $row['disabled']
                    ?? false
                )
            ) {
                continue;
            }

            if (
                strtolower(
                    (string) (
                        $row['list']
                        ?? ''
                    )
                ) !== 'wan'
            ) {
                continue;
            }

            $listName =
                (string)
                $row['list'];

            $interface =
                trim(
                    (string) (
                        $row[
                            'interface'
                        ] ?? ''
                    )
                );

            if ($interface !== '') {
                $interfaces[] =
                    $interface;
            }
        }

        foreach ($routes as $row) {
            if (
                ($row['dst-address']
                    ?? null)
                    !== '0.0.0.0/0'
                || !$this->isTrue(
                    $row['active']
                    ?? false
                )
                || $this->isTrue(
                    $row['disabled']
                    ?? false
                )
                || (
                    isset(
                        $row[
                            'routing-table'
                        ]
                    )
                    && $row[
                        'routing-table'
                    ] !== 'main'
                )
            ) {
                continue;
            }

            $immediate =
                trim(
                    (string) (
                        $row[
                            'immediate-gw'
                        ] ?? ''
                    )
                );

            if (
                preg_match(
                    '/%([^%]+)$/',
                    $immediate,
                    $matches
                )
            ) {
                $interfaces[] =
                    trim(
                        $matches[1]
                    );

                continue;
            }

            $gateway =
                trim(
                    (string) (
                        $row[
                            'gateway'
                        ] ?? ''
                    )
                );

            if (
                $gateway !== ''
                && !filter_var(
                    $gateway,
                    FILTER_VALIDATE_IP
                )
                && !str_contains(
                    $gateway,
                    ','
                )
            ) {
                $interfaces[] =
                    $gateway;
            }
        }

        return [
            'list_name' =>
                $listName,

            'interfaces' =>
                array_values(
                    array_unique(
                        array_filter(
                            $interfaces
                        )
                    )
                ),
        ];
    }

    protected function rollback(
        Client $api,
        Router $router,
        array $changes
    ): void {
        if (
            $changes[
                'profile_directory_changed'
            ]
            && $changes[
                'profile_id'
            ]
            && !(
                $changes[
                    'profile_created'
                ]
            )
        ) {
            try {
                $this->write(
                    $api,
                    (new Query(
                        '/ip/hotspot/profile/set'
                    ))
                        ->equal(
                            '.id',
                            $changes[
                                'profile_id'
                            ]
                        )
                        ->equal(
                            'html-directory',
                            $changes[
                                'profile_previous_directory'
                            ]
                        )
                );
            } catch (Throwable) {
                //
            }
        }

        if (
            $changes[
                'server_created'
            ]
        ) {
            $this->removeBy(
                $api,
                '/ip/hotspot/print',
                '/ip/hotspot/remove',
                'name',
                'mp-hotspot-'
                    . $router->id
            );
        }

        if (
            $changes[
                'profile_created'
            ]
        ) {
            try {
                $profiles =
                    $this->read(
                        $api,
                        '/ip/hotspot/profile/print',
                        '.id,name'
                    );

                foreach ($profiles as $row) {
                    if (
                        isset(
                            $row['.id']
                        )
                        && str_starts_with(
                            (string) (
                                $row['name']
                                ?? ''
                            ),
                            'mp-hsprof-'
                        )
                    ) {
                        $this->write(
                            $api,
                            (new Query(
                                '/ip/hotspot/profile/remove'
                            ))
                                ->equal(
                                    '.id',
                                    $row['.id']
                                )
                        );
                    }
                }
            } catch (Throwable) {
                //
            }
        }

        foreach (
            $changes[
                'nat_comments'
            ]
            as $comment
        ) {
            $this->removeBy(
                $api,
                '/ip/firewall/nat/print',
                '/ip/firewall/nat/remove',
                'comment',
                $comment
            );
        }

        if (
            $changes[
                'dns_changed'
            ]
        ) {
            try {
                $this->write(
                    $api,
                    (new Query(
                        '/ip/dns/set'
                    ))
                        ->equal(
                            'allow-remote-requests',
                            'false'
                        )
                );
            } catch (Throwable) {
                //
            }
        }

        if (
            $changes[
                'portal_directory_created'
            ]
            && $changes[
                'portal_directory'
            ]
        ) {
            $this->removePortalDirectory(
                $api,
                $changes[
                    'portal_directory'
                ]
            );
        }
    }

    protected function removePortalDirectory(
        Client $api,
        string $directory
    ): void {
        try {
            $files =
                $this->read(
                    $api,
                    '/file/print',
                    '.id,name,type'
                );

            usort(
                $files,
                fn ($a, $b) =>
                    strlen(
                        (string) (
                            $b['name']
                            ?? ''
                        )
                    )
                    <=>
                    strlen(
                        (string) (
                            $a['name']
                            ?? ''
                        )
                    )
            );

            foreach ($files as $row) {
                $name =
                    (string) (
                        $row['name']
                        ?? ''
                    );

                if (
                    $name !== $directory
                    && !str_starts_with(
                        $name,
                        $directory
                            . '/'
                    )
                ) {
                    continue;
                }

                if (!isset($row['.id'])) {
                    continue;
                }

                try {
                    $this->write(
                        $api,
                        (new Query(
                            '/file/remove'
                        ))
                            ->equal(
                                '.id',
                                $row['.id']
                            )
                    );
                } catch (Throwable) {
                    //
                }
            }

        } catch (Throwable) {
            //
        }
    }

    protected function removeBy(
        Client $api,
        string $printPath,
        string $removePath,
        string $field,
        string $value
    ): void {
        try {
            $rows =
                $this->read(
                    $api,
                    $printPath,
                    '.id,'
                    . $field
                );

            foreach ($rows as $row) {
                if (
                    ($row[$field]
                        ?? null)
                        !== $value
                    || !isset(
                        $row['.id']
                    )
                ) {
                    continue;
                }

                $this->write(
                    $api,
                    (new Query(
                        $removePath
                    ))
                        ->equal(
                            '.id',
                            $row['.id']
                        )
                );
            }

        } catch (Throwable) {
            //
        }
    }

    protected function normalizeSettings(
        array $settings,
        bool $strict = true
    ): array {
        $settings = array_merge(
            [
                'mode' => 'existing',
                'bridge_name' => '',
                'gateway_cidr' => '',
                'pool_name' => '',
                'dhcp_server' => '',
                'hotspot_server' => '',
                'hotspot_profile' => '',
                'dns_name' => '',
                'cookie_lifetime' => '3d',
                'brand_name' =>
                    'MikroPanel Internet',
                'portal_directory' => null,
            ],
            $settings
        );

        foreach (
            [
                'mode',
                'bridge_name',
                'gateway_cidr',
                'pool_name',
                'dhcp_server',
                'hotspot_server',
                'hotspot_profile',
                'dns_name',
                'cookie_lifetime',
                'brand_name',
            ]
            as $key
        ) {
            $settings[$key] =
                trim(
                    (string)
                    $settings[$key]
                );
        }

        if (
            !in_array(
                $settings['mode'],
                [
                    'existing',
                    'new',
                ],
                true
            )
        ) {
            throw new \RuntimeException(
                'Invalid final Hotspot setup mode.'
            );
        }

        if (!$strict) {
            return $settings;
        }

        foreach (
            [
                'bridge_name',
                'gateway_cidr',
                'pool_name',
                'dhcp_server',
                'hotspot_server',
                'hotspot_profile',
                'dns_name',
            ]
            as $key
        ) {
            if (
                $settings[$key]
                === ''
            ) {
                throw new \RuntimeException(
                    "{$key} is required."
                );
            }
        }

        if (
            strlen(
                $settings[
                    'hotspot_server'
                ]
            ) > 100
            || strlen(
                $settings[
                    'hotspot_profile'
                ]
            ) > 100
        ) {
            throw new \RuntimeException(
                'Hotspot server/profile name is too long.'
            );
        }

        if (
            !preg_match(
                '/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/i',
                $settings[
                    'dns_name'
                ]
            )
        ) {
            throw new \RuntimeException(
                'Hotspot DNS name is invalid.'
            );
        }

        if (
            !preg_match(
                '/^[0-9]+[smhdw](?:[0-9]+[smhdw])*$/i',
                $settings[
                    'cookie_lifetime'
                ]
            )
        ) {
            throw new \RuntimeException(
                'Cookie lifetime is invalid. Example: 3d.'
            );
        }

        $this->ipv4CidrInfo(
            $settings[
                'gateway_cidr'
            ]
        );

        return $settings;
    }

    protected function ipv4CidrInfo(
        string $cidr
    ): array {
        if (
            !preg_match(
                '/^([^\/]+)\/(\d{1,2})$/',
                trim($cidr),
                $matches
            )
        ) {
            throw new \RuntimeException(
                'Gateway must be IPv4/CIDR.'
            );
        }

        $ip =
            trim(
                $matches[1]
            );

        $prefix =
            (int)
            $matches[2];

        if (
            !filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4
            )
            || $prefix < 1
            || $prefix > 30
        ) {
            throw new \RuntimeException(
                'Invalid gateway IPv4/CIDR.'
            );
        }

        $ipLong =
            ip2long(
                $ip
            );

        $ipLong &=
            0xFFFFFFFF;

        $mask =
            (
                0xFFFFFFFF
                << (32 - $prefix)
            )
            & 0xFFFFFFFF;

        $network =
            $ipLong
            & $mask;

        return [
            'ip' =>
                $ip,

            'prefix' =>
                $prefix,

            'network_cidr' =>
                long2ip(
                    $network
                )
                . '/'
                . $prefix,
        ];
    }

    protected function client(
        Router $router
    ): Client {
        return new Client(
            new Config([
                'host' =>
                    $router->host,

                'user' =>
                    $router->username,

                'pass' =>
                    $router->password,

                'port' =>
                    (int)
                    $router->api_port,

                'ssl' =>
                    (bool)
                    $router->use_ssl,

                'timeout' => 3,
                'socket_timeout' => 5,
                'attempts' => 1,
                'delay' => 0,

                'socket_options' => [
                    'tcp_nodelay' => true,
                ],
            ])
        );
    }

    protected function read(
        Client $api,
        string $path,
        ?string $properties = null
    ): array {
        $query =
            new Query(
                $path
            );

        if ($properties) {
            $query->equal(
                '.proplist',
                $properties
            );
        }

        return $api
            ->query(
                $query
            )
            ->read();
    }

    protected function write(
        Client $api,
        Query $query
    ): array {
        return $api
            ->query(
                $query
            )
            ->read();
    }

    protected function findBy(
        array $rows,
        string $field,
        string $value
    ): ?array {
        foreach ($rows as $row) {
            if (
                (string) (
                    $row[$field]
                    ?? ''
                ) === $value
            ) {
                return $row;
            }
        }

        return null;
    }

    protected function isTrue(
        mixed $value
    ): bool {
        return in_array(
            strtolower(
                trim(
                    (string)
                    $value
                )
            ),
            [
                '1',
                'true',
                'yes',
                'on',
            ],
            true
        );
    }
}
