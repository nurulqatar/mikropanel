import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Create({
    zones = [],
    selectedZoneId = null,
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        zone_id:
            selectedZoneId
                ? String(selectedZoneId)
                : zones.length === 1
                  ? String(zones[0].id)
                  : '',
        name: '',
        network: '',
        gateway: '',
        dns_server: '',
        start_ip: '',
        end_ip: '',
        enabled: true,
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('ip-ranges.store'));
    };

    const inputClass =
        'mt-1 w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500';

    return (
        <AppLayout title="Add IP Pool">
            <Head title="Add IP Pool" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-800">
                        Add Zone IP Pool
                    </h1>

                    <p className="mt-1 text-slate-500">
                        IP allocation is isolated inside the selected MAC Network Zone.
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-xl bg-white p-6 shadow"
                >
                    <div className="rounded-lg border border-cyan-200 bg-cyan-50 px-4 py-3 text-sm text-cyan-800">
                        Every client in this pool belongs to the selected
                        zone. The same private subnet may be reused in a different
                        remote zone without consuming IPs from this zone.
                    </div>

                    <div className="grid gap-5 md:grid-cols-2">
                        <Field
                            label="Network Zone"
                            error={errors.zone_id}
                        >
                            <select
                                className={inputClass}
                                value={data.zone_id}
                                onChange={(event) =>
                                    setData(
                                        'zone_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">
                                    Select MAC Zone
                                </option>

                                {zones.map((zone) => (
                                    <option
                                        key={zone.id}
                                        value={zone.id}
                                    >
                                        {zone.name}
                                        {zone.code
                                            ? ` — ${zone.code}`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                        </Field>

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
                                placeholder="Main Client Pool"
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
                                placeholder="172.10.12.0/22"
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
                                placeholder="172.10.12.1"
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
                                placeholder="8.8.8.8"
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
                                placeholder="172.10.12.2"
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
                                placeholder="172.10.15.255"
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
                                ? 'Saving...'
                                : 'Save IP Pool'}
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
