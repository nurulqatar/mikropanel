<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IpRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'router_id' => [
                'nullable',
                'integer',
                'exists:routers,id',
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
}
