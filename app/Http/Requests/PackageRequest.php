<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'validity_days' => ['required', 'integer', 'min:1'],
            'speed_download' => ['required', 'string', 'max:50'],
            'speed_upload' => ['required', 'string', 'max:50'],
            'mikrotik_profile' => ['nullable', 'string', 'max:100'],
            'enabled' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Package name is required.',
            'price.required' => 'Price is required.',
            'validity_days.required' => 'Validity is required.',
            'speed_download.required' => 'Download speed is required.',
            'speed_upload.required' => 'Upload speed is required.',
        ];
    }
}
