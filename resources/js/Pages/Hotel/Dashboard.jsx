import HotelLayout from '@/Layouts/HotelLayout';
import {
    Head,
    Link,
    usePage,
} from '@inertiajs/react';

export default function Dashboard({
    usage = {},
    stats = {},
}) {
    const hotelAuth =
        usePage().props.hotelAuth
        ?? {};

    const user =
        hotelAuth.user
        ?? {};

    return (
        <HotelLayout title="Dashboard">
            <Head title="Hotel Dashboard" />
            {/* HOTEL_COMPLIANCE_ACCESS_V1 */}
            <a
                href={route('hotel.compliance.enter')}
                className="mb-6 block rounded-2xl border border-cyan-200 bg-cyan-50 p-5 transition hover:border-cyan-400"
            >
                <div className="font-black text-slate-900">Internet Compliance</div>
                <div className="mt-1 text-sm text-slate-600">Open Logging / Filtering</div>
            </a>


            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black text-slate-900">
                        Hotel WiFi Dashboard
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Hotel Guest WiFi management is
                        completely separate from the
                        Company ISP panel.
                    </p>
                </div>

                {!usage.usable && (
                    <div className="rounded-2xl border border-red-200 bg-red-50 p-5 font-bold text-red-700">
                        Hotel subscription is inactive
                        or expired. Contact Super Admin.
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Card
                        label="Active Guests"
                        value={
                            stats.active_stays
                            ?? 0
                        }
                    />

                    <Card
                        label="Active Vouchers"
                        value={
                            stats.active_vouchers
                            ?? 0
                        }
                    />
                    <Card
                        label="Guest Limit"
                        value={
                            usage.guest_unlimited
                                ? 'Unlimited'
                                : usage.guest_limit
                                    ?? 0
                        }
                    />

                    <Card
                        label="Router Limit"
                        value={
                            usage.router_unlimited
                                ? 'Unlimited'
                                : usage.router_limit
                                    ?? 0
                        }
                    />

                    <Card
                        label="Receptionists"
                        value={
                            `${usage.receptionist_usage ?? 0} / ${
                                usage.receptionist_unlimited
                                    ? 'Unlimited'
                                    : usage.receptionist_limit
                                        ?? 0
                            }`
                        }
                    />

                    <Card
                        label="Concurrent Guests"
                        value={
                            usage.concurrent_limit
                            ?? 'No fixed limit'
                        }
                    />
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="rounded-2xl border bg-white p-6 shadow-sm">
                        <h2 className="text-xl font-black">
                            Subscription
                        </h2>

                        <div className="mt-4 text-sm text-slate-600">
                            Expiry:{' '}
                            <strong>
                                {usage.expires_at
                                    ?? '-'}
                            </strong>
                        </div>
                    </div>

                    {user.role ===
                        'admin' && (
                        <div className="rounded-2xl border bg-white p-6 shadow-sm">
                            <h2 className="text-xl font-black">
                                Hotel Administration
                            </h2>

                            <div className="mt-5 flex flex-wrap gap-3">
                                <Link
                                    href={route(
                                        'hotel.staff.index',
                                    )}
                                    className="rounded-xl bg-slate-900 px-4 py-2.5 font-black text-white"
                                >
                                    Receptionists
                                </Link>

                                <Link
                                    href={route(
                                        'hotel.settings.index',
                                    )}
                                    className="rounded-xl bg-emerald-600 px-4 py-2.5 font-black text-white"
                                >
                                    Hotel Settings
                                </Link>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </HotelLayout>
    );
}

function Card({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border bg-white p-5 shadow-sm">
            <div className="text-xs font-black uppercase tracking-wide text-slate-400">
                {label}
            </div>

            <div className="mt-2 text-2xl font-black text-slate-900">
                {value}
            </div>
        </div>
    );
}
