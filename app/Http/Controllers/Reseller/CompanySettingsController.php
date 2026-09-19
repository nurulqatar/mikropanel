<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerSetting;
use App\Models\User;
use App\Services\CompanyBrandingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    public function index(
        Request $request,
        CompanyBrandingService $branding
    ): Response {
        $owner =
            $this->owner(
                $request
            );

        $reseller =
            Reseller::query()
                ->findOrFail(
                    $owner->reseller_id
                );

        return Inertia::render(
            'Reseller/CompanySettings',
            [
                'company' => [
                    ...$branding
                        ->forResellerId(
                            $reseller->id
                        ),

                    'owner_name' =>
                        $reseller->owner_name,

                    'timezone' =>
                        $reseller->timezone
                        ?: 'Asia/Qatar',

                    'currency' =>
                        $reseller->currency
                        ?: 'QAR',
                ],
            ]
        );
    }

    public function update(
        Request $request
    ): RedirectResponse {
        $owner =
            $this->owner(
                $request
            );

        $data =
            $request->validate([
                'company_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'panel_name' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'owner_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'company_email' => [
                    'nullable',
                    'email',
                    'max:255',
                ],

                'company_phone' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'company_address' => [
                    'nullable',
                    'string',
                    'max:1500',
                ],

                'website' => [
                    'nullable',
                    'url',
                    'max:500',
                ],

                'timezone' => [
                    'required',
                    'timezone',
                ],

                'currency' => [
                    'required',
                    'string',
                    'max:10',
                ],

                'invoice_terms' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],

                'invoice_footer' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'authorized_signature' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'show_logo_on_documents' => [
                    'nullable',
                ],

                'company_logo' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:4096',
                ],
            ]);

        $reseller =
            Reseller::query()
                ->findOrFail(
                    $owner->reseller_id
                );

        $reseller->fill([
            'company_name' =>
                trim(
                    $data['company_name']
                ),

            'owner_name' =>
                trim(
                    $data['owner_name']
                ),

            'email' =>
                filled(
                    $data['company_email']
                    ?? null
                )
                    ? strtolower(
                        trim(
                            $data[
                                'company_email'
                            ]
                        )
                    )
                    : null,

            'phone' =>
                filled(
                    $data['company_phone']
                    ?? null
                )
                    ? trim(
                        $data[
                            'company_phone'
                        ]
                    )
                    : null,

            'address' =>
                filled(
                    $data['company_address']
                    ?? null
                )
                    ? trim(
                        $data[
                            'company_address'
                        ]
                    )
                    : null,

            'timezone' =>
                $data['timezone'],

            'currency' =>
                strtoupper(
                    trim(
                        $data['currency']
                    )
                ),
        ]);

        $reseller->save();

        $settings = [
            'panel_name' => [
                $data['panel_name']
                ?? null,
                'company',
                'string',
            ],

            'website' => [
                $data['website']
                ?? null,
                'company',
                'string',
            ],

            'invoice_terms' => [
                $data['invoice_terms']
                ?? null,
                'billing',
                'string',
            ],

            'invoice_footer' => [
                $data['invoice_footer']
                ?? null,
                'billing',
                'string',
            ],

            'authorized_signature' => [
                $data[
                    'authorized_signature'
                ]
                ?? null,
                'billing',
                'string',
            ],

            'show_logo_on_documents' => [
                $request->boolean(
                    'show_logo_on_documents'
                ),
                'billing',
                'boolean',
            ],
        ];

        foreach (
            $settings
            as $key => [
                $value,
                $group,
                $type,
            ]
        ) {
            ResellerSetting::setValue(
                resellerId:
                    $reseller->id,

                key:
                    $key,

                value:
                    $value,

                group:
                    $group,

                type:
                    $type
            );
        }

        if (
            $request->hasFile(
                'company_logo'
            )
        ) {
            $old =
                ResellerSetting::getValue(
                    $reseller->id,
                    'company_logo_path'
                );

            if ($old) {
                Storage::disk('public')
                    ->delete($old);
            }

            $path =
                $request
                    ->file(
                        'company_logo'
                    )
                    ->store(
                        'company-branding/'
                        . $reseller->id,
                        'public'
                    );

            ResellerSetting::setValue(
                resellerId:
                    $reseller->id,

                key:
                    'company_logo_path',

                value:
                    $path,

                group:
                    'company'
            );
        }

        return back()->with(
            'success',
            'Company settings updated.'
        );
    }

    public function removeLogo(
        Request $request
    ): RedirectResponse {
        $owner =
            $this->owner(
                $request
            );

        $path =
            ResellerSetting::getValue(
                $owner->reseller_id,
                'company_logo_path'
            );

        if ($path) {
            Storage::disk('public')
                ->delete($path);
        }

        ResellerSetting::setValue(
            resellerId:
                $owner->reseller_id,

            key:
                'company_logo_path',

            value:
                null,

            group:
                'company'
        );

        return back()->with(
            'success',
            'Company logo removed.'
        );
    }

    private function owner(
        Request $request
    ): User {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->reseller_id
            && $user
                ->isResellerOwner(),
            403
        );

        return $user;
    }
}
