import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';
import { useState } from 'react';

export default function Index({
    clients = [],
    flash = {},
}) {
    const [search, setSearch] = useState('');
    const [filter, setFilter] =
        useState('all');

    const [selectedClient, setSelectedClient] =
        useState(null);

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm({
        received_amount: '',
        payment_method: 'Cash',
        transaction_id: '',
        notes: '',
    });

    const paymentMethods = [
        'Cash',
        'Bank Transfer',
        'bKash',
        'Nagad',
        'Rocket',
        'Upay',
        'Ooredoo Money',
        'iPay',
        'Stripe',
        'PayPal',
        'Manual Adjustment',
    ];

    const clientDue = (client) =>
        numberValue(client?.total_due);

    const hasDue = (client) =>
        clientDue(client) > 0;

    const packagePrice = (client) =>
        numberValue(client?.package?.price);

    const openPaymentModal = (client) => {
        clearErrors();

        const defaultAmount = hasDue(client)
            ? clientDue(client)
            : packagePrice(client);

        setData({
            received_amount:
                defaultAmount.toFixed(2),

            payment_method: 'Cash',
            transaction_id: '',
            notes: '',
        });

        setSelectedClient(client);
    };

    const closePaymentModal = () => {
        if (processing) {
            return;
        }

        setSelectedClient(null);
        clearErrors();
        reset();
    };

    const submitPayment = (event) => {
        event.preventDefault();

        if (!selectedClient) {
            return;
        }

        post(
            route(
                'clients.renew',
                selectedClient.id,
            ),
            {
                preserveScroll: true,

                onSuccess: () => {
                    setSelectedClient(null);
                    clearErrors();
                    reset();
                },
            },
        );
    };

    const suspendClient = (id) => {
        if (!confirm('Suspend this client?')) {
            return;
        }

        router.post(
            route('clients.suspend', id),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const activateClient = (id) => {
        if (!confirm('Activate this client?')) {
            return;
        }

        router.post(
            route('clients.unsuspend', id),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const archiveClient = (id) => {
        if (
            !confirm(
                'Archive this client? Invoice and payment history will be preserved.',
            )
        ) {
            return;
        }

        router.delete(
            route('clients.destroy', id),
            {
                preserveScroll: true,
            },
        );
    };

    const filteredClients = clients.filter(
        (client) => {
            const text = [
                client.name,
                client.client_code,
                client.ip_address,
                client.phone,
                client.package?.name,
            ]
                .join(' ')
                .toLowerCase();

            const matchesSearch = text.includes(
                search.toLowerCase(),
            );

            let matchesFilter = true;

            if (filter === 'active') {
                matchesFilter =
                    Boolean(client.enabled);
            }

            if (filter === 'suspended') {
                matchesFilter =
                    !client.enabled;
            }

            if (filter === 'online') {
                matchesFilter =
                    Boolean(client.connected);
            }

            if (filter === 'due') {
                matchesFilter =
                    hasDue(client);
            }

            return (
                matchesSearch
                && matchesFilter
            );
        },
    );

    const selectedHasDue =
        selectedClient
        && hasDue(selectedClient);

    const payableAmount = selectedClient
        ? (
              selectedHasDue
                  ? clientDue(selectedClient)
                  : packagePrice(
                        selectedClient,
                    )
          )
        : 0;

    const receivedAmount = numberValue(
        data.received_amount,
    );

    const remainingAmount = Math.max(
        0,
        payableAmount - receivedAmount,
    );

    return (
        <AppLayout title="Clients">
            <Head title="Clients" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-800">
                            Clients
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Payment, due and renewal from one place
                        </p>
                    </div>

                    <Link
                        href={route(
                            'clients.create',
                        )}
                        className="rounded-xl bg-cyan-600 px-5 py-3 font-semibold text-white shadow hover:bg-cyan-700"
                    >
                        + Add Client
                    </Link>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-700">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-700">
                        {flash.error}
                    </div>
                )}

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="grid gap-4 md:grid-cols-3">
                        <input
                            type="text"
                            placeholder="Search name, code, IP or phone..."
                            value={search}
                            onChange={(event) =>
                                setSearch(
                                    event.target.value,
                                )
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3"
                        />

                        <select
                            value={filter}
                            onChange={(event) =>
                                setFilter(
                                    event.target.value,
                                )
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3"
                        >
                            <option value="all">
                                All Clients
                            </option>

                            <option value="active">
                                Active
                            </option>

                            <option value="suspended">
                                Suspended
                            </option>

                            <option value="online">
                                Online
                            </option>

                            <option value="due">
                                Clients With Due
                            </option>
                        </select>

                        <div className="rounded-xl bg-slate-100 px-4 py-3">
                            Showing:
                            <b className="ml-2">
                                {
                                    filteredClients.length
                                }
                            </b>
                        </div>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-800 text-white">
                            <tr>
                                <TableHead>
                                    Client
                                </TableHead>

                                <TableHead>
                                    IP
                                </TableHead>

                                <TableHead>
                                    Package
                                </TableHead>

                                <TableHead>
                                    Expiry
                                </TableHead>

                                <TableHead>
                                    Due
                                </TableHead>

                                <TableHead>
                                    Account
                                </TableHead>

                                <TableHead>
                                    Connection
                                </TableHead>

                                <TableHead>
                                    Action
                                </TableHead>
                            </tr>
                        </thead>

                        <tbody>
                            {filteredClients.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="8"
                                        className="py-12 text-center text-slate-500"
                                    >
                                        No clients found
                                    </td>
                                </tr>
                            )}

                            {filteredClients.map(
                                (client) => (
                                    <tr
                                        key={client.id}
                                        className="border-t hover:bg-slate-50"
                                    >
                                        <td className="px-5 py-4">
                                            <Link
                                                href={route(
                                                    'clients.show',
                                                    client.id,
                                                )}
                                                className="font-bold text-cyan-700 hover:underline"
                                            >
                                                {
                                                    client.name
                                                }
                                            </Link>

                                            <div className="mt-1 font-mono text-xs text-slate-500">
                                                {
                                                    client.client_code
                                                }
                                            </div>

                                            <div className="mt-1 text-xs text-slate-400">
                                                {client
                                                    .router
                                                    ?.name ??
                                                    '-'}
                                            </div>
                                        </td>

                                        <td className="whitespace-nowrap px-5 py-4 font-mono text-sm">
                                            {
                                                client.ip_address
                                            }
                                        </td>

                                        <td className="px-5 py-4">
                                            <div className="font-semibold text-slate-800">
                                                {client
                                                    .package
                                                    ?.name ??
                                                    '-'}
                                            </div>

                                            <div className="mt-1 text-sm text-slate-500">
                                                QAR{' '}
                                                {formatMoney(
                                                    client
                                                        .package
                                                        ?.price,
                                                )}
                                            </div>

                                            <div className="text-xs text-slate-400">
                                                {client
                                                    .package
                                                    ?.validity_days ??
                                                    0}{' '}
                                                days
                                            </div>
                                        </td>

                                        <td className="whitespace-nowrap px-5 py-4">
                                            {formatDate(
                                                client.expiry_date,
                                            )}
                                        </td>

                                        <td className="whitespace-nowrap px-5 py-4">
                                            {hasDue(
                                                client,
                                            ) ? (
                                                <div>
                                                    <span className="inline-flex rounded-full bg-red-100 px-3 py-1 text-sm font-bold text-red-700">
                                                        QAR{' '}
                                                        {formatMoney(
                                                            clientDue(
                                                                client,
                                                            ),
                                                        )}
                                                    </span>

                                                    <p className="mt-1 text-xs font-semibold text-red-500">
                                                        Payment due
                                                    </p>
                                                </div>
                                            ) : (
                                                <span className="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-700">
                                                    QAR 0.00
                                                </span>
                                            )}
                                        </td>

                                        <td className="px-5 py-4">
                                            <AccountBadge
                                                enabled={
                                                    client.enabled
                                                }
                                            />
                                        </td>

                                        <td className="px-5 py-4">
                                            <ConnectionBadge
                                                connected={
                                                    client.connected
                                                }
                                            />
                                        </td>

                                        <td className="px-5 py-4">
                                            <div className="flex min-w-[340px] flex-wrap gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        openPaymentModal(
                                                            client,
                                                        )
                                                    }
                                                    className={`rounded-lg px-3 py-2 text-sm font-bold text-white ${
                                                        hasDue(
                                                            client,
                                                        )
                                                            ? 'bg-orange-600 hover:bg-orange-700'
                                                            : 'bg-cyan-600 hover:bg-cyan-700'
                                                    }`}
                                                >
                                                    {hasDue(
                                                        client,
                                                    )
                                                        ? `Pay Due — QAR ${formatMoney(
                                                              clientDue(
                                                                  client,
                                                              ),
                                                          )}`
                                                        : 'Pay & Renew'}
                                                </button>

                                                <Link
                                                    href={route(
                                                        'clients.edit',
                                                        client.id,
                                                    )}
                                                    className="rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-600"
                                                >
                                                    Edit
                                                </Link>

                                                {client.enabled ? (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            suspendClient(
                                                                client.id,
                                                            )
                                                        }
                                                        className="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700"
                                                    >
                                                        Suspend
                                                    </button>
                                                ) : (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            activateClient(
                                                                client.id,
                                                            )
                                                        }
                                                        className="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                                                    >
                                                        Activate
                                                    </button>
                                                )}

                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        archiveClient(
                                                            client.id,
                                                        )
                                                    }
                                                    className="rounded-lg bg-slate-700 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-800"
                                                >
                                                    Archive
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ),
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {selectedClient && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="max-h-[95vh] w-full max-w-xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                        <div className="flex items-center justify-between border-b px-6 py-5">
                            <div>
                                <h2 className="text-xl font-bold text-slate-800">
                                    {selectedHasDue
                                        ? 'Pay Previous Due'
                                        : 'Pay & Renew'}
                                </h2>

                                <p className="mt-1 text-sm text-slate-500">
                                    {
                                        selectedClient.client_code
                                    }{' '}
                                    —{' '}
                                    {
                                        selectedClient.name
                                    }
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={
                                    closePaymentModal
                                }
                                className="rounded-lg px-3 py-2 text-xl text-slate-500 hover:bg-slate-100"
                            >
                                ×
                            </button>
                        </div>

                        <form
                            onSubmit={submitPayment}
                            className="space-y-5 p-6"
                        >
                            <div
                                className={`rounded-xl border p-4 ${
                                    selectedHasDue
                                        ? 'border-orange-200 bg-orange-50'
                                        : 'border-cyan-200 bg-cyan-50'
                                }`}
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Summary
                                        label={
                                            selectedHasDue
                                                ? 'Previous Due'
                                                : 'Package Bill'
                                        }
                                        value={`QAR ${formatMoney(
                                            payableAmount,
                                        )}`}
                                    />

                                    <Summary
                                        label="Received Now"
                                        value={`QAR ${formatMoney(
                                            receivedAmount,
                                        )}`}
                                    />

                                    <Summary
                                        label="Remaining Due"
                                        value={`QAR ${formatMoney(
                                            remainingAmount,
                                        )}`}
                                        danger={
                                            remainingAmount >
                                            0
                                        }
                                    />

                                    <Summary
                                        label={
                                            selectedHasDue
                                                ? 'Expiry Change'
                                                : 'Validity'
                                        }
                                        value={
                                            selectedHasDue
                                                ? 'No change'
                                                : `${
                                                      selectedClient
                                                          .package
                                                          ?.validity_days ??
                                                      0
                                                  } days`
                                        }
                                    />
                                </div>

                                <p className="mt-4 text-sm leading-6 text-slate-600">
                                    {selectedHasDue
                                        ? 'This payment will clear the previous balance only. Client expiry will not be extended again.'
                                        : 'Client will renew and activate immediately. Any unpaid amount will become Due automatically.'}
                                </p>
                            </div>

                            {errors.renewal && (
                                <ErrorBox
                                    text={
                                        errors.renewal
                                    }
                                />
                            )}

                            <div>
                                <label className="mb-1 block font-semibold text-slate-700">
                                    Received Amount
                                    (QAR)
                                </label>

                                <input
                                    type="number"
                                    min={
                                        selectedHasDue
                                            ? '0.01'
                                            : '0'
                                    }
                                    max={
                                        payableAmount
                                    }
                                    step="0.01"
                                    value={
                                        data.received_amount
                                    }
                                    onChange={(event) =>
                                        setData(
                                            'received_amount',
                                            event
                                                .target
                                                .value,
                                        )
                                    }
                                    className="w-full rounded-xl border border-slate-300 px-4 py-3 text-lg font-bold"
                                    autoFocus
                                />

                                {errors.received_amount && (
                                    <p className="mt-1 text-sm font-semibold text-red-600">
                                        {
                                            errors.received_amount
                                        }
                                    </p>
                                )}

                                <div className="mt-3 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setData(
                                                'received_amount',
                                                payableAmount.toFixed(
                                                    2,
                                                ),
                                            )
                                        }
                                        className="rounded-lg bg-emerald-100 px-3 py-2 text-sm font-bold text-emerald-700 hover:bg-emerald-200"
                                    >
                                        Full Amount
                                    </button>

                                    {!selectedHasDue && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setData(
                                                    'received_amount',
                                                    '0.00',
                                                )
                                            }
                                            className="rounded-lg bg-orange-100 px-3 py-2 text-sm font-bold text-orange-700 hover:bg-orange-200"
                                        >
                                            Full Due / Credit
                                        </button>
                                    )}
                                </div>
                            </div>

                            <div>
                                <label className="mb-1 block font-semibold text-slate-700">
                                    Payment Method
                                </label>

                                <select
                                    value={
                                        data.payment_method
                                    }
                                    onChange={(event) =>
                                        setData(
                                            'payment_method',
                                            event.target
                                                .value,
                                        )
                                    }
                                    className="w-full rounded-xl border border-slate-300 px-4 py-3"
                                >
                                    {paymentMethods.map(
                                        (method) => (
                                            <option
                                                key={
                                                    method
                                                }
                                                value={
                                                    method
                                                }
                                            >
                                                {
                                                    method
                                                }
                                            </option>
                                        ),
                                    )}
                                </select>

                                {errors.payment_method && (
                                    <p className="mt-1 text-sm text-red-600">
                                        {
                                            errors.payment_method
                                        }
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="mb-1 block font-semibold text-slate-700">
                                    Transaction ID
                                    <span className="ml-1 text-sm font-normal text-slate-400">
                                        Optional
                                    </span>
                                </label>

                                <input
                                    type="text"
                                    value={
                                        data.transaction_id
                                    }
                                    onChange={(event) =>
                                        setData(
                                            'transaction_id',
                                            event.target
                                                .value,
                                        )
                                    }
                                    placeholder="Reference or transaction number"
                                    className="w-full rounded-xl border border-slate-300 px-4 py-3"
                                />
                            </div>

                            <div>
                                <label className="mb-1 block font-semibold text-slate-700">
                                    Notes
                                    <span className="ml-1 text-sm font-normal text-slate-400">
                                        Optional
                                    </span>
                                </label>

                                <textarea
                                    rows="2"
                                    value={data.notes}
                                    onChange={(event) =>
                                        setData(
                                            'notes',
                                            event.target
                                                .value,
                                        )
                                    }
                                    className="w-full rounded-xl border border-slate-300 px-4 py-3"
                                />
                            </div>

                            <div className="flex justify-end gap-3 border-t pt-5">
                                <button
                                    type="button"
                                    onClick={
                                        closePaymentModal
                                    }
                                    disabled={
                                        processing
                                    }
                                    className="rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="submit"
                                    disabled={
                                        processing
                                    }
                                    className={`rounded-xl px-5 py-3 font-bold text-white disabled:opacity-50 ${
                                        selectedHasDue
                                            ? 'bg-orange-600 hover:bg-orange-700'
                                            : 'bg-cyan-600 hover:bg-cyan-700'
                                    }`}
                                >
                                    {processing
                                        ? 'Processing...'
                                        : selectedHasDue
                                          ? 'Confirm Due Payment'
                                          : 'Confirm Payment & Renew'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

function TableHead({ children }) {
    return (
        <th className="whitespace-nowrap px-5 py-4 text-left text-sm font-semibold">
            {children}
        </th>
    );
}

function Summary({
    label,
    value,
    danger = false,
}) {
    return (
        <div>
            <p className="text-sm text-slate-500">
                {label}
            </p>

            <p
                className={`mt-1 text-lg font-bold ${
                    danger
                        ? 'text-red-600'
                        : 'text-slate-800'
                }`}
            >
                {value}
            </p>
        </div>
    );
}

function AccountBadge({ enabled }) {
    return (
        <span
            className={`rounded-full px-3 py-1 text-sm font-semibold ${
                enabled
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-red-100 text-red-700'
            }`}
        >
            {enabled ? 'Active' : 'Suspended'}
        </span>
    );
}

function ConnectionBadge({ connected }) {
    return (
        <span
            className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-sm font-semibold ${
                connected
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-slate-100 text-slate-600'
            }`}
        >
            <span
                className={`h-2 w-2 rounded-full ${
                    connected
                        ? 'bg-emerald-500'
                        : 'bg-slate-400'
                }`}
            />

            {connected ? 'Online' : 'Offline'}
        </span>
    );
}

function ErrorBox({ text }) {
    return (
        <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
            {text}
        </div>
    );
}

function numberValue(value) {
    const amount = Number(value);

    return Number.isFinite(amount)
        ? amount
        : 0;
}

function formatMoney(value) {
    return numberValue(value).toLocaleString(
        'en-US',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        },
    );
}

function formatDate(value) {
    return value
        ? String(value).slice(0, 10)
        : '-';
}
