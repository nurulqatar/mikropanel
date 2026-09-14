import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';
import {
    useMemo,
    useState,
} from 'react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

const formatMac = (value) =>
    value
        .toUpperCase()
        .replace(/[^0-9A-F]/g, '')
        .slice(0, 12)
        .replace(/(.{2})(?=.)/g, '$1:');

export default function MacClientPos({
    clients = [],
    packages = [],
    ipRanges = [],
    permissions = {},
    stats = {},
}) {
    const [search, setSearch] =
        useState('');

    const [status, setStatus] =
        useState('all');

    const [modal, setModal] =
        useState(null);

    const [selected, setSelected] =
        useState(null);

    const visibleClients =
        useMemo(() => {
            const needle =
                search
                    .trim()
                    .toLowerCase();

            return clients.filter(
                (client) => {
                    if (
                        status === 'active'
                        && !client.enabled
                    ) {
                        return false;
                    }

                    if (
                        status === 'suspended'
                        && client.enabled
                    ) {
                        return false;
                    }

                    if (
                        status === 'due'
                        && Number(
                            client.total_due
                            ?? 0,
                        ) <= 0
                    ) {
                        return false;
                    }

                    if (!needle) {
                        return true;
                    }

                    const haystack = [
                        client.name,
                        client.client_code,
                        client.phone,
                        client.mac_address,
                        client.ip_address,
                        client.package?.name,
                    ]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase();

                    return haystack.includes(
                        needle,
                    );
                },
            );
        }, [
            clients,
            search,
            status,
        ]);

    const openModal = (
        type,
        client = null,
    ) => {
        setSelected(client);
        setModal(type);
    };

    const closeModal = () => {
        setModal(null);
        setSelected(null);
    };

    const changeState = (
        client,
    ) => {
        const action =
            client.enabled
                ? 'suspend'
                : 'unsuspend';

        const label =
            client.enabled
                ? 'Suspend'
                : 'Activate';

        if (
            !window.confirm(
                `${label} ${client.name}?`,
            )
        ) {
            return;
        }

        router.post(
            route(
                `clients.${action}`,
                client.id,
            ),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="MAC Client POS">
            <Head title="MAC Client POS" />

            <div className="space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black text-slate-900">
                            MAC Client POS
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Daily MAC client operations from one screen
                        </p>
                    </div>

                    {permissions.create && (
                        <button
                            type="button"
                            onClick={() =>
                                openModal(
                                    'create',
                                )
                            }
                            className="rounded-xl bg-cyan-600 px-5 py-3 font-black text-white shadow-sm hover:bg-cyan-700"
                        >
                            + NEW CLIENT
                        </button>
                    )}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Total Clients"
                        value={stats.total}
                    />

                    <StatCard
                        label="Active"
                        value={stats.active}
                    />

                    <StatCard
                        label="Suspended"
                        value={
                            stats.suspended
                        }
                    />

                    <StatCard
                        label="Total Due"
                        value={`QAR ${money(
                            stats.due,
                        )}`}
                    />
                </div>

                <QuickClientWorkspace
                    clients={clients}
                    permissions={permissions}
                    onRecharge={(client) =>
                        openModal(
                            'recharge',
                            client,
                        )
                    }
                    onEdit={(client) =>
                        openModal(
                            'edit',
                            client,
                        )
                    }
                    onToggle={
                        changeState
                    }
                />

                <section className="rounded-2xl border bg-white shadow-sm">
                    <div className="border-b p-4">
                        <div className="flex flex-col gap-3 xl:flex-row xl:items-center">
                            <input
                                type="text"
                                value={search}
                                onChange={(
                                    event,
                                ) =>
                                    setSearch(
                                        event
                                            .target
                                            .value,
                                    )
                                }
                                placeholder="Search Name / Phone / MAC / IP / Client ID"
                                className="min-w-0 flex-1 rounded-xl border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500"
                            />

                            <div className="flex flex-wrap gap-2">
                                {[
                                    [
                                        'all',
                                        'All',
                                    ],
                                    [
                                        'active',
                                        'Active',
                                    ],
                                    [
                                        'suspended',
                                        'Suspended',
                                    ],
                                    [
                                        'due',
                                        'Due',
                                    ],
                                ].map(
                                    ([
                                        value,
                                        label,
                                    ]) => (
                                        <button
                                            key={
                                                value
                                            }
                                            type="button"
                                            onClick={() =>
                                                setStatus(
                                                    value,
                                                )
                                            }
                                            className={`rounded-lg px-4 py-2 text-sm font-bold ${
                                                status
                                                === value
                                                    ? 'bg-slate-900 text-white'
                                                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                            }`}
                                        >
                                            {
                                                label
                                            }
                                        </button>
                                    ),
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>
                                        Client
                                    </Th>
                                    <Th>
                                        MAC / IP
                                    </Th>
                                    <Th>
                                        Package
                                    </Th>
                                    <Th>
                                        Expiry
                                    </Th>
                                    <Th>
                                        Due
                                    </Th>
                                    <Th>
                                        Status
                                    </Th>
                                    <Th>
                                        Actions
                                    </Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {visibleClients.map(
                                    (
                                        client,
                                    ) => (
                                        <tr
                                            key={
                                                client.id
                                            }
                                            className="hover:bg-slate-50"
                                        >
                                            <Td>
                                                <div className="font-black text-slate-800">
                                                    {
                                                        client.name
                                                    }
                                                </div>

                                                <div className="mt-1 text-xs text-slate-400">
                                                    {client.client_code
                                                        || `#${client.id}`}
                                                    {client.phone
                                                        ? ` · ${client.phone}`
                                                        : ''}
                                                </div>
                                            </Td>

                                            <Td>
                                                <div className="font-mono text-xs font-bold text-slate-700">
                                                    {client.mac_address
                                                        || '-'}
                                                </div>

                                                <div className="mt-1 text-xs text-slate-500">
                                                    {client.ip_address
                                                        || '-'}
                                                </div>
                                            </Td>

                                            <Td>
                                                <div className="font-bold">
                                                    {client.package
                                                        ?.name
                                                        || '-'}
                                                </div>

                                                <div className="mt-1 text-xs text-slate-400">
                                                    {client.ip_range
                                                        ?.name
                                                        || '-'}
                                                </div>
                                            </Td>

                                            <Td>
                                                {
                                                    client.expiry_date
                                                    || '-'
                                                }
                                            </Td>

                                            <Td>
                                                <span
                                                    className={
                                                        Number(
                                                            client.total_due
                                                            ?? 0,
                                                        )
                                                        > 0
                                                            ? 'font-black text-amber-600'
                                                            : 'font-bold text-emerald-600'
                                                    }
                                                >
                                                    QAR{' '}
                                                    {money(
                                                        client.total_due,
                                                    )}
                                                </span>
                                            </Td>

                                            <Td>
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-black ${
                                                        client.enabled
                                                            ? 'bg-emerald-100 text-emerald-700'
                                                            : 'bg-red-100 text-red-700'
                                                    }`}
                                                >
                                                    {client.enabled
                                                        ? 'ACTIVE'
                                                        : 'SUSPENDED'}
                                                </span>

                                                {client.connected && (
                                                    <div className="mt-1 text-xs font-bold text-cyan-600">
                                                        ● ONLINE
                                                    </div>
                                                )}
                                            </Td>

                                            <Td>
                                                <div className="flex min-w-64 flex-wrap gap-2">
                                                    {permissions.renew && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                openModal(
                                                                    'recharge',
                                                                    client,
                                                                )
                                                            }
                                                            className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-black text-white hover:bg-emerald-700"
                                                        >
                                                            Recharge
                                                        </button>
                                                    )}

                                                    {permissions.edit && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                openModal(
                                                                    'edit',
                                                                    client,
                                                                )
                                                            }
                                                            className="rounded-lg bg-blue-600 px-3 py-2 text-xs font-black text-white hover:bg-blue-700"
                                                        >
                                                            Edit
                                                        </button>
                                                    )}

                                                    {permissions.suspend && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                changeState(
                                                                    client,
                                                                )
                                                            }
                                                            className={`rounded-lg px-3 py-2 text-xs font-black text-white ${
                                                                client.enabled
                                                                    ? 'bg-red-600 hover:bg-red-700'
                                                                    : 'bg-cyan-600 hover:bg-cyan-700'
                                                            }`}
                                                        >
                                                            {client.enabled
                                                                ? 'Suspend'
                                                                : 'Activate'}
                                                        </button>
                                                    )}

                                                    <Link
                                                        href={route(
                                                            'clients.show',
                                                            client.id,
                                                        )}
                                                        className="rounded-lg bg-slate-700 px-3 py-2 text-xs font-black text-white hover:bg-slate-800"
                                                    >
                                                        View
                                                    </Link>
                                                </div>
                                            </Td>
                                        </tr>
                                    ),
                                )}

                                {visibleClients.length ===
                                    0 && (
                                    <tr>
                                        <td
                                            colSpan="7"
                                            className="p-10 text-center text-sm text-slate-400"
                                        >
                                            No matching MAC client found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            {modal === 'create'
                && permissions.create && (
                <CreateClientModal
                    packages={packages}
                    ipRanges={ipRanges}
                    canReceivePayment={
                        permissions.receive_payment
                    }
                    onClose={
                        closeModal
                    }
                />
            )}

            {modal === 'recharge'
                && selected
                && permissions.renew && (
                <RechargeModal
                    client={selected}
                    packages={packages}
                    canReceivePayment={
                        permissions.receive_payment
                    }
                    onClose={
                        closeModal
                    }
                />
            )}

            {modal === 'edit'
                && selected
                && permissions.edit && (
                <EditClientModal
                    client={selected}
                    packages={packages}
                    ipRanges={ipRanges}
                    onClose={
                        closeModal
                    }
                />
            )}
        </AppLayout>
    );
}

function QuickClientWorkspace({
    clients = [],
    permissions = {},
    onRecharge,
    onEdit,
    onToggle,
}) {
    const [search, setSearch] =
        useState('');

    const [selectedId, setSelectedId] =
        useState(null);

    const selectedClient =
        clients.find(
            (client) =>
                Number(client.id)
                === Number(selectedId),
        ) ?? null;

    const results =
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
                        client.package?.name,
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

    const selectClient = (
        client,
    ) => {
        setSelectedId(
            client.id,
        );

        setSearch(
            `${client.name}${
                client.client_code
                    ? ` · ${client.client_code}`
                    : ''
            }`,
        );
    };

    return (
        <section className="overflow-hidden rounded-2xl border border-cyan-200 bg-white shadow-sm">
            <div className="border-b border-cyan-100 bg-gradient-to-r from-cyan-50 to-sky-50 px-5 py-4">
                <div>
                    <h2 className="text-lg font-black text-slate-900">
                        Quick Client Work
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Search and select a MAC client
                    </p>
                </div>
            </div>

            <div className="grid gap-5 p-5 lg:grid-cols-2">
                <div>
                    <label className="text-xs font-black uppercase tracking-wide text-slate-500">
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
                        className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
                    />

                    {!selectedClient && (
                        <div className="mt-2 max-h-72 overflow-y-auto rounded-xl border border-slate-200">
                            {results.map(
                                (client) => (
                                    <button
                                        key={
                                            client.id
                                        }
                                        type="button"
                                        onClick={() =>
                                            selectClient(
                                                client,
                                            )
                                        }
                                        className="flex w-full items-center justify-between gap-4 border-b border-slate-100 px-4 py-3 text-left transition last:border-b-0 hover:bg-cyan-50"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate font-black text-slate-800">
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

                                        <span className="shrink-0 rounded-lg bg-cyan-50 px-3 py-1.5 text-xs font-black text-cyan-700">
                                            Select
                                        </span>
                                    </button>
                                ),
                            )}

                            {results.length ===
                                0 && (
                                <div className="p-7 text-center text-sm font-semibold text-slate-400">
                                    No matching client
                                </div>
                            )}
                        </div>
                    )}

                    {selectedClient && (
                        <button
                            type="button"
                            onClick={() => {
                                setSelectedId(
                                    null,
                                );

                                setSearch('');
                            }}
                            className="mt-3 text-sm font-bold text-cyan-700 hover:underline"
                        >
                            ← Select another client
                        </button>
                    )}
                </div>

                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    {!selectedClient ? (
                        <div className="flex min-h-72 items-center justify-center text-center">
                            <div>
                                <div className="text-4xl text-slate-300">
                                    ◇
                                </div>

                                <div className="mt-3 font-black text-slate-500">
                                    Select a client to start recharge
                                </div>

                                <div className="mt-1 text-xs text-slate-400">
                                    Search by name, phone, MAC, IP or Client ID
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 className="text-xl font-black text-slate-900">
                                        {
                                            selectedClient.name
                                        }
                                    </h3>

                                    <div className="mt-1 text-sm text-slate-500">
                                        {selectedClient.client_code
                                            || `#${selectedClient.id}`}
                                    </div>
                                </div>

                                <span
                                    className={`rounded-full px-3 py-1 text-xs font-black ${
                                        selectedClient.enabled
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-red-100 text-red-700'
                                    }`}
                                >
                                    {selectedClient.enabled
                                        ? 'ACTIVE'
                                        : 'SUSPENDED'}
                                </span>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <QuickInfo
                                    label="MAC"
                                    value={
                                        selectedClient.mac_address
                                        || '-'
                                    }
                                />

                                <QuickInfo
                                    label="IP"
                                    value={
                                        selectedClient.ip_address
                                        || '-'
                                    }
                                />

                                <QuickInfo
                                    label="Package"
                                    value={
                                        selectedClient.package
                                            ?.name
                                        || '-'
                                    }
                                />

                                <QuickInfo
                                    label="Expiry"
                                    value={
                                        selectedClient.expiry_date
                                        || '-'
                                    }
                                />

                                <QuickInfo
                                    label="Phone"
                                    value={
                                        selectedClient.phone
                                        || '-'
                                    }
                                />

                                <QuickInfo
                                    label="Due"
                                    value={`QAR ${money(
                                        selectedClient.total_due,
                                    )}`}
                                />
                            </div>

                            <div className="grid gap-2 pt-2 sm:grid-cols-2">
                                {permissions.renew && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onRecharge(
                                                selectedClient,
                                            )
                                        }
                                        className="rounded-xl bg-emerald-600 px-4 py-3 font-black text-white hover:bg-emerald-700"
                                    >
                                        Recharge / Due Payment
                                    </button>
                                )}

                                {permissions.edit && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onEdit(
                                                selectedClient,
                                            )
                                        }
                                        className="rounded-xl bg-blue-600 px-4 py-3 font-black text-white hover:bg-blue-700"
                                    >
                                        Edit Client
                                    </button>
                                )}

                                {permissions.suspend && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onToggle(
                                                selectedClient,
                                            )
                                        }
                                        className={`rounded-xl px-4 py-3 font-black text-white ${
                                            selectedClient.enabled
                                                ? 'bg-red-600 hover:bg-red-700'
                                                : 'bg-cyan-600 hover:bg-cyan-700'
                                        }`}
                                    >
                                        {selectedClient.enabled
                                            ? 'Suspend Client'
                                            : 'Activate Client'}
                                    </button>
                                )}

                                <Link
                                    href={route(
                                        'clients.show',
                                        selectedClient.id,
                                    )}
                                    className="rounded-xl bg-slate-700 px-4 py-3 text-center font-black text-white hover:bg-slate-800"
                                >
                                    Full Client Details
                                </Link>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}

function QuickInfo({
    label,
    value,
}) {
    return (
        <div className="rounded-lg bg-white p-3">
            <div className="text-[10px] font-black uppercase tracking-wide text-slate-400">
                {label}
            </div>

            <div className="mt-1 break-all text-sm font-black text-slate-700">
                {value}
            </div>
        </div>
    );
}

function CreateClientModal({
    packages,
    ipRanges,
    canReceivePayment,
    onClose,
}) {
    const form = useForm({
        ip_range_id: '',
        package_id: '',
        name: '',
        mac_address: '',
        phone: '',
        connection_payment_status:
            canReceivePayment
                ? 'paid'
                : 'due',
        connection_payment_method:
            'Cash',
        connection_transaction_id:
            '',
        return_to: 'mac-pos',
    });

    const selectedPackage =
        packages.find(
            (item) =>
                String(item.id)
                === String(
                    form.data
                        .package_id,
                ),
        );

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route('clients.store'),
            {
                preserveScroll: true,
                onSuccess:
                    onClose,
            },
        );
    };

    return (
        <Modal
            title="New MAC Client"
            onClose={onClose}
        >
            <form
                onSubmit={submit}
                className="space-y-4"
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        label="Client Name"
                        error={
                            form.errors.name
                        }
                    >
                        <input
                            autoFocus
                            value={
                                form.data.name
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'name',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Phone"
                        error={
                            form.errors.phone
                        }
                    >
                        <input
                            value={
                                form.data.phone
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'phone',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="MAC Address"
                        error={
                            form.errors
                                .mac_address
                        }
                    >
                        <input
                            value={
                                form.data
                                    .mac_address
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'mac_address',
                                    formatMac(
                                        event.target
                                            .value,
                                    ),
                                )
                            }
                            placeholder="AA:BB:CC:DD:EE:FF"
                            className={`${inputClass} font-mono`}
                        />
                    </Field>

                    <Field
                        label="IP Pool"
                        error={
                            form.errors
                                .ip_range_id
                        }
                    >
                        <select
                            value={
                                form.data
                                    .ip_range_id
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'ip_range_id',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        >
                            <option value="">
                                Select IP Pool
                            </option>

                            {ipRanges.map(
                                (range) => (
                                    <option
                                        key={
                                            range.id
                                        }
                                        value={
                                            range.id
                                        }
                                    >
                                        {
                                            range.name
                                        }
                                    </option>
                                ),
                            )}
                        </select>
                    </Field>

                    <Field
                        label="Package"
                        error={
                            form.errors
                                .package_id
                        }
                    >
                        <select
                            value={
                                form.data
                                    .package_id
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'package_id',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        >
                            <option value="">
                                Select Package
                            </option>

                            {packages.map(
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
                                        {' Days'}
                                    </option>
                                ),
                            )}
                        </select>
                    </Field>

                    <Field label="Connection Amount">
                        <input
                            readOnly
                            value={
                                selectedPackage
                                    ? `QAR ${money(
                                        selectedPackage.price,
                                    )}`
                                    : ''
                            }
                            className={`${inputClass} bg-slate-100 font-black`}
                        />
                    </Field>

                    <Field
                        label="Payment"
                        error={
                            form.errors
                                .connection_payment_status
                        }
                    >
                        <select
                            value={
                                form.data
                                    .connection_payment_status
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'connection_payment_status',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        >
                            {canReceivePayment && (
                                <option value="paid">
                                    Paid
                                </option>
                            )}

                            <option value="due">
                                Due
                            </option>
                        </select>
                    </Field>

                    {form.data
                        .connection_payment_status
                        === 'paid' && (
                        <Field
                            label="Payment Method"
                            error={
                                form.errors
                                    .connection_payment_method
                            }
                        >
                            <PaymentMethod
                                value={
                                    form.data
                                        .connection_payment_method
                                }
                                onChange={(
                                    value,
                                ) =>
                                    form.setData(
                                        'connection_payment_method',
                                        value,
                                    )
                                }
                            />
                        </Field>
                    )}
                </div>

                <div className="flex justify-end gap-3 border-t pt-4">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg bg-slate-200 px-5 py-3 font-bold text-slate-700"
                    >
                        Cancel
                    </button>

                    <button
                        disabled={
                            form.processing
                        }
                        className="rounded-lg bg-cyan-600 px-6 py-3 font-black text-white disabled:opacity-50"
                    >
                        {form.processing
                            ? 'Creating...'
                            : 'CREATE & ACTIVATE'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

function RechargeModal({
    client,
    packages,
    canReceivePayment,
    onClose,
}) {
    const due =
        Number(
            client.total_due
            ?? 0,
        );

    const [mode, setMode] =
        useState(
            canReceivePayment
                ? 'paid'
                : 'due',
        );

    const form = useForm({
        package_id:
            String(
                client.package_id
                ?? '',
            ),
        received_amount:
            canReceivePayment
                ? (
                    due > 0
                        ? due
                        : Number(
                            client.package
                                ?.price
                            ?? 0,
                        )
                ).toFixed(2)
                : '0.00',
        payment_method: 'Cash',
        transaction_id: '',
        notes: '',
    });

    const selectedPackage =
        packages.find(
            (pkg) =>
                Number(pkg.id)
                === Number(
                    form.data
                        .package_id,
                ),
        )
        ?? client.package;

    const amount =
        due > 0
            ? due
            : Number(
                selectedPackage?.price
                ?? 0,
            );

    const setPaymentMode = (
        value,
    ) => {
        setMode(value);

        if (value === 'due') {
            form.setData(
                'received_amount',
                '0.00',
            );
        }

        if (value === 'paid') {
            form.setData(
                'received_amount',
                amount.toFixed(2),
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

        const pkg =
            packages.find(
                (item) =>
                    Number(item.id)
                    === Number(value),
            );

        if (
            due <= 0
            && mode === 'paid'
            && pkg
        ) {
            form.setData(
                'received_amount',
                Number(
                    pkg.price,
                ).toFixed(2),
            );
        }
    };

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route(
                'clients.renew',
                client.id,
            ),
            {
                preserveScroll: true,
                onSuccess:
                    onClose,
            },
        );
    };

    return (
        <Modal
            title={
                due > 0
                    ? `Receive Due · ${client.name}`
                    : `Recharge · ${client.name}`
            }
            onClose={onClose}
        >
            <form
                onSubmit={submit}
                className="space-y-4"
            >
                <div className="grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                    <MiniInfo
                        label="MAC"
                        value={
                            client.mac_address
                            || '-'
                        }
                    />

                    <MiniInfo
                        label="IP"
                        value={
                            client.ip_address
                            || '-'
                        }
                    />

                    <MiniInfo
                        label="Current Package"
                        value={
                            client.package
                                ?.name
                            || '-'
                        }
                    />

                    <MiniInfo
                        label="Current Due"
                        value={`QAR ${money(
                            due,
                        )}`}
                    />
                </div>

                <Field
                    label="Package"
                    error={
                        form.errors
                            .package_id
                    }
                >
                    <select
                        disabled={
                            due > 0
                        }
                        value={
                            form.data
                                .package_id
                        }
                        onChange={(
                            event,
                        ) =>
                            changePackage(
                                event.target
                                    .value,
                            )
                        }
                        className={`${inputClass} disabled:bg-slate-100`}
                    >
                        {packages.map(
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
                                    {' Days'}
                                </option>
                            ),
                        )}
                    </select>
                </Field>

                {due > 0 && (
                    <div className="rounded-lg bg-amber-50 p-3 text-sm font-bold text-amber-700">
                        Previous due must be cleared before changing package or creating another service period.
                    </div>
                )}

                <div>
                    <div className="mb-2 text-xs font-bold uppercase text-slate-500">
                        Payment
                    </div>

                    <div className="grid grid-cols-3 gap-2">
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
                                    (
                                        !canReceivePayment
                                        && value
                                            !== 'due'
                                    )
                                    || (
                                        due > 0
                                        && value
                                            === 'due'
                                    );

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
                                            setPaymentMode(
                                                value,
                                            )
                                        }
                                        className={`rounded-lg border px-3 py-2 font-bold ${
                                            mode
                                            === value
                                                ? 'border-cyan-600 bg-cyan-600 text-white'
                                                : 'bg-white text-slate-600'
                                        } disabled:opacity-30`}
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

                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        label="Received Amount"
                        error={
                            form.errors
                                .received_amount
                        }
                    >
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            disabled={
                                mode
                                === 'due'
                            }
                            value={
                                form.data
                                    .received_amount
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'received_amount',
                                    event.target
                                        .value,
                                )
                            }
                            className={`${inputClass} disabled:bg-slate-100`}
                        />
                    </Field>

                    <Field
                        label="Payment Method"
                        error={
                            form.errors
                                .payment_method
                        }
                    >
                        <PaymentMethod
                            disabled={
                                mode
                                === 'due'
                            }
                            value={
                                form.data
                                    .payment_method
                            }
                            onChange={(
                                value,
                            ) =>
                                form.setData(
                                    'payment_method',
                                    value,
                                )
                            }
                        />
                    </Field>
                </div>

                {form.errors.renewal && (
                    <div className="rounded-lg bg-red-50 p-3 text-sm font-bold text-red-700">
                        {
                            form.errors
                                .renewal
                        }
                    </div>
                )}

                <div className="flex justify-end gap-3 border-t pt-4">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg bg-slate-200 px-5 py-3 font-bold text-slate-700"
                    >
                        Cancel
                    </button>

                    <button
                        disabled={
                            form.processing
                        }
                        className="rounded-lg bg-emerald-600 px-6 py-3 font-black text-white disabled:opacity-50"
                    >
                        {form.processing
                            ? 'Processing...'
                            : due > 0
                              ? 'RECEIVE PAYMENT'
                              : 'RECHARGE & ACTIVATE'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

function EditClientModal({
    client,
    packages,
    ipRanges,
    onClose,
}) {
    const form = useForm({
        ip_range_id:
            String(
                client.ip_range_id
                ?? '',
            ),
        package_id:
            String(
                client.package_id
                ?? '',
            ),
        name:
            client.name ?? '',
        mac_address:
            client.mac_address ?? '',
        phone:
            client.phone ?? '',
        email:
            client.email ?? '',
        address:
            client.address ?? '',
        return_to: 'mac-pos',
    });

    const submit = (event) => {
        event.preventDefault();

        form.put(
            route(
                'clients.update',
                client.id,
            ),
            {
                preserveScroll: true,
                onSuccess:
                    onClose,
            },
        );
    };

    return (
        <Modal
            title={`Edit · ${client.name}`}
            onClose={onClose}
        >
            <form
                onSubmit={submit}
                className="space-y-4"
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        label="Client Name"
                        error={
                            form.errors.name
                        }
                    >
                        <input
                            value={
                                form.data.name
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'name',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Phone"
                        error={
                            form.errors.phone
                        }
                    >
                        <input
                            value={
                                form.data.phone
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'phone',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="MAC Address"
                        error={
                            form.errors
                                .mac_address
                        }
                    >
                        <input
                            value={
                                form.data
                                    .mac_address
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'mac_address',
                                    formatMac(
                                        event.target
                                            .value,
                                    ),
                                )
                            }
                            className={`${inputClass} font-mono`}
                        />
                    </Field>

                    <Field
                        label="Package"
                        error={
                            form.errors
                                .package_id
                        }
                    >
                        <select
                            value={
                                form.data
                                    .package_id
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'package_id',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        >
                            {packages.map(
                                (pkg) => (
                                    <option
                                        key={
                                            pkg.id
                                        }
                                        value={
                                            pkg.id
                                        }
                                    >
                                        {
                                            pkg.name
                                        }
                                    </option>
                                ),
                            )}
                        </select>
                    </Field>

                    <Field label="IP Pool">
                        <select
                            disabled
                            value={
                                form.data
                                    .ip_range_id
                            }
                            className={`${inputClass} bg-slate-100`}
                        >
                            {ipRanges.map(
                                (range) => (
                                    <option
                                        key={
                                            range.id
                                        }
                                        value={
                                            range.id
                                        }
                                    >
                                        {
                                            range.name
                                        }
                                    </option>
                                ),
                            )}
                        </select>
                    </Field>

                    <Field
                        label="Email"
                        error={
                            form.errors.email
                        }
                    >
                        <input
                            type="email"
                            value={
                                form.data.email
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'email',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>
                </div>

                {form.errors
                    .mac_address && (
                    <div className="rounded-lg bg-red-50 p-3 text-sm font-bold text-red-700">
                        {
                            form.errors
                                .mac_address
                        }
                    </div>
                )}

                <div className="flex justify-end gap-3 border-t pt-4">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg bg-slate-200 px-5 py-3 font-bold text-slate-700"
                    >
                        Cancel
                    </button>

                    <button
                        disabled={
                            form.processing
                        }
                        className="rounded-lg bg-blue-600 px-6 py-3 font-black text-white disabled:opacity-50"
                    >
                        {form.processing
                            ? 'Saving...'
                            : 'SAVE CLIENT'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

const inputClass =
    'w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500';

function PaymentMethod({
    value,
    onChange,
    disabled = false,
}) {
    return (
        <select
            disabled={disabled}
            value={value}
            onChange={(event) =>
                onChange(
                    event.target.value,
                )
            }
            className={`${inputClass} disabled:bg-slate-100`}
        >
            <option value="Cash">
                Cash
            </option>

            <option value="Bank Transfer">
                Bank Transfer
            </option>

            <option value="Ooredoo Money">
                Ooredoo Money
            </option>

            <option value="bKash">
                bKash
            </option>

            <option value="Nagad">
                Nagad
            </option>

            <option value="Rocket">
                Rocket
            </option>

            <option value="Upay">
                Upay
            </option>

            <option value="iPay">
                iPay
            </option>

            <option value="Manual Adjustment">
                Manual Adjustment
            </option>
        </select>
    );
}

function Modal({
    title,
    children,
    onClose,
}) {
    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/70 p-4">
            <div className="max-h-[94vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div className="sticky top-0 z-10 flex items-center justify-between border-b bg-white px-5 py-4">
                    <h2 className="text-xl font-black text-slate-900">
                        {title}
                    </h2>

                    <button
                        type="button"
                        onClick={onClose}
                        className="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-xl font-black text-slate-500 hover:bg-slate-200"
                    >
                        ×
                    </button>
                </div>

                <div className="p-5">
                    {children}
                </div>
            </div>
        </div>
    );
}

function Field({
    label,
    error,
    children,
}) {
    return (
        <label className="block">
            <span className="text-xs font-bold uppercase tracking-wide text-slate-500">
                {label}
            </span>

            <div className="mt-2">
                {children}
            </div>

            {error && (
                <div className="mt-1 text-xs font-bold text-red-600">
                    {error}
                </div>
            )}
        </label>
    );
}

function MiniInfo({
    label,
    value,
}) {
    return (
        <div>
            <div className="text-[10px] font-bold uppercase text-slate-400">
                {label}
            </div>

            <div className="mt-1 break-all text-sm font-black text-slate-700">
                {value}
            </div>
        </div>
    );
}

function StatCard({
    label,
    value,
}) {
    return (
        <div className="rounded-xl border bg-white p-4 shadow-sm">
            <div className="text-xs font-bold uppercase text-slate-400">
                {label}
            </div>

            <div className="mt-2 text-2xl font-black text-slate-800">
                {value ?? 0}
            </div>
        </div>
    );
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
        <td className="whitespace-nowrap px-4 py-4 align-top text-sm">
            {children}
        </td>
    );
}
