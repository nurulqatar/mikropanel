<?php

namespace App\Http\Controllers;

use App\Models\IpRange;
use App\Models\Package;
use App\Models\Router;
use App\Models\Setting;
use Illuminate\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    private const SECRET_KEYS = [
        'smtp_password',
        'sms_api_token',
        'whatsapp_api_token',
    ];

    public function index(): Response
    {
        $saved = Setting::allDecoded();

        $settings = array_replace(
            $this->defaults(),
            $saved
        );

        foreach (self::SECRET_KEYS as $key) {
            $settings[$key] = '';
        }

        $logoPath = Setting::getValue(
            'company_logo_path'
        );

        $settings['company_logo_url'] =
            $logoPath
                ? Storage::disk('public')
                    ->url($logoPath)
                : null;

        return Inertia::render(
            'Settings/Index',
            [
                'settings' => $settings,

                'secretStatus' => [
                    'smtp_password' =>
                        filled(
                            Setting::getValue(
                                'smtp_password'
                            )
                        ),

                    'sms_api_token' =>
                        filled(
                            Setting::getValue(
                                'sms_api_token'
                            )
                        ),

                    'whatsapp_api_token' =>
                        filled(
                            Setting::getValue(
                                'whatsapp_api_token'
                            )
                        ),
                ],

                'routers' => Router::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'host',
                    ]),

                'packages' => Package::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'price',
                    ]),

                'ipRanges' => IpRange::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'router_id',
                        'name',
                        'start_ip',
                        'end_ip',
                    ]),

                'systemInfo' => [
                    'panel_version' =>
                        'MikroPanel v1.0',

                    'laravel_version' =>
                        Application::VERSION,

                    'php_version' =>
                        PHP_VERSION,

                    'environment' =>
                        app()->environment(),

                    'server_timezone' =>
                        config('app.timezone'),
                ],
            ]
        );
    }

    public function update(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            /*
             * Company & Branding
             */
            'panel_name' => [
                'required',
                'string',
                'max:100',
            ],

            'company_name' => [
                'required',
                'string',
                'max:255',
            ],

            'legal_name' => [
                'nullable',
                'string',
                'max:255',
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

            'support_email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'website' => [
                'nullable',
                'url',
                'max:255',
            ],

            'company_address' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'company_city' => [
                'nullable',
                'string',
                'max:100',
            ],

            'company_country' => [
                'required',
                'string',
                'max:100',
            ],

            'cr_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'tax_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'company_logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],

            /*
             * Localization
             */
            'timezone' => [
                'required',
                'string',
                'max:100',
            ],

            'currency_code' => [
                'required',
                'string',
                'size:3',
            ],

            'currency_symbol' => [
                'required',
                'string',
                'max:20',
            ],

            'language' => [
                'required',
                Rule::in([
                    'en',
                    'bn',
                ]),
            ],

            'date_format' => [
                'required',
                Rule::in([
                    'd/m/Y',
                    'Y-m-d',
                    'd-m-Y',
                    'm/d/Y',
                ]),
            ],

            'time_format' => [
                'required',
                Rule::in([
                    'H:i',
                    'h:i A',
                ]),
            ],

            /*
             * Billing & Renewal
             */
            'invoice_prefix' => [
                'required',
                'string',
                'max:20',
            ],

            'receipt_prefix' => [
                'required',
                'string',
                'max:20',
            ],

            'default_due_days' => [
                'required',
                'integer',
                'min:0',
                'max:365',
            ],

            'grace_days' => [
                'required',
                'integer',
                'min:0',
                'max:90',
            ],

            'auto_billing_enabled' => [
                'required',
                'boolean',
            ],

            'tax_enabled' => [
                'required',
                'boolean',
            ],

            'tax_name' => [
                'nullable',
                'string',
                'max:50',
            ],

            'tax_rate' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
            ],

            'allow_partial_payment' => [
                'required',
                'boolean',
            ],

            'allow_credit_renewal' => [
                'required',
                'boolean',
            ],

            'show_previous_due' => [
                'required',
                'boolean',
            ],

            'renewal_mode' => [
                'required',
                Rule::in([
                    'from_expiry',
                    'from_payment_date',
                ]),
            ],

            'invoice_terms' => [
                'nullable',
                'string',
                'max:3000',
            ],

            'invoice_footer' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'authorized_signature' => [
                'nullable',
                'string',
                'max:100',
            ],

            'show_logo_on_documents' => [
                'required',
                'boolean',
            ],

            /*
             * Client defaults
             */
            'default_router_id' => [
                'nullable',
                'integer',
                'exists:routers,id',
            ],

            'default_package_id' => [
                'nullable',
                'integer',
                'exists:packages,id',
            ],

            'default_ip_range_id' => [
                'nullable',
                'integer',
                'exists:ip_ranges,id',
            ],

            /*
             * Network automation
             */
            'auto_suspend_enabled' => [
                'required',
                'boolean',
            ],

            'connection_sync_enabled' => [
                'required',
                'boolean',
            ],

            'usage_sync_enabled' => [
                'required',
                'boolean',
            ],

            'router_api_timeout' => [
                'required',
                'integer',
                'min:3',
                'max:120',
            ],

            'offline_after_minutes' => [
                'required',
                'integer',
                'min:1',
                'max:1440',
            ],

            /*
             * Notifications
             */
            'payment_receipt_enabled' => [
                'required',
                'boolean',
            ],

            'due_reminder_enabled' => [
                'required',
                'boolean',
            ],

            'expiry_reminder_enabled' => [
                'required',
                'boolean',
            ],

            'reminder_days_before' => [
                'required',
                'integer',
                'min:0',
                'max:60',
            ],

            'email_notifications_enabled' => [
                'required',
                'boolean',
            ],

            'sms_notifications_enabled' => [
                'required',
                'boolean',
            ],

            'whatsapp_notifications_enabled' => [
                'required',
                'boolean',
            ],

            'payment_message_template' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'due_message_template' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'expiry_message_template' => [
                'nullable',
                'string',
                'max:2000',
            ],

            /*
             * Email gateway
             */
            'smtp_host' => [
                'nullable',
                'string',
                'max:255',
            ],

            'smtp_port' => [
                'nullable',
                'integer',
                'min:1',
                'max:65535',
            ],

            'smtp_username' => [
                'nullable',
                'string',
                'max:255',
            ],

            'smtp_password' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'smtp_encryption' => [
                'nullable',
                Rule::in([
                    'tls',
                    'ssl',
                    'none',
                ]),
            ],

            'smtp_from_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'smtp_from_email' => [
                'nullable',
                'email',
                'max:255',
            ],

            /*
             * SMS / WhatsApp gateway
             */
            'sms_api_url' => [
                'nullable',
                'url',
                'max:1000',
            ],

            'sms_api_token' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'sms_sender_id' => [
                'nullable',
                'string',
                'max:100',
            ],

            'whatsapp_api_url' => [
                'nullable',
                'url',
                'max:1000',
            ],

            'whatsapp_api_token' => [
                'nullable',
                'string',
                'max:2000',
            ],

            /*
             * Accounting & Reports
             */
            'accounting_basis' => [
                'required',
                Rule::in([
                    'cash',
                ]),
            ],

            'fiscal_year_start_month' => [
                'required',
                'integer',
                'min:1',
                'max:12',
            ],

            'report_footer' => [
                'nullable',
                'string',
                'max:1000',
            ],

            /*
             * System
             */
            'items_per_page' => [
                'required',
                'integer',
                'min:10',
                'max:500',
            ],

            'activity_log_enabled' => [
                'required',
                'boolean',
            ],

            'system_notes' => [
                'nullable',
                'string',
                'max:5000',
            ],
        ]);

        foreach (
            $this->definitions()
            as $key => $definition
        ) {
            if (
                !array_key_exists(
                    $key,
                    $data
                )
            ) {
                continue;
            }

            /*
             * Secret field blank থাকলে আগের secret
             * অপরিবর্তিত থাকবে।
             */
            if (
                in_array(
                    $key,
                    self::SECRET_KEYS,
                    true
                )
                && blank($data[$key])
            ) {
                continue;
            }

            Setting::setValue(
                key: $key,
                value: $data[$key],
                group: $definition['group'],
                type: $definition['type'],
                encrypted:
                    $definition['encrypted']
                    ?? false
            );
        }

        if ($request->hasFile('company_logo')) {
            $oldLogo = Setting::getValue(
                'company_logo_path'
            );

            if ($oldLogo) {
                Storage::disk('public')
                    ->delete($oldLogo);
            }

            $path = $request
                ->file('company_logo')
                ->store(
                    'branding',
                    'public'
                );

            Setting::setValue(
                key: 'company_logo_path',
                value: $path,
                group: 'company'
            );
        }

        return back()->with(
            'success',
            'ISP panel settings saved successfully.'
        );
    }

    public function removeLogo(): RedirectResponse
    {
        $path = Setting::getValue(
            'company_logo_path'
        );

        if ($path) {
            Storage::disk('public')
                ->delete($path);
        }

        Setting::setValue(
            key: 'company_logo_path',
            value: null,
            group: 'company'
        );

        return back()->with(
            'success',
            'Company logo removed successfully.'
        );
    }

    public function clearCache(): RedirectResponse
    {
        Artisan::call('optimize:clear');

        return back()->with(
            'success',
            'Application cache cleared successfully.'
        );
    }

    public function export(): JsonResponse
    {
        $settings = Setting::allDecoded();

        foreach (self::SECRET_KEYS as $key) {
            unset($settings[$key]);
        }

        return response()->json(
            [
                'panel' => 'MikroPanel',
                'exported_at' => now()
                    ->toIso8601String(),

                'settings' => $settings,
            ],
            200,
            [
                'Content-Disposition' =>
                    'attachment; filename="mikropanel-settings-'
                    . today()->format('Y-m-d')
                    . '.json"',
            ]
        );
    }

    private function defaults(): array
    {
        return [
            'panel_name' => 'MikroPanel',

            'company_name' =>
                'Genius Information Technology W.L.L',

            'legal_name' =>
                'Genius Information Technology W.L.L',

            'company_phone' =>
                '+974 6633 2403',

            'company_whatsapp' =>
                '+974 6633 2403',

            'company_email' =>
                'qatarhighspeedwifi@gmail.com',

            'support_email' =>
                'qatarhighspeedwifi@gmail.com',

            'website' => '',
            'company_address' => '',
            'company_city' => 'Doha',
            'company_country' => 'Qatar',
            'cr_number' => '',
            'tax_number' => '',
            'company_logo_path' => null,

            'timezone' => 'Asia/Qatar',
            'currency_code' => 'QAR',
            'currency_symbol' => 'QAR',
            'language' => 'en',
            'date_format' => 'd/m/Y',
            'time_format' => 'h:i A',

            'invoice_prefix' => 'INV-',
            'receipt_prefix' => 'RCP-',
            'default_due_days' => 0,
            'grace_days' => 0,
            'auto_billing_enabled' => false,
            'tax_enabled' => false,
            'tax_name' => 'Tax',
            'tax_rate' => 0,

            'allow_partial_payment' => true,
            'allow_credit_renewal' => true,
            'show_previous_due' => true,

            'renewal_mode' =>
                'from_expiry',

            'invoice_terms' =>
                'Thank you for your payment.',

            'invoice_footer' =>
                'Internet service is subject to the applicable package terms.',

            'authorized_signature' =>
                'Authorized Signature',

            'show_logo_on_documents' =>
                true,

            'default_router_id' => null,
            'default_package_id' => null,
            'default_ip_range_id' => null,

            'auto_suspend_enabled' => true,
            'connection_sync_enabled' => true,
            'usage_sync_enabled' => true,
            'router_api_timeout' => 10,
            'offline_after_minutes' => 2,

            'payment_receipt_enabled' => true,
            'due_reminder_enabled' => true,
            'expiry_reminder_enabled' => true,
            'reminder_days_before' => 3,

            'email_notifications_enabled' =>
                false,

            'sms_notifications_enabled' =>
                false,

            'whatsapp_notifications_enabled' =>
                false,

            'payment_message_template' =>
                'Payment received: QAR {amount}. Remaining due: QAR {due}. Thank you.',

            'due_message_template' =>
                'Dear {client}, your outstanding balance is QAR {due}.',

            'expiry_message_template' =>
                'Dear {client}, your internet package will expire on {expiry_date}.',

            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'smtp_from_name' =>
                'Genius Information Technology',

            'smtp_from_email' =>
                'qatarhighspeedwifi@gmail.com',

            'sms_api_url' => '',
            'sms_api_token' => '',
            'sms_sender_id' => '',

            'whatsapp_api_url' => '',
            'whatsapp_api_token' => '',

            'accounting_basis' => 'cash',
            'fiscal_year_start_month' => 1,

            'report_footer' =>
                'Generated automatically by MikroPanel.',

            'items_per_page' => 50,
            'activity_log_enabled' => true,
            'system_notes' => '',
        ];
    }

    private function definitions(): array
    {
        $boolean = [
            'tax_enabled',
            'auto_billing_enabled',
            'allow_partial_payment',
            'allow_credit_renewal',
            'show_previous_due',
            'show_logo_on_documents',
            'auto_suspend_enabled',
            'connection_sync_enabled',
            'usage_sync_enabled',
            'payment_receipt_enabled',
            'due_reminder_enabled',
            'expiry_reminder_enabled',
            'email_notifications_enabled',
            'sms_notifications_enabled',
            'whatsapp_notifications_enabled',
            'activity_log_enabled',
        ];

        $integer = [
            'default_due_days',
            'grace_days',
            'default_router_id',
            'default_package_id',
            'default_ip_range_id',
            'router_api_timeout',
            'offline_after_minutes',
            'reminder_days_before',
            'smtp_port',
            'fiscal_year_start_month',
            'items_per_page',
        ];

        $float = [
            'tax_rate',
        ];

        $groups = [
            'company' => [
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
            ],

            'localization' => [
                'timezone',
                'currency_code',
                'currency_symbol',
                'language',
                'date_format',
                'time_format',
            ],

            'billing' => [
                'invoice_prefix',
                'receipt_prefix',
                'default_due_days',
                'grace_days',
                'auto_billing_enabled',
                'tax_enabled',
                'tax_name',
                'tax_rate',
                'allow_partial_payment',
                'allow_credit_renewal',
                'show_previous_due',
                'renewal_mode',
                'invoice_terms',
                'invoice_footer',
                'authorized_signature',
                'show_logo_on_documents',
            ],

            'client_defaults' => [
                'default_router_id',
                'default_package_id',
                'default_ip_range_id',
            ],

            'automation' => [
                'auto_suspend_enabled',
                'connection_sync_enabled',
                'usage_sync_enabled',
                'router_api_timeout',
                'offline_after_minutes',
            ],

            'notifications' => [
                'payment_receipt_enabled',
                'due_reminder_enabled',
                'expiry_reminder_enabled',
                'reminder_days_before',
                'email_notifications_enabled',
                'sms_notifications_enabled',
                'whatsapp_notifications_enabled',
                'payment_message_template',
                'due_message_template',
                'expiry_message_template',
                'smtp_host',
                'smtp_port',
                'smtp_username',
                'smtp_password',
                'smtp_encryption',
                'smtp_from_name',
                'smtp_from_email',
                'sms_api_url',
                'sms_api_token',
                'sms_sender_id',
                'whatsapp_api_url',
                'whatsapp_api_token',
            ],

            'accounting' => [
                'accounting_basis',
                'fiscal_year_start_month',
                'report_footer',
            ],

            'system' => [
                'items_per_page',
                'activity_log_enabled',
                'system_notes',
            ],
        ];

        $definitions = [];

        foreach (
            $groups
            as $group => $keys
        ) {
            foreach ($keys as $key) {
                $definitions[$key] = [
                    'group' => $group,

                    'type' => in_array(
                        $key,
                        $boolean,
                        true
                    )
                        ? 'boolean'
                        : (
                            in_array(
                                $key,
                                $integer,
                                true
                            )
                                ? 'integer'
                                : (
                                    in_array(
                                        $key,
                                        $float,
                                        true
                                    )
                                        ? 'float'
                                        : 'string'
                                )
                        ),

                    'encrypted' => in_array(
                        $key,
                        self::SECRET_KEYS,
                        true
                    ),
                ];
            }
        }

        return $definitions;
    }
}
