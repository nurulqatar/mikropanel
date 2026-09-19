import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';

const inputClass =
    'w-full rounded-xl border-slate-300';

export default function CompanySettings({
    company = {},
}) {
    const form = useForm({
        company_name:
            company.company_name ?? '',

        panel_name:
            company.panel_name
            ?? company.company_name
            ?? '',

        owner_name:
            company.owner_name ?? '',

        company_email:
            company.company_email ?? '',

        company_phone:
            company.company_phone ?? '',

        company_address:
            company.company_address ?? '',

        website:
            company.website ?? '',

        timezone:
            company.timezone
            ?? 'Asia/Qatar',

        currency:
            company.currency ?? 'QAR',

        invoice_terms:
            company.invoice_terms ?? '',

        invoice_footer:
            company.invoice_footer ?? '',

        authorized_signature:
            company.authorized_signature
            ?? 'Authorized Signature',

        show_logo_on_documents:
            company.show_logo_on_documents
            ?? true,

        company_logo: null,
    });

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route(
                'reseller.company-settings.save',
            ),
            {
                forceFormData: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="Company Settings">
            <Head title="Company Settings" />

            <div className="mx-auto max-w-6xl space-y-6">
                <div>
                    <Link
                        href={route(
                            'reseller.dashboard',
                        )}
                        className="font-semibold text-cyan-700"
                    >
                        ← Company Dashboard
                    </Link>

                    <h1 className="mt-2 text-3xl font-black text-slate-900">
                        Company Settings
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Manage Company identity,
                        panel branding and invoice/PDF
                        branding.
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6"
                >
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="text-xl font-black">
                            Company & Panel Branding
                        </h2>

                        <div className="mt-5 grid gap-5 md:grid-cols-2">
                            <Input
                                label="Company Name"
                                value={
                                    form.data
                                        .company_name
                                }
                                error={
                                    form.errors
                                        .company_name
                                }
                                onChange={(value) =>
                                    form.setData(
                                        'company_name',
                                        value,
                                    )
                                }
                            />

                            <Input
                                label="Panel Name"
                                value={
                                    form.data
                                        .panel_name
                                }
                                error={
                                    form.errors
                                        .panel_name
                                }
                                onChange={(value) =>
                                    form.setData(
                                        'panel_name',
                                        value,
                                    )
                                }
                            />

                            <Input
                                label="Owner Name"
                                value={
                                    form.data
                                        .owner_name
                                }
                                error={
                                    form.errors
                                        .owner_name
                                }
                                onChange={(value) =>
                                    form.setData(
                                        'owner_name',
                                        value,
                                    )
                                }
                            />

                            <Input
                                label="Company Email"
                                type="email"
                                value={
                                    form.data
                                        .company_email
                                }
                                error={
                                    form.errors
                                        .company_email
                                }
                                onChange={(value) =>
                                    form.setData(
                                        'company_email',
                                        value,
                                    )
                                }
                            />

                            <Input
                                label="Company Phone"
                                value={
                                    form.data
                                        .company_phone
                                }
                                error={
                                    form.errors
                                        .company_phone
                                }
                                onChange={(value) =>
                                    form.setData(
                                        'company_phone',
                                        value,
                                    )
                                }
                            />

                            <Input
                                label="Website"
                                value={
                                    form.data
                                        .website
                                }
                                error={
                                    form.errors
                                        .website
                                }
                                onChange={(value) =>
                                    form.setData(
                                        'website',
                                        value,
                                    )
                                }
                            />

                            <Input
                                label="Timezone"
                                value={
                                    form.data
                                        .timezone
                                }
                                error={
                                    form.errors
                                        .timezone
                                }
                                onChange={(value) =>
                                    form.setData(
                                        'timezone',
                                        value,
                                    )
                                }
                            />

                            <Input
                                label="Currency"
                                value={
                                    form.data
                                        .currency
                                }
                                error={
                                    form.errors
                                        .currency
                                }
                                onChange={(value) =>
                                    form.setData(
                                        'currency',
                                        value,
                                    )
                                }
                            />

                            <label className="md:col-span-2">
                                <div className="mb-1 text-sm font-semibold">
                                    Company Address
                                </div>

                                <textarea
                                    rows="3"
                                    value={
                                        form.data
                                            .company_address
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'company_address',
                                            e.target.value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </label>
                        </div>

                        <div className="mt-5 grid gap-5 md:grid-cols-2">
                            <label>
                                <div className="mb-1 text-sm font-semibold">
                                    Company Logo
                                </div>

                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    onChange={(e) =>
                                        form.setData(
                                            'company_logo',
                                            e.target
                                                .files?.[0]
                                                ?? null,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </label>

                            <div>
                                <div className="mb-1 text-sm font-semibold">
                                    Current Logo
                                </div>

                                {company.company_logo_url ? (
                                    <div className="flex items-center gap-4 rounded-xl border p-3">
                                        <img
                                            src={
                                                company.company_logo_url
                                            }
                                            alt="Company logo"
                                            className="h-14 max-w-48 object-contain"
                                        />

                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (
                                                    confirm(
                                                        'Remove Company logo?',
                                                    )
                                                ) {
                                                    router.delete(
                                                        route(
                                                            'reseller.company-settings.logo.destroy',
                                                        ),
                                                        {
                                                            preserveScroll:
                                                                true,
                                                        },
                                                    );
                                                }
                                            }}
                                            className="rounded-lg bg-red-50 px-3 py-2 text-sm font-bold text-red-700"
                                        >
                                            Remove Logo
                                        </button>
                                    </div>
                                ) : (
                                    <div className="rounded-xl border border-dashed p-5 text-sm text-slate-400">
                                        No Company logo uploaded
                                    </div>
                                )}
                            </div>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="text-xl font-black">
                            Invoice & PDF Settings
                        </h2>

                        <div className="mt-5 grid gap-5">
                            <label>
                                <div className="mb-1 text-sm font-semibold">
                                    Invoice Terms / Notes
                                </div>

                                <textarea
                                    rows="4"
                                    value={
                                        form.data
                                            .invoice_terms
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'invoice_terms',
                                            e.target.value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </label>

                            <label>
                                <div className="mb-1 text-sm font-semibold">
                                    Invoice Footer
                                </div>

                                <textarea
                                    rows="3"
                                    value={
                                        form.data
                                            .invoice_footer
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'invoice_footer',
                                            e.target.value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </label>

                            <Input
                                label="Authorized Signature Label"
                                value={
                                    form.data
                                        .authorized_signature
                                }
                                onChange={(value) =>
                                    form.setData(
                                        'authorized_signature',
                                        value,
                                    )
                                }
                            />

                            <label className="flex items-center gap-3 rounded-xl bg-slate-50 p-4">
                                <input
                                    type="checkbox"
                                    checked={
                                        Boolean(
                                            form.data
                                                .show_logo_on_documents,
                                        )
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'show_logo_on_documents',
                                            e.target.checked,
                                        )
                                    }
                                    className="rounded"
                                />

                                <span className="font-semibold">
                                    Show Company logo on
                                    invoices and PDFs
                                </span>
                            </label>
                        </div>
                    </section>

                    {Object.keys(
                        form.errors,
                    ).length > 0 && (
                        <div className="rounded-xl bg-red-50 p-4 font-semibold text-red-700">
                            {Object.values(
                                form.errors,
                            ).map(
                                (
                                    error,
                                    index,
                                ) => (
                                    <div
                                        key={
                                            index
                                        }
                                    >
                                        {error}
                                    </div>
                                ),
                            )}
                        </div>
                    )}

                    <div className="flex justify-end">
                        <button
                            type="submit"
                            disabled={
                                form.processing
                            }
                            className="rounded-xl bg-cyan-700 px-7 py-3 font-black text-white disabled:opacity-50"
                        >
                            {form.processing
                                ? 'Saving...'
                                : 'Save Company Settings'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function Input({
    label,
    value,
    onChange,
    error,
    type = 'text',
}) {
    return (
        <label>
            <div className="mb-1 text-sm font-semibold">
                {label}
            </div>

            <input
                type={type}
                value={value}
                onChange={(e) =>
                    onChange(
                        e.target.value,
                    )
                }
                className={
                    inputClass
                }
            />

            {error && (
                <div className="mt-1 text-sm font-semibold text-red-600">
                    {error}
                </div>
            )}
        </label>
    );
}
