import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
} from '@inertiajs/react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

export default function Batches({
    batches = [],
}) {
    return (
        <AppLayout title="Hotspot Voucher Batches">
            <Head title="Hotspot Voucher Batches" />

            <div className="space-y-6">
                <div>
                    <Link
                        href={route(
                            'hotspot.index',
                        )}
                        className="font-semibold text-cyan-700"
                    >
                        ← Hotspot
                    </Link>

                    <h1 className="mt-2 text-3xl font-bold">
                        Voucher Batches
                    </h1>
                </div>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Batch</Th>
                                <Th>Server</Th>
                                <Th>Plan</Th>
                                <Th>Price</Th>
                                <Th>Quantity</Th>
                                <Th>Created</Th>
                                <Th>Actions</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {batches.map(
                                (batch) => (
                                    <tr
                                        key={
                                            batch.id
                                        }
                                    >
                                        <Td>
                                            {
                                                batch.batch_code
                                            }
                                        </Td>

                                        <Td>
                                            {batch.server?.name}
                                        </Td>

                                        <Td>
                                            {batch.plan?.name}
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {money(
                                                batch.plan
                                                    ?.price,
                                            )}
                                        </Td>

                                        <Td>
                                            {
                                                batch.vouchers_count
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                batch.created_at
                                            }
                                        </Td>

                                        <Td>
                                            <div className="flex gap-2">
                                                <a
                                                    target="_blank"
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
                                ),
                            )}

                            {batches.length === 0 && (
                                <tr>
                                    <td
                                        colSpan="7"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No voucher batch yet.
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
        <th className="px-5 py-3 text-left text-xs font-bold uppercase text-slate-500">
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
