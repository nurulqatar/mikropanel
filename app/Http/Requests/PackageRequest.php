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
            'zone_id' => [
                'required',
                'integer',
                'exists:network_zones,id',
            ],

            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'validity_days' => [
                'required',
                'integer',
                'min:1',
            ],

            'speed_download' => [
                'required',
                'string',
                'max:50',
            ],

            'speed_upload' => [
                'required',
                'string',
                'max:50',
            ],

            'mikrotik_profile' => [
                'nullable',
                'string',
                'max:100',
            ],

            /* PACKAGE_ROAMING_COVERAGE_REQUEST_V1 */

            'coverage_mode' => [

                'sometimes',

                'string',

                'in:home_zone,all_zones',

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
                'Network Zone is required.',

            'name.required' =>
                'Package name is required.',

            'price.required' =>
                'Price is required.',

            'validity_days.required' =>
                'Validity is required.',

            'speed_download.required' =>
                'Download speed is required.',

            'speed_upload.required' =>
                'Upload speed is required.',
        ];
    }
}
