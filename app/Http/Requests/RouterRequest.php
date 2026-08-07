<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RouterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $passwordRule = $this->isMethod('POST')
            ? ['required', 'string']
            : ['nullable', 'string'];

        return [
            'name' => ['required', 'string', 'max:100'],
            'host' => ['required', 'ip'],
            'api_port' => ['required', 'integer', 'between:1,65535'],
            'username' => ['required', 'string', 'max:100'],
            'password' => $passwordRule,
            'use_ssl' => ['nullable', 'boolean'],
            'enabled' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Router name is required.',
            'host.required' => 'Router IP is required.',
            'host.ip' => 'Enter a valid IP address.',
            'api_port.required' => 'API port is required.',
            'username.required' => 'Username is required.',
            'password.required' => 'Password is required.',
        ];
    }
}
