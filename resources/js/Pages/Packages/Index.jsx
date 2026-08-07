import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function Index({
    packages,
    flash = {},
}) {
    const deletePackage = (id) => {
        if (!confirm('Delete this package?')) {
            return;
        }

        router.delete(
            route('packages.destroy', id),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="Internet Packages">
            <Head title="Internet Packages" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-800">
                            Internet Packages
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Manage package price, validity and speed
                        </p>
                    </div>

                    <Link
                        href={route('packages.create')}
                        className="rounded-lg bg-cyan-600 px-5 py-3 font-semibold text-white shadow hover:bg-cyan-700"
                    >
                        + Add Package
                    </Link>
                </div>

                {flash?.success && (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                        {flash.error}
                    </div>
                )}

                {packages.length === 0 ? (
                    <div className="rounded-2xl bg-white p-12 text-center shadow">
                        <div className="text-5xl">
                            📦
                        </div>

                        <h2 className="mt-4 text-xl font-bold text-slate-800">
                            No Package Found
                        </h2>

                        <p className="mt-2 text-slate-500">
                            Create your first internet package.
                        </p>

                        <Link
                            href={route('packages.create')}
                            className="mt-5 inline-block rounded-lg bg-cyan-600 px-5 py-3 font-semibold text-white hover:bg-cyan-700"
                        >
                            Add Package
                        </Link>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow">
                        <div className="overflow-x-auto">
                            <table className="min-w-full">
                                <thead className="bg-slate-100">
                                    <tr>
                                        <TableHead>
                                            Package
                                        </TableHead>

                                        <TableHead>
                                            Price
                                        </TableHead>

                                        <TableHead>
                                            Validity
                                        </TableHead>

                                        <TableHead>
                                            Download
                                        </TableHead>

                                        <TableHead>
                                            Upload
                                        </TableHead>

                                        <TableHead>
                                            Status
                                        </TableHead>

                                        <TableHead>
                                            Actions
                                        </TableHead>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-200">
                                    {packages.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="hover:bg-slate-50"
                                        >
                                            <td className="px-5 py-4">
                                                <div className="font-bold text-slate-800">
                                                    {item.name}
                                                </div>

                                                {item.mikrotik_profile && (
                                                    <div className="mt-1 text-xs text-slate-400">
                                                        Profile:{' '}
                                                        {item.mikrotik_profile}
                                                    </div>
                                                )}
                                            </td>

                                            <td className="px-5 py-4 font-semibold text-slate-700">
                                                {formatPrice(
                                                    item.price,
                                                )}
                                            </td>

                                            <td className="px-5 py-4">
                                                <span className="rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-700">
                                                    {item.validity_days}{' '}
                                                    Days
                                                </span>
                                            </td>

                                            <td className="px-5 py-4">
                                                <SpeedBadge
                                                    value={
                                                        item.speed_download
                                                    }
                                                />
                                            </td>

                                            <td className="px-5 py-4">
                                                <SpeedBadge
                                                    value={
                                                        item.speed_upload
                                                    }
                                                />
                                            </td>

                                            <td className="px-5 py-4">
                                                <StatusBadge
                                                    enabled={Boolean(
                                                        item.enabled,
                                                    )}
                                                />
                                            </td>

                                            <td className="px-5 py-4">
                                                <div className="flex flex-wrap gap-2">
                                                    <Link
                                                        href={route(
                                                            'packages.edit',
                                                            item.id,
                                                        )}
                                                        className="rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-600"
                                                    >
                                                        Edit
                                                    </Link>

                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            deletePackage(
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
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function TableHead({ children }) {
    return (
        <th className="whitespace-nowrap px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
            {children}
        </th>
    );
}

function SpeedBadge({ value }) {
    return (
        <span className="rounded-lg bg-cyan-50 px-3 py-2 font-bold text-cyan-700">
            {value || 0} Mbps
        </span>
    );
}

function StatusBadge({ enabled }) {
    return (
        <span
            className={`rounded-full px-3 py-1 text-xs font-bold ${
                enabled
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-red-100 text-red-700'
            }`}
        >
            {enabled ? '● Enabled' : '● Disabled'}
        </span>
    );
}

function formatPrice(value) {
    const amount = Number(value);

    if (!Number.isFinite(amount)) {
        return value ?? '-';
    }

    return amount.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}
