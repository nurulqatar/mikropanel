import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

const navigation = [
    {
        label: 'Dashboard',
        short: 'DB',
        route: 'superadmin.dashboard',
        match: '/super-admin',
        exact: true,
    },
    {
        label: 'Resellers',
        short: 'RS',
        route: 'superadmin.resellers.index',
        match: '/super-admin/resellers',
    },
    {
        label: 'Website Settings',
        short: 'WS',
        route: 'superadmin.website-settings.index',
        match: '/super-admin/website-settings',
    },
    {
        label: 'Reseller Plans',
        short: 'PL',
        route: 'superadmin.plans.index',
        match: '/super-admin/reseller-plans',
    },
    {
        label: 'Wallet',
        short: 'WL',
        route: 'superadmin.wallet.index',
        match: '/super-admin/wallet',
    },
    {
        label: 'Recharges',
        short: 'RC',
        route: 'superadmin.recharges.index',
        match: '/super-admin/recharges',
    },
    {
        label: 'Reports',
        short: 'RP',
        route: 'superadmin.reports.index',
        match: '/super-admin/reports',
    },
    {
        label: 'Notifications',
        short: 'NT',
        route: 'superadmin.notifications.index',
        match: '/super-admin/notifications',
    },
    {
        label: 'Audit Log',
        short: 'AU',
        route: 'superadmin.audit.index',
        match: '/super-admin/audit',
    },
];

