import HotelLayout from '@/Layouts/HotelLayout';
import {
    Head,
    Link,
} from '@inertiajs/react';

export default function Index({
    vouchers = [],
}) {
    return (
        <HotelLayout title="Vouchers">
            <Head title="Hotel Vouchers" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black">
                            Guest Vouchers
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Voucher validity follows the
                            guest checkout date.
                        </p>
                    </div>

                    <Link
                        href={route(
                            'hotel.vouchers.create',
                        )}
                        className="rounded-xl bg-emerald-600 px-5 py-2.5 font-black text-white"
                    >
                        Create Voucher
                    </Link>
                </div>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Voucher</Th>
                                <Th>Guest</Th>
                                <Th>Room</Th>
                                <Th>Expiry</Th>
                                <Th>Status</Th>
                                <Th>Source</Th>
                                <Th>Router Sync</Th>
                                <Th>Action</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {vouchers.map(
                                (voucher) => (
                                    <tr
                                        key={
                                            voucher.id
                                        }
                                    >
                                        <Td>
                                            <strong className="font-mono text-base">
                                                {
                                                    voucher.username
                                                }
                                            </strong>
                                        </Td>

                                        <Td>
                                            {
                                                voucher.stay
                                                    ?.guest
                                                    ?.name
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                voucher.stay
                                                    ?.room_number
                                            }
                                        </Td>

                                        <Td>
                                            {new Date(
                                                voucher.expires_at,
                                            ).toLocaleString()}
                                        </Td>

                                        <Td>
                                            {
                                                voucher.status
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                voucher.creation_source
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                routerSyncLabel(
                                                    voucher,
                                                )
                                            }
                                        </Td>

                                        <Td>
                                            <a
                                                href={route(
                                                    'hotel.vouchers.print',
                                                    voucher.id,
                                                )}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="font-black text-indigo-600"
                                            >
                                                Print
                                            </a>
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {vouchers.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="8"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No vouchers created yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </HotelLayout>
    );
}

function routerSyncLabel(voucher) {
    const syncs =
        voucher.router_syncs ?? [];

    if (syncs.length === 0) {
        return 'No router sync';
    }

    const failed =
        syncs.filter(
            (sync) =>
                sync.status === 'failed',
        ).length;

    if (failed > 0) {
        return `${failed} failed / ${syncs.length}`;
    }

    const completed =
        syncs.filter(
            (sync) =>
                sync.status === 'synced' ||
                sync.status === 'expired',
        ).length;

    return `${completed}/${syncs.length} synced`;
}

function Th({ children }) {
    return (
        <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-black uppercase text-slate-500">
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
