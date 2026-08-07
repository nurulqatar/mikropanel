<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Throwable;

class PanelSettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            if (!Schema::hasTable('settings')) {
                return;
            }

            $all = Setting::allDecoded();

            $publicKeys = [
                'panel_name',
                'company_name',
                'legal_name',
                'company_phone',
                'company_whatsapp',
                'company_email',
                'support_email',
                'website',
                'company_address',
                'company_city',
                'company_country',
                'cr_number',
                'tax_number',
                'company_logo_path',
                'currency_code',
                'currency_symbol',
                'timezone',
                'date_format',
                'time_format',
                'invoice_terms',
                'invoice_footer',
                'authorized_signature',
                'show_logo_on_documents',
                'report_footer',
            ];

            $settings = [];

            foreach ($publicKeys as $key) {
                if (
                    array_key_exists(
                        $key,
                        $all
                    )
                ) {
                    $settings[$key] =
                        $all[$key];
                }
            }

            $logoPath =
                $settings[
                    'company_logo_path'
                ] ?? null;

            $settings['company_logo_url'] =
                $logoPath
                    ? Storage::disk('public')
                        ->url($logoPath)
                    : null;

            View::share(
                'panelSettings',
                $settings
            );

            Inertia::share(
                'panelSettings',
                $settings
            );
        } catch (Throwable) {
            /*
             * Migration বা database unavailable
             * হলেও application boot বন্ধ হবে না।
             */
        }
    }
}
