import {
    Link,
    usePage,
} from '@inertiajs/react';
import { useState } from 'react';

const navigation = [
    {
        label: 'Dashboard',
        short: 'DB',
        route: 'hotel.dashboard',
        match: '/hotel',
        exact: true,
    },
    {
        label: 'Guests',
        short: 'GU',
        route: 'hotel.guests.index',
        match: '/hotel/guests',
        permissions: [
            'guests.manage',
            'reports.view',
        ],
    },
    {
        label: 'Vouchers',
        short: 'VC',
        route: 'hotel.vouchers.index',
        match: '/hotel/vouchers',
        permissions: [
            'vouchers.issue',
            'vouchers.print',
            'reports.view',
        ],
    },
    {
        label: 'MikroTik Routers',
        short: 'MT',
        route: 'hotel.routers.index',
        match: '/hotel/routers',
        adminOnly: true,
    },
    {
        label: 'Receptionists',
        short: 'ST',
        route: 'hotel.staff.index',
        match: '/hotel/staff',
        adminOnly: true,
    },
    {
        label: 'Hotel Settings',
        short: 'SE',
        route: 'hotel.settings.index',
        match: '/hotel/settings',
        adminOnly: true,
    },
];

function Sidebar({
    closeMenu,
}) {
    const page =
        usePage();

    const hotelAuth =
        page.props.hotelAuth
        ?? {};

    const hotel =
        hotelAuth.hotel
        ?? {};

    const user =
        hotelAuth.user
        ?? {};

    const cleanUrl =
        (page.url || '')
            .split('?')[0];

    const items =
        navigation.filter(
            (item) => {
                if (
                    user.role
                    === 'admin'
                ) {
                    return true;
                }

                if (item.adminOnly) {
                    return false;
                }

                if (
                    !item.permissions
                ) {
                    return true;
                }

                return item.permissions
                    .some(
                        (permission) =>
                            (
                                user.permissions
                                ?? []
                            ).includes(
                                permission,
                            ),
                    );
            },
        );

    const active = (item) =>
        item.exact
            ? cleanUrl
                === item.match
            : cleanUrl.startsWith(
                  item.match,
              );

    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-slate-800 p-5">
                <Link
                    href={route(
                        'hotel.dashboard',
                    )}
                    onClick={closeMenu}
                    className="flex items-center gap-3"
                >
                    {hotel.logo_url ? (
                        <img
                            src={
                                hotel.logo_url
                            }
                            alt={
                                hotel.name
                            }
                            className="h-11 w-11 rounded-xl bg-white object-contain p-1"
                        />
                    ) : (
                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-500 text-xs font-black text-white">
                            HT
                        </div>
                    )}

                    <div className="min-w-0">
                        <div className="truncate font-black text-white">
                            {hotel.name
                                || 'Hotel WiFi'}
                        </div>

                        <div className="text-xs font-bold uppercase tracking-widest text-emerald-300">
                            Hotel Hotspot
                        </div>
                    </div>
                </Link>
            </div>

            <nav className="flex-1 space-y-1 overflow-y-auto p-3">
                {items.map(
                    (item) => {
                        const selected =
                            active(item);

                        return (
                            <Link
                                key={
                                    item.route
                                }
                                href={route(
                                    item.route,
                                )}
                                onClick={
                                    closeMenu
                                }
                                className={[
                                    'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-bold transition',
                                    selected
                                        ? 'bg-emerald-600 text-white'
                                        : 'text-slate-300 hover:bg-slate-800 hover:text-white',
                                ].join(
                                    ' ',
                                )}
                            >
                                <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-[10px] font-black">
                                    {
                                        item.short
                                    }
                                </span>

                                <span>
                                    {
                                        item.label
                                    }
                                </span>
                            </Link>
                        );
                    },
                )}
            </nav>

            <div className="border-t border-slate-800 p-4">
                <div className="mb-3 rounded-xl bg-slate-800 p-3">
                    <div className="truncate text-sm font-black text-white">
                        {user.name}
                    </div>

                    <div className="truncate text-xs text-slate-400">
                        {user.email}
                    </div>

                    <div className="mt-1 text-xs font-black uppercase text-emerald-300">
                        {user.role}
                    </div>
                </div>

                <Link
                    href={route(
                        'hotel.logout',
                    )}
                    method="post"
                    as="button"
                    className="w-full rounded-lg border border-slate-700 px-3 py-2 text-sm font-black text-slate-300 hover:border-red-500 hover:bg-red-500 hover:text-white"
                >
                    Logout
                </Link>
            </div>
        </div>
    );
}

export default function HotelLayout({
    children,
    title = 'Hotel Hotspot',
}) {
    const page =
        usePage();

    const [
        mobileOpen,
        setMobileOpen,
    ] = useState(false);

    const subscription =
        page.props.hotelAuth
            ?.subscription
        ?? {};

    return (
        <div className="min-h-screen bg-slate-100">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 bg-slate-950 md:block">
                <Sidebar
                    closeMenu={() => {}}
                />
            </aside>

            {mobileOpen && (
                <div className="fixed inset-0 z-50 md:hidden">
                    <button
                        type="button"
                        aria-label="Close navigation"
                        onClick={() =>
                            setMobileOpen(
                                false,
                            )
                        }
                        className="absolute inset-0 bg-black/60"
                    />

                    <aside className="relative h-full w-72 bg-slate-950">
                        <Sidebar
                            closeMenu={() =>
                                setMobileOpen(
                                    false,
                                )
                            }
                        />
                    </aside>
                </div>
            )}

            <div className="md:pl-64">
                <header className="sticky top-0 z-30 border-b bg-white">
                    <div className="flex min-h-16 items-center justify-between gap-4 px-4 md:px-6">
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() =>
                                    setMobileOpen(
                                        true,
                                    )
                                }
                                className="rounded-lg border px-3 py-2 md:hidden"
                            >
                                ☰
                            </button>

                            <div>
                                <div className="font-black text-slate-900">
                                    {title}
                                </div>

                                <div className="text-xs text-slate-500">
                                    Hotel Guest WiFi Management
                                </div>
                            </div>
                        </div>

                        <span
                            className={[
                                'rounded-full px-3 py-1 text-xs font-black',
                                subscription.usable
                                    ? 'bg-emerald-100 text-emerald-700'
                                    : 'bg-red-100 text-red-700',
                            ].join(
                                ' ',
                            )}
                        >
                            {subscription.usable
                                ? 'Subscription Active'
                                : 'Subscription Inactive'}
                        </span>
                    </div>
                </header>

                <main className="p-4 md:p-6">
                    <div className="mx-auto max-w-[1600px]">
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
