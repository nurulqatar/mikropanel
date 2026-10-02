import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
} from '@inertiajs/react';
import {
    useMemo,
    useState,
} from 'react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

export default function Batches({
    batches = [],
}) {
    const [zone, setZone] = useState('');
    const [plan, setPlan] = useState('');
    const [sync, setSync] = useState('');
    const [search, setSearch] = useState('');

    const zones = useMemo(
        () => [
            ...new Map(
                batches
                    .filter((b) => b.zone)
                    .map((b) => [
                        String(b.zone.id),
                        b.zone,
                    ]),
            ).values(),
        ],
        [batches],
    );

    const plans = useMemo(
        () => [
            ...new Map(
                batches
                    .filter((b) => b.plan)
                    .map((b) => [
                        String(b.plan.id),
                        b.plan,
                    ]),
            ).values(),
        ],
        [batches],
    );

    const filtered = useMemo(
        () =>
            batches.filter((batch) => {
                if (
                    zone &&
                    String(batch.zone?.id ?? '') !== zone
                ) {
                    return false;
                }

                if (
                    plan &&
                    String(batch.plan?.id ?? '') !== plan
                ) {
                    return false;
                }

                if (
                    sync &&
                    batch.sync_status !== sync
                ) {
                    return false;
                }

                const q = search.trim().toLowerCase();
                if (!q) {
                    return true;
                }

                return [
                    batch.batch_name,
                    batch.batch_code,
                    batch.zone?.name,
                    batch.plan?.name,
                ]
                    .filter(Boolean)
                    .some((value) =>
                        String(value)
                            .toLowerCase()
                            .includes(q),
                    );
            }),
        [batches, plan, search, sync, zone],
    );

    return (
        <AppLayout title="Hotspot Voucher Batches">
            <Head title="Hotspot Voucher Batches" />

            <div className="space-y-6">
                <div>
                    <Link
                        href={route('hotspot.index')}
                        className="font-semibold text-cyan-700"
                    >
                        ← Hotspot
                    </Link>

                    <h1 className="mt-2 text-3xl font-bold">
                        Voucher Batches
                    </h1>

                    <p className="mt-1 text-sm text-slate-500">
                        Zone-owned batches and synchronization
                        status across every MikroTik in each zone.
                    </p>
                </div>

                <div className="grid gap-3 rounded-2xl border bg-white p-4 shadow-sm md:grid-cols-4">
                    <input
                        value={search}
                        onChange={(e) =>
                            setSearch(e.target.value)
                        }
                        placeholder="Batch name / ID"
                        className="rounded-lg border-slate-300"
                    />

                    <select
                        value={zone}
                        onChange={(e) =>
                            setZone(e.target.value)
                        }
                        className="rounded-lg border-slate-300"
                    >
                        <option value="">All Zones</option>
                        {zones.map((item) => (
                            <option
                                key={item.id}
                                value={item.id}
                            >
                                {item.name}
                            </option>
                        ))}
                    </select>

                    <select
                        value={plan}
                        onChange={(e) =>
                            setPlan(e.target.value)
                        }
                        className="rounded-lg border-slate-300"
                    >
                        <option value="">All Packages</option>
                        {plans.map((item) => (
                            <option
                                key={item.id}
                                value={item.id}
                            >
                                {item.name}
                            </option>
                        ))}
                    </select>

                    <select
                        value={sync}
                        onChange={(e) =>
                            setSync(e.target.value)
                        }
                        className="rounded-lg border-slate-300"
                    >
                        <option value="">All Sync Status</option>
                        <option value="EMPTY">Empty</option>
                        <option value="PENDING">Pending</option>
                        <option value="PARTIAL">Partial</option>
                        <option value="SYNCED">Synced</option>
                        <option value="NO ROUTER">No Router</option>
                    </select>
                </div>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Batch</Th>
                                <Th>Zone</Th>
                                <Th>Package</Th>
                                <Th>Price</Th>
                                <Th>Quantity</Th>
                                <Th>Date</Th>
                                <Th>Sync Status</Th>
                                <Th>Actions</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {filtered.map((batch) => (
                                <tr key={batch.id}>
                                    <Td>
                                        <div className="font-bold text-slate-900">
                                            {batch.batch_name}
                                        </div>
                                        <div className="mt-1 font-mono text-xs text-slate-400">
                                            {batch.batch_code}
                                        </div>
                                    </Td>

                                    <Td>
                                        {batch.zone?.name || '-'}
                                    </Td>

                                    <Td>
                                        {batch.plan?.name || '-'}
                                    </Td>

                                    <Td>
                                        QAR{' '}
                                        {money(batch.plan?.price)}
                                    </Td>

                                    <Td>
                                        {batch.vouchers_count}
                                        <div className="text-xs text-slate-400">
                                            Sold {batch.sold_vouchers_count ?? 0}
                                        </div>
                                    </Td>

                                    <Td>
                                        {batch.created_at || '-'}
                                    </Td>

                                    <Td>
                                        <SyncBadge
                                            status={batch.sync_status}
                                        />
                                        <div className="mt-1 text-xs text-slate-500">
                                            {batch.sync_synced ?? 0}/
                                            {batch.sync_expected ?? 0}
                                            {' routers synced'}
                                            {(batch.sync_failed ?? 0) > 0
                                                ? ` · ${batch.sync_failed} failed`
                                                : ''}
                                        </div>
                                    </Td>

                                    <Td>
                                        <div className="flex gap-2">
                                            <a
                                                target="_blank"
                                                rel="noreferrer"
                                                href={route(
                                                    'hotspot.batches.print',
                                                    batch.id,
                                                )}
                                                className="rounded bg-slate-700 px-3 py-2 text-sm font-semibold text-white"
                                            >
                                                Print
                                            </a>

                                            <a
                                                href={route(
                                                    'hotspot.batches.pdf',
                                                    batch.id,
                                                )}
                                                className="rounded bg-violet-600 px-3 py-2 text-sm font-semibold text-white"
                                            >
                                                PDF
                                            </a>
                                        </div>
                                    </Td>
                                </tr>
                            ))}

                            {filtered.length === 0 && (
                                <tr>
                                    <td
                                        colSpan="8"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No matching voucher batch.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function SyncBadge({ status }) {
    const cls = {
        SYNCED: 'bg-emerald-100 text-emerald-700',
        PARTIAL: 'bg-amber-100 text-amber-700',
        PENDING: 'bg-cyan-100 text-cyan-700',
        EMPTY: 'bg-slate-100 text-slate-600',
        'NO ROUTER': 'bg-red-100 text-red-700',
    }[status] ?? 'bg-slate-100 text-slate-600';

    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-black ${cls}`}
        >
            {status || 'UNKNOWN'}
        </span>
    );
}

function Th({ children }) {
    return (
        <th className="whitespace-nowrap px-5 py-3 text-left text-xs font-bold uppercase text-slate-500">
            {children}
        </th>
    );
}

function Td({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-700">
            {children}
        </td>
    );
}
