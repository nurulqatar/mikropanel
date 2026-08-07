import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';
import { useState } from 'react';

export default function Index({
    settings = {},
    secretStatus = {},
    routers = [],
    packages = [],
    ipRanges = [],
    systemInfo = {},
    flash = {},
}) {
    const [activeTab, setActiveTab] =
        useState('company');

    const {
        data,
        setData,
        post,
        processing,
        errors,
        progress,
    } = useForm({
        ...settings,

        company_logo: null,
        smtp_password: '',
        sms_api_token: '',
        whatsapp_api_token: '',
    });

    const tabs = [
        {
            key: 'company',
            label: 'Company',
        },
        {
            key: 'billing',
            label: 'Billing & Due',
        },
        {
            key: 'automation',
            label: 'Network & Automation',
        },
        {
            key: 'notifications',
            label: 'Notifications',
        },
        {
            key: 'documents',
            label: 'Invoices & Reports',
        },
        {
            key: 'system',
            label: 'System',
        },
    ];

    const submit = (event) => {
        event.preventDefault();

        post(route('settings.update'), {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const removeLogo = () => {
        if (
            !confirm(
                'Remove the company logo?',
            )
        ) {
            return;
        }

        router.delete(
            route(
                'settings.logo.destroy',
            ),
            {
                preserveScroll: true,
            },
        );
    };

    const clearCache = () => {
        if (
            !confirm(
                'Clear application cache?',
            )
        ) {
            return;
        }

        router.post(
            route(
                'settings.cache.clear',
            ),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="Settings">
            <Head title="Settings" />

            <form
                onSubmit={submit}
                className="space-y-6"
            >
                <section className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900">
                            ISP Panel Settings
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Company, billing, network,
                            notification and system
                            configuration
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <a
                            href={route(
                                'settings.export',
                            )}
                            className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50"
                        >
                            Export Settings
                        </a>

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-xl bg-cyan-600 px-5 py-3 text-sm font-bold text-white hover:bg-cyan-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Saving...'
                                : 'Save All Settings'}
                        </button>
                    </div>
                </section>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 font-semibold text-emerald-700">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-5 py-4 font-semibold text-red-700">
                        {flash.error}
                    </div>
                )}

                {progress && (
                    <div className="rounded-full bg-slate-200">
                        <div
                            className="h-2 rounded-full bg-cyan-600"
                            style={{
                                width: `${progress.percentage}%`,
                            }}
                        />
                    </div>
                )}

                <section className="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-2 shadow-sm">
                    <div className="flex min-w-max gap-2">
                        {tabs.map((tab) => (
                            <button
                                key={tab.key}
                                type="button"
                                onClick={() =>
                                    setActiveTab(
                                        tab.key,
                                    )
                                }
                                className={`rounded-xl px-5 py-3 text-sm font-bold transition ${
                                    activeTab ===
                                    tab.key
                                        ? 'bg-slate-900 text-white'
                                        : 'text-slate-600 hover:bg-slate-100'
                                }`}
                            >
                                {tab.label}
                            </button>
                        ))}
                    </div>
                </section>

                {activeTab === 'company' && (
                    <div className="space-y-6">
                        <Panel
                            title="Company & Branding"
                            description="Information shown throughout the ISP panel and documents"
                        >
                            <Grid>
                                <Input
                                    label="Panel Name"
                                    value={
                                        data.panel_name
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'panel_name',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.panel_name
                                    }
                                    required
                                />

                                <Input
                                    label="Company Display Name"
                                    value={
                                        data.company_name
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'company_name',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.company_name
                                    }
                                    required
                                />

                                <Input
                                    label="Legal Company Name"
                                    value={
                                        data.legal_name
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'legal_name',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.legal_name
                                    }
                                />

                                <Input
                                    label="CR Number"
                                    value={
                                        data.cr_number
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'cr_number',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.cr_number
                                    }
                                />

                                <Input
                                    label="Tax / VAT Number"
                                    value={
                                        data.tax_number
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'tax_number',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.tax_number
                                    }
                                />

                                <Input
                                    label="Mobile Number"
                                    value={
                                        data.company_phone
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'company_phone',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.company_phone
                                    }
                                />

                                <Input
                                    label="WhatsApp Number"
                                    value={
                                        data.company_whatsapp
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'company_whatsapp',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.company_whatsapp
                                    }
                                />

                                <Input
                                    label="Company Email"
                                    type="email"
                                    value={
                                        data.company_email
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'company_email',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.company_email
                                    }
                                />

                                <Input
                                    label="Support Email"
                                    type="email"
                                    value={
                                        data.support_email
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'support_email',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.support_email
                                    }
                                />

                                <Input
                                    label="Website"
                                    type="url"
                                    value={
                                        data.website
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'website',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.website
                                    }
                                    placeholder="https://example.com"
                                />

                                <Input
                                    label="City"
                                    value={
                                        data.company_city
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'company_city',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.company_city
                                    }
                                />

                                <Input
                                    label="Country"
                                    value={
                                        data.company_country
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'company_country',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.company_country
                                    }
                                    required
                                />
                            </Grid>

                            <Textarea
                                label="Company Address"
                                value={
                                    data.company_address
                                }
                                onChange={(value) =>
                                    setData(
                                        'company_address',
                                        value,
                                    )
                                }
                                error={
                                    errors.company_address
                                }
                            />
                        </Panel>

                        <Panel
                            title="Company Logo"
                            description="PNG, JPG or WebP; maximum 2 MB"
                        >
                            <div className="grid gap-6 lg:grid-cols-2">
                                <div>
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        onChange={(
                                            event,
                                        ) =>
                                            setData(
                                                'company_logo',
                                                event
                                                    .target
                                                    .files?.[0] ??
                                                    null,
                                            )
                                        }
                                        className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3"
                                    />

                                    {errors.company_logo && (
                                        <Error
                                            text={
                                                errors.company_logo
                                            }
                                        />
                                    )}
                                </div>

                                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                    {settings.company_logo_url ? (
                                        <div className="flex items-center gap-5">
                                            <img
                                                src={
                                                    settings.company_logo_url
                                                }
                                                alt="Company Logo"
                                                className="h-20 w-20 rounded-xl bg-white object-contain p-2"
                                            />

                                            <button
                                                type="button"
                                                onClick={
                                                    removeLogo
                                                }
                                                className="rounded-lg bg-red-600 px-4 py-2 text-sm font-bold text-white hover:bg-red-700"
                                            >
                                                Remove Logo
                                            </button>
                                        </div>
                                    ) : (
                                        <p className="text-sm text-slate-500">
                                            No company logo uploaded.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </Panel>
                    </div>
                )}

                {activeTab === 'billing' && (
                    <div className="space-y-6">
                        <Panel
                            title="Billing & Invoice Defaults"
                            description="Default billing behaviour for ISP customers"
                        >
                            <Grid>
                                <Input
                                    label="Invoice Prefix"
                                    value={
                                        data.invoice_prefix
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'invoice_prefix',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.invoice_prefix
                                    }
                                    required
                                />

                                <Input
                                    label="Receipt Prefix"
                                    value={
                                        data.receipt_prefix
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'receipt_prefix',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.receipt_prefix
                                    }
                                    required
                                />

                                <Toggle
                                    label="Automatic Monthly Billing"
                                    description="Generate each client's monthly invoice automatically on their billing day"
                                    checked={
                                        data.auto_billing_enabled
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'auto_billing_enabled',
                                            value,
                                        )
                                    }
                                />

                                <Input
                                    label="Default Due Days"
                                    type="number"
                                    min="0"
                                    value={
                                        data.default_due_days
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'default_due_days',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.default_due_days
                                    }
                                />

                                <Input
                                    label="Grace Days"
                                    type="number"
                                    min="0"
                                    value={
                                        data.grace_days
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'grace_days',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.grace_days
                                    }
                                />

                                <Select
                                    label="Renewal Date Calculation"
                                    value={
                                        data.renewal_mode
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'renewal_mode',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.renewal_mode
                                    }
                                    options={[
                                        {
                                            value:
                                                'from_expiry',
                                            label:
                                                'Add validity to existing expiry',
                                        },
                                        {
                                            value:
                                                'from_payment_date',
                                            label:
                                                'Start from payment date',
                                        },
                                    ]}
                                />

                                <Input
                                    label="Tax Name"
                                    value={
                                        data.tax_name
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'tax_name',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.tax_name
                                    }
                                />

                                <Input
                                    label="Tax Rate (%)"
                                    type="number"
                                    min="0"
                                    max="100"
                                    step="0.01"
                                    value={
                                        data.tax_rate
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'tax_rate',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.tax_rate
                                    }
                                />
                            </Grid>

                            <div className="mt-6 grid gap-4 lg:grid-cols-2">
                                <Toggle
                                    label="Enable Tax"
                                    description="Apply configured tax to supported invoices"
                                    checked={
                                        data.tax_enabled
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'tax_enabled',
                                            value,
                                        )
                                    }
                                />

                                <Toggle
                                    label="Allow Partial Payment"
                                    description="Allow clients to pay less than the full bill"
                                    checked={
                                        data.allow_partial_payment
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'allow_partial_payment',
                                            value,
                                        )
                                    }
                                />

                                <Toggle
                                    label="Allow Credit / Full Due Renewal"
                                    description="Renew a client even when received amount is zero"
                                    checked={
                                        data.allow_credit_renewal
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'allow_credit_renewal',
                                            value,
                                        )
                                    }
                                />

                                <Toggle
                                    label="Show Previous Due"
                                    description="Display outstanding balance in client billing"
                                    checked={
                                        data.show_previous_due
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'show_previous_due',
                                            value,
                                        )
                                    }
                                />
                            </div>
                        </Panel>

                        <Panel
                            title="New Client Defaults"
                            description="Preselected values for adding new customers"
                        >
                            <Grid>
                                <Select
                                    label="Default Router"
                                    value={
                                        data.default_router_id ??
                                        ''
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'default_router_id',
                                            value ||
                                                null,
                                        )
                                    }
                                    error={
                                        errors.default_router_id
                                    }
                                    options={[
                                        {
                                            value: '',
                                            label:
                                                'No default router',
                                        },

                                        ...routers.map(
                                            (router) => ({
                                                value:
                                                    router.id,
                                                label: `${router.name} — ${router.host}`,
                                            }),
                                        ),
                                    ]}
                                />

                                <Select
                                    label="Default Package"
                                    value={
                                        data.default_package_id ??
                                        ''
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'default_package_id',
                                            value ||
                                                null,
                                        )
                                    }
                                    error={
                                        errors.default_package_id
                                    }
                                    options={[
                                        {
                                            value: '',
                                            label:
                                                'No default package',
                                        },

                                        ...packages.map(
                                            (item) => ({
                                                value:
                                                    item.id,
                                                label: `${item.name} — QAR ${money(
                                                    item.price,
                                                )}`,
                                            }),
                                        ),
                                    ]}
                                />

                                <Select
                                    label="Default IP Pool"
                                    value={
                                        data.default_ip_range_id ??
                                        ''
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'default_ip_range_id',
                                            value ||
                                                null,
                                        )
                                    }
                                    error={
                                        errors.default_ip_range_id
                                    }
                                    options={[
                                        {
                                            value: '',
                                            label:
                                                'No default IP pool',
                                        },

                                        ...ipRanges.map(
                                            (range) => ({
                                                value:
                                                    range.id,
                                                label: `${range.name} — ${range.start_ip} to ${range.end_ip}`,
                                            }),
                                        ),
                                    ]}
                                />
                            </Grid>
                        </Panel>
                    </div>
                )}

                {activeTab === 'automation' && (
                    <div className="space-y-6">
                        <Panel
                            title="Automatic Client Management"
                            description="Background MikroTik and billing jobs"
                        >
                            <div className="grid gap-4 lg:grid-cols-2">
                                <Toggle
                                    label="Automatic Expiry Suspension"
                                    description="Suspend expired clients automatically"
                                    checked={
                                        data.auto_suspend_enabled
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'auto_suspend_enabled',
                                            value,
                                        )
                                    }
                                />

                                <Toggle
                                    label="Online / Offline Sync"
                                    description="Read DHCP lease status from MikroTik every minute"
                                    checked={
                                        data.connection_sync_enabled
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'connection_sync_enabled',
                                            value,
                                        )
                                    }
                                />

                                <Toggle
                                    label="Internet Usage Sync"
                                    description="Collect monthly upload and download usage"
                                    checked={
                                        data.usage_sync_enabled
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'usage_sync_enabled',
                                            value,
                                        )
                                    }
                                />
                            </div>
                        </Panel>

                        <Panel
                            title="Router Communication"
                            description="MikroTik API monitoring defaults"
                        >
                            <Grid>
                                <Input
                                    label="API Timeout (Seconds)"
                                    type="number"
                                    min="3"
                                    max="120"
                                    value={
                                        data.router_api_timeout
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'router_api_timeout',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.router_api_timeout
                                    }
                                />

                                <Input
                                    label="Mark Offline After (Minutes)"
                                    type="number"
                                    min="1"
                                    max="1440"
                                    value={
                                        data.offline_after_minutes
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'offline_after_minutes',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.offline_after_minutes
                                    }
                                />
                            </Grid>

                            <div className="mt-5 rounded-xl border border-cyan-200 bg-cyan-50 p-4 text-sm leading-6 text-cyan-900">
                                Client connection status is determined from
                                MikroTik DHCP static lease status. A bound
                                lease is Online; otherwise it is Offline.
                            </div>
                        </Panel>
                    </div>
                )}

                {activeTab ===
                    'notifications' && (
                    <div className="space-y-6">
                        <Panel
                            title="Notification Rules"
                            description="Configure which customer notifications should be generated"
                        >
                            <div className="grid gap-4 lg:grid-cols-2">
                                <Toggle
                                    label="Payment Receipt"
                                    description="Generate a receipt notification after payment"
                                    checked={
                                        data.payment_receipt_enabled
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'payment_receipt_enabled',
                                            value,
                                        )
                                    }
                                />

                                <Toggle
                                    label="Due Reminder"
                                    description="Enable outstanding balance reminders"
                                    checked={
                                        data.due_reminder_enabled
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'due_reminder_enabled',
                                            value,
                                        )
                                    }
                                />

                                <Toggle
                                    label="Expiry Reminder"
                                    description="Enable package expiry reminders"
                                    checked={
                                        data.expiry_reminder_enabled
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'expiry_reminder_enabled',
                                            value,
                                        )
                                    }
                                />

                                <Input
                                    label="Reminder Days Before Expiry"
                                    type="number"
                                    min="0"
                                    max="60"
                                    value={
                                        data.reminder_days_before
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'reminder_days_before',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.reminder_days_before
                                    }
                                />
                            </div>

                            <div className="mt-6 grid gap-4 lg:grid-cols-3">
                                <Toggle
                                    label="Email Channel"
                                    description="Send notifications by email"
                                    checked={
                                        data.email_notifications_enabled
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'email_notifications_enabled',
                                            value,
                                        )
                                    }
                                />

                                <Toggle
                                    label="SMS Channel"
                                    description="Send notifications through an SMS API"
                                    checked={
                                        data.sms_notifications_enabled
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'sms_notifications_enabled',
                                            value,
                                        )
                                    }
                                />

                                <Toggle
                                    label="WhatsApp Channel"
                                    description="Send notifications through a WhatsApp API"
                                    checked={
                                        data.whatsapp_notifications_enabled
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'whatsapp_notifications_enabled',
                                            value,
                                        )
                                    }
                                />
                            </div>
                        </Panel>

                        <Panel
                            title="Message Templates"
                            description="Supported variables: {client}, {amount}, {due}, {expiry_date}, {invoice_no}"
                        >
                            <Textarea
                                label="Payment Message"
                                value={
                                    data.payment_message_template
                                }
                                onChange={(value) =>
                                    setData(
                                        'payment_message_template',
                                        value,
                                    )
                                }
                                error={
                                    errors.payment_message_template
                                }
                            />

                            <Textarea
                                label="Due Reminder Message"
                                value={
                                    data.due_message_template
                                }
                                onChange={(value) =>
                                    setData(
                                        'due_message_template',
                                        value,
                                    )
                                }
                                error={
                                    errors.due_message_template
                                }
                            />

                            <Textarea
                                label="Expiry Reminder Message"
                                value={
                                    data.expiry_message_template
                                }
                                onChange={(value) =>
                                    setData(
                                        'expiry_message_template',
                                        value,
                                    )
                                }
                                error={
                                    errors.expiry_message_template
                                }
                            />
                        </Panel>

                        <Panel
                            title="Email SMTP Gateway"
                            description="SMTP password is encrypted in the database"
                        >
                            <Grid>
                                <Input
                                    label="SMTP Host"
                                    value={
                                        data.smtp_host
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'smtp_host',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.smtp_host
                                    }
                                />

                                <Input
                                    label="SMTP Port"
                                    type="number"
                                    value={
                                        data.smtp_port
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'smtp_port',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.smtp_port
                                    }
                                />

                                <Input
                                    label="SMTP Username"
                                    value={
                                        data.smtp_username
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'smtp_username',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.smtp_username
                                    }
                                />

                                <SecretInput
                                    label="SMTP Password"
                                    saved={
                                        secretStatus.smtp_password
                                    }
                                    value={
                                        data.smtp_password
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'smtp_password',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.smtp_password
                                    }
                                />

                                <Select
                                    label="Encryption"
                                    value={
                                        data.smtp_encryption
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'smtp_encryption',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.smtp_encryption
                                    }
                                    options={[
                                        {
                                            value: 'tls',
                                            label: 'TLS',
                                        },
                                        {
                                            value: 'ssl',
                                            label: 'SSL',
                                        },
                                        {
                                            value: 'none',
                                            label: 'None',
                                        },
                                    ]}
                                />

                                <Input
                                    label="From Name"
                                    value={
                                        data.smtp_from_name
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'smtp_from_name',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.smtp_from_name
                                    }
                                />

                                <Input
                                    label="From Email"
                                    type="email"
                                    value={
                                        data.smtp_from_email
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'smtp_from_email',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.smtp_from_email
                                    }
                                />
                            </Grid>
                        </Panel>

                        <Panel
                            title="SMS & WhatsApp Gateway"
                            description="API tokens are encrypted in the database"
                        >
                            <Grid>
                                <Input
                                    label="SMS API URL"
                                    type="url"
                                    value={
                                        data.sms_api_url
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'sms_api_url',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.sms_api_url
                                    }
                                />

                                <SecretInput
                                    label="SMS API Token"
                                    saved={
                                        secretStatus.sms_api_token
                                    }
                                    value={
                                        data.sms_api_token
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'sms_api_token',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.sms_api_token
                                    }
                                />

                                <Input
                                    label="SMS Sender ID"
                                    value={
                                        data.sms_sender_id
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'sms_sender_id',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.sms_sender_id
                                    }
                                />

                                <Input
                                    label="WhatsApp API URL"
                                    type="url"
                                    value={
                                        data.whatsapp_api_url
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'whatsapp_api_url',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.whatsapp_api_url
                                    }
                                />

                                <SecretInput
                                    label="WhatsApp API Token"
                                    saved={
                                        secretStatus.whatsapp_api_token
                                    }
                                    value={
                                        data.whatsapp_api_token
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'whatsapp_api_token',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.whatsapp_api_token
                                    }
                                />
                            </Grid>
                        </Panel>
                    </div>
                )}

                {activeTab === 'documents' && (
                    <div className="space-y-6">
                        <Panel
                            title="Invoice & Receipt Appearance"
                            description="Text displayed on printable documents"
                        >
                            <Toggle
                                label="Show Company Logo"
                                description="Show uploaded logo on invoices and reports"
                                checked={
                                    data.show_logo_on_documents
                                }
                                onChange={(value) =>
                                    setData(
                                        'show_logo_on_documents',
                                        value,
                                    )
                                }
                            />

                            <div className="mt-6">
                                <Textarea
                                    label="Invoice Terms"
                                    value={
                                        data.invoice_terms
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'invoice_terms',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.invoice_terms
                                    }
                                />

                                <Textarea
                                    label="Invoice Footer"
                                    value={
                                        data.invoice_footer
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'invoice_footer',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.invoice_footer
                                    }
                                />

                                <Input
                                    label="Signature Label"
                                    value={
                                        data.authorized_signature
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'authorized_signature',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.authorized_signature
                                    }
                                />
                            </div>
                        </Panel>

                        <Panel
                            title="Accounting & Reports"
                            description="Financial report defaults"
                        >
                            <Grid>
                                <Select
                                    label="Accounting Basis"
                                    value={
                                        data.accounting_basis
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'accounting_basis',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.accounting_basis
                                    }
                                    options={[
                                        {
                                            value: 'cash',
                                            label:
                                                'Cash Basis — Collection minus Expenses',
                                        },
                                    ]}
                                />

                                <Select
                                    label="Fiscal Year Starts"
                                    value={
                                        data.fiscal_year_start_month
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'fiscal_year_start_month',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.fiscal_year_start_month
                                    }
                                    options={months.map(
                                        (
                                            month,
                                            index,
                                        ) => ({
                                            value:
                                                index + 1,
                                            label: month,
                                        }),
                                    )}
                                />
                            </Grid>

                            <Textarea
                                label="Report Footer"
                                value={
                                    data.report_footer
                                }
                                onChange={(value) =>
                                    setData(
                                        'report_footer',
                                        value,
                                    )
                                }
                                error={
                                    errors.report_footer
                                }
                            />
                        </Panel>
                    </div>
                )}

                {activeTab === 'system' && (
                    <div className="space-y-6">
                        <Panel
                            title="Localization"
                            description="Currency, timezone and display formats"
                        >
                            <Grid>
                                <Select
                                    label="Timezone"
                                    value={
                                        data.timezone
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'timezone',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.timezone
                                    }
                                    options={[
                                        {
                                            value:
                                                'Asia/Qatar',
                                            label:
                                                'Asia/Qatar',
                                        },
                                        {
                                            value:
                                                'Asia/Dhaka',
                                            label:
                                                'Asia/Dhaka',
                                        },
                                        {
                                            value: 'UTC',
                                            label: 'UTC',
                                        },
                                    ]}
                                />

                                <Input
                                    label="Currency Code"
                                    value={
                                        data.currency_code
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'currency_code',
                                            value.toUpperCase(),
                                        )
                                    }
                                    error={
                                        errors.currency_code
                                    }
                                />

                                <Input
                                    label="Currency Symbol"
                                    value={
                                        data.currency_symbol
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'currency_symbol',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.currency_symbol
                                    }
                                />

                                <Select
                                    label="Language"
                                    value={
                                        data.language
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'language',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.language
                                    }
                                    options={[
                                        {
                                            value: 'en',
                                            label: 'English',
                                        },
                                        {
                                            value: 'bn',
                                            label: 'Bangla',
                                        },
                                    ]}
                                />

                                <Select
                                    label="Date Format"
                                    value={
                                        data.date_format
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'date_format',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.date_format
                                    }
                                    options={[
                                        {
                                            value:
                                                'd/m/Y',
                                            label:
                                                'DD/MM/YYYY',
                                        },
                                        {
                                            value:
                                                'Y-m-d',
                                            label:
                                                'YYYY-MM-DD',
                                        },
                                        {
                                            value:
                                                'd-m-Y',
                                            label:
                                                'DD-MM-YYYY',
                                        },
                                        {
                                            value:
                                                'm/d/Y',
                                            label:
                                                'MM/DD/YYYY',
                                        },
                                    ]}
                                />

                                <Select
                                    label="Time Format"
                                    value={
                                        data.time_format
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'time_format',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.time_format
                                    }
                                    options={[
                                        {
                                            value: 'H:i',
                                            label:
                                                '24 Hour',
                                        },
                                        {
                                            value:
                                                'h:i A',
                                            label:
                                                '12 Hour',
                                        },
                                    ]}
                                />
                            </Grid>
                        </Panel>

                        <Panel
                            title="Panel Behaviour"
                            description="General system preferences"
                        >
                            <Grid>
                                <Input
                                    label="Rows Per Page"
                                    type="number"
                                    min="10"
                                    max="500"
                                    value={
                                        data.items_per_page
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'items_per_page',
                                            value,
                                        )
                                    }
                                    error={
                                        errors.items_per_page
                                    }
                                />
                            </Grid>

                            <div className="mt-5">
                                <Toggle
                                    label="Activity Logging"
                                    description="Keep operator and system activity records"
                                    checked={
                                        data.activity_log_enabled
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'activity_log_enabled',
                                            value,
                                        )
                                    }
                                />
                            </div>

                            <Textarea
                                label="Internal System Notes"
                                value={
                                    data.system_notes
                                }
                                onChange={(value) =>
                                    setData(
                                        'system_notes',
                                        value,
                                    )
                                }
                                error={
                                    errors.system_notes
                                }
                            />
                        </Panel>

                        <Panel
                            title="System Information"
                            description="Current application environment"
                        >
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                                {Object.entries(
                                    systemInfo,
                                ).map(
                                    ([key, value]) => (
                                        <div
                                            key={key}
                                            className="rounded-xl bg-slate-50 p-4"
                                        >
                                            <p className="text-xs font-bold uppercase text-slate-400">
                                                {key.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </p>

                                            <p className="mt-2 break-all font-semibold text-slate-800">
                                                {value}
                                            </p>
                                        </div>
                                    ),
                                )}
                            </div>

                            <div className="mt-6">
                                <button
                                    type="button"
                                    onClick={clearCache}
                                    className="rounded-xl bg-slate-800 px-5 py-3 font-bold text-white hover:bg-slate-900"
                                >
                                    Clear Application Cache
                                </button>
                            </div>
                        </Panel>
                    </div>
                )}

                <div className="sticky bottom-4 flex justify-end rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-lg backdrop-blur">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-xl bg-cyan-600 px-7 py-3 font-bold text-white hover:bg-cyan-700 disabled:opacity-50"
                    >
                        {processing
                            ? 'Saving Settings...'
                            : 'Save All Settings'}
                    </button>
                </div>
            </form>
        </AppLayout>
    );
}

