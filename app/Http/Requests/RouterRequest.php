<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $passwordRule =
            $this->isMethod('POST')
                ? ['required', 'string']
                : ['nullable', 'string'];

        /*
         * ROUTER_SERVICE_ZONE_VALIDATION_V2
         *
         * Router service is determined by its
         * Network Zone:
         *
         * mac     => MAC/DHCP/ARP client router
         * hotspot => MikroTik Hotspot router
         */
        $isMacZone =
            $this->selectedZoneType()
            === 'mac';

        $clientInterfaceRules =
            $isMacZone
                ? [
                    'required',
                    'string',
                    'max:100',
                ]
                : [
                    'nullable',
                    'string',
                    'max:100',
                ];

        $dhcpServerRules =
            $isMacZone
                ? [
                    'required',
                    'string',
                    'max:100',
                ]
                : [
                    'nullable',
                    'string',
                    'max:100',
                ];

        return [
            'zone_id' => [
                'required',
                'integer',
                $this->routerZoneRule(),
            ],

            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'host' => [
                'required',
                'ip',
            ],

            'api_port' => [
                'required',
                'integer',
                'between:1,65535',
            ],

            'username' => [
                'required',
                'string',
                'max:100',
            ],

            'password' =>
                $passwordRule,

            'client_interface' =>
                $clientInterfaceRules,

            'dhcp_server' =>
                $dhcpServerRules,

            'use_ssl' => [
                'nullable',
                'boolean',
            ],

            'enabled' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'zone_id.required' =>
                'Select a Network Zone for this MikroTik router.',

            'zone_id.exists' =>
                'Selected Network Zone is not available to your account.',

            'name.required' =>
                'Router name is required.',

            'host.required' =>
                'Router IP is required.',

            'host.ip' =>
                'Enter a valid IP address.',

            'api_port.required' =>
                'API port is required.',

            'username.required' =>
                'Username is required.',

            'password.required' =>
                'Password is required.',

            'client_interface.required' =>
                'MAC Client Interface is required for a MAC Network Zone.',

            'dhcp_server.required' =>
                'DHCP Server is required for a MAC Network Zone.',
        ];
    }

    private function routerZoneRule()
    {
        $user =
            $this->user();

        return Rule::exists(
            'network_zones',
            'id'
        )->where(
            function ($query) use ($user): void {
                $query
                    ->where(
                        'enabled',
                        true
                    )
                    ->whereIn(
                        'service_type',
                        [
                            'mac',
                            'hotspot',
                        ]
                    );

                if (
                    $user
                    && method_exists(
                        $user,
                        'isSuperAdmin'
                    )
                    && $user->isSuperAdmin()
                ) {
                    return;
                }

                if (
                    $user
                    && $user->reseller_id
                ) {
                    $query->where(
                        'reseller_id',
                        (int)
                        $user->reseller_id
                    );

                    /*
                     * Normal operators remain locked
                     * to their assigned zone.
                     */
                    if (
                        method_exists(
                            $user,
                            'isOperator'
                        )
                        && $user->isOperator()
                    ) {
                        $query->where(
                            'id',
                            (int)
                            $user->zone_id
                        );
                    }

                    return;
                }

                $query->whereNull(
                    'reseller_id'
                );
            }
        );
    }

    private function selectedZoneType(): ?string
    {
        $zoneId =
            (int)
            $this->input(
                'zone_id',
                0
            );

        if (!$zoneId) {
            return null;
        }

        $type =
            DB::table(
                'network_zones'
            )
                ->where(
                    'id',
                    $zoneId
                )
                ->value(
                    'service_type'
                );

        return $type
            ? (string) $type
            : null;
    }
}
