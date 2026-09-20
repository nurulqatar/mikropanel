<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\HotelRouter;
use App\Models\Hotel\HotelUser;
use App\Services\Hotel\HotelMikroTikService;
use App\Services\Hotel\HotelRouterQuotaService;
use App\Services\Hotel\HotelRouterSetupCommandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class RouterController extends Controller
{
    public function index(
        HotelRouterQuotaService $quota,
        HotelRouterSetupCommandService $setup
    ): Response {
        $admin =
            $this->admin();

        $hotel =
            $admin->hotel;

        $routers =
            HotelRouter::query()
                ->where(
                    'hotel_id',
                    $hotel->id
                )
                ->orderBy('name')
                ->get()
                ->map(
                    function (
                        HotelRouter $router
                    ) use ($setup): array {
                        try {
                            $generated =
                                $setup->generate(
                                    $router
                                );

                            $command =
                                $generated[
                                    'script'
                                ];

                            $portalUrl =
                                $generated[
                                    'portal_url'
                                ];

                            $setupError =
                                null;
                        } catch (
                            Throwable $e
                        ) {
                            $command =
                                null;

                            $portalUrl =
                                null;

                            $setupError =
                                mb_substr(
                                    $e->getMessage(),
                                    0,
                                    500
                                );
                        }

                        return [
                            'id' =>
                                $router->id,

                            'name' =>
                                $router->name,

                            'host' =>
                                $router->host,

                            'api_port' =>
                                $router
                                    ->api_port,

                            'username' =>
                                $router
                                    ->username,

                            'use_ssl' =>
                                $router
                                    ->use_ssl,

                            'enabled' =>
                                $router
                                    ->enabled,

                            'status' =>
                                $router
                                    ->status,

                            'router_identity' =>
                                $router
                                    ->router_identity,

                            'routeros_version' =>
                                $router
                                    ->routeros_version,

                            'architecture' =>
                                $router
                                    ->architecture,

                            'guest_interface' =>
                                $router
                                    ->guest_interface,

                            'guest_gateway_cidr' =>
                                $router
                                    ->guest_gateway_cidr,

                            'guest_pool_start' =>
                                $router
                                    ->guest_pool_start,

                            'guest_pool_end' =>
                                $router
                                    ->guest_pool_end,

                            'hotspot_server_name' =>
                                $router
                                    ->hotspot_server_name,

                            'hotspot_profile_name' =>
                                $router
                                    ->hotspot_profile_name,

                            'dns_name' =>
                                $router
                                    ->dns_name,

                            'last_tested_at' =>
                                $router
                                    ->last_tested_at
                                    ?->toISOString(),

                            'last_error' =>
                                $router
                                    ->last_error,

                            'notes' =>
                                $router->notes,

                            'setup_command' =>
                                $command,

                            'setup_error' =>
                                $setupError,

                            'portal_url' =>
                                $portalUrl,
                        ];
                    }
                )
                ->values();

        return Inertia::render(
            'Hotel/Routers/Index',
            [
                'routers' =>
                    $routers,

                'quota' =>
                    $quota->snapshot(
                        $hotel
                    ),
            ]
        );
    }

    public function store(
        Request $request,
        HotelRouterQuotaService $quota,
        HotelRouterSetupCommandService $setup
    ): RedirectResponse {
        $admin =
            $this->admin();

        $hotel =
            $admin->hotel;

        $data =
            $this->validated(
                $request,
                false
            );

        $this->safeHost(
            $data['host']
        );

        DB::transaction(
            function () use (
                $hotel,
                $data,
                $quota,
                $setup
            ): void {
                $quota
                    ->assertCanAddForUpdate(
                        $hotel
                    );

                $this->uniqueEndpoint(
                    $hotel->id,
                    $data['host'],
                    (int)
                    $data['api_port']
                );

                $router =
                    HotelRouter::query()
                        ->create([
                            ...$data,

                            'hotel_id' =>
                                $hotel->id,

                            'status' =>
                                'untested',
                        ]);

                /*
                 * Validate generated network
                 * configuration before commit.
                 * No connection is made.
                 */
                $setup->generate(
                    $router
                );
            }
        );

        return back()->with(
            'success',
            'MikroTik router added.'
        );
    }

    public function update(
        Request $request,
        HotelRouter $router,
        HotelRouterSetupCommandService $setup
    ): RedirectResponse {
        $admin =
            $this->admin();

        $this->owned(
            $router,
            $admin
        );

        $data =
            $this->validated(
                $request,
                true
            );

        $this->safeHost(
            $data['host']
        );

        $this->uniqueEndpoint(
            $admin->hotel_id,
            $data['host'],
            (int)
            $data['api_port'],
            $router->id
        );

        if (
            empty(
                $data['password']
            )
        ) {
            unset(
                $data['password']
            );
        }

        DB::transaction(
            function () use (
                $router,
                $data,
                $setup
            ): void {
                $endpointChanged =
                    $router->host
                        !== $data['host']
                    || (int)
                        $router
                            ->api_port
                        !== (int)
                        $data['api_port']
                    || $router->username
                        !== $data[
                            'username'
                        ]
                    || array_key_exists(
                        'password',
                        $data
                    )
                    || (bool)
                        $router
                            ->use_ssl
                        !== (bool)
                        $data['use_ssl'];

                $router->update(
                    $data
                );

                if ($endpointChanged) {
                    $router->forceFill([
                        'status' =>
                            'untested',

                        'router_identity' =>
                            null,

                        'routeros_version' =>
                            null,

                        'architecture' =>
                            null,

                        'last_error' =>
                            null,
                    ])->save();
                }

                $setup->generate(
                    $router->fresh()
                );
            }
        );

        return back()->with(
            'success',
            'MikroTik router updated.'
        );
    }

    public function destroy(
        HotelRouter $router
    ): RedirectResponse {
        $admin =
            $this->admin();

        $this->owned(
            $router,
            $admin
        );

        $router->delete();

        return back()->with(
            'success',
            'MikroTik router removed from the Hotel panel.'
        );
    }

    public function test(
        HotelRouter $router,
        HotelMikroTikService $mikrotik
    ): RedirectResponse {
        $admin =
            $this->admin();

        $this->owned(
            $router,
            $admin
        );

        if (!$router->enabled) {
            throw ValidationException::withMessages([
                'router' =>
                    'Enable this router before testing the connection.',
            ]);
        }

        $result =
            $mikrotik->test(
                $router
            );

        if (
            !$result['success']
        ) {
            $router->forceFill([
                'status' =>
                    'offline',

                'last_tested_at' =>
                    now(),

                'last_error' =>
                    $result[
                        'message'
                    ],
            ])->save();

            return back()
                ->withErrors([
                    'router' =>
                        'MikroTik connection failed: '
                        . $result[
                            'message'
                        ],
                ]);
        }

        $router->forceFill([
            'status' =>
                'online',

            'router_identity' =>
                $result[
                    'identity'
                ],

            'routeros_version' =>
                $result[
                    'version'
                ],

            'architecture' =>
                $result[
                    'architecture'
                ],

            'last_tested_at' =>
                now(),

            'last_error' =>
                null,
        ])->save();

        return back()->with(
            'success',
            'MikroTik connection successful.'
        );
    }

    private function validated(
        Request $request,
        bool $update
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'host' => [
                'required',
                'ipv4',
            ],

            'api_port' => [
                'required',
                'integer',
                'between:1,65535',
            ],

            'username' => [
                'required',
                'string',
                'max:150',
            ],

            'password' =>
                $update
                    ? [
                        'nullable',
                        'string',
                        'max:255',
                    ]
                    : [
                        'required',
                        'string',
                        'max:255',
                    ],

            'use_ssl' => [
                'required',
                'boolean',
            ],

            'enabled' => [
                'required',
                'boolean',
            ],

            'guest_interface' => [
                'required',
                'string',
                'max:100',
                'not_regex:/[\r\n]/',
            ],

            'guest_gateway_cidr' => [
                'required',
                'string',
                'max:50',
                'regex:/^(?:\d{1,3}\.){3}\d{1,3}\/(?:[89]|1\d|2\d|30)$/',
            ],

            'guest_pool_start' => [
                'required',
                'ipv4',
            ],

            'guest_pool_end' => [
                'required',
                'ipv4',
            ],

            'hotspot_server_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9._-]+$/',
            ],

            'hotspot_profile_name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9._-]+$/',
            ],

            'dns_name' => [
                'required',
                'string',
                'max:253',
                'regex:/^(?=.{1,253}$)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?\.)*[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?$/',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:3000',
            ],
        ]);
    }

    private function safeHost(
        string $host
    ): void {
        $value =
            $this->ipLong(
                $host
            );

        $blocked =
            $this->between(
                $value,
                '0.0.0.0',
                '0.255.255.255'
            )
            || $this->between(
                $value,
                '127.0.0.0',
                '127.255.255.255'
            )
            || $this->between(
                $value,
                '169.254.0.0',
                '169.254.255.255'
            )
            || $this->between(
                $value,
                '224.0.0.0',
                '255.255.255.255'
            );

        if ($blocked) {
            throw ValidationException::withMessages([
                'host' =>
                    'This MikroTik IP address is not allowed.',
            ]);
        }
    }

    private function ipLong(
        string $ip
    ): int {
        $value =
            ip2long(
                $ip
            );

        if ($value === false) {
            throw ValidationException::withMessages([
                'host' =>
                    'A valid IPv4 address is required.',
            ]);
        }

        return (int)
            sprintf(
                '%u',
                $value
            );
    }

    private function between(
        int $value,
        string $first,
        string $last
    ): bool {
        $min =
            (int)
            sprintf(
                '%u',
                ip2long(
                    $first
                )
            );

        $max =
            (int)
            sprintf(
                '%u',
                ip2long(
                    $last
                )
            );

        return $value >= $min
            && $value <= $max;
    }

    private function uniqueEndpoint(
        int $hotelId,
        string $host,
        int $port,
        ?int $ignoreId = null
    ): void {
        $query =
            HotelRouter::query()
                ->where(
                    'hotel_id',
                    $hotelId
                )
                ->where(
                    'host',
                    $host
                )
                ->where(
                    'api_port',
                    $port
                );

        if ($ignoreId !== null) {
            $query->where(
                'id',
                '!=',
                $ignoreId
            );
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'host' =>
                    'This MikroTik API endpoint already exists for the Hotel.',
            ]);
        }
    }

    private function owned(
        HotelRouter $router,
        HotelUser $admin
    ): void {
        abort_unless(
            $router->hotel_id
                === $admin->hotel_id,
            404
        );
    }

    private function admin(): HotelUser
    {
        $user =
            Auth::guard('hotel')
                ->user();

        abort_unless(
            $user
            && $user->isAdmin(),
            403
        );

        return $user;
    }
}
