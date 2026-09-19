<?php

namespace App\Http\Controllers;

use App\Models\ResellerPlan;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PublicWebsiteController extends Controller
{
    public function index(): Response
    {
        $site =
            $this->site();

        return Inertia::render(
            'Public/Home',
            [
                'brand' =>
                    $site[
                        'website_name'
                    ],

                'site' =>
                    $site,

                'plans' =>
                    $this->plans(),
            ]
        );
    }

    public function terms(): Response
    {
        $site =
            $this->site();

        return Inertia::render(
            'Public/Legal',
            [
                'brand' =>
                    $site[
                        'website_name'
                    ],

                'site' =>
                    $site,

                'type' =>
                    'terms',

                'content' =>
                    $site[
                        'terms_content'
                    ],
            ]
        );
    }

    public function privacy(): Response
    {
        $site =
            $this->site();

        return Inertia::render(
            'Public/Legal',
            [
                'brand' =>
                    $site[
                        'website_name'
                    ],

                'site' =>
                    $site,

                'type' =>
                    'privacy',

                'content' =>
                    $site[
                        'privacy_content'
                    ],
            ]
        );
    }

    private function plans(): array
    {
        return ResellerPlan::query()
            ->where(
                'active',
                true
            )
            ->orderBy(
                'is_unlimited'
            )
            ->orderBy('price')
            ->orderBy(
                'client_limit'
            )
            ->get()
            ->map(
                fn (
                    ResellerPlan $plan
                ): array => [
                    'id' =>
                        $plan->id,

                    'name' =>
                        $plan->name,

                    'code' =>
                        $plan->code,

                    'client_limit' =>
                        $plan
                            ->client_limit,

                    'is_unlimited' =>
                        (bool)
                        $plan
                            ->is_unlimited,

                    'price' =>
                        (float)
                        $plan->price,

                    'validity_days' =>
                        $plan
                            ->validity_days,

                    'features' =>
                        collect(
                            $plan->features
                            ?? []
                        )
                            ->filter(
                                fn ($item) =>
                                    is_string(
                                        $item
                                    )
                            )
                            ->values()
                            ->all(),

                    'notes' =>
                        $plan->notes,

                    'is_free_trial' =>
                        !$plan
                            ->is_unlimited
                        && (float)
                            $plan->price
                            <= 0.0001
                        && (int)
                            $plan
                                ->validity_days
                            === 7,
                ]
            )
            ->values()
            ->all();
    }

    private function site(): array
    {
        $logoPath =
            Setting::getValue(
                'website_logo_path'
            );

        $logoUrl =
            $logoPath
            && Storage::disk('public')
                ->exists($logoPath)
                ? Storage::disk('public')
                    ->url($logoPath)
                : null;

        $phone =
            trim(
                (string)
                Setting::getValue(
                    'company_phone',
                    ''
                )
            );

        $whatsapp =
            trim(
                (string)
                Setting::getValue(
                    'company_whatsapp',
                    ''
                )
            );

        $email =
            trim(
                (string)
                Setting::getValue(
                    'company_email',
                    ''
                )
            );

        $digits =
            preg_replace(
                '/\D+/',
                '',
                $whatsapp
            );

        $contactUrl =
            $digits
                ? 'https://wa.me/'
                    . $digits
                : (
                    $email !== ''
                        ? 'mailto:' . $email
                        : (
                            $phone !== ''
                                ? 'tel:' . preg_replace(
                                    '/\s+/',
                                    '',
                                    $phone
                                )
                                : null
                        )
                );

        return [
            'website_name' =>
                Setting::getValue(
                    'website_name',
                    Setting::getValue(
                        'panel_name',
                        'MikroPanel'
                    )
                ),

            'website_tagline' =>
                Setting::getValue(
                    'website_tagline',
                    'Professional ISP & MikroTik Operations Platform'
                ),

            'logo_url' =>
                $logoUrl,

            'hero_badge' =>
                Setting::getValue(
                    'hero_badge',
                    'MikroTik ISP Operations · Company SaaS'
                ),

            'hero_title' =>
                Setting::getValue(
                    'hero_title',
                    'Run your network, clients and cash flow from one professional control panel.'
                ),

            'hero_text' =>
                Setting::getValue(
                    'hero_text',
                    'Manage clients, Network Zones, MikroTik synchronization, Hotspot, billing, staff and accounting from one platform.'
                ),

            'pricing_title' =>
                Setting::getValue(
                    'pricing_title',
                    'Choose your Company package'
                ),

            'pricing_text' =>
                Setting::getValue(
                    'pricing_text',
                    'Active packages are managed by Super Admin and appear here automatically.'
                ),

            'footer_text' =>
                Setting::getValue(
                    'footer_text',
                    'ISP & MikroTik Operations Platform'
                ),

            'company_phone' =>
                $phone,

            'company_whatsapp' =>
                $whatsapp,

            'company_email' =>
                $email,

            'company_address' =>
                Setting::getValue(
                    'company_address',
                    ''
                ),

            'contact_url' =>
                $contactUrl,

            'terms_content' =>
                Setting::getValue(
                    'terms_content',
                    'Company accounts must be used for lawful network and customer-management operations.'
                ),

            'privacy_content' =>
                Setting::getValue(
                    'privacy_content',
                    'Registration and operational information is used to provide, secure and administer the service.'
                ),
        ];
    }
}
