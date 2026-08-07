import {
    Link,
    router,
    useForm,
} from '@inertiajs/react';
import {
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';

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

export default function ClientQuickDesk({
    quickDesk = {},
    flash = {},
}) {
    const clients = quickDesk.clients ?? [];
    const routers = quickDesk.routers ?? [];
    const packages = quickDesk.packages ?? [];
    const ipRanges = quickDesk.ip_ranges ?? [];
    const permissions = quickDesk.permissions ?? {};

    const searchInput = useRef(null);
    const [search, setSearch] = useState('');
    const [showResults, setShowResults] = useState(false);
    const [selectedClient, setSelectedClient] = useState(null);
    const [modalMode, setModalMode] = useState('details');
    const [actionBusy, setActionBusy] = useState(false);

    const paymentForm = useForm({
        received_amount: '',
        payment_method: 'Cash',
        transaction_id: '',
        notes: '',
    });

    const editForm = useForm({
        router_id: '',
        ip_range_id: '',
        package_id: '',
        name: '',
        mac_address: '',
        phone: '',
    });

    const visibleClients = useMemo(() => {
        const term = search.trim().toLowerCase();

        const rows = term
            ? clients.filter((client) => {
                  const searchable = [
                      client.name,
                      client.client_code,
                      client.phone,
                      client.email,
                      client.address,
                      client.ip_address,
                      client.mac_address,
                      client.router?.name,
                      client.package?.name,
                      client.ip_range?.name,
                  ]
                      .filter(Boolean)
                      .join(' ')
                      .toLowerCase();

                  return searchable.includes(term);
              })
            : clients;

        return rows.slice(0, 12);
    }, [clients, search]);

    const selectedHasDue =
        numberValue(selectedClient?.total_due) > 0;

    const payableAmount = selectedClient
        ? selectedHasDue
            ? numberValue(selectedClient.total_due)
            : numberValue(selectedClient.package?.price)
        : 0;

    const receivedAmount = numberValue(
        paymentForm.data.received_amount,
    );

    const remainingAmount = Math.max(
        0,
        payableAmount - receivedAmount,
    );

    const filteredIpRanges = useMemo(() => {
        if (!editForm.data.router_id) {
            return [];
        }

        return ipRanges.filter(
            (range) =>
                String(range.router_id) ===
                String(editForm.data.router_id),
        );
    }, [editForm.data.router_id, ipRanges]);

    useEffect(() => {
        if (!selectedClient) {
            return;
        }

        const freshClient = clients.find(
            (client) =>
                Number(client.id) ===
                Number(selectedClient.id),
        );

        if (freshClient) {
            setSelectedClient(freshClient);
        }
    }, [clients, selectedClient?.id]);

    useEffect(() => {
        const handleShortcut = (event) => {
            const key = event.key.toLowerCase();

            if (
                (event.ctrlKey || event.metaKey)
                && key === 'k'
            ) {
                event.preventDefault();
                searchInput.current?.focus();
                setShowResults(true);
                return;
            }

            if (event.key === 'Escape') {
                if (selectedClient) {
                    closeModal();
                } else {
                    setShowResults(false);
                }

                return;
            }

            if (!selectedClient || !event.altKey) {
                return;
            }

            if (key === 'r' && permissions.renew) {
                event.preventDefault();
                openRenewModal(selectedClient);
            }

            if (key === 'e' && permissions.edit) {
                event.preventDefault();
                openEditModal(selectedClient);
            }

            if (key === 's' && permissions.suspend) {
                event.preventDefault();
                toggleClientStatus(selectedClient);
            }
        };

        window.addEventListener('keydown', handleShortcut);

        return () => {
            window.removeEventListener(
                'keydown',
                handleShortcut,
            );
        };
    }, [selectedClient, permissions]);

    const openClient = (client) => {
        setSelectedClient(client);
        setModalMode('details');
        setShowResults(false);
    };

    const closeModal = () => {
        if (
            paymentForm.processing
            || editForm.processing
            || actionBusy
        ) {
            return;
        }

        setSelectedClient(null);
        setModalMode('details');
        paymentForm.clearErrors();
        editForm.clearErrors();
    };

    const openRenewModal = (client) => {
        if (!permissions.renew) {
            return;
        }

        const due = numberValue(client.total_due);
        const amount = due > 0
            ? due
            : numberValue(client.package?.price);

        paymentForm.clearErrors();
        paymentForm.setData({
            received_amount: amount.toFixed(2),
            payment_method: 'Cash',
            transaction_id: '',
            notes: '',
        });

        setSelectedClient(client);
        setModalMode('renew');
        setShowResults(false);
    };

    const submitPayment = (event) => {
        event.preventDefault();

        if (!selectedClient) {
            return;
        }

        paymentForm.post(
            route('clients.renew', selectedClient.id),
            {
                preserveScroll: true,
                onSuccess: () => {
                    paymentForm.reset();
                    paymentForm.clearErrors();
                    setModalMode('details');
                },
            },
        );
    };

    const openEditModal = (client) => {
        if (!permissions.edit) {
            return;
        }

        editForm.clearErrors();
        editForm.setData({
            router_id: client.router_id ?? '',
            ip_range_id: client.ip_range_id ?? '',
            package_id: client.package_id ?? '',
            name: client.name ?? '',
            mac_address: client.mac_address ?? '',
            phone: client.phone ?? '',
        });

        setSelectedClient(client);
        setModalMode('edit');
        setShowResults(false);
    };

    const submitEdit = (event) => {
        event.preventDefault();

        if (!selectedClient) {
            return;
        }

        editForm.put(
            route('clients.update', {
                client: selectedClient.id,
                from: 'dashboard',
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    editForm.clearErrors();
                    setModalMode('details');
                },
            },
        );
    };

    const changeRouter = (event) => {
        editForm.setData({
            ...editForm.data,
            router_id: event.target.value,
            ip_range_id: '',
        });
    };

    const toggleClientStatus = (client) => {
        if (!permissions.suspend || actionBusy) {
            return;
        }

        const action = client.enabled
            ? 'suspend'
            : 'activate';

        if (
            !confirm(
                `${action === 'suspend' ? 'Suspend' : 'Activate'} ${client.name}?`,
            )
        ) {
            return;
        }

        setActionBusy(true);

        router.post(
            route(
                client.enabled
                    ? 'clients.suspend'
                    : 'clients.unsuspend',
                client.id,
            ),
            {},
            {
                preserveScroll: true,
                onFinish: () => setActionBusy(false),
            },
        );
    };

    if (!quickDesk.enabled || !permissions.view) {
        return (
            <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 className="text-xl font-bold text-slate-900">
                    Client Quick Desk
                </h2>

                <p className="mt-2 text-sm text-slate-500">
                    Client search is hidden because this account does not have Client View permission.
                </p>
            </section>
        );
    }

    return (
        <>
            <section className="overflow-visible rounded-2xl border border-cyan-200 bg-slate-900 shadow-xl">
                <div className="grid gap-6 p-5 lg:grid-cols-[1fr_auto] lg:items-center lg:p-7">
                    <div>
                        <div className="flex flex-wrap items-center gap-3">
                            <span className="rounded-full bg-cyan-500/20 px-3 py-1 text-xs font-black uppercase tracking-wider text-cyan-300">
                                POS Style
                            </span>

                            <span className="text-xs font-semibold text-slate-400">
                                Ctrl + K Search
                            </span>

                            <span className="text-xs font-semibold text-slate-400">
                                Alt + R Renew
                            </span>

                            <span className="text-xs font-semibold text-slate-400">
                                Alt + E Edit
                            </span>

                            <span className="text-xs font-semibold text-slate-400">
                                Alt + S Status
                            </span>
                        </div>

                        <h2 className="mt-3 text-2xl font-black text-white">
                            Client Quick Desk
                        </h2>

                        <p className="mt-1 text-sm text-slate-400">
                            Search and manage a client without leaving the dashboard
                        </p>
                    </div>

                    {permissions.create && (
                        <Link
                            href={route('clients.create')}
                            className="rounded-xl bg-cyan-500 px-5 py-3 text-center font-black text-slate-950 hover:bg-cyan-400"
                        >
                            + Add Client
                        </Link>
                    )}
                </div>

                <div className="relative border-t border-slate-700 p-5 lg:p-7">
                    <div className="relative">
                        <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-4 text-xl text-slate-400">
                            ⌕
                        </span>

                        <input
                            ref={searchInput}
                            type="text"
                            value={search}
                            onFocus={() => setShowResults(true)}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                setShowResults(true);
                            }}
                            placeholder="Search name, client code, phone, IP, MAC, email, package or router..."
                            className="w-full rounded-2xl border border-slate-600 bg-white py-4 pl-12 pr-28 text-base font-semibold text-slate-900 outline-none ring-0 placeholder:font-normal placeholder:text-slate-400 focus:border-cyan-400"
                        />

                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                searchInput.current?.focus();
                            }}
                            className="absolute inset-y-0 right-2 my-2 rounded-xl bg-slate-100 px-4 text-sm font-bold text-slate-600 hover:bg-slate-200"
                        >
                            Clear
                        </button>
                    </div>

                    {showResults && (
                        <div className="absolute left-5 right-5 top-[88px] z-40 max-h-[430px] overflow-y-auto rounded-2xl border border-slate-200 bg-white shadow-2xl lg:left-7 lg:right-7 lg:top-[96px]">
                            <div className="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                                <p className="text-sm font-bold text-slate-700">
                                    {search.trim()
                                        ? `${visibleClients.length} matching clients`
                                        : 'Quick client list'}
                                </p>

                                <button
                                    type="button"
                                    onClick={() => setShowResults(false)}
                                    className="rounded-lg px-3 py-1 text-sm font-bold text-slate-500 hover:bg-slate-100"
                                >
                                    Close
                                </button>
                            </div>

                            {visibleClients.length === 0 ? (
                                <div className="px-6 py-12 text-center text-slate-500">
                                    No client found.
                                </div>
                            ) : (
                                <div className="divide-y divide-slate-100">
                                    {visibleClients.map((client) => (
                                        <button
                                            key={client.id}
                                            type="button"
                                            onClick={() => openClient(client)}
                                            className="grid w-full gap-3 px-5 py-4 text-left hover:bg-cyan-50 md:grid-cols-[1.3fr_1fr_1fr_auto] md:items-center"
                                        >
                                            <div>
                                                <p className="font-black text-slate-900">
                                                    {client.name}
                                                </p>

                                                <p className="mt-1 font-mono text-xs text-slate-500">
                                                    {client.client_code}
                                                </p>
                                            </div>

                                            <div className="text-sm">
                                                <p className="font-mono font-bold text-slate-700">
                                                    {client.ip_address || '-'}
                                                </p>

                                                <p className="mt-1 text-xs text-slate-500">
                                                    {client.phone || 'No phone'}
                                                </p>
                                            </div>

                                            <div className="text-sm">
                                                <p className="font-bold text-slate-700">
                                                    {client.package?.name || '-'}
                                                </p>

                                                <p className="mt-1 text-xs text-slate-500">
                                                    Expires {formatDate(client.expiry_date)}
                                                </p>
                                            </div>

                                            <div className="flex flex-wrap gap-2 md:justify-end">
                                                <AccountBadge enabled={client.enabled} />
                                                <ConnectionBadge connected={client.connected} />

                                                {numberValue(client.total_due) > 0 && (
                                                    <span className="rounded-full bg-orange-100 px-3 py-1 text-xs font-black text-orange-700">
                                                        Due QAR {money(client.total_due)}
                                                    </span>
                                                )}
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </section>

            {(flash?.success || flash?.error) && (
                <div
                    className={`rounded-xl border px-5 py-4 font-semibold ${
                        flash?.error
                            ? 'border-red-200 bg-red-50 text-red-700'
                            : 'border-emerald-200 bg-emerald-50 text-emerald-700'
                    }`}
                >
                    {flash?.error || flash?.success}
                </div>
            )}

            {selectedClient && (
                <div
                    className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/70 p-4"
                    onMouseDown={(event) => {
                        if (event.target === event.currentTarget) {
                            closeModal();
                        }
                    }}
                >
                    <div className="max-h-[95vh] w-full max-w-4xl overflow-y-auto rounded-3xl bg-white shadow-2xl">
                        <div className="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-slate-200 bg-white px-6 py-5">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <h2 className="text-2xl font-black text-slate-900">
                                        {selectedClient.name}
                                    </h2>

                                    <AccountBadge enabled={selectedClient.enabled} />
                                    <ConnectionBadge connected={selectedClient.connected} />
                                </div>

                                <p className="mt-1 font-mono text-sm text-slate-500">
                                    {selectedClient.client_code} · {selectedClient.ip_address || '-'}
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={closeModal}
                                className="rounded-xl px-4 py-2 text-2xl text-slate-500 hover:bg-slate-100"
                            >
                                ×
                            </button>
                        </div>

                        {modalMode === 'details' && (
                            <ClientDetails
                                client={selectedClient}
                                permissions={permissions}
                                actionBusy={actionBusy}
                                onRenew={() => openRenewModal(selectedClient)}
                                onEdit={() => openEditModal(selectedClient)}
                                onToggleStatus={() => toggleClientStatus(selectedClient)}
                            />
                        )}

                        {modalMode === 'renew' && (
                            <form
                                onSubmit={submitPayment}
                                className="space-y-5 p-6"
                            >
                                <div className={`rounded-2xl border p-5 ${
                                    selectedHasDue
                                        ? 'border-orange-200 bg-orange-50'
                                        : 'border-cyan-200 bg-cyan-50'
                                }`}>
                                    <h3 className="text-xl font-black text-slate-900">
                                        {selectedHasDue
                                            ? 'Pay Previous Due'
                                            : 'Pay & Renew'}
                                    </h3>

                                    <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <Summary
                                            label={selectedHasDue ? 'Previous Due' : 'Package Bill'}
                                            value={`QAR ${money(payableAmount)}`}
                                        />
                                        <Summary
                                            label="Received Now"
                                            value={`QAR ${money(receivedAmount)}`}
                                        />
                                        <Summary
                                            label="Remaining Due"
                                            value={`QAR ${money(remainingAmount)}`}
                                            danger={remainingAmount > 0}
                                        />
                                        <Summary
                                            label={selectedHasDue ? 'Expiry Change' : 'Validity'}
                                            value={selectedHasDue
                                                ? 'No change'
                                                : `${selectedClient.package?.validity_days ?? 0} days`}
                                        />
                                    </div>

                                    <p className="mt-4 text-sm leading-6 text-slate-600">
                                        {selectedHasDue
                                            ? 'This payment clears the old due only. Expiry will not extend again.'
                                            : 'The client renews and activates immediately. Any unpaid amount becomes due.'}
                                    </p>
                                </div>

                                {paymentForm.errors.renewal && (
                                    <ErrorBox text={paymentForm.errors.renewal} />
                                )}

                                <div className="grid gap-5 md:grid-cols-2">
                                    <Field
                                        label="Received Amount (QAR)"
                                        error={paymentForm.errors.received_amount}
                                    >
                                        <input
                                            type="number"
                                            min={selectedHasDue ? '0.01' : '0'}
                                            max={payableAmount}
                                            step="0.01"
                                            value={paymentForm.data.received_amount}
                                            onChange={(event) =>
                                                paymentForm.setData(
                                                    'received_amount',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full rounded-xl border border-slate-300 px-4 py-3 text-lg font-black"
                                            autoFocus
                                        />

                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    paymentForm.setData(
                                                        'received_amount',
                                                        payableAmount.toFixed(2),
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
                                                        paymentForm.setData(
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
                                    </Field>

                                    <Field
                                        label="Payment Method"
                                        error={paymentForm.errors.payment_method}
                                    >
                                        <select
                                            value={paymentForm.data.payment_method}
                                            onChange={(event) =>
                                                paymentForm.setData(
                                                    'payment_method',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                                        >
                                            {paymentMethods.map((method) => (
                                                <option key={method} value={method}>
                                                    {method}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Field label="Transaction ID">
                                        <input
                                            type="text"
                                            value={paymentForm.data.transaction_id}
                                            onChange={(event) =>
                                                paymentForm.setData(
                                                    'transaction_id',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                                            placeholder="Optional reference"
                                        />
                                    </Field>

                                    <Field label="Notes">
                                        <input
                                            type="text"
                                            value={paymentForm.data.notes}
                                            onChange={(event) =>
                                                paymentForm.setData(
                                                    'notes',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                                            placeholder="Optional note"
                                        />
                                    </Field>
                                </div>

                                <div className="flex justify-end gap-3 border-t border-slate-200 pt-5">
                                    <button
                                        type="button"
                                        onClick={() => setModalMode('details')}
                                        disabled={paymentForm.processing}
                                        className="rounded-xl border border-slate-300 px-5 py-3 font-bold text-slate-700 hover:bg-slate-50"
                                    >
                                        Back
                                    </button>

                                    <button
                                        type="submit"
                                        disabled={paymentForm.processing}
                                        className={`rounded-xl px-6 py-3 font-black text-white disabled:opacity-50 ${
                                            selectedHasDue
                                                ? 'bg-orange-600 hover:bg-orange-700'
                                                : 'bg-cyan-600 hover:bg-cyan-700'
                                        }`}
                                    >
                                        {paymentForm.processing
                                            ? 'Processing...'
                                            : selectedHasDue
                                              ? 'Confirm Due Payment'
                                              : 'Confirm Payment & Renew'}
                                    </button>
                                </div>
                            </form>
                        )}

                        {modalMode === 'edit' && (
                            <form
                                onSubmit={submitEdit}
                                className="space-y-6 p-6"
                            >
                                <div>
                                    <h3 className="text-xl font-black text-slate-900">
                                        Quick Edit Client
                                    </h3>

                                    <p className="mt-1 text-sm text-slate-500">
                                        Package or MAC changes also update MikroTik DHCP, ARP and Simple Queue.
                                    </p>
                                </div>

                                <div className="grid gap-5 md:grid-cols-2">
                                    <Field
                                        label="Client Name"
                                        error={editForm.errors.name}
                                    >
                                        <input
                                            type="text"
                                            value={editForm.data.name}
                                            onChange={(event) =>
                                                editForm.setData('name', event.target.value)
                                            }
                                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                                            autoFocus
                                        />
                                    </Field>

                                    <Field
                                        label="Phone"
                                        error={editForm.errors.phone}
                                    >
                                        <input
                                            type="text"
                                            value={editForm.data.phone}
                                            onChange={(event) =>
                                                editForm.setData('phone', event.target.value)
                                            }
                                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                                        />
                                    </Field>

                                    <Field
                                        label="Router"
                                        error={editForm.errors.router_id}
                                    >
                                        <select
                                            value={editForm.data.router_id}
                                            onChange={changeRouter}
                                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                                        >
                                            <option value="">Select Router</option>
                                            {routers.map((item) => (
                                                <option key={item.id} value={item.id}>
                                                    {item.name}{item.enabled ? '' : ' (Disabled)'}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Field
                                        label="IP Pool"
                                        error={editForm.errors.ip_range_id}
                                    >
                                        <select
                                            value={editForm.data.ip_range_id}
                                            onChange={(event) =>
                                                editForm.setData(
                                                    'ip_range_id',
                                                    event.target.value,
                                                )
                                            }
                                            disabled={!editForm.data.router_id}
                                            className="w-full rounded-xl border border-slate-300 px-4 py-3 disabled:bg-slate-100"
                                        >
                                            <option value="">Select IP Pool</option>
                                            {filteredIpRanges.map((item) => (
                                                <option key={item.id} value={item.id}>
                                                    {item.name}{item.enabled ? '' : ' (Disabled)'}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Field
                                        label="Package"
                                        error={editForm.errors.package_id}
                                    >
                                        <select
                                            value={editForm.data.package_id}
                                            onChange={(event) =>
                                                editForm.setData(
                                                    'package_id',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                                        >
                                            <option value="">Select Package</option>
                                            {packages.map((item) => (
                                                <option key={item.id} value={item.id}>
                                                    {item.name} — QAR {money(item.price)}
                                                    {item.enabled ? '' : ' (Disabled)'}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Field
                                        label="MAC Address"
                                        error={editForm.errors.mac_address}
                                    >
                                        <input
                                            type="text"
                                            value={editForm.data.mac_address}
                                            onChange={(event) =>
                                                editForm.setData(
                                                    'mac_address',
                                                    event.target.value.toUpperCase(),
                                                )
                                            }
                                            className="w-full rounded-xl border border-slate-300 px-4 py-3 font-mono"
                                            placeholder="AA:BB:CC:DD:EE:FF"
                                        />
                                    </Field>
                                </div>

                                <div className="rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                                    Current IP: <span className="font-mono font-bold text-slate-900">{selectedClient.ip_address || '-'}</span>. IP address, installation date, billing day and expiry remain unchanged.
                                </div>

                                <div className="flex justify-end gap-3 border-t border-slate-200 pt-5">
                                    <button
                                        type="button"
                                        onClick={() => setModalMode('details')}
                                        disabled={editForm.processing}
                                        className="rounded-xl border border-slate-300 px-5 py-3 font-bold text-slate-700 hover:bg-slate-50"
                                    >
                                        Back
                                    </button>

                                    <button
                                        type="submit"
                                        disabled={editForm.processing}
                                        className="rounded-xl bg-amber-500 px-6 py-3 font-black text-white hover:bg-amber-600 disabled:opacity-50"
                                    >
                                        {editForm.processing
                                            ? 'Updating...'
                                            : 'Update Client'}
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}

function ClientDetails({
    client,
    permissions,
    actionBusy,
    onRenew,
    onEdit,
    onToggleStatus,
}) {
    return (
        <div className="space-y-6 p-6">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <InfoCard
                    label="Total Due"
                    value={`QAR ${money(client.total_due)}`}
                    danger={numberValue(client.total_due) > 0}
                />
                <InfoCard
                    label="Package Bill"
                    value={`QAR ${money(client.package?.price)}`}
                />
                <InfoCard
                    label="Expiry Date"
                    value={formatDate(client.expiry_date)}
                />
                <InfoCard
                    label="Validity"
                    value={`${client.package?.validity_days ?? 0} days`}
                />
            </div>

            <div className="grid gap-5 rounded-2xl border border-slate-200 p-5 md:grid-cols-2 lg:grid-cols-3">
                <Info label="Phone" value={client.phone} />
                <Info label="Email" value={client.email} />
                <Info label="Address" value={client.address} />
                <Info label="Router" value={client.router?.name} />
                <Info label="IP Pool" value={client.ip_range?.name} />
                <Info label="Package" value={client.package?.name} />
                <Info label="IP Address" value={client.ip_address} mono />
                <Info label="MAC Address" value={client.mac_address} mono />
                <Info label="Installed" value={formatDate(client.installed_at)} />
                <Info label="Billing Day" value={client.billing_day} />
                <Info
                    label="Speed"
                    value={`${client.package?.speed_download || '-'} down / ${client.package?.speed_upload || '-'} up`}
                />
            </div>

            <div className="flex flex-wrap gap-3 border-t border-slate-200 pt-5">
                {permissions.renew && (
                    <button
                        type="button"
                        onClick={onRenew}
                        className={`rounded-xl px-5 py-3 font-black text-white ${
                            numberValue(client.total_due) > 0
                                ? 'bg-orange-600 hover:bg-orange-700'
                                : 'bg-cyan-600 hover:bg-cyan-700'
                        }`}
                    >
                        {numberValue(client.total_due) > 0
                            ? `Pay Due — QAR ${money(client.total_due)}`
                            : 'Pay & Renew'}
                    </button>
                )}

                {permissions.edit && (
                    <button
                        type="button"
                        onClick={onEdit}
                        className="rounded-xl bg-amber-500 px-5 py-3 font-black text-white hover:bg-amber-600"
                    >
                        Edit Client
                    </button>
                )}

                {permissions.suspend && (
                    <button
                        type="button"
                        onClick={onToggleStatus}
                        disabled={actionBusy}
                        className={`rounded-xl px-5 py-3 font-black text-white disabled:opacity-50 ${
                            client.enabled
                                ? 'bg-red-600 hover:bg-red-700'
                                : 'bg-emerald-600 hover:bg-emerald-700'
                        }`}
                    >
                        {actionBusy
                            ? 'Processing...'
                            : client.enabled
                              ? 'Suspend Client'
                              : 'Activate Client'}
                    </button>
                )}

                <Link
                    href={route('clients.show', client.id)}
                    className="rounded-xl bg-slate-800 px-5 py-3 font-black text-white hover:bg-slate-900"
                >
                    Full Details
                </Link>

                {permissions.invoice_export && (
                    <>
                        <a
                            href={route('clients.invoices.print', client.id)}
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-xl border border-slate-300 bg-white px-5 py-3 font-black text-slate-700 hover:bg-slate-50"
                        >
                            Print Invoices
                        </a>

                        <a
                            href={route('clients.invoices.download', client.id)}
                            className="rounded-xl border border-slate-300 bg-white px-5 py-3 font-black text-slate-700 hover:bg-slate-50"
                        >
                            Download PDF
                        </a>
                    </>
                )}
            </div>
        </div>
    );
}

function Field({ label, error, children }) {
    return (
        <div>
            <label className="mb-1.5 block font-bold text-slate-700">
                {label}
            </label>

            {children}

            {error && (
                <p className="mt-1 text-sm font-semibold text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}

function Info({ label, value, mono = false }) {
    return (
        <div>
            <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                {label}
            </p>

            <p className={`mt-1 break-words font-bold text-slate-800 ${mono ? 'font-mono' : ''}`}>
                {value || '-'}
            </p>
        </div>
    );
}

function InfoCard({ label, value, danger = false }) {
    return (
        <div className={`rounded-2xl border p-4 ${
            danger
                ? 'border-red-200 bg-red-50'
                : 'border-slate-200 bg-slate-50'
        }`}>
            <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                {label}
            </p>

            <p className={`mt-2 text-xl font-black ${
                danger ? 'text-red-700' : 'text-slate-900'
            }`}>
                {value}
            </p>
        </div>
    );
}

function Summary({ label, value, danger = false }) {
    return (
        <div>
            <p className="text-sm text-slate-500">
                {label}
            </p>

            <p className={`mt-1 text-lg font-black ${
                danger ? 'text-red-600' : 'text-slate-900'
            }`}>
                {value}
            </p>
        </div>
    );
}

function AccountBadge({ enabled }) {
    return (
        <span className={`inline-flex rounded-full px-3 py-1 text-xs font-black ${
            enabled
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-red-100 text-red-700'
        }`}>
            {enabled ? 'Active' : 'Suspended'}
        </span>
    );
}

function ConnectionBadge({ connected }) {
    return (
        <span className={`inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-black ${
            connected
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-slate-100 text-slate-600'
        }`}>
            <span className={`h-2 w-2 rounded-full ${
                connected ? 'bg-emerald-500' : 'bg-slate-400'
            }`} />
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
    const number = Number(value);

    return Number.isFinite(number)
        ? number
        : 0;
}

function money(value) {
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

