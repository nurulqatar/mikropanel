<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $routeClient = $this->route('client');

        $clientId = $routeClient instanceof Client
            ? $routeClient->id
            : $routeClient;

        return [
            'router_id' => [
                'required',
                'integer',
                'exists:routers,id',
            ],

            'ip_range_id' => [
                'required',
                'integer',
                'exists:ip_ranges,id',
            ],

            'package_id' => [
                'required',
                'integer',
                'exists:packages,id',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'mac_address' => [
                'required',
                'string',
                'regex:/^([0-9A-Fa-f]{2}:){5}[0-9A-Fa-f]{2}$/',
                Rule::unique(
                    'clients',
                    'mac_address'
                )->ignore($clientId),
            ],

            'phone' => [
                'nullable',
                'string',
                'max:50',
            ],

            /*
             * Edit page compatibility.
             * Create page থেকে এগুলো পাঠাতে হবে না।
             */
            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'address' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'expiry_date' => [
                'nullable',
                'date',
            ],

            'installed_at' => [
                'nullable',
                'date',
            ],

            'billing_day' => [
                'nullable',
                'integer',
                'between:1,31',
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
            'mac_address.regex' =>
                'MAC address format must be AA:BB:CC:DD:EE:FF.',

            'mac_address.unique' =>
                'This MAC address is already assigned to another client.',
        ];
    }
}
