import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    router,
} from '@inertiajs/react';

export default function Index({
    logs = [],
    resellers = [],
    filters = {},
}) {
    const filter = (
        key,
        value,
    ) => {
        router.get(
            route(
                'superadmin.audit.index',
            ),
            {
                ...filters,
                [key]: value,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <AppLayout title="Reseller Audit Log">
            <Head title="Reseller Audit Log" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black">
                        Audit Log
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Security and administrative
                        reseller activity history.
                    </p>
                </div>

                <div className="flex flex-wrap gap-3 rounded-2xl border bg-white p-4">
                    <select
                        value={
                            filters.reseller_id ??
                            ''
                        }
                        onChange={(e) =>
                            filter(
                                'reseller_id',
                                e.target.value,
                            )
                        }
                        className="rounded-lg border-slate-300"
                    >
                        <option value="">
                            All Resellers
                        </option>

                        {resellers.map(
                            (item) => (
                                <option
                                    key={
                                        item.id
                                    }
                                    value={
                                        item.id
                                    }
                                >
                                    {
                                        item.company_name
                                    }
                                </option>
                            ),
                        )}
                    </select>

                    <input
                        value={
                            filters.action ??
                            ''
                        }
                        onChange={(e) =>
                            filter(
                                'action',
                                e.target.value,
                            )
                        }
                        placeholder="Action filter"
                        className="rounded-lg border-slate-300"
                    />
                </div>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Date</Th>
                                <Th>Reseller</Th>
                                <Th>User</Th>
                                <Th>Action</Th>
                                <Th>Subject</Th>
                                <Th>IP</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {logs.map(
                                (log) => (
                                    <tr key={log.id}>
                                        <Td>
                                            {
                                                log.created_at
                                            }
                                        </Td>

                                        <Td>
                                            {log.reseller
                                                ?.company_name ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {log.user?.name ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            <span className="font-mono text-xs">
                                                {
                                                    log.action
                                                }
                                            </span>
                                        </Td>

                                        <Td>
                                            {log.subject_type
                                                ? `${log.subject_type} #${log.subject_id ?? '-'}`
                                                : '-'}
                                        </Td>

                                        <Td>
                                            {log.ip_address ||
                                                '-'}
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {!logs.length && (
                                <tr>
                                    <td
                                        colSpan="6"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No audit record.
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

function Th({ children }) {
    return (
        <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">
            {children}
        </th>
    );
}

function Td({ children }) {
    return (
        <td className="whitespace-nowrap px-4 py-4 text-sm">
            {children}
        </td>
    );
}
