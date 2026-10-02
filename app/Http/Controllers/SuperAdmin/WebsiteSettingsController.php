<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteSettingsController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $this->superAdmin(
            $request
        );

        $data =
            $this->values();

        $logoPath =
            Setting::getValue(
                'website_logo_path'
            );

        $data['logo_url'] =
            $logoPath
            && Storage::disk('public')
                ->exists($logoPath)
                ? Storage::disk('public')
                    ->url($logoPath)
                : null;

        return Inertia::render(
            'SuperAdmin/WebsiteSettings',
            [
                'website' =>
                    $data,
            ]
        );
    }

    public function update(
        Request $request
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        $data =
            $request->validate([
                'website_name' => [
                    'required',
                    'string',
                    'max:150',
                ],

                'website_tagline' => [
                    'nullable',
                    'string',
                    'max:300',
                ],

                'hero_badge' => [
                    'nullable',
                    'string',
                    'max:200',
                ],

                'hero_title' => [
                    'nullable',
                    'string',
                    'max:500',
                ],

                'hero_text' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'pricing_title' => [
                    'nullable',
                    'string',
                    'max:300',
                ],

                'pricing_text' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'company_phone' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'company_whatsapp' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'company_email' => [
                    'nullable',
                    'email',
                    'max:255',
                ],

                'company_address' => [
                    'nullable',
                    'string',
                    'max:1500',
                ],

                'footer_text' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'terms_content' => [
                    'nullable',
                    'string',
                    'max:30000',
                ],

                'privacy_content' => [
                    'nullable',
                    'string',
                    'max:30000',
                ],

                'website_logo' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:4096',
                ],
            ]);

        $map = [
            'website_name' =>
                ['website', 'string'],

            'website_tagline' =>
                ['website', 'string'],

            'hero_badge' =>
                ['website', 'string'],

            'hero_title' =>
                ['website', 'string'],

            'hero_text' =>
                ['website', 'string'],

            'pricing_title' =>
                ['website', 'string'],

            'pricing_text' =>
                ['website', 'string'],

            'footer_text' =>
                ['website', 'string'],

            'terms_content' =>
                ['website', 'string'],

            'privacy_content' =>
                ['website', 'string'],

            'company_phone' =>
                ['company', 'string'],

            'company_whatsapp' =>
                ['company', 'string'],

            'company_email' =>
                ['company', 'string'],

            'company_address' =>
                ['company', 'string'],
        ];

        foreach (
            $map
            as $key => [$group, $type]
        ) {
            Setting::setValue(
                key: $key,
                value:
                    $data[$key]
                    ?? null,
                group: $group,
                type: $type
            );
        }

        if (
            $request->hasFile(
                'website_logo'
            )
        ) {
            $old =
                Setting::getValue(
                    'website_logo_path'
                );

            if ($old) {
                Storage::disk('public')
                    ->delete($old);
            }

            $path =
                $request
                    ->file(
                        'website_logo'
                    )
                    ->store(
                        'website-branding',
                        'public'
                    );

            Setting::setValue(
                key: 'website_logo_path',
                value: $path,
                group: 'website'
            );
        }

        return back()->with(
            'success',
            'Website settings updated.'
        );
    }

    public function removeLogo(
        Request $request
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        $path =
            Setting::getValue(
                'website_logo_path'
            );

        if ($path) {
            Storage::disk('public')
                ->delete($path);
        }

        Setting::setValue(
            key: 'website_logo_path',
            value: null,
            group: 'website'
        );

        return back()->with(
            'success',
            'Website logo removed.'
        );
    }

    private function values(): array
    {
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

            'company_phone' =>
                Setting::getValue(
                    'company_phone',
                    ''
                ),

            'company_whatsapp' =>
                Setting::getValue(
                    'company_whatsapp',
                    ''
                ),

            'company_email' =>
                Setting::getValue(
                    'company_email',
                    ''
                ),

            'company_address' =>
                Setting::getValue(
                    'company_address',
                    ''
                ),

            'footer_text' =>
                Setting::getValue(
                    'footer_text',
                    'ISP & MikroTik Operations Platform'
                ),

            'terms_content' =>
                Setting::getValue(
                    'terms_content',
                    $this->defaultTerms()
                ),

            'privacy_content' =>
                Setting::getValue(
                    'privacy_content',
                    $this->defaultPrivacy()
                ),
        ];
    }

    private function defaultTerms(): string
    {
        return <<<'TEXT'
Account Use

Company accounts must be used for lawful network and customer-management operations. Account owners are responsible for their staff access and credentials.

Subscriptions

Plan limits, validity, pricing and access follow the active Company subscription. Access may be restricted after expiry or suspension.

Operational Responsibility

Each Company is responsible for its own customer relationships, local network deployment, billing practices and lawful use of connected MikroTik infrastructure.
TEXT;
    }

    private function defaultPrivacy(): string
    {
        return <<<'TEXT'
Information We Collect

Registration information may include Company name, owner name, email, phone, service address, selected package, IP address and basic browser information.

How Information Is Used

Information is used to create and secure accounts, review applications, operate subscriptions, provide support and maintain platform audit history.

Security and Retention

Passwords use the application's secure password hashing system. Operational and account history may be retained for accounting, security and audit purposes.
TEXT;
    }

    private function superAdmin(
        Request $request
    ): void {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->is_active
            && $user->isSuperAdmin(),
            403
        );
    }
}
