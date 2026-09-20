import AppLayout from '@/Layouts/AppLayout';
import { useMemo, useState } from 'react';
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

export default function Dashboard({
    reseller = {},
    usage = {},
    stats = {},
    pos = {},
    recentClients = [],
    upgradePlans = [],
    isOwner = false,
}) {
    const expired =
        !usage.subscription_usable;

    const [
        upgradingPlanId,
        setUpgradingPlanId,
    ] = useState(null);

    const upgradePlan = (plan) => {
        if (
            Number(
                reseller.wallet_balance ?? 0,
            ) < Number(plan.price ?? 0)
        ) {
            window.alert(
                'Insufficient wallet balance.',
            );

            return;
        }

        if (
            !window.confirm(
                `Upgrade to ${plan.name} for QAR ${money(plan.price)}?`,
            )
        ) {
            return;
        }

        setUpgradingPlanId(
            plan.id,
        );

        router.post(
            route(
                'reseller.subscription.upgrade',
                plan.id,
            ),
            {},
            {
                preserveScroll: true,

                onFinish: () =>
                    setUpgradingPlanId(
                        null,
                    ),
            },
        );
    };

    return (
        <AppLayout title={isOwner ? 'Reseller Dashboard' : 'Operator Dashboard'}>
            <Head title={isOwner ? 'Reseller Dashboard' : 'Operator Dashboard'} />
            {/* RESELLER_COMPLIANCE_ACCESS_V1 */}
            <a
                href={route('reseller.compliance.enter')}
                className="mb-6 block rounded-2xl border border-cyan-200 bg-cyan-50 p-5 transition hover:border-cyan-400"
            >
                <div className="font-black text-slate-900">Internet Compliance</div>
                <div className="mt-1 text-sm text-slate-600">Open Logging / Filtering</div>
            </a>


            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black">
                            {reseller.company_name}
                        </h1>

                        <p className="mt-1 text-slate-500">
                            {isOwner
                                ? 'Reseller Dashboard'
                                : 'Operator Dashboard'}{' '}
                            · {reseller.code}
                        </p>
                    </div>

                    <Link
                        href={route(
                            'reseller.notifications.index',
                        )}
                        className="rounded-xl bg-slate-700 px-5 py-3 font-bold text-white"
                    >
                        Notifications ({stats.unread_notifications ?? 0})
                    </Link>

                    {isOwner && (
                        <Link
                            href={route(
                                'reseller.operators.index',
                            )}
                            className="rounded-xl bg-violet-600 px-5 py-3 font-bold text-white"
                        >
                            Operators
                        </Link>
                    )}
                </div>

                {isOwner && (expired ||
                    reseller.status !==
                        'active') && (
                    <div className="rounded-xl border border-amber-300 bg-amber-50 p-4 font-semibold text-amber-800">
                        Account status:{' '}
                        {reseller.status}.
                        Subscription:{' '}
                        {usage.subscription_usable
                            ? 'active'
                            : 'expired'}.
                        Expiry policy:{' '}
                        {reseller.expiry_mode}.
                    </div>
                )}

                {/* RESELLER_SELF_UPGRADE_UI_V1 */}
                {isOwner && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h2 className="text-xl font-black text-slate-900">
                                    Subscription & Wallet
                                </h2>

                                <p className="mt-1 text-sm text-slate-500">
                                    Paid packages automatically renew from wallet balance after expiry.
                                </p>
                            </div>

                            <div className="rounded-xl bg-emerald-50 px-5 py-3 text-right">
                                <div className="text-xs font-bold uppercase tracking-wide text-emerald-700">
                                    Wallet Balance
                                </div>

                                <div className="mt-1 text-2xl font-black text-emerald-700">
                                    QAR{' '}
                                    {money(
                                        reseller.wallet_balance,
                                    )}
                                </div>
                            </div>
                        </div>

                        <div className="mt-5 grid gap-4 md:grid-cols-2">
                            <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    Current Package
                                </div>

                                <div className="mt-2 text-lg font-black text-slate-900">
                                    {reseller.current_plan?.name ||
                                        reseller.plan ||
                                        'No active package'}
                                </div>

                                <div className="mt-2 text-sm text-slate-600">
                                    Client Limit:{' '}
                                    {reseller.current_plan?.client_limit ??
                                        usage.client_limit ??
                                        '-'}
                                </div>

                                <div className="text-sm text-slate-600">
                                    Expires:{' '}
                                    {reseller.current_plan?.expires_at ||
                                        usage.subscription_expires_at ||
                                        '-'}
                                </div>

                                {Number(
                                    reseller.current_plan?.price ??
                                        0,
                                ) > 0 && (
                                    <div className="mt-3 rounded-lg bg-cyan-50 px-3 py-2 text-sm font-semibold text-cyan-800">
                                        Auto renew price: QAR{' '}
                                        {money(
                                            reseller.current_plan
                                                ?.price,
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="rounded-xl border border-slate-200 p-4">
                                <div className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    Available Upgrades
                                </div>

                                {upgradePlans.length ===
                                0 ? (
                                    <div className="mt-3 text-sm text-slate-500">
                                        No higher package is currently available.
                                    </div>
                                ) : (
                                    <div className="mt-3 space-y-3">
                                        {upgradePlans.map(
                                            (plan) => {
                                                const enoughBalance =
                                                    Number(
                                                        reseller.wallet_balance ??
                                                            0,
                                                    ) >=
                                                    Number(
                                                        plan.price ??
                                                            0,
                                                    );

                                                return (
                                                    <div
                                                        key={
                                                            plan.id
                                                        }
                                                        className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-3"
                                                    >
                                                        <div>
                                                            <div className="font-black text-slate-900">
                                                                {
                                                                    plan.name
                                                                }
                                                            </div>

                                                            <div className="text-sm text-slate-500">
                                                                {
                                                                    plan.client_limit
                                                                }{' '}
                                                                clients ·{' '}
                                                                {
                                                                    plan.validity_days
                                                                }{' '}
                                                                days · QAR{' '}
                                                                {money(
                                                                    plan.price,
                                                                )}
                                                            </div>
                                                        </div>

                                                        <button
                                                            type="button"
                                                            disabled={
                                                                !enoughBalance ||
                                                                upgradingPlanId ===
                                                                    plan.id
                                                            }
                                                            onClick={() =>
                                                                upgradePlan(
                                                                    plan,
                                                                )
                                                            }
                                                            className="rounded-xl bg-cyan-600 px-4 py-2 text-sm font-black text-white disabled:cursor-not-allowed disabled:bg-slate-300"
                                                        >
                                                            {upgradingPlanId ===
                                                            plan.id
                                                                ? 'UPGRADING...'
                                                                : enoughBalance
                                                                  ? 'UPGRADE'
                                                                  : 'LOW BALANCE'}
                                                        </button>
                                                    </div>
                                                );
                                            },
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    </section>
                )}

                {isOwner && (
                    <QuickRechargePos
                        pos={pos}
                    />
                )}

                {isOwner ? (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <Stat
                            label="Client Limit"
                            value={
                                usage.client_limit
                            }
                        />

                        <Stat
                            label="Used Clients"
                            value={
                                usage.used_clients
                            }
                        />

                        <Stat
                            label="Remaining"
                            value={
                                usage.remaining_clients
                            }
                        />

                        <Stat
                            label="Wallet"
                            value={`QAR ${money(
                                reseller.wallet_balance,
                            )}`}
                        />

                        <Stat
                            label="Active Clients"
                            value={
                                stats.active_clients
                            }
                        />

                        <Stat
                            label="Routers"
                            value={
                                stats.routers
                            }
                        />

                        <Stat
                            label="Today Collection"
                            value={`QAR ${money(
                                stats.today_collection,
                            )}`}
                        />

                        <Stat
                            label="Total Due"
                            value={`QAR ${money(
                                Number(
                                    stats.normal_due ??
                                        0,
                                ) +
                                    Number(
                                        stats.hotspot_due ??
                                            0,
                                    ),
                            )}`}
                        />

                        <Stat
                            label="Hotspot Vouchers"
                            value={
                                stats.hotspot_vouchers
                            }
                        />

                        <Stat
                            label="Hotspot Online"
                            value={
                                stats.online_hotspot
                            }
                        />

                        <Stat
                            label="Operators"
                            value={
                                usage.operators
                            }
                        />

                        <Stat
                            label="Expires"
                            value={
                                usage.subscription_expires_at ||
                                '-'
                            }
                        />
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Stat
                            label="Total Clients"
                            value={stats.clients ?? 0}
                        />

                        <Stat
                            label="Online Clients"
                            value={stats.online_clients ?? 0}
                        />
                    </div>
                )}

                {isOwner && (
                    <section className="rounded-2xl border bg-white p-5 shadow-sm">
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            <Quick
                                routeName="clients.index"
                                label="Clients"
                            />

                            <Quick
                                routeName="routers.index"
                                label="Routers"
                            />

                            <Quick
                                routeName="packages.index"
                                label="Packages"
                            />

                            <Quick
                                routeName="ip-ranges.index"
                                label="IP Pools"
                            />

                            <Quick
                                routeName="invoices.index"
                                label="Invoices"
                            />

                            <Quick
                                routeName="payments.index"
                                label="Payments"
                            />

                            <Quick
                                routeName="accounting.index"
                                label="Accounting"
                            />

                            <Quick
                                routeName="hotspot.index"
                                label="Hotspot"
                            />
                        </div>
                    </section>
                )}

                <section className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <div className="border-b px-5 py-4 text-lg font-bold">
                        Recent Clients
                    </div>

                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Client</Th>
                                <Th>IP</Th>
                                <Th>Package</Th>
                                <Th>Router</Th>
                                <Th>Status</Th>
                                <Th>Expiry</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {recentClients.map(
                                (client) => (
                                    <tr
                                        key={
                                            client.id
                                        }
                                    >
                                        <Td>
                                            <Link
                                                href={route(
                                                    'clients.show',
                                                    client.id,
                                                )}
                                                className="font-bold text-cyan-700"
                                            >
                                                {
                                                    client.name
                                                }
                                            </Link>
                                        </Td>

                                        <Td>
                                            {
                                                client.ip_address
                                            }
                                        </Td>

                                        <Td>
                                            {client.package
                                                ?.name ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {client.router
                                                ?.name ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {client.enabled
                                                ? 'Active'
                                                : 'Suspended'}
                                        </Td>

                                        <Td>
                                            {client.expiry_date ||
                                                '-'}
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {recentClients.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="6"
                                        className="p-8 text-center text-slate-400"
                                    >
                                        No client yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>
            </div>
        </AppLayout>
    );
}

function QuickRechargePos({
    pos = {},
}) {
    const clients =
        pos.clients ?? [];

    const packages =
        pos.packages ?? [];

    const canRenew =
        Boolean(pos.can_renew);

    const canReceivePayment =
        Boolean(
            pos.can_receive_payment,
        );

    const [search, setSearch] =
        useState('');

    const [selectedId, setSelectedId] =
        useState(null);

    const [paymentMode, setPaymentMode] =
        useState(
            canReceivePayment
                ? 'paid'
                : 'due',
        );

    const form = useForm({
        package_id: '',
        received_amount: '',
        payment_method: 'Cash',
        transaction_id: '',
        notes: '',
    });

    const filteredClients =
        useMemo(() => {
            const needle =
                search
                    .trim()
                    .toLowerCase();

            if (!needle) {
                return clients.slice(
                    0,
                    8,
                );
            }

            return clients
                .filter((client) => {
                    const haystack = [
                        client.name,
                        client.client_code,
                        client.phone,
                        client.mac_address,
                        client.ip_address,
                    ]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase();

                    return haystack.includes(
                        needle,
                    );
                })
                .slice(0, 8);
        }, [
            clients,
            search,
        ]);

    const selectedClient =
        clients.find(
            (client) =>
                Number(client.id)
                === Number(selectedId),
        ) ?? null;

    const dueAmount =
        Number(
            selectedClient
                ?.total_due
            ?? 0,
        );

    const selectedPackage =
        packages.find(
            (pkg) =>
                Number(pkg.id)
                === Number(
                    form.data
                        .package_id,
                ),
        )
        ?? selectedClient?.package
        ?? null;

    const renewalAmount =
        dueAmount > 0
            ? dueAmount
            : Number(
                selectedPackage?.price
                ?? 0,
            );

    const chooseClient = (
        client,
    ) => {
        setSelectedId(
            client.id,
        );

        setSearch(
            `${client.name} · ${
                client.client_code
                || client.ip_address
                || ''
            }`,
        );

        const currentDue =
            Number(
                client.total_due
                ?? 0,
            );

        const currentPrice =
            Number(
                client.package
                    ?.price
                ?? 0,
            );

        form.clearErrors();

        form.setData({
            package_id:
                client.package_id
                ? String(
                    client.package_id,
                )
                : '',

            received_amount:
                canReceivePayment
                    ? (
                        currentDue > 0
                            ? currentDue
                            : currentPrice
                    ).toFixed(2)
                    : '0.00',

            payment_method:
                'Cash',

            transaction_id:
                '',

            notes:
                '',
        });

        setPaymentMode(
            canReceivePayment
                ? 'paid'
                : 'due',
        );
    };

    const applyPaymentMode = (
        mode,
    ) => {
        setPaymentMode(mode);

        if (mode === 'due') {
            form.setData(
                'received_amount',
                '0.00',
            );

            return;
        }

        if (mode === 'paid') {
            form.setData(
                'received_amount',
                renewalAmount
                    .toFixed(2),
            );
        }
    };

    const changePackage = (
        value,
    ) => {
        form.setData(
            'package_id',
            value,
        );

        if (
            dueAmount > 0
            || paymentMode === 'due'
        ) {
            return;
        }

        const pkg =
            packages.find(
                (item) =>
                    Number(item.id)
                    === Number(value),
            );

        if (
            pkg
            && paymentMode === 'paid'
        ) {
            form.setData(
                'received_amount',
                Number(
                    pkg.price ?? 0,
                ).toFixed(2),
            );
        }
    };

    const submit = (event) => {
        event.preventDefault();

        if (!selectedClient) {
            return;
        }

        form.post(
            route(
                'clients.renew',
                selectedClient.id,
            ),
            {
                preserveScroll: true,
                onSuccess: () => {
                    form.clearErrors();
                },
            },
        );
    };

    if (!canRenew) {
        return null;
    }

    return (
        <section className="overflow-hidden rounded-2xl border border-cyan-200 bg-white shadow-sm">
            <div className="border-b border-cyan-100 bg-gradient-to-r from-cyan-50 to-sky-50 px-5 py-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 className="text-lg font-black text-slate-900">
                            Quick Recharge POS
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Search MAC client and recharge in seconds
                        </p>
                    </div>

                    <span className="rounded-full bg-cyan-600 px-3 py-1 text-xs font-bold text-white">
                        MAC CLIENT
                    </span>
                </div>
            </div>

            <div className="grid gap-5 p-5 xl:grid-cols-[1.15fr_1fr]">
                <div>
                    <label className="text-xs font-bold uppercase tracking-wide text-slate-500">
                        Search Client
                    </label>

                    <input
                        type="text"
                        value={search}
                        onChange={(event) => {
                            setSearch(
                                event.target
                                    .value,
                            );

                            setSelectedId(
                                null,
                            );
                        }}
                        placeholder="Name / Phone / MAC / IP / Client ID"
                        className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none transition focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
                    />

                    {!selectedClient && (
                        <div className="mt-2 max-h-64 overflow-y-auto rounded-xl border border-slate-200 bg-white">
                            {filteredClients.map(
                                (client) => (
                                    <button
                                        key={
                                            client.id
                                        }
                                        type="button"
                                        onClick={() =>
                                            chooseClient(
                                                client,
                                            )
                                        }
                                        className="flex w-full items-center justify-between gap-4 border-b border-slate-100 px-4 py-3 text-left transition last:border-b-0 hover:bg-cyan-50"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate font-bold text-slate-800">
                                                {
                                                    client.name
                                                }
                                            </span>

                                            <span className="mt-1 block truncate text-xs text-slate-500">
                                                {client.client_code || '-'}
                                                {' · '}
                                                {client.mac_address || '-'}
                                                {' · '}
                                                {client.ip_address || '-'}
                                            </span>
                                        </span>

                                        <span className="shrink-0 text-xs font-bold text-cyan-700">
                                            Select
                                        </span>
                                    </button>
                                ),
                            )}

                            {filteredClients.length ===
                                0 && (
                                <div className="p-5 text-center text-sm text-slate-400">
                                    No matching client
                                </div>
                            )}
                        </div>
                    )}

                    {selectedClient && (
                        <div className="mt-3 grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2">
                            <Info
                                label="Client"
                                value={
                                    selectedClient.name
                                }
                            />

                            <Info
                                label="Client ID"
                                value={
                                    selectedClient.client_code
                                    || selectedClient.id
                                }
                            />

                            <Info
                                label="MAC"
                                value={
                                    selectedClient.mac_address
                                    || '-'
                                }
                            />

                            <Info
                                label="IP"
                                value={
                                    selectedClient.ip_address
                                    || '-'
                                }
                            />

                            <Info
                                label="Current Package"
                                value={
                                    selectedClient.package
                                        ?.name
                                    || '-'
                                }
                            />

                            <Info
                                label="Expiry"
                                value={
                                    selectedClient.expiry_date
                                    || '-'
                                }
                            />

                            <Info
                                label="Status"
                                value={
                                    selectedClient.enabled
                                        ? 'Active'
                                        : 'Suspended'
                                }
                            />

                            <Info
                                label="Current Due"
                                value={`QAR ${money(
                                    dueAmount,
                                )}`}
                            />
                        </div>
                    )}
                </div>

                <form
                    onSubmit={submit}
                    className="rounded-xl border border-slate-200 bg-slate-50 p-4"
                >
                    {!selectedClient ? (
                        <div className="flex min-h-56 items-center justify-center text-center text-sm font-medium text-slate-400">
                            Select a client to start recharge
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div>
                                <label className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    Package
                                </label>

                                <select
                                    value={
                                        form.data
                                            .package_id
                                    }
                                    onChange={(event) =>
                                        changePackage(
                                            event.target
                                                .value,
                                        )
                                    }
                                    disabled={
                                        dueAmount > 0
                                    }
                                    className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-3 disabled:bg-slate-100"
                                >
                                    <option value="">
                                        Select Package
                                    </option>

                                    {packages
                                        .filter(
                                            (pkg) =>
                                                Number(
                                                    pkg.zone_id
                                                )
                                                === Number(
                                                    selectedClient
                                                        ?.zone_id
                                                )
                                        )
                                        .map(
                                        (pkg) => (
                                            <option
                                                key={
                                                    pkg.id
                                                }
                                                value={
                                                    pkg.id
                                                }
                                            >
                                                {pkg.name}
                                                {' · QAR '}
                                                {money(
                                                    pkg.price,
                                                )}
                                                {' · '}
                                                {
                                                    pkg.validity_days
                                                }
                                                {' days'}
                                            </option>
                                        ),
                                    )}
                                </select>

                                {dueAmount > 0 && (
                                    <p className="mt-1 text-xs font-semibold text-amber-600">
                                        Clear previous due before changing package.
                                    </p>
                                )}

                                {form.errors
                                    .package_id && (
                                    <p className="mt-1 text-xs font-semibold text-red-600">
                                        {
                                            form.errors
                                                .package_id
                                        }
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    Payment
                                </label>

                                <div className="mt-2 grid grid-cols-3 gap-2">
                                    {[
                                        [
                                            'paid',
                                            'Paid',
                                        ],
                                        [
                                            'partial',
                                            'Partial',
                                        ],
                                        [
                                            'due',
                                            'Due',
                                        ],
                                    ].map(
                                        ([
                                            value,
                                            label,
                                        ]) => {
                                            const disabled =
                                                !canReceivePayment
                                                && value
                                                    !==
                                                    'due';

                                            return (
                                                <button
                                                    key={
                                                        value
                                                    }
                                                    type="button"
                                                    disabled={
                                                        disabled
                                                    }
                                                    onClick={() =>
                                                        applyPaymentMode(
                                                            value,
                                                        )
                                                    }
                                                    className={`rounded-lg border px-3 py-2 text-sm font-bold transition ${
                                                        paymentMode
                                                        === value
                                                            ? 'border-cyan-600 bg-cyan-600 text-white'
                                                            : 'border-slate-300 bg-white text-slate-600'
                                                    } disabled:cursor-not-allowed disabled:opacity-40`}
                                                >
                                                    {
                                                        label
                                                    }
                                                </button>
                                            );
                                        },
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Received
                                    </label>

                                    <input
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        disabled={
                                            paymentMode
                                            === 'due'
                                            || !canReceivePayment
                                        }
                                        value={
                                            form.data
                                                .received_amount
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'received_amount',
                                                event
                                                    .target
                                                    .value,
                                            )
                                        }
                                        className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-3 disabled:bg-slate-100"
                                    />

                                    {form.errors
                                        .received_amount && (
                                        <p className="mt-1 text-xs font-semibold text-red-600">
                                            {
                                                form.errors
                                                    .received_amount
                                            }
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <label className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Method
                                    </label>

                                    <select
                                        value={
                                            form.data
                                                .payment_method
                                        }
                                        disabled={
                                            paymentMode
                                            === 'due'
                                            || !canReceivePayment
                                        }
                                        onChange={(event) =>
                                            form.setData(
                                                'payment_method',
                                                event
                                                    .target
                                                    .value,
                                            )
                                        }
                                        className="mt-2 w-full rounded-xl border border-slate-300 bg-white px-3 py-3 disabled:bg-slate-100"
                                    >
                                        <option>
                                            Cash
                                        </option>
                                        <option>
                                            Bank Transfer
                                        </option>
                                        <option>
                                            Ooredoo Money
                                        </option>
                                        <option>
                                            Manual Adjustment
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div className="rounded-xl bg-white p-3 text-sm">
                                <div className="flex justify-between gap-3">
                                    <span className="text-slate-500">
                                        Amount
                                    </span>

                                    <strong>
                                        QAR{' '}
                                        {money(
                                            renewalAmount,
                                        )}
                                    </strong>
                                </div>

                                <div className="mt-2 flex justify-between gap-3">
                                    <span className="text-slate-500">
                                        Action
                                    </span>

                                    <strong className="text-cyan-700">
                                        {dueAmount >
                                        0
                                            ? 'Pay Previous Due'
                                            : 'Recharge & Renew'}
                                    </strong>
                                </div>
                            </div>

                            {form.errors
                                .renewal && (
                                <div className="rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-700">
                                    {
                                        form.errors
                                            .renewal
                                    }
                                </div>
                            )}

                            {!canReceivePayment && (
                                <div className="rounded-lg bg-amber-50 p-3 text-xs font-semibold text-amber-700">
                                    Your account can create Due recharge only. Payment permission is required to receive money.
                                </div>
                            )}

                            <button
                                type="submit"
                                disabled={
                                    form.processing
                                    || !selectedClient
                                }
                                className="w-full rounded-xl bg-cyan-600 px-5 py-3.5 font-black text-white transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {form.processing
                                    ? 'Processing...'
                                    : dueAmount >
                                        0
                                      ? 'RECEIVE DUE PAYMENT'
                                      : 'RECHARGE & ACTIVATE'}
                            </button>
                        </div>
                    )}
                </form>
            </div>
        </section>
    );
}

function Info({
    label,
    value,
}) {
    return (
        <div>
            <div className="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                {label}
            </div>

            <div className="mt-1 break-all text-sm font-bold text-slate-700">
                {value}
            </div>
        </div>
    );
}

function Quick({
    routeName,
    label,
}) {
    return (
        <Link
            href={route(routeName)}
            className="rounded-xl border bg-slate-50 px-4 py-4 font-bold text-slate-700 transition hover:border-cyan-300 hover:bg-white"
        >
            {label}
        </Link>
    );
}

function Stat({
    label,
    value,
}) {
    return (
        <div className="rounded-xl border bg-white p-5 shadow-sm">
            <div className="text-xs font-bold uppercase text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-xl font-black">
                {value ?? 0}
            </div>
        </div>
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
