<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IpRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user =
            $this->user();

        $zoneRule =
            Rule::exists(
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

        $routerRule =
            Rule::exists(
                'routers',
                'id'
            )->where(
                function ($query) use ($user): void {
                    if (
                        $this->filled(
                            'zone_id'
                        )
                    ) {
                        $query->where(
                            'zone_id',
                            (int)
                            $this->input(
                                'zone_id'
                            )
                        );
                    }

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

                        return;
                    }

                    $query->whereNull(
                        'reseller_id'
                    );
                }
            );

        return [
            /*
             * IP_POOL_MAC_ZONE_VALIDATION_V1
             */
            'zone_id' => [
                'required',
                'integer',
                $zoneRule,
            ],

            'router_id' => [
                'nullable',
                'integer',
                $routerRule,
            ],

            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'interface' => [
                'nullable',
                'string',
                'max:100',
            ],

            'network' => [
                'required',
                'string',
                'max:50',
            ],

            'gateway' => [
                'required',
                'ip',
            ],

            'dns_server' => [
                'nullable',
                'ip',
            ],

            'start_ip' => [
                'required',
                'ip',
            ],

            'end_ip' => [
                'required',
                'ip',
            ],

            'enabled' => [
                'boolean',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'zone_id.required' =>
                'Select the MAC Network Zone for this IP Pool.',

            'zone_id.exists' =>
                'Selected MAC Network Zone is not available to your account.',

            'router_id.exists' =>
                'Selected router must belong to the same Network Zone.',
        ];
    }
}
