import HotelLayout from '@/Layouts/HotelLayout';
import {
    Head,
    useForm,
} from '@inertiajs/react';

const inputClass =
    'w-full rounded-xl border-slate-300';

export default function Settings({
    settings = {},
}) {
    const form =
        useForm({
            name:
                settings.name ?? '',

            owner_name:
                settings.owner_name ?? '',

            email:
                settings.email ?? '',

            phone:
                settings.phone ?? '',

            whatsapp:
                settings.whatsapp ?? '',

            address:
                settings.address ?? '',

            timezone:
                settings.timezone
                ?? 'Asia/Qatar',

            currency:
                settings.currency
                ?? 'QAR',

            check_out_time:
                settings.check_out_time
                ?? '12:00',

            portal_title:
                settings.portal_title
                ?? 'Welcome to our Guest WiFi',

            portal_subtitle:
                settings.portal_subtitle
                ?? '',

            primary_color:
                settings.primary_color
                ?? '#0f766e',

            secondary_color:
                settings.secondary_color
                ?? '#0f172a',

            default_locale:
                settings.default_locale
                ?? 'en',

            enabled_locales:
                settings.enabled_locales
                ?? 'en',

            terms_text:
                settings.terms_text
                ?? '',

            privacy_text:
                settings.privacy_text
                ?? '',

            logo: null,
            background: null,
        });

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route(
                'hotel.settings.update',
            ),
            {
                forceFormData: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <HotelLayout title="Hotel Settings">
            <Head title="Hotel Settings" />

            <form
                onSubmit={submit}
                className="space-y-6"
            >
                <section className="rounded-2xl border bg-white p-6 shadow-sm">
                    <h1 className="text-2xl font-black">
                        Hotel Identity
                    </h1>

                    <div className="mt-5 grid gap-5 md:grid-cols-2">
                        <Input
                            label="Hotel Name"
                            value={
                                form.data.name
                            }
                            onChange={(v) =>
                                form.setData(
                                    'name',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Owner / Contact"
                            value={
                                form.data
                                    .owner_name
                            }
                            onChange={(v) =>
                                form.setData(
                                    'owner_name',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Email"
                            type="email"
                            value={
                                form.data.email
                            }
                            onChange={(v) =>
                                form.setData(
                                    'email',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Phone"
                            value={
                                form.data.phone
                            }
                            onChange={(v) =>
                                form.setData(
                                    'phone',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="WhatsApp"
                            value={
                                form.data
                                    .whatsapp
                            }
                            onChange={(v) =>
                                form.setData(
                                    'whatsapp',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Timezone"
                            value={
                                form.data
                                    .timezone
                            }
                            onChange={(v) =>
                                form.setData(
                                    'timezone',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Currency"
                            value={
                                form.data
                                    .currency
                            }
                            onChange={(v) =>
                                form.setData(
                                    'currency',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Checkout Time"
                            type="time"
                            value={
                                form.data
                                    .check_out_time
                            }
                            onChange={(v) =>
                                form.setData(
                                    'check_out_time',
                                    v,
                                )
                            }
                        />

                        <label className="md:col-span-2">
                            <div className="mb-1 text-sm font-bold">
                                Address
                            </div>

                            <textarea
                                rows="3"
                                value={
                                    form.data
                                        .address
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'address',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </label>
                    </div>
                </section>

                <section className="rounded-2xl border bg-white p-6 shadow-sm">
                    <h2 className="text-xl font-black">
                        Captive Portal Branding
                    </h2>

                    <div className="mt-5 grid gap-5 md:grid-cols-2">
                        <Input
                            label="Welcome Title"
                            value={
                                form.data
                                    .portal_title
                            }
                            onChange={(v) =>
                                form.setData(
                                    'portal_title',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Welcome Subtitle"
                            value={
                                form.data
                                    .portal_subtitle
                            }
                            onChange={(v) =>
                                form.setData(
                                    'portal_subtitle',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Primary Color"
                            type="color"
                            value={
                                form.data
                                    .primary_color
                            }
                            onChange={(v) =>
                                form.setData(
                                    'primary_color',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Secondary Color"
                            type="color"
                            value={
                                form.data
                                    .secondary_color
                            }
                            onChange={(v) =>
                                form.setData(
                                    'secondary_color',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Default Language Code"
                            value={
                                form.data
                                    .default_locale
                            }
                            onChange={(v) =>
                                form.setData(
                                    'default_locale',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Enabled Language Codes"
                            value={
                                form.data
                                    .enabled_locales
                            }
                            onChange={(v) =>
                                form.setData(
                                    'enabled_locales',
                                    v,
                                )
                            }
                        />

                        <label>
                            <div className="mb-1 text-sm font-bold">
                                Hotel Logo
                            </div>

                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                onChange={(e) =>
                                    form.setData(
                                        'logo',
                                        e.target
                                            .files?.[0]
                                        ?? null,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />

                            {settings.logo_url && (
                                <img
                                    src={
                                        settings.logo_url
                                    }
                                    alt="Hotel logo"
                                    className="mt-3 h-16 max-w-48 object-contain"
                                />
                            )}
                        </label>

                        <label>
                            <div className="mb-1 text-sm font-bold">
                                Portal Background
                            </div>

                            <input
                                type="file"
                                accept="image/png,image/jpeg,image/webp"
                                onChange={(e) =>
                                    form.setData(
                                        'background',
                                        e.target
                                            .files?.[0]
                                        ?? null,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />

                            {settings.background_url && (
                                <img
                                    src={
                                        settings.background_url
                                    }
                                    alt="Portal background"
                                    className="mt-3 h-24 w-48 rounded-xl object-cover"
                                />
                            )}
                        </label>
                    </div>
                </section>

                <section className="rounded-2xl border bg-white p-6 shadow-sm">
                    <h2 className="text-xl font-black">
                        Guest Terms & Privacy
                    </h2>

                    <div className="mt-5 grid gap-5 lg:grid-cols-2">
                        <label>
                            <div className="mb-1 text-sm font-bold">
                                Terms & Conditions
                            </div>

                            <textarea
                                rows="12"
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
                        </label>

                        <label>
                            <div className="mb-1 text-sm font-bold">
                                Privacy Notice
                            </div>

                            <textarea
                                rows="12"
                                value={
                                    form.data
                                        .privacy_text
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'privacy_text',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </label>
                    </div>
                </section>

                {Object.keys(
                    form.errors,
                ).length > 0 && (
                    <div className="rounded-xl bg-red-50 p-4 font-bold text-red-700">
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
                        className="rounded-xl bg-emerald-600 px-7 py-3 font-black text-white disabled:opacity-50"
                    >
                        Save Hotel Settings
                    </button>
                </div>
            </form>
        </HotelLayout>
    );
}

function Input({
    label,
    value,
    onChange,
    type = 'text',
}) {
    return (
        <label>
            <div className="mb-1 text-sm font-bold">
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
        </label>
    );
}
