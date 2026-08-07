import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

export default function Edit({
    range,
    routers = [],
}) {
    const {
        data,
        setData,
        put,
        processing,
        errors,
    } = useForm({
        router_id: range.router_id ?? '',
        name: range.name ?? '',
        interface: range.interface ?? '',
        network: range.network ?? '',
        gateway: range.gateway ?? '',
        dns_server: range.dns_server ?? '',
        start_ip: range.start_ip ?? '',
        end_ip: range.end_ip ?? '',
        enabled: Boolean(range.enabled),
    });

    const capacity = calculateCapacity(
        data.start_ip,
        data.end_ip,
    );

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
        'mt-1 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100';

    return (
        <AppLayout title="Edit IP Pool">
            <Head title="Edit IP Pool" />

            <div className="mx-auto max-w-5xl space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-800">
                        Edit IP Pool
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Update {range.name}
                    </p>
                </div>

                {Object.keys(errors).length > 0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-5 py-4">
                        <p className="font-bold text-red-700">
                            Please correct the highlighted fields.
                        </p>
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="space-y-6"
                >
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="mb-6 flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-100 text-xl">
                                📡
                            </div>

                            <div>
                                <h2 className="text-xl font-bold text-slate-800">
                                    Router Configuration
                                </h2>

                                <p className="text-sm text-slate-500">
                                    Router and MikroTik interface
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-5 md:grid-cols-2">
                            <Field
                                label="Router"
                                error={errors.router_id}
                                required
                            >
                                <select
                                    value={data.router_id}
                                    onChange={(event) =>
                                        setData(
                                            'router_id',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                >
                                    <option value="">
                                        Select Router
                                    </option>

                                    {routers.map((router) => (
                                        <option
                                            key={router.id}
                                            value={router.id}
                                        >
                                            {router.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <Field
                                label="Interface"
                                error={errors.interface}
                                required
                            >
                                <input
                                    type="text"
                                    value={data.interface}
                                    onChange={(event) =>
                                        setData(
                                            'interface',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="bridge"
                                    className={inputClass}
                                />
                            </Field>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="mb-6 flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100 text-xl">
                                🌐
                            </div>

                            <div>
                                <h2 className="text-xl font-bold text-slate-800">
                                    Network Information
                                </h2>

                                <p className="text-sm text-slate-500">
                                    Network, gateway and DNS configuration
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-5 md:grid-cols-2">
                            <Field
                                label="Pool Name"
                                error={errors.name}
                                required
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
                                label="Network"
                                error={errors.network}
                                required
                            >
                                <input
                                    type="text"
                                    value={data.network}
                                    onChange={(event) =>
                                        setData(
                                            'network',
                                            event.target.value,
                                        )
                                    }
                                    className={`${inputClass} font-mono`}
                                />
                            </Field>

                            <Field
                                label="Gateway"
                                error={errors.gateway}
                                required
                            >
                                <input
                                    type="text"
                                    value={data.gateway}
                                    onChange={(event) =>
                                        setData(
                                            'gateway',
                                            event.target.value,
                                        )
                                    }
                                    className={`${inputClass} font-mono`}
                                />
                            </Field>

                            <Field
                                label="DNS Server"
                                error={errors.dns_server}
                            >
                                <input
                                    type="text"
                                    value={data.dns_server}
                                    onChange={(event) =>
                                        setData(
                                            'dns_server',
                                            event.target.value,
                                        )
                                    }
                                    className={`${inputClass} font-mono`}
                                />
                            </Field>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="mb-6 flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100 text-xl">
                                🔢
                            </div>

                            <div>
                                <h2 className="text-xl font-bold text-slate-800">
                                    Client Address Range
                                </h2>

                                <p className="text-sm text-slate-500">
                                    IP addresses available for automatic allocation
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-5 md:grid-cols-2">
                            <Field
                                label="Start IP"
                                error={errors.start_ip}
                                required
                            >
                                <input
                                    type="text"
                                    value={data.start_ip}
                                    onChange={(event) =>
                                        setData(
                                            'start_ip',
                                            event.target.value,
                                        )
                                    }
                                    className={`${inputClass} font-mono`}
                                />
                            </Field>

                            <Field
                                label="End IP"
                                error={errors.end_ip}
                                required
                            >
                                <input
                                    type="text"
                                    value={data.end_ip}
                                    onChange={(event) =>
                                        setData(
                                            'end_ip',
                                            event.target.value,
                                        )
                                    }
                                    className={`${inputClass} font-mono`}
                                />
                            </Field>
                        </div>

                        <div className="mt-5 rounded-xl border border-cyan-200 bg-cyan-50 p-4">
                            <p className="text-sm font-semibold text-cyan-700">
                                Current Pool Capacity
                            </p>

                            <p className="mt-1 text-2xl font-bold text-cyan-900">
                                {capacity > 0
                                    ? `${capacity.toLocaleString()} IP addresses`
                                    : 'Invalid address range'}
                            </p>
                        </div>
                    </section>

                    <label className="flex cursor-pointer items-center gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
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
                                IP Pool Enabled
                            </span>

                            <span className="text-sm text-slate-500">
                                Disabled pools cannot allocate IP addresses to new clients.
                            </span>
                        </span>
                    </label>

                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800">
                        Changing the Start IP or End IP will not automatically change existing client IP addresses.
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-xl bg-cyan-600 px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing
                                ? 'Updating IP Pool...'
                                : 'Update IP Pool'}
                        </button>

                        <Link
                            href={route('ip-ranges.index')}
                            className="rounded-xl bg-slate-600 px-6 py-3 font-semibold text-white transition hover:bg-slate-700"
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
    required = false,
    children,
}) {
    return (
        <div>
            <label className="block text-sm font-semibold text-slate-700">
                {label}

                {required && (
                    <span className="ml-1 text-red-500">
                        *
                    </span>
                )}
            </label>

            {children}

            {error && (
                <p className="mt-1 text-sm font-medium text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}

function calculateCapacity(startIp, endIp) {
    const start = ipv4ToNumber(startIp);
    const end = ipv4ToNumber(endIp);

    if (
        start === null ||
        end === null ||
        end < start
    ) {
        return 0;
    }

    return end - start + 1;
}

function ipv4ToNumber(ip) {
    const parts = String(ip || '')
        .trim()
        .split('.')
        .map(Number);

    if (
        parts.length !== 4 ||
        parts.some(
            (part) =>
                !Number.isInteger(part) ||
                part < 0 ||
                part > 255,
        )
    ) {
        return null;
    }

    return parts.reduce(
        (total, part) => total * 256 + part,
        0,
    );
}
