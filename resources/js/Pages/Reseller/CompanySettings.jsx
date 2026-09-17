import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

export default function CompanySettings({
    company = {},
}) {
    const {
        data,
        setData,
        put,
        processing,
        errors,
        recentlySuccessful,
    } = useForm({
        company_name:
            company.company_name ?? '',
        owner_name:
            company.owner_name ?? '',
        email:
            company.email ?? '',
        phone:
            company.phone ?? '',
        address:
            company.address ?? '',
        timezone:
            company.timezone ??
            'Asia/Qatar',
        currency:
            company.currency ?? 'QAR',
    });

    const submit = (event) => {
        event.preventDefault();

        put(
            route(
                'reseller.company-settings.update',
            ),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="Company Settings">
            <Head title="Company Settings" />

            <div className="mx-auto max-w-5xl space-y-6">
                <div>
                    <Link
                        href={route(
                            'reseller.dashboard',
                        )}
                        className="font-semibold text-cyan-700"
                    >
                        ← Reseller Dashboard
                    </Link>

                    <h1 className="mt-2 text-3xl font-black text-slate-900">
                        Company Settings
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Manage your reseller company
                        profile and regional settings.
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div className="grid gap-5 md:grid-cols-2">
                        <Input
                            label="Company Name"
                            value={
                                data.company_name
                            }
                            error={
                                errors.company_name
                            }
                            onChange={(value) =>
                                setData(
                                    'company_name',
                                    value,
                                )
                            }
                        />

                        <Input
                            label="Owner Name"
                            value={
                                data.owner_name
                            }
                            error={
                                errors.owner_name
                            }
                            onChange={(value) =>
                                setData(
                                    'owner_name',
                                    value,
                                )
                            }
                        />

                        <Input
                            label="Company Email"
                            type="email"
                            value={data.email}
                            error={errors.email}
                            onChange={(value) =>
                                setData(
                                    'email',
                                    value,
                                )
                            }
                        />

                        <Input
                            label="Phone"
                            value={data.phone}
                            error={errors.phone}
                            onChange={(value) =>
                                setData(
                                    'phone',
                                    value,
                                )
                            }
                        />

                        <Input
                            label="Timezone"
                            value={
                                data.timezone
                            }
                            error={
                                errors.timezone
                            }
                            onChange={(value) =>
                                setData(
                                    'timezone',
                                    value,
                                )
                            }
                        />

                        <Input
                            label="Currency"
                            value={
                                data.currency
                            }
                            error={
                                errors.currency
                            }
                            onChange={(value) =>
                                setData(
                                    'currency',
                                    value,
                                )
                            }
                        />

                        <label className="md:col-span-2">
                            <div className="mb-1 text-sm font-semibold text-slate-700">
                                Company Address
                            </div>

                            <textarea
                                rows="4"
                                value={
                                    data.address
                                }
                                onChange={(e) =>
                                    setData(
                                        'address',
                                        e.target.value,
                                    )
                                }
                                className="w-full rounded-xl border-slate-300"
                            />

                            {errors.address && (
                                <div className="mt-1 text-sm font-semibold text-red-600">
                                    {
                                        errors.address
                                    }
                                </div>
                            )}
                        </label>
                    </div>

                    <div className="mt-6 flex items-center gap-4">
                        <button
                            disabled={
                                processing
                            }
                            className="rounded-xl bg-cyan-700 px-6 py-3 font-bold text-white disabled:opacity-50"
                        >
                            {processing
                                ? 'SAVING...'
                                : 'SAVE COMPANY SETTINGS'}
                        </button>

                        {recentlySuccessful && (
                            <span className="font-semibold text-emerald-600">
                                Saved
                            </span>
                        )}
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
            <div className="mb-1 text-sm font-semibold text-slate-700">
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
                className="w-full rounded-xl border-slate-300"
            />

            {error && (
                <div className="mt-1 text-sm font-semibold text-red-600">
                    {error}
                </div>
            )}
        </label>
    );
}
