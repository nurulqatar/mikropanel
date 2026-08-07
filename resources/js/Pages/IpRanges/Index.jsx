import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

export default function Index({
    ranges = [],
    summary = {},
}) {
    const removeRange = (id) => {
        if (
            !confirm(
                'Delete this IP Pool?',
            )
        ) {
            return;
        }

        router.delete(
            route(
                'ip-ranges.destroy',
                id,
            ),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="IP Pools">
            <Head title="IP Pools" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-800">
                            Global IP Pools
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Panel-managed client IP allocation shared
                            across all enabled MikroTik routers.
                        </p>
                    </div>

                    <Link
                        href={route(
                            'ip-ranges.create',
                        )}
                        className="rounded-lg bg-cyan-600 px-5 py-3 font-semibold text-white shadow hover:bg-cyan-700"
                    >
                        + Add IP Pool
                    </Link>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <Stat
                        label="Total Pools"
                        value={
                            summary.total_pools ?? 0
                        }
                    />

                    <Stat
                        label="Enabled Pools"
                        value={
                            summary.enabled_pools ?? 0
                        }
                    />

                    <Stat
                        label="Total IPs"
                        value={
                            formatNumber(
                                summary.total_ips,
                            )
                        }
                    />

                    <Stat
                        label="Used IPs"
                        value={
                            formatNumber(
                                summary.used_ips,
                            )
                        }
                    />

                    <Stat
                        label="Free IPs"
                        value={
                            formatNumber(
                                summary.free_ips,
                            )
                        }
                    />
                </div>

                <div className="overflow-hidden rounded-xl bg-white shadow">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead className="bg-slate-50 text-left text-sm text-slate-500">
                                <tr>
                                    <th className="px-5 py-4">
                                        Pool
                                    </th>

                                    <th className="px-5 py-4">
                                        Network
                                    </th>

                                    <th className="px-5 py-4">
                                        IP Range
                                    </th>

                                    <th className="px-5 py-4 text-center">
                                        Total IP
                                    </th>

                                    <th className="px-5 py-4 text-center">
                                        Used
                                    </th>

                                    <th className="px-5 py-4 text-center">
                                        Free
                                    </th>

                                    <th className="min-w-48 px-5 py-4">
                                        Usage
                                    </th>

                                    <th className="px-5 py-4">
                                        Status
                                    </th>

                                    <th className="px-5 py-4 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                {ranges.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan="9"
                                            className="px-5 py-10 text-center text-slate-500"
                                        >
                                            No IP Pool found.
                                        </td>
                                    </tr>
                                ) : (
                                    ranges.map(
                                        (item) => {
                                            const usage =
                                                Number(
                                                    item.usage_percent ??
                                                        0,
                                                );

                                            return (
                                                <tr
                                                    key={item.id}
                                                    className="border-t border-slate-100"
                                                >
                                                    <td className="px-5 py-4">
                                                        <p className="font-semibold text-slate-800">
                                                            {item.name}
                                                        </p>

                                                        <p className="mt-1 font-mono text-xs text-slate-400">
                                                            Gateway:{' '}
                                                            {item.gateway}
                                                        </p>
                                                    </td>

                                                    <td className="px-5 py-4 font-mono text-sm">
                                                        {item.network}
                                                    </td>

                                                    <td className="px-5 py-4 font-mono text-sm whitespace-nowrap">
                                                        {item.start_ip}
                                                        {' - '}
                                                        {item.end_ip}
                                                    </td>

                                                    <td className="px-5 py-4 text-center text-lg font-bold text-slate-800">
                                                        {formatNumber(
                                                            item.total_ips,
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-4 text-center">
                                                        <span className="rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-700">
                                                            {formatNumber(
                                                                item.used_ips,
                                                            )}
                                                        </span>
                                                    </td>

                                                    <td className="px-5 py-4 text-center">
                                                        <span className="rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-700">
                                                            {formatNumber(
                                                                item.free_ips,
                                                            )}
                                                        </span>
                                                    </td>

                                                    <td className="px-5 py-4">
                                                        <div className="flex items-center justify-between gap-3 text-xs">
                                                            <span className="font-semibold text-slate-600">
                                                                {usage}%
                                                            </span>

                                                            <span className="text-slate-400">
                                                                {formatNumber(
                                                                    item.used_ips,
                                                                )}
                                                                {' / '}
                                                                {formatNumber(
                                                                    item.total_ips,
                                                                )}
                                                            </span>
                                                        </div>

                                                        <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-200">
                                                            <div
                                                                className="h-full rounded-full bg-cyan-600 transition-all"
                                                                style={{
                                                                    width: `${Math.min(
                                                                        100,
                                                                        Math.max(
                                                                            0,
                                                                            usage,
                                                                        ),
                                                                    )}%`,
                                                                }}
                                                            />
                                                        </div>
                                                    </td>

                                                    <td className="px-5 py-4">
                                                        <span
                                                            className={
                                                                item.enabled
                                                                    ? 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-700'
                                                                    : 'rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600'
                                                            }
                                                        >
                                                            {item.enabled
                                                                ? 'Enabled'
                                                                : 'Disabled'}
                                                        </span>
                                                    </td>

                                                    <td className="px-5 py-4">
                                                        <div className="flex justify-end gap-2">
                                                            <Link
                                                                href={route(
                                                                    'ip-ranges.edit',
                                                                    item.id,
                                                                )}
                                                                className="rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-600"
                                                            >
                                                                Edit
                                                            </Link>

                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    removeRange(
                                                                        item.id,
                                                                    )
                                                                }
                                                                className="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700"
                                                            >
                                                                Delete
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            );
                                        },
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

function Stat({
    label,
    value,
}) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-sm font-medium text-slate-500">
                {label}
            </p>

            <p className="mt-2 text-3xl font-bold text-slate-800">
                {value}
            </p>
        </div>
    );
}

function formatNumber(value) {
    const number = Number(
        value ?? 0,
    );

    if (!Number.isFinite(number)) {
        return '0';
    }

    return new Intl.NumberFormat(
        'en-US',
    ).format(number);
}