const months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

function Panel({
    title,
    description,
    children,
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-6">
                <h2 className="text-xl font-bold text-slate-900">
                    {title}
                </h2>

                <p className="mt-1 text-sm text-slate-500">
                    {description}
                </p>
            </div>

            {children}
        </section>
    );
}

function Grid({ children }) {
    return (
        <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            {children}
        </div>
    );
}

function Input({
    label,
    type = 'text',
    value,
    onChange,
    error,
    required = false,
    placeholder = '',
    ...props
}) {
    return (
        <div>
            <label className="mb-1 block text-sm font-semibold text-slate-700">
                {label}

                {required && (
                    <span className="ml-1 text-red-500">
                        *
                    </span>
                )}
            </label>

            <input
                type={type}
                value={value ?? ''}
                onChange={(event) =>
                    onChange(
                        event.target.value,
                    )
                }
                placeholder={placeholder}
                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
                {...props}
            />

            {error && <Error text={error} />}
        </div>
    );
}

function SecretInput({
    label,
    saved,
    value,
    onChange,
    error,
}) {
    return (
        <Input
            label={label}
            type="password"
            value={value}
            onChange={onChange}
            error={error}
            placeholder={
                saved
                    ? 'Saved — leave blank to keep unchanged'
                    : 'Enter secret value'
            }
        />
    );
}

