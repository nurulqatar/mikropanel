import {
    Link,
    usePage,
} from '@inertiajs/react';

export default function Sidebar() {
    const { props } = usePage();

    const user =
        props.panelAuth?.user
        ?? props.auth?.user
        ?? null;

    const panelName =
        props.panelSettings?.panel_name
        ?? 'MikroPanel';

    const permissions =
        user?.permissions ?? [];

    const isAdmin =
        user?.role === 'admin';

    const can = (permission) =>
        isAdmin
        || permissions.includes(
            permission,
        );

    const routeExists = (name) => {
        try {
            return route().has(name);
        } catch {
            return false;
        }
    };

    const items = [
        {
            label: 'Dashboard',
            route: 'dashboard',
            active: 'dashboard',
            permission: 'dashboard.view',
            icon: '⌂',
        },
        {
            label: 'Clients',
            route: 'clients.index',
            active: 'clients.*',
            permission: 'clients.view',
            icon: '👥',
        },
        {
            label: 'Routers',
            route: 'routers.index',
            active: 'routers.*',
            permission: 'routers.view',
            icon: '◉',
        },
        {
            label: 'Packages',
            route: 'packages.index',
            active: 'packages.*',
            permission: 'packages.view',
            icon: '▣',
        },
        {
            label: 'IP Pools',
            route: 'ip-ranges.index',
            active: 'ip-ranges.*',
            permission: 'ip_pools.view',
            icon: '⌘',
        },
        {
            label: 'Invoices',
            route: 'invoices.index',
            active: 'invoices.*',
            permission: 'invoices.view',
            icon: '▤',
        },
        {
            label: 'Payments',
            route: 'payments.index',
            active: 'payments.*',
            permission: 'payments.view',
            icon: '◈',
        },
        {
            label: 'Expenses',
            route: 'expenses.index',
            active: 'expenses.*',
            permission: 'expenses.view',
            icon: '↘',
        },
        {
            label: 'Accounting',
            route: 'accounting.index',
            active: 'accounting.*',
            permission: 'accounting.view',
            icon: '▥',
        },
        {
            label: 'Settings',
            route: 'settings.index',
            active: 'settings.*',
            permission: 'settings.manage',
            icon: '⚙',
        },
    ];

    const visibleItems = items.filter(
        (item) =>
            can(item.permission)
            && routeExists(item.route),
    );

    return (
        <aside className="sticky top-0 flex h-screen w-72 shrink-0 flex-col overflow-y-auto bg-slate-900 text-white">
            <div className="border-b border-slate-700 px-6 py-6">
                <h1 className="truncate text-2xl font-black text-cyan-400">
                    {panelName}
                </h1>

                <p className="mt-1 text-xs uppercase tracking-wider text-slate-400">
                    ISP Billing System
                </p>
            </div>

            <nav className="flex-1 space-y-1 px-3 py-5">
                {visibleItems.map((item) => {
                    const active =
                        route().current(
                            item.active,
                        );

                    return (
                        <Link
                            key={item.route}
                            href={route(
                                item.route,
                            )}
                            className={`flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition ${
                                active
                                    ? 'bg-cyan-600 text-white shadow'
                                    : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                            }`}
                        >
                            <span className="flex h-7 w-7 items-center justify-center text-lg">
                                {item.icon}
                            </span>

                            <span>
                                {item.label}
                            </span>
                        </Link>
                    );
                })}

                {isAdmin
                    && routeExists(
                        'users.index',
                    ) && (
                    <Link
                        href={route(
                            'users.index',
                        )}
                        className={`flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition ${
                            route().current(
                                'users.*',
                            )
                                ? 'bg-violet-600 text-white shadow'
                                : 'text-slate-300 hover:bg-slate-800 hover:text-white'
                        }`}
                    >
                        <span className="flex h-7 w-7 items-center justify-center text-lg">
                            ♙
                        </span>

                        <span>
                            Panel Users
                        </span>
                    </Link>
                )}
            </nav>

            <div className="border-t border-slate-700 p-4">
                <div className="mb-3 rounded-xl bg-slate-800 p-4">
                    <p className="truncate font-bold text-white">
                        {user?.name ??
                            'Panel User'}
                    </p>

                    <p className="mt-1 truncate text-xs text-slate-400">
                        {user?.email}
                    </p>

                    <span className="mt-3 inline-flex rounded-full bg-slate-700 px-3 py-1 text-xs font-bold capitalize text-cyan-300">
                        {user?.role ??
                            'operator'}
                    </span>
                </div>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-3 font-bold text-white hover:bg-red-700"
                >
                    <span>↪</span>
                    Logout
                </Link>

                <p className="mt-4 text-center text-xs text-slate-500">
                    MikroPanel v1.0
                </p>
            </div>
        </aside>
    );
}
