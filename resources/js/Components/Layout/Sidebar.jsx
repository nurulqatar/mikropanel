import {
    Link,
    usePage,
} from '@inertiajs/react';
import { useState } from 'react';

export default function Sidebar() {
    const { props } = usePage();

    const user =
        props.auth?.user
        ?? props.panelAuth?.user
        ?? null;

    const panelName =
        props.panelSettings?.panel_name
        ?? 'MikroPanel';

    const permissions =
        user?.permissions ?? [];

    const isAdmin =
        user?.role === 'admin';

    const isResellerUser =
        user?.reseller_id !== null
        && user?.reseller_id !== undefined;

    const isResellerOwner =
        isResellerUser
        && user?.role === 'reseller';

    const can = (permission) =>
        isAdmin
        || permissions.includes(permission);

    const routeExists = (name) => {
        try {
            return route().has(name);
        } catch {
            return false;
        }
    };

    if (isResellerUser) {
        return (
            <ResellerSidebar
                user={user}
                panelName={panelName}
                can={can}
                routeExists={routeExists}
                isOwner={isResellerOwner}
            />
        );
    }

    return (
        <NormalSidebar
            user={user}
            panelName={panelName}
            can={can}
            routeExists={routeExists}
            isAdmin={isAdmin}
        />
    );
}