function SidebarContent({ url, closeMenu }) {
    const active = (item) => {
        const cleanUrl = (url || '').split('?')[0];

        if (item.exact) {
            return cleanUrl === item.match;
        }

        return cleanUrl.startsWith(item.match);
    };

    return (
        <div className="flex h-full flex-col">
            <div className="border-b border-slate-800 px-5 py-5">
                <Link
                    href={route('superadmin.dashboard')}
                    onClick={closeMenu}
                    className="block"
                >
                    <div className="flex items-center gap-3">
                        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-500 text-sm font-black text-white shadow-lg shadow-indigo-950/30">
                            SA
                        </div>

                        <div>
                            <div className="text-base font-bold tracking-tight text-white">
                                MikroPanel
                            </div>

                            <div className="text-xs font-medium uppercase tracking-[0.16em] text-indigo-300">
                                Super Admin
                            </div>
                        </div>
                    </div>
                </Link>
            </div>

            <div className="px-4 pt-5">
                <div className="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Control Center
                </div>
            </div>

            <nav className="flex-1 space-y-1 overflow-y-auto px-3 pb-5">
                {navigation.map((item) => {
                    const selected = active(item);

                    return (
                        <Link
                            key={item.route}
                            href={route(item.route)}
                            onClick={closeMenu}
                            className={[
                                'group flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                                selected
                                    ? 'bg-indigo-500 text-white shadow-lg shadow-indigo-950/20'
                                    : 'text-slate-300 hover:bg-slate-800 hover:text-white',
                            ].join(' ')}
                        >
                            <span
                                className={[
                                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-[10px] font-black tracking-wide',
                                    selected
                                        ? 'bg-white/15 text-white'
                                        : 'bg-slate-800 text-slate-400 group-hover:bg-slate-700 group-hover:text-white',
                                ].join(' ')}
                            >
                                {item.short}
                            </span>

                            <span>{item.label}</span>
                        </Link>
                    );
                })}

                    {/* HOTEL_HOTSPOT_SUPERADMIN_MENU_V1 */}
                    <details
                        className="mt-3 rounded-xl border border-emerald-900/50 bg-emerald-950/20"
                        open={(url || '').startsWith(
                            '/super-admin/hotel-hotspot',
                        )}
                    >
                        <summary className="cursor-pointer list-none rounded-xl px-4 py-3 text-sm font-black text-emerald-300 hover:bg-emerald-950/40">
                            <span className="flex items-center justify-between gap-3">
                                <span>
                                    Hotel Hotspot
                                </span>

                                <span>
                                    ▾
                                </span>
                            </span>
                        </summary>

                        <div className="space-y-1 px-2 pb-2">
                            <Link
                                href={route(
                                    'superadmin.hotel.dashboard',
                                )}
                                onClick={closeMenu}
                                className="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800 hover:text-white"
                            >
                                Dashboard
                            </Link>

                            <Link
                                href={route(
                                    'superadmin.hotel.hotels.index',
                                )}
                                onClick={closeMenu}
                                className="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800 hover:text-white"
                            >
                                Hotels
                            </Link>

                            <Link
                                href={route(
                                    'superadmin.hotel.plans.index',
                                )}
                                onClick={closeMenu}
                                className="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-300 hover:bg-slate-800 hover:text-white"
                            >
                                Hotel Plans
                            </Link>
                        </div>
                    </details>

                    <a
                        href={route(
                            'superadmin.registrations.index',
                        )}
                        className={`block rounded-xl px-4 py-3 text-sm font-bold transition ${
                            route().current(
                                'superadmin.registrations.*',
                            )
                                ? 'bg-violet-600 text-white shadow'
                                : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                        }`}
                    >
                        Registration Requests
                    </a>

</nav>

            <div className="border-t border-slate-800 p-4">
                <div className="rounded-xl bg-slate-800/70 px-3 py-3">
                    <div className="text-xs font-medium text-slate-400">
                        Dedicated administration
                    </div>

                    <div className="mt-1 text-xs text-slate-500">
                        Reseller platform control
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function SuperAdminLayout({
    children,
    title = 'Super Admin',
}) {
    const page = usePage();
    const { auth } = page.props;
    const url = page.url || '';
    const [mobileOpen, setMobileOpen] = useState(false);

    const user = auth?.user;

    return (
        <div className="min-h-screen bg-slate-100">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 bg-slate-950 md:block">
                <SidebarContent
                    url={url}
                    closeMenu={() => {}}
                />
            </aside>

            {mobileOpen && (
                <div className="fixed inset-0 z-50 md:hidden">
                    <button
                        type="button"
                        aria-label="Close navigation"
                        onClick={() => setMobileOpen(false)}
                        className="absolute inset-0 bg-slate-950/60"
                    />

                    <aside className="relative h-full w-72 max-w-[85vw] bg-slate-950 shadow-2xl">
                        <SidebarContent
                            url={url}
                            closeMenu={() => setMobileOpen(false)}
                        />
                    </aside>
                </div>
            )}

            <div className="md:pl-64">
                <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
                    <div className="flex h-16 items-center justify-between gap-4 px-4 md:px-6">
                        <div className="flex min-w-0 items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setMobileOpen(true)}
                                className="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-700 md:hidden"
                            >
                                <span className="text-xl leading-none">☰</span>
                            </button>

                            <div className="min-w-0">
                                <div className="truncate text-lg font-bold text-slate-900">
                                    {title}
                                </div>

                                <div className="hidden text-xs text-slate-500 sm:block">
                                    Super Admin Control Center
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center gap-3">
                            <div className="hidden text-right sm:block">
                                <div className="max-w-48 truncate text-sm font-semibold text-slate-800">
                                    {user?.name || 'Super Admin'}
                                </div>

                                <div className="max-w-48 truncate text-xs text-slate-500">
                                    {user?.email || ''}
                                </div>
                            </div>

                            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700">
                                {(user?.name || 'SA')
                                    .split(' ')
                                    .slice(0, 2)
                                    .map((part) => part.charAt(0))
                                    .join('')
                                    .toUpperCase()}
                            </div>

                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-600 transition hover:border-red-200 hover:bg-red-50 hover:text-red-600"
                            >
                                Logout
                            </Link>
                        </div>
                    </div>
                </header>

                <main className="min-h-[calc(100vh-4rem)] p-4 md:p-6">
                    <div className="mx-auto w-full max-w-[1600px]">
                        {children}
                    </div>
                </main>
            </div>
        </div>
    );
}
