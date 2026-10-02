import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import {
    Head,
    Link,
} from '@inertiajs/react';

export default function Index({
    hotels = [],
}) {
    return (
        <SuperAdminLayout title="Hotel Hotspot · Hotels">
            <Head title="Hotels" />

            <div className="space-y-6">
                <div className="flex items-end justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black">
                            Hotels
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Hotel Hotspot tenants.
                        </p>
                    </div>

                    <Link
                        href={route(
                            'superadmin.hotel.hotels.create',
                        )}
                        className="rounded-xl bg-emerald-600 px-5 py-2.5 font-black text-white"
                    >
                        Add Hotel
                    </Link>
                </div>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Hotel</Th>
                                <Th>Code</Th>
                                <Th>Plan</Th>
                                <Th>Users</Th>
                                <Th>Status</Th>
                                <Th>Action</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {hotels.map(
                                (hotel) => (
                                    <tr
                                        key={
                                            hotel.id
                                        }
                                    >
                                        <Td>
                                            {
                                                hotel.name
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                hotel.code
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                hotel.active_subscription
                                                    ?.plan
                                                    ?.name
                                                ?? '-'
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                hotel.users_count
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                hotel.status
                                            }
                                        </Td>

                                        <Td>
                                            <Link
                                                href={route(
                                                    'superadmin.hotel.hotels.edit',
                                                    hotel.id,
                                                )}
                                                className="font-black text-indigo-600"
                                            >
                                                Manage
                                            </Link>
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {hotels.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="6"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No Hotels yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </SuperAdminLayout>
    );
}

function Th({ children }) {
    return (
        <th className="px-4 py-3 text-left text-xs font-black uppercase text-slate-500">
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
