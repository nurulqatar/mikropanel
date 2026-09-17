<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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

        return [
            /*
             * ROUTER_MAC_ZONE_VALIDATION_V1
             *
             * Router records are MAC-client routers.
             * Hotspot service ownership is carried by
             * HotspotServer.zone_id separately.
             */
            'zone_id' => [
                'required',
                'integer',
                $this->macZoneRule(),
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

            'client_interface' => [
                'required',
                'string',
                'max:100',
            ],

            'dhcp_server' => [
                'required',
                'string',
                'max:100',
            ],

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
                'Select the MAC Network Zone for this router.',

            'zone_id.exists' =>
                'Selected MAC Network Zone is not available to your account.',

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
                'Client Interface is required.',

            'dhcp_server.required' =>
                'DHCP Server is required.',
        ];
    }

    private function macZoneRule()
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
                        'service_type',
                        'mac'
                    )
                    ->where(
                        'enabled',
                        true
                    );

                /*
                 * Super Admin may manage all tenants.
                 */
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

                /*
                 * Reseller owner/manager/operator:
                 * zone must belong to their reseller.
                 */
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
                     * Normal Operator is permanently
                     * locked to one MAC zone.
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

                /*
                 * Platform admin/operator only uses
                 * platform-owned MAC zones.
                 */
                $query->whereNull(
                    'reseller_id'
                );
            }
        );
    }
}
