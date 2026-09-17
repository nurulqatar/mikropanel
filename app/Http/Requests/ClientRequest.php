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
                )
                    ->whereNull(
                        'deleted_at'
                    )
                    ->ignore(
                        $clientId
                    ),
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
            /*
             * Qatar ID / Passport information.
             * Every document field is optional.
             */
            'qatar_id_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'qatar_id_expiry_date' => [
                'nullable',
                'date',
            ],

            'occupation' => [
                'nullable',
                'string',
                'max:255',
            ],

            'passport_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'passport_expiry_date' => [
                'nullable',
                'date',
            ],

            'document_serial_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'residency_type' => [
                'nullable',
                'string',
                'max:255',
            ],

            'employer' => [
                'nullable',
                'string',
                'max:255',
            ],

            'place_of_birth' => [
                'nullable',
                'string',
                'max:255',
            ],

            'passport_issue_date' => [
                'nullable',
                'date',
            ],

            'issuing_country' => [
                'nullable',
                'string',
                'max:255',
            ],


            'identity_type' => [
                'nullable',
                'string',
                Rule::in([
                    'qatar_id',
                    'passport',
                    'other',
                ]),
            ],

            'identity_number' => [
                'nullable',
                'string',
                'max:150',
            ],

            'identity_barcode' => [
                'nullable',
                'string',
                'max:4000',
            ],

            /*
             * Private staged identity images.
             * UUID only; browser never submits a path.
             */
            'qatar_id_front_scan_token' => [
                'nullable',
                'uuid',
            ],

            'qatar_id_back_scan_token' => [
                'nullable',
                'uuid',
            ],

            'passport_scan_token' => [
                'nullable',
                'uuid',
            ],

            'nationality' => [
                'nullable',
                'string',
                'max:120',
            ],

            'date_of_birth' => [
                'nullable',
                'date',
            ],

            'gender' => [
                'nullable',
                'string',
                'in:M,F,X,male,female,other',
            ],

            'document_expiry_date' => [
                'nullable',
                'date',
            ],

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