function Select({
    label,
    value,
    onChange,
    options,
    error,
}) {
    return (
        <div>
            <label className="mb-1 block text-sm font-semibold text-slate-700">
                {label}
            </label>

            <select
                value={value ?? ''}
                onChange={(event) =>
                    onChange(
                        event.target.value,
                    )
                }
                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            >
                {options.map((option) => (
                    <option
                        key={option.value}
                        value={option.value}
                    >
                        {option.label}
                    </option>
                ))}
            </select>

            {error && <Error text={error} />}
        </div>
    );
}

function Textarea({
    label,
    value,
    onChange,
    error,
}) {
    return (
        <div className="mt-5">
            <label className="mb-1 block text-sm font-semibold text-slate-700">
                {label}
            </label>

            <textarea
                rows="3"
                value={value ?? ''}
                onChange={(event) =>
                    onChange(
                        event.target.value,
                    )
                }
                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
            />

            {error && <Error text={error} />}
        </div>
    );
}

function Toggle({
    label,
    description,
    checked,
    onChange,
}) {
    return (
        <label className="flex cursor-pointer items-start justify-between gap-5 rounded-xl border border-slate-200 p-4 hover:bg-slate-50">
            <div>
                <p className="font-bold text-slate-800">
                    {label}
                </p>

                <p className="mt-1 text-sm leading-5 text-slate-500">
                    {description}
                </p>
            </div>

            <input
                type="checkbox"
                checked={Boolean(checked)}
                onChange={(event) =>
                    onChange(
                        event.target.checked,
                    )
                }
                className="mt-1 h-5 w-5 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
            />
        </label>
    );
}

function Error({ text }) {
    return (
        <p className="mt-1 text-sm font-semibold text-red-600">
            {text}
        </p>
    );
}

function money(value) {
    return Number(value ?? 0).toLocaleString(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        },
    );
}
