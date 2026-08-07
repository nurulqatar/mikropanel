import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

export default function Edit({ package: pkg }) {
    const {
        data,
        setData,
        put,
        processing,
        errors,
    } = useForm({
        name: pkg.name ?? '',
        price: pkg.price ?? '',
        validity_days: pkg.validity_days ?? 30,
        speed_download: pkg.speed_download ?? '',
        speed_upload: pkg.speed_upload ?? '',
        mikrotik_profile: pkg.mikrotik_profile ?? '',
        enabled: Boolean(pkg.enabled),
    });

    const submit = (event) => {
        event.preventDefault();

        put(route('packages.update', pkg.id));
    };

    const inputClass =
        'mt-1 w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500';

    return (
        <AppLayout title="Edit Package">
            <Head title="Edit Package" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-800">
                        Edit Internet Package
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Update {pkg.name}
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6"
                >
                    <section className="rounded-2xl bg-white p-6 shadow">
                        <h2 className="mb-5 text-xl font-bold text-slate-800">
                            Package Information
                        </h2>

                        <div className="grid gap-5 md:grid-cols-2">
                            <Field
                                label="Package Name"
                                error={errors.name}
                            >
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(event) =>
                                        setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                    autoFocus
                                />
                            </Field>

                            <Field
                                label="Price"
                                error={errors.price}
                            >
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.price}
                                    onChange={(event) =>
                                        setData(
                                            'price',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>

                            <Field
                                label="Validity (Days)"
                                error={errors.validity_days}
                            >
                                <input
                                    type="number"
                                    min="1"
                                    value={data.validity_days}
                                    onChange={(event) =>
                                        setData(
                                            'validity_days',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>

                            <Field
                                label="MikroTik Profile (Optional)"
                                error={errors.mikrotik_profile}
                            >
                                <input
                                    type="text"
                                    value={data.mikrotik_profile}
                                    onChange={(event) =>
                                        setData(
                                            'mikrotik_profile',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Optional profile name"
                                    className={inputClass}
                                />
                            </Field>
                        </div>
                    </section>

                    <section className="rounded-2xl bg-white p-6 shadow">
                        <h2 className="mb-2 text-xl font-bold text-slate-800">
                            Speed Limit
                        </h2>

                        <p className="mb-5 text-sm text-slate-500">
                            শুধু number লিখুন। MikroTik Queue-তে M automatic যোগ হবে।
                        </p>

                        <div className="grid gap-5 md:grid-cols-2">
                            <Field
                                label="Download Speed (Mbps)"
                                error={errors.speed_download}
                            >
                                <input
                                    type="number"
                                    min="1"
                                    value={data.speed_download}
                                    onChange={(event) =>
                                        setData(
                                            'speed_download',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>

                            <Field
                                label="Upload Speed (Mbps)"
                                error={errors.speed_upload}
                            >
                                <input
                                    type="number"
                                    min="1"
                                    value={data.speed_upload}
                                    onChange={(event) =>
                                        setData(
                                            'speed_upload',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>
                        </div>
                    </section>

                    <label className="flex items-center gap-3 rounded-xl bg-white p-5 shadow">
                        <input
                            type="checkbox"
                            checked={data.enabled}
                            onChange={(event) =>
                                setData(
                                    'enabled',
                                    event.target.checked,
                                )
                            }
                            className="h-5 w-5 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                        />

                        <span>
                            <span className="block font-bold text-slate-800">
                                Package Enabled
                            </span>

                            <span className="text-sm text-slate-500">
                                Disabled packages cannot be assigned to new clients.
                            </span>
                        </span>
                    </label>

                    <div className="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Package speed পরিবর্তন করলে existing client-এর
                        MikroTik Queue সঙ্গে সঙ্গে পরিবর্তন হবে না।
                        Client Edit করে Update করলে নতুন speed apply হবে।
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-cyan-600 px-6 py-3 font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Updating Package...'
                                : 'Update Package'}
                        </button>

                        <Link
                            href={route('packages.index')}
                            className="rounded-lg bg-slate-600 px-6 py-3 font-semibold text-white hover:bg-slate-700"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ label, error, children }) {
    return (
        <div>
            <label className="block text-sm font-semibold text-slate-700">
                {label}
            </label>

            {children}

            {error && (
                <p className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}
