import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import {
    Head,
    router,
} from '@inertiajs/react';

const money = (value) =>
    Number(value || 0).toLocaleString(
        undefined,
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        },
    );

export default function Index({
    requests = [],
    stats = {},
}) {
    const approve = (id) => {
        if (
            !window.confirm(
                'Approve this reseller registration and activate the selected package?',
            )
        ) {
            return;
        }

        router.post(
            route(
                'superadmin.registrations.approve',
                id,
            ),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const reject = (id) => {
        const reason =
            window.prompt(
                'Reason for rejection:',
                '',
            );

        if (reason === null) {
            return;
        }

        router.post(
            route(
                'superadmin.registrations.reject',
                id,
            ),
            {
                reason,
            },
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <SuperAdminLayout title="Registration Requests">
            <Head title="Registration Requests" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black text-slate-900">
                        Reseller Registration Requests
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Website signups and package approval queue.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <Stat
                        label="Pending"
                        value={stats.pending}
                    />
                    <Stat
                        label="Approved"
                        value={stats.approved}
                    />
                    <Stat
                        label="Rejected"
                        value={stats.rejected}
                    />
                    <Stat
                        label="Total"
                        value={stats.total}
                    />
                </div>

                <div className="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Submitted</Th>
                                    <Th>Business</Th>
                                    <Th>Contact</Th>
                                    <Th>Package</Th>
                                    <Th>Status</Th>
                                    <Th>Review</Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {requests.map(
                                    (item) => (
                                        <tr key={item.id}>
                                            <Td>
                                                {item.created_at}
                                            </Td>

                                            <Td>
                                                <div className="font-bold text-slate-900">
                                                    {item.company_name}
                                                </div>
                                                <div className="text-slate-500">
                                                    Owner: {item.owner_name}
                                                </div>
                                                {item.address && (
                                                    <div className="mt-1 max-w-xs whitespace-normal text-xs text-slate-400">
                                                        {item.address}
                                                    </div>
                                                )}
                                            </Td>

                                            <Td>
                                                <div>
                                                    {item.email}
                                                </div>
                                                <div className="text-slate-500">
                                                    {item.phone || '—'}
                                                </div>
                                            </Td>

                                            <Td>
                                                <div className="font-bold">
                                                    {item.plan_name}
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    QAR {money(item.price)}
                                                    {' · '}
                                                    {item.validity_days} days
                                                    {' · '}
                                                    {item.client_limit} clients
                                                </div>
                                            </Td>

                                            <Td>
                                                <Status
                                                    value={item.status}
                                                />

                                                {item.auto_approved && (
                                                    <div className="mt-2 text-xs font-bold text-cyan-700">
                                                        AUTO FREE TRIAL
                                                    </div>
                                                )}

                                                {item.review_notes && (
                                                    <div className="mt-2 max-w-xs whitespace-normal text-xs text-slate-500">
                                                        {item.review_notes}
                                                    </div>
                                                )}
                                            </Td>

                                            <Td>
                                                {item.status === 'pending' ? (
                                                    <div className="flex gap-2">
                                                        <button
                                                            onClick={() =>
                                                                approve(item.id)
                                                            }
                                                            className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-black text-white"
                                                        >
                                                            Approve
                                                        </button>

                                                        <button
                                                            onClick={() =>
                                                                reject(item.id)
                                                            }
                                                            className="rounded-lg bg-red-600 px-3 py-2 text-xs font-black text-white"
                                                        >
                                                            Reject
                                                        </button>
                                                    </div>
                                                ) : (
                                                    <div className="text-xs text-slate-500">
                                                        {item.reviewer?.name
                                                            ? `By ${item.reviewer.name}`
                                                            : item.auto_approved
                                                              ? 'Automatic'
                                                              : 'Reviewed'}
                                                        {item.reviewed_at
                                                            ? ` · ${item.reviewed_at}`
                                                            : ''}
                                                    </div>
                                                )}
                                            </Td>
                                        </tr>
                                    ),
                                )}

                                {requests.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-5 py-12 text-center text-slate-400"
                                        >
                                            No website registrations yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </SuperAdminLayout>
    );
}

function Stat({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border bg-white p-5 shadow-sm">
            <div className="text-xs font-bold uppercase text-slate-500">
                {label}
            </div>
            <div className="mt-2 text-3xl font-black text-slate-900">
                {value ?? 0}
            </div>
        </div>
    );
}

function Th({ children }) {
    return (
        <th className="whitespace-nowrap px-5 py-3 text-left text-xs font-black uppercase text-slate-500">
            {children}
        </th>
    );
}

function Td({ children }) {
    return (
        <td className="whitespace-nowrap px-5 py-4 align-top text-slate-700">
            {children}
        </td>
    );
}

function Status({ value }) {
    const cls =
        value === 'approved'
            ? 'bg-emerald-100 text-emerald-700'
            : value === 'rejected'
              ? 'bg-red-100 text-red-700'
              : 'bg-amber-100 text-amber-700';

    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-black uppercase ${cls}`}
        >
            {value}
        </span>
    );
}
