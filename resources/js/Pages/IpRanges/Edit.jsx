import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Edit({
    range,
}) {
    const {
        data,
        setData,
        put,
        processing,
        errors,
    } = useForm({
        name: range.name ?? '',
        network: range.network ?? '',
        gateway: range.gateway ?? '',
        dns_server: range.dns_server ?? '',
        start_ip: range.start_ip ?? '',
        end_ip: range.end_ip ?? '',
        enabled: Boolean(range.enabled),
    });

    const submit = (event) => {
        event.preventDefault();

        put(
            route(
                'ip-ranges.update',
                range.id,
            ),
        );
    };

    const inputClass =
        'mt-1 w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500';

    return (
        <AppLayout title="Edit IP Pool">
            <Head title="Edit IP Pool" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-800">
                        Edit Global IP Pool
                    </h1>

                    <p className="mt-1 text-slate-500">
                        {range.name}
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-xl bg-white p-6 shadow"
                >
                    <div className="rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-800">
                        This pool is global. Router and MikroTik
                        interface are configured from the Routers menu.
                    </div>

                    <div className="grid gap-5 md:grid-cols-2">
                        <Field
                            label="Pool Name"
                            error={errors.name}
                        >
                            <input
                                className={inputClass}
                                value={data.name}
                                onChange={(event) =>
                                    setData(
                                        'name',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>

                        <Field
                            label="Network"
                            error={errors.network}
                        >
                            <input
                                className={inputClass}
                                value={data.network}
                                onChange={(event) =>
                                    setData(
                                        'network',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>

                        <Field
                            label="Gateway"
                            error={errors.gateway}
                        >
                            <input
                                className={inputClass}
                                value={data.gateway}
                                onChange={(event) =>
                                    setData(
                                        'gateway',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>

                        <Field
                            label="DNS Server"
                            error={errors.dns_server}
                        >
                            <input
                                className={inputClass}
                                value={data.dns_server}
                                onChange={(event) =>
                                    setData(
                                        'dns_server',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>

                        <Field
                            label="Start IP"
                            error={errors.start_ip}
                        >
                            <input
                                className={inputClass}
                                value={data.start_ip}
                                onChange={(event) =>
                                    setData(
                                        'start_ip',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>

                        <Field
                            label="End IP"
                            error={errors.end_ip}
                        >
                            <input
                                className={inputClass}
                                value={data.end_ip}
                                onChange={(event) =>
                                    setData(
                                        'end_ip',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>

                    <label className="flex items-center gap-2 rounded-lg bg-slate-50 p-4">
                        <input
                            type="checkbox"
                            checked={data.enabled}
                            onChange={(event) =>
                                setData(
                                    'enabled',
                                    event.target.checked,
                                )
                            }
                        />

                        Enabled
                    </label>

                    <div className="flex gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-cyan-600 px-6 py-3 font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Updating...'
                                : 'Update IP Pool'}
                        </button>

                        <Link
                            href={route('ip-ranges.index')}
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

function Field({
    label,
    error,
    children,
}) {
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
