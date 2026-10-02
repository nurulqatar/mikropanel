import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';

const inputClass =
    'w-full rounded-xl border border-slate-300 px-3 py-2.5';

export default function WebsiteSettings({
    website = {},
}) {
    const form = useForm({
        website_name:
            website.website_name ?? '',

        website_tagline:
            website.website_tagline ?? '',

        hero_badge:
            website.hero_badge ?? '',

        hero_title:
            website.hero_title ?? '',

        hero_text:
            website.hero_text ?? '',

        pricing_title:
            website.pricing_title ?? '',

        pricing_text:
            website.pricing_text ?? '',

        company_phone:
            website.company_phone ?? '',

        company_whatsapp:
            website.company_whatsapp ?? '',

        company_email:
            website.company_email ?? '',

        company_address:
            website.company_address ?? '',

        footer_text:
            website.footer_text ?? '',

        terms_content:
            website.terms_content ?? '',

        privacy_content:
            website.privacy_content ?? '',

        website_logo: null,
    });

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route(
                'superadmin.website-settings.update',
            ),
            {
                forceFormData: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <SuperAdminLayout title="Website Settings">
            <Head title="Website Settings" />

            <form
                onSubmit={submit}
                className="space-y-6"
            >
                <section className="rounded-2xl border bg-white p-6 shadow-sm">
                    <h1 className="text-2xl font-black">
                        Website Branding
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Control the public website name,
                        logo and primary content.
                    </p>

                    <div className="mt-6 grid gap-5 md:grid-cols-2">
                        <Field
                            label="Website Name"
                            error={
                                form.errors
                                    .website_name
                            }
                        >
                            <input
                                value={
                                    form.data
                                        .website_name
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'website_name',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Website Tagline">
                            <input
                                value={
                                    form.data
                                        .website_tagline
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'website_tagline',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Website Logo">
                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                onChange={(e) =>
                                    form.setData(
                                        'website_logo',
                                        e.target
                                            .files?.[0]
                                            ?? null,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <div>
                            <div className="mb-1 text-sm font-semibold">
                                Current Logo
                            </div>

                            {website.logo_url ? (
                                <div className="flex items-center gap-4 rounded-xl border p-3">
                                    <img
                                        src={
                                            website.logo_url
                                        }
                                        alt="Website logo"
                                        className="h-14 max-w-48 object-contain"
                                    />

                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (
                                                confirm(
                                                    'Remove website logo?',
                                                )
                                            ) {
                                                router.delete(
                                                    route(
                                                        'superadmin.website-settings.logo.destroy',
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
                                    No website logo uploaded
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border bg-white p-6 shadow-sm">
                    <h2 className="text-xl font-black">
                        Homepage Content
                    </h2>

                    <div className="mt-5 grid gap-5">
                        <Field label="Hero Badge">
                            <input
                                value={
                                    form.data
                                        .hero_badge
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'hero_badge',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Hero Title">
                            <textarea
                                rows="2"
                                value={
                                    form.data
                                        .hero_title
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'hero_title',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Hero Description">
                            <textarea
                                rows="4"
                                value={
                                    form.data
                                        .hero_text
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'hero_text',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <div className="grid gap-5 md:grid-cols-2">
                            <Field label="Pricing Title">
                                <input
                                    value={
                                        form.data
                                            .pricing_title
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'pricing_title',
                                            e.target.value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Pricing Description">
                                <input
                                    value={
                                        form.data
                                            .pricing_text
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'pricing_text',
                                            e.target.value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>
                        </div>

                        <Field label="Footer Text">
                            <textarea
                                rows="2"
                                value={
                                    form.data
                                        .footer_text
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'footer_text',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>
                    </div>
                </section>

                <section className="rounded-2xl border bg-white p-6 shadow-sm">
                    <h2 className="text-xl font-black">
                        Contact Details
                    </h2>

                    <div className="mt-5 grid gap-5 md:grid-cols-2">
                        <Field label="Phone">
                            <input
                                value={
                                    form.data
                                        .company_phone
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'company_phone',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="WhatsApp">
                            <input
                                value={
                                    form.data
                                        .company_whatsapp
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'company_whatsapp',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Email">
                            <input
                                type="email"
                                value={
                                    form.data
                                        .company_email
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'company_email',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Address">
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
                        </Field>
                    </div>
                </section>

                <section className="rounded-2xl border bg-white p-6 shadow-sm">
                    <h2 className="text-xl font-black">
                        Legal Pages
                    </h2>

                    <div className="mt-5 grid gap-5 lg:grid-cols-2">
                        <Field label="Terms & Conditions">
                            <textarea
                                rows="16"
                                value={
                                    form.data
                                        .terms_content
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'terms_content',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Privacy Policy">
                            <textarea
                                rows="16"
                                value={
                                    form.data
                                        .privacy_content
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'privacy_content',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>
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
                        className="rounded-xl bg-indigo-600 px-7 py-3 font-black text-white disabled:opacity-50"
                    >
                        {form.processing
                            ? 'Saving...'
                            : 'Save Website Settings'}
                    </button>
                </div>
            </form>
        </SuperAdminLayout>
    );
}

function Field({
    label,
    error,
    children,
}) {
    return (
        <label className="block">
            <div className="mb-1 text-sm font-semibold text-slate-700">
                {label}
            </div>

            {children}

            {error && (
                <div className="mt-1 text-sm font-semibold text-red-600">
                    {error}
                </div>
            )}
        </label>
    );
}
