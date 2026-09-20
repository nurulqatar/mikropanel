import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import {
    Head,
    Link,
} from '@inertiajs/react';

export default function Dashboard({
    stats = {},
    recentHotels = [],
}) {
    const cards = [
        [
            'Hotels',
            stats.hotels ?? 0,
        ],
        [
            'Active Hotels',
            stats.active_hotels ?? 0,
        ],
        [
            'Active Plans',
            stats.plans ?? 0,
        ],
        [
            'Subscriptions',
            stats.active_subscriptions
                ?? 0,
        ],
        [
            'Hotel Users',
            stats.hotel_users ?? 0,
        ],
    ];

    return (
        <SuperAdminLayout title="Hotel Hotspot">
            <Head title="Hotel Hotspot" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black">
                            Hotel Hotspot
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Independent Hotel Guest
                            WiFi SaaS management.
                        </p>
                    </div>

                    <div className="flex gap-2">
                        <Link
                            href={route(
                                'superadmin.hotel.hotels.create',
                            )}
                            className="rounded-xl bg-emerald-600 px-4 py-2.5 font-black text-white"
                        >
                            Add Hotel
                        </Link>

                        <Link
                            href={route(
                                'superadmin.hotel.plans.index',
                            )}
                            className="rounded-xl bg-indigo-600 px-4 py-2.5 font-black text-white"
                        >
                            Hotel Plans
                        </Link>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    {cards.map(
                        ([
                            label,
                            value,
                        ]) => (
                            <div
                                key={
                                    label
                                }
                                className="rounded-2xl border bg-white p-5 shadow-sm"
                            >
                                <div className="text-xs font-black uppercase tracking-wide text-slate-400">
                                    {label}
                                </div>

                                <div className="mt-2 text-3xl font-black">
                                    {value}
                                </div>
                            </div>
                        ),
                    )}
                </div>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Hotel</Th>
                                <Th>Code</Th>
                                <Th>Plan</Th>
                                <Th>Status</Th>
                                <Th>Action</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {recentHotels.map(
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

                            {recentHotels.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="5"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No Hotel tenant created yet.
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