function ResellerSidebar({
    user,
    panelName,
    can,
    routeExists,
    isOwner,
}) {
    const [open, setOpen] = useState({
        mac:
            route().current('clients.*')
            || route().current('packages.*')
            || route().current('ip-ranges.*'),

        hotspot:
            route().current('hotspot.*'),

        finance:
            route().current('invoices.*')
            || route().current('payments.*')
            || route().current('accounting.*')
            || route().current('expenses.*'),

        management:
            route().current('reseller.notifications.*')
            || route().current('reseller.operators.*')
            || route().current('settings.*'),
    });

    const toggle = (key) => {
        setOpen((current) => ({
            ...current,
            [key]: !current[key],
        }));
    };

    const macItems = [
        {
            label: 'Clients',
            route: 'clients.index',
            active: 'clients.*',
            permission: 'clients.view',
            icon: '👥',
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
    ].filter(
        (item) =>
            can(item.permission)
            && routeExists(item.route),
    );

    const hotspotItems = [
        {
            label: 'Hotspot Dashboard',
            route: 'hotspot.index',
            active: 'hotspot.index',
            permission: 'hotspot.view',
        },
        {
            label: 'Servers',
            route: 'hotspot.servers.index',
            active: 'hotspot.servers.*',
            permission: 'hotspot.view',
        },
        {
            label: 'Plans',
            route: 'hotspot.plans.index',
            active: 'hotspot.plans.*',
            permission: 'hotspot.view',
        },
        {
            label: 'Vouchers',
            route: 'hotspot.vouchers.index',
            active: 'hotspot.vouchers.*',
            permission: 'hotspot.view',
        },
        {
            label: 'Voucher Batches',
            route: 'hotspot.batches.index',
            active: 'hotspot.batches.*',
            permission: 'hotspot.view',
        },
        {
            label: 'Live Sessions',
            route: 'hotspot.sessions.index',
            active: 'hotspot.sessions.*',
            permission: 'hotspot.view',
        },
        {
            label: 'Billing & Dues',
            route: 'hotspot.billing.index',
            active: 'hotspot.billing.*',
            permission: 'hotspot.view',
        },
        {
            label: 'Branding & Portal',
            route: 'hotspot.branding.index',
            active: 'hotspot.branding.*',
            permission: 'hotspot.manage',
        },
        {
            label: 'Reports',
            route: 'hotspot.reports.index',
            active: 'hotspot.reports.*',
            permission: 'hotspot.view',
        },
    ].filter(
        (item) =>
            can(item.permission)
            && routeExists(item.route),
    );

    const financeItems = [
        {
            label: 'Invoices',
            route: 'invoices.index',
            active: 'invoices.*',
            permission: 'invoices.view',
        },
        {
            label: 'Payments',
            route: 'payments.index',
            active: 'payments.*',
            permission: 'payments.view',
        },
        {
            label: 'Accounting',
            route: 'accounting.index',
            active: 'accounting.*',
            permission: 'accounting.view',
        },
        {
            label: 'Expenses',
            route: 'expenses.index',
            active: 'expenses.*',
            permission: 'expenses.view',
        },
    ].filter(
        (item) =>
            can(item.permission)
            && routeExists(item.route),
    );

    const managementItems = [];

    if (
        isOwner
        && routeExists('reseller.operators.index')
    ) {
        managementItems.push({
            label: 'Operators',
            route: 'reseller.operators.index',
            active: 'reseller.operators.*',
        });
    }

    if (
        routeExists(
            'reseller.notifications.index',
        )
    ) {
        managementItems.push({
            label: 'Notifications',
            route: 'reseller.notifications.index',
            active: 'reseller.notifications.*',
        });
    }

    if (
        can('settings.manage')
        && routeExists('settings.index')
    ) {
        managementItems.push({
            label: 'Settings',
            route: 'settings.index',
            active: 'settings.*',
        });
    }

    return (
        <aside className="sticky top-0 flex h-screen w-72 shrink-0 flex-col overflow-y-auto bg-slate-950 text-white">
            <div className="border-b border-slate-800 px-6 py-6">
                <h1 className="truncate text-2xl font-black text-cyan-400">
                    {panelName}
                </h1>

                <p className="mt-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                    Reseller Control Panel
                </p>
            </div>

            <nav className="flex-1 space-y-2 px-3 py-5">
                {routeExists(
                    'reseller.dashboard',
                ) && (
                    <ResellerTopLink
                        label="Dashboard"
                        icon="⌂"
                        routeName="reseller.dashboard"
                        active="reseller.dashboard"
                    />
                )}

                {can('routers.view')
                    && routeExists(
                        'routers.index',
                    ) && (
                    <ResellerTopLink
                        label="Router"
                        icon="◉"
                        routeName="routers.index"
                        active="routers.*"
                    />
                )}

                {macItems.length > 0 && (
                    <ResellerGroup
                        label="MAC Client"
                        icon="◆"
                        open={open.mac}
                        onToggle={() =>
                            toggle('mac')
                        }
                        active={
                            route().current(
                                'clients.*',
                            )
                            || route().current(
                                'packages.*',
                            )
                            || route().current(
                                'ip-ranges.*',
                            )
                        }
                        items={macItems}
                    />
                )}

                {hotspotItems.length >
                    0 && (
                    <ResellerGroup
                        label="Hotspot Client"
                        icon="◉"
                        open={open.hotspot}
                        onToggle={() =>
                            toggle('hotspot')
                        }
                        active={route().current(
                            'hotspot.*',
                        )}
                        items={hotspotItems}
                    />
                )}

                {financeItems.length >
                    0 && (
                    <ResellerGroup
                        label="Billing & Finance"
                        icon="▥"
                        open={open.finance}
                        onToggle={() =>
                            toggle('finance')
                        }
                        active={
                            route().current(
                                'invoices.*',
                            )
                            || route().current(
                                'payments.*',
                            )
                            || route().current(
                                'accounting.*',
                            )
                            || route().current(
                                'expenses.*',
                            )
                        }
                        items={financeItems}
                    />
                )}

                {managementItems.length >
                    0 && (
                    <ResellerGroup
                        label="Management"
                        icon="⚙"
                        open={
                            open.management
                        }
                        onToggle={() =>
                            toggle(
                                'management',
                            )
                        }
                        active={
                            route().current(
                                'reseller.notifications.*',
                            )
                            || route().current(
                                'reseller.operators.*',
                            )
                            || route().current(
                                'settings.*',
                            )
                        }
                        items={
                            managementItems
                        }
                    />
                )}
            </nav>

            <div className="border-t border-slate-800 p-4">
                <div className="mb-3 rounded-xl bg-slate-900 p-4">
                    <p className="truncate font-bold text-white">
                        {user?.name ??
                            'Reseller User'}
                    </p>

                    <p className="mt-1 truncate text-xs text-slate-400">
                        {user?.email}
                    </p>

                    <span className="mt-3 inline-flex rounded-full bg-cyan-950 px-3 py-1 text-xs font-bold capitalize text-cyan-300">
                        {user?.role ??
                            'reseller'}
                    </span>
                </div>

                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-3 font-bold text-white transition hover:bg-red-700"
                >
                    <span>↪</span>
                    Logout
                </Link>

                <p className="mt-4 text-center text-xs text-slate-600">
                    MikroPanel Reseller
                </p>
            </div>
        </aside>
    );
}

function ResellerTopLink({
    label,
    icon,
    routeName,
    active,
}) {
    const selected =
        route().current(active);

    return (
        <Link
            href={route(routeName)}
            className={`flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition ${
                selected
                    ? 'bg-cyan-600 text-white shadow'
                    : 'text-slate-300 hover:bg-slate-900 hover:text-white'
            }`}
        >
            <span className="flex h-7 w-7 items-center justify-center text-lg">
                {icon}
            </span>

            <span className="flex-1">
                {label}
            </span>
        </Link>
    );
}

function ResellerGroup({
    label,
    icon,
    open,
    onToggle,
    active,
    items,
}) {
    return (
        <div>
            <button
                type="button"
                onClick={onToggle}
                className={`flex w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-sm font-semibold transition ${
                    active
                        ? 'bg-slate-800 text-cyan-300'
                        : 'text-slate-300 hover:bg-slate-900 hover:text-white'
                }`}
            >
                <span className="flex h-7 w-7 items-center justify-center text-lg">
                    {icon}
                </span>

                <span className="flex-1">
                    {label}
                </span>

                <span className="text-xs opacity-70">
                    {open ? '▼' : '›'}
                </span>
            </button>

            {open && (
                <div className="ml-8 mt-1 space-y-1 border-l border-slate-800 pl-3">
                    {items.map(
                        (item) => (
                            <Link
                                key={
                                    item.route
                                }
                                href={route(
                                    item.route,
                                )}
                                className={`flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition ${
                                    route().current(
                                        item.active,
                                    )
                                        ? 'bg-slate-800 text-cyan-300'
                                        : 'text-slate-400 hover:bg-slate-900 hover:text-white'
                                }`}
                            >
                                {item.icon && (
                                    <span>
                                        {
                                            item.icon
                                        }
                                    </span>
                                )}

                                <span>
                                    {
                                        item.label
                                    }
                                </span>
                            </Link>
                        ),
                    )}
                </div>
            )}
        </div>
    );
}

function NormalSidebar({
    user,
    panelName,
    can,
    routeExists,
    isAdmin,
}) {
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
            label: 'Hotspot',
            route: 'hotspot.index',
            active: 'hotspot.*',
            permission: 'hotspot.view',
            icon: '◉',
            children: [
                {
                    label: 'Dashboard',
                    route: 'hotspot.index',
                    active: 'hotspot.index',
                    permission: 'hotspot.view',
                },
                {
                    label: 'Servers',
                    route: 'hotspot.servers.index',
                    active: 'hotspot.servers.*',
                    permission: 'hotspot.view',
                },
                {
                    label: 'Plans',
                    route: 'hotspot.plans.index',
                    active: 'hotspot.plans.*',
                    permission: 'hotspot.view',
                },
                {
                    label: 'Vouchers',
                    route: 'hotspot.vouchers.index',
                    active: 'hotspot.vouchers.*',
                    permission: 'hotspot.view',
                },
                {
                    label: 'Voucher Batches',
                    route: 'hotspot.batches.index',
                    active: 'hotspot.batches.*',
                    permission: 'hotspot.view',
                },
                {
                    label: 'Live Sessions',
                    route: 'hotspot.sessions.index',
                    active: 'hotspot.sessions.*',
                    permission: 'hotspot.view',
                },
                {
                    label: 'Billing & Dues',
                    route: 'hotspot.billing.index',
                    active: 'hotspot.billing.*',
                    permission: 'hotspot.view',
                },
                {
                    label: 'Reports',
                    route: 'hotspot.reports.index',
                    active: 'hotspot.reports.*',
                    permission: 'hotspot.view',
                },
                {
                    label: 'Branding & Portal',
                    route: 'hotspot.branding.index',
                    active: 'hotspot.branding.*',
                    permission: 'hotspot.manage',
                },
            ],
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
        (item) => {
            const parentVisible =
                can(item.permission)
                && routeExists(
                    item.route,
                );

            const childVisible =
                (
                    item.children ?? []
                ).some(
                    (child) =>
                        can(
                            child.permission,
                        )
                        && routeExists(
                            child.route,
                        ),
                );

            return parentVisible
                || childVisible;
        },
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
                {visibleItems.map(
                    (item) => {
                        const active =
                            route().current(
                                item.active,
                            );

                        const children =
                            (
                                item.children ??
                                []
                            ).filter(
                                (child) =>
                                    can(
                                        child.permission,
                                    )
                                    && routeExists(
                                        child.route,
                                    ),
                            );

                        return (
                            <div
                                key={
                                    item.route
                                }
                            >
                                <Link
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
                                        {
                                            item.icon
                                        }
                                    </span>

                                    <span className="flex-1">
                                        {
                                            item.label
                                        }
                                    </span>

                                    {children.length >
                                        0 && (
                                        <span className="text-xs opacity-70">
                                            {active
                                                ? '▼'
                                                : '›'}
                                        </span>
                                    )}
                                </Link>

                                {children.length >
                                    0
                                    && active && (
                                    <div className="ml-8 mt-1 space-y-1 border-l border-slate-700 pl-3">
                                        {children.map(
                                            (
                                                child,
                                            ) => (
                                                <Link
                                                    key={
                                                        child.route
                                                    }
                                                    href={route(
                                                        child.route,
                                                    )}
                                                    className={`block rounded-lg px-3 py-2 text-sm font-medium transition ${
                                                        route().current(
                                                            child.active,
                                                        )
                                                            ? 'bg-slate-700 text-cyan-300'
                                                            : 'text-slate-400 hover:bg-slate-800 hover:text-white'
                                                    }`}
                                                >
                                                    {
                                                        child.label
                                                    }
                                                </Link>
                                            ),
                                        )}
                                    </div>
                                )}
                            </div>
                        );
                    },
                )}

                {Boolean(
                    user?.is_super_admin,
                )
                    && routeExists(
                        'superadmin.dashboard',
                    ) && (
                    <Link
                        href={route(
                            'superadmin.dashboard',
                        )}
                        className="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold text-violet-200 transition hover:bg-slate-800 hover:text-white"
                    >
                        <span className="flex h-7 w-7 items-center justify-center text-lg">
                            ★
                        </span>

                        <span>
                            Super Admin
                        </span>
                    </Link>
                )}

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
            </div>
        </aside>
    );
}
