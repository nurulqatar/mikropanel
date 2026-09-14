import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

const inputClass =
    'w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:border-cyan-500 focus:ring-cyan-500';

export default function Branding({
    branding,
}) {
    const form = useForm({
        brand_name:
            branding.brand_name ?? '',
        portal_title:
            branding.portal_title ?? '',
        support_phone:
            branding.support_phone ?? '',
        support_text:
            branding.support_text ?? '',
        primary_color:
            branding.primary_color ??
            '#0891b2',
        terms_text:
            branding.terms_text ?? '',
        show_price:
            Boolean(
                branding.show_price,
            ),
        show_qr:
            Boolean(
                branding.show_qr,
            ),
    });

    return (
        <AppLayout title="Hotspot Branding">
            <Head title="Hotspot Branding" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div>
                    <Link
                        href={route(
                            'hotspot.index',
                        )}
                        className="font-semibold text-cyan-700"
                    >
                        ← Hotspot
                    </Link>

                    <h1 className="mt-2 text-3xl font-bold">
                        Voucher & Portal Branding
                    </h1>
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();

                        form.put(
                            route(
                                'hotspot.branding.update',
                            ),
                            {
                                preserveScroll: true,
                            },
                        );
                    }}
                    className="rounded-2xl border bg-white p-6 shadow-sm"
                >
                    <div className="grid gap-5 md:grid-cols-2">
                        <Field label="Brand Name">
                            <input
                                value={
                                    form.data
                                        .brand_name
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'brand_name',
                                        e.target
                                            .value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Portal Title">
                            <input
                                value={
                                    form.data
                                        .portal_title
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'portal_title',
                                        e.target
                                            .value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Support Phone">
                            <input
                                value={
                                    form.data
                                        .support_phone
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'support_phone',
                                        e.target
                                            .value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Support Text">
                            <input
                                value={
                                    form.data
                                        .support_text
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'support_text',
                                        e.target
                                            .value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Primary Color">
                            <input
                                type="color"
                                value={
                                    form.data
                                        .primary_color
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'primary_color',
                                        e.target
                                            .value,
                                    )
                                }
                                className="h-11 w-full rounded-lg border"
                            />
                        </Field>
                    </div>

                    <Field label="Terms / Footer">
                        <textarea
                            rows="4"
                            value={
                                form.data
                                    .terms_text
                            }
                            onChange={(e) =>
                                form.setData(
                                    'terms_text',
                                    e.target.value,
                                )
                            }
                            className={
                                inputClass
                            }
                        />
                    </Field>

                    <div className="mt-5 flex flex-wrap gap-5">
                        <Check
                            label="Show Price on Voucher"
                            checked={
                                form.data
                                    .show_price
                            }
                            onChange={(v) =>
                                form.setData(
                                    'show_price',
                                    v,
                                )
                            }
                        />

                        <Check
                            label="Show QR on Voucher"
                            checked={
                                form.data.show_qr
                            }
                            onChange={(v) =>
                                form.setData(
                                    'show_qr',
                                    v,
                                )
                            }
                        />
                    </div>

                    <div className="mt-6 flex flex-wrap gap-3">
                        <button
                            type="submit"
                            disabled={
                                form.processing
                            }
                            className="rounded-lg bg-cyan-600 px-5 py-3 font-bold text-white"
                        >
                            Save Branding
                        </button>

                        <a
                            href={route(
                                'hotspot.branding.portal',
                            )}
                            className="rounded-lg bg-violet-600 px-5 py-3 font-bold text-white"
                        >
                            Download MikroTik login.html
                        </a>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({
    label,
    children,
}) {
    return (
        <label className="mt-4 block">
            <div className="mb-1 text-sm font-semibold">
                {label}
            </div>
            {children}
        </label>
    );
}

function Check({
    label,
    checked,
    onChange,
}) {
    return (
        <label className="flex items-center gap-2 font-semibold text-slate-700">
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) =>
                    onChange(
                        e.target.checked,
                    )
                }
                className="rounded border-slate-300 text-cyan-600"
            />
            {label}
        </label>
    );
}
