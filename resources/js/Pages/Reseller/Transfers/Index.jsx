import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
    useForm,
    usePage,
} from '@inertiajs/react';
import {
    useMemo,
    useState,
} from 'react';

export default function Index({
    transfers = [],
    transferStats = {},
    clients = [],
    zones = [],
    ipRanges = [],
    currentZoneId = null,
    isOwner = false,
    isManager = false,
}) {
    const page =
        usePage();

    const flash =
        page.props.flash ?? {};

    const [
        activeTab,
        setActiveTab,
    ] = useState(
        isOwner
            ? 'pending'
            : 'incoming',
    );

    const [
        clientSearch,
        setClientSearch,
    ] = useState('');

    const [
        transferSearch,
        setTransferSearch,
    ] = useState('');

    const [
        rangeSelections,
        setRangeSelections,
    ] = useState({});

    const requestForm =
        useForm({
            client_id: '',
            target_zone_id: '',
            request_note: '',
        });

    const selectedClient =
        useMemo(
            () =>
                clients.find(
                    (client) =>
                        String(client.id)
                        === String(
                            requestForm
                                .data
                                .client_id,
                        ),
                ) ?? null,
            [
                clients,
                requestForm
                    .data
                    .client_id,
            ],
        );

    const filteredClients =
        useMemo(() => {
            const term =
                clientSearch
                    .trim()
                    .toLowerCase();

            if (!term) {
                return clients;
            }

            return clients.filter(
                (client) =>
                    [
                        client.client_code,
                        client.name,
                        client.phone,
                        client.mac_address,
                        client.zone_name,
                    ]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase()
                        .includes(term),
            );
        }, [
            clients,
            clientSearch,
        ]);

    const targetZones =
        useMemo(
            () =>
                zones.filter(
                    (zone) =>
                        !selectedClient
                        || String(
                            zone.id,
                        )
                            !== String(
                                selectedClient
                                    .zone_id,
                            ),
                ),
            [
                zones,
                selectedClient,
            ],
        );

    const visibleTransfers =
        useMemo(() => {
            let rows =
                [...transfers];

            if (
                activeTab ===
                'incoming'
            ) {
                rows =
                    rows.filter(
                        (item) =>
                            item.status
                                === 'pending'
                            && String(
                                item.target_zone_id,
                            )
                                === String(
                                    currentZoneId,
                                ),
                    );
            }

            if (
                activeTab ===
                'outgoing'
            ) {
                rows =
                    rows.filter(
                        (item) =>
                            item.status
                                === 'pending'
                            && String(
                                item.source_zone_id,
                            )
                                === String(
                                    currentZoneId,
                                ),
                    );
            }

            if (
                activeTab ===
                'pending'
            ) {
                rows =
                    rows.filter(
                        (item) =>
                            item.status
                                === 'pending',
                    );
            }

            if (
                activeTab ===
                'history'
            ) {
                rows =
                    rows.filter(
                        (item) =>
                            item.status
                                !== 'pending',
                    );
            }

            const term =
                transferSearch
                    .trim()
                    .toLowerCase();

            if (!term) {
                return rows;
            }

            return rows.filter(
                (item) =>
                    [
                        item.client_code,
                        item.client_name,
                        item.phone,
                        item.mac_address,
                        item.source_zone_name,
                        item.target_zone_name,
                        item.status,
                        item.network_status,
                        item.source_ip_address,
                        item.target_ip_address,
                    ]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase()
                        .includes(term),
            );
        }, [
            transfers,
            activeTab,
            currentZoneId,
            transferSearch,
        ]);

    const tabs =
        isOwner
            ? [
                {
                    key: 'pending',
                    label:
                        'Pending',
                    count:
                        transferStats
                            .pending ?? 0,
                },
                {
                    key: 'history',
                    label:
                        'History',
                    count:
                        transferStats
                            .history ?? 0,
                },
                {
                    key: 'all',
                    label:
                        'All',
                    count:
                        transferStats
                            .total ?? 0,
                },
            ]
            : [
                {
                    key: 'incoming',
                    label:
                        'Incoming',
                    count:
                        transferStats
                            .incoming ?? 0,
                },
                {
                    key: 'outgoing',
                    label:
                        'Outgoing',
                    count:
                        transferStats
                            .outgoing ?? 0,
                },
                {
                    key: 'history',
                    label:
                        'History',
                    count:
                        transferStats
                            .history ?? 0,
                },
                {
                    key: 'all',
                    label:
                        'All',
                    count:
                        transferStats
                            .total ?? 0,
                },
            ];

    const submitRequest = (
        event,
    ) => {
        event.preventDefault();

        requestForm.post(
            route(
                'reseller.transfers.store',
            ),
            {
                preserveScroll: true,

                onSuccess: () => {
                    requestForm.reset();

                    setClientSearch(
                        '',
                    );
                },
            },
        );
    };

    const rangesFor = (
        transfer,
    ) =>
        ipRanges.filter(
            (range) =>
                String(
                    range.zone_id,
                )
                === String(
                    transfer
                        .target_zone_id,
                ),
        );

    const selectedRange = (
        transfer,
    ) => {
        const ranges =
            rangesFor(
                transfer,
            );

        return (
            rangeSelections[
                transfer.id
            ]
            ?? ranges[0]?.id
            ?? ''
        );
    };

    const approve = (
        transfer,
    ) => {
        const ipRangeId =
            selectedRange(
                transfer,
            );

        if (!ipRangeId) {
            window.alert(
                'Destination zone has no enabled IP Pool.',
            );

            return;
        }

        if (
            !window.confirm(
                `Approve and move ${transfer.client_name} to ${transfer.target_zone_name}?`,
            )
        ) {
            return;
        }

        router.post(
            route(
                'reseller.transfers.approve',
                transfer.id,
            ),
            {
                ip_range_id:
                    ipRangeId,
            },
            {
                preserveScroll:
                    true,
            },
        );
    };

    const reject = (
        transfer,
    ) => {
        if (
            !window.confirm(
                `Reject transfer request for ${transfer.client_name}?`,
            )
        ) {
            return;
        }

        router.post(
            route(
                'reseller.transfers.reject',
                transfer.id,
            ),
            {},
            {
                preserveScroll:
                    true,
            },
        );
    };

    const cancel = (
        transfer,
    ) => {
        if (
            !window.confirm(
                `Cancel transfer request for ${transfer.client_name}?`,
            )
        ) {
            return;
        }

        router.post(
            route(
                'reseller.transfers.cancel',
                transfer.id,
            ),
            {},
            {
                preserveScroll:
                    true,
            },
        );
    };

    const incoming =
        Number(
            transferStats
                .incoming ?? 0,
        );

    const pending =
        Number(
            transferStats
                .pending ?? 0,
        );

    return (
        <AppLayout title="Client Transfers">
            <Head title="Client Transfers" />

            <div className="space-y-6">
                <section className="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-cyan-950 p-6 text-white shadow-xl">
                    <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                        <div>
                            <div className="mb-2 inline-flex rounded-full bg-white/10 px-3 py-1 text-xs font-black uppercase tracking-widest text-cyan-200">
                                Zone Mobility
                            </div>

                            <h1 className="text-2xl font-black md:text-3xl">
                                Client Zone Transfer
                            </h1>

                            <p className="mt-2 max-w-3xl text-sm leading-6 text-slate-300">
                                Transfer a client
                                between MAC zones
                                without losing
                                accounting history.
                                Existing service-period
                                billing remains in the
                                source zone. New
                                renewals belong to the
                                destination zone.
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {route().has(
                                'reseller.notifications.index',
                            ) && (
                                <Link
                                    href={route(
                                        'reseller.notifications.index',
                                    )}
                                    className="rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-black hover:bg-white/20"
                                >
                                    Notifications
                                </Link>
                            )}

                            {!isOwner && (
                                <div className="rounded-xl bg-cyan-500/20 px-4 py-2 text-sm font-black text-cyan-100 ring-1 ring-cyan-400/30">
                                    Active Zone #
                                    {currentZoneId ??
                                        '-'}
                                    {isManager
                                        ? ' · Manager'
                                        : ' · Operator'}
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                {flash.success && (
                    <Alert
                        type="success"
                    >
                        {flash.success}
                    </Alert>
                )}

                {flash.error && (
                    <Alert type="error">
                        {flash.error}
                    </Alert>
                )}

                {!isOwner
                    && incoming > 0 && (
                    <Alert type="warning">
                        You have{' '}
                        <strong>
                            {incoming}
                        </strong>{' '}
                        incoming client
                        transfer
                        {incoming === 1
                            ? ''
                            : 's'}{' '}
                        waiting for
                        approval.
                    </Alert>
                )}

                {isOwner
                    && pending > 0 && (
                    <Alert type="warning">
                        Company-wide pending
                        transfers:{' '}
                        <strong>
                            {pending}
                        </strong>
                    </Alert>
                )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label={
                            isOwner
                                ? 'Pending'
                                : 'Incoming'
                        }
                        value={
                            isOwner
                                ? transferStats
                                    .pending
                                : transferStats
                                    .incoming
                        }
                        helper={
                            isOwner
                                ? 'Awaiting action'
                                : 'Needs your approval'
                        }
                    />

                    <StatCard
                        label={
                            isOwner
                                ? 'Approved'
                                : 'Outgoing'
                        }
                        value={
                            isOwner
                                ? transferStats
                                    .approved
                                : transferStats
                                    .outgoing
                        }
                        helper={
                            isOwner
                                ? 'Completed transfers'
                                : 'Sent by your zone'
                        }
                    />

                    <StatCard
                        label="History"
                        value={
                            transferStats
                                .history
                        }
                        helper="Completed / closed"
                    />

                    <StatCard
                        label="Total"
                        value={
                            transferStats
                                .total
                        }
                        helper="Transfer records"
                    />
                </section>

                <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm md:p-6">
                    <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 className="text-xl font-black text-slate-900">
                                New Transfer
                                Request
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Select a client
                                from your current
                                zone and send the
                                request to another
                                MAC zone.
                            </p>
                        </div>

                        <div className="rounded-xl bg-slate-100 px-3 py-2 text-xs font-bold text-slate-600">
                            Primary + extra
                            devices move
                            together
                        </div>
                    </div>

                    <form
                        onSubmit={
                            submitRequest
                        }
                        className="mt-6 space-y-4"
                    >
                        <div className="grid gap-4 xl:grid-cols-2">
                            <Field label="Search Client">
                                <input
                                    type="text"
                                    value={
                                        clientSearch
                                    }
                                    onChange={(
                                        event,
                                    ) =>
                                        setClientSearch(
                                            event
                                                .target
                                                .value,
                                        )
                                    }
                                    placeholder="Name, code, phone, MAC or zone"
                                    className="w-full rounded-xl border-slate-300"
                                />
                            </Field>

                            <Field
                                label="Client"
                                error={
                                    requestForm
                                        .errors
                                        .client_id
                                }
                            >
                                <select
                                    value={
                                        requestForm
                                            .data
                                            .client_id
                                    }
                                    onChange={(
                                        event,
                                    ) => {
                                        requestForm.setData(
                                            {
                                                ...requestForm.data,
                                                client_id:
                                                    event
                                                        .target
                                                        .value,
                                                target_zone_id:
                                                    '',
                                            },
                                        );
                                    }}
                                    className="w-full rounded-xl border-slate-300"
                                >
                                    <option value="">
                                        Select client
                                    </option>

                                    {filteredClients.map(
                                        (
                                            client,
                                        ) => (
                                            <option
                                                key={
                                                    client.id
                                                }
                                                value={
                                                    client.id
                                                }
                                            >
                                                {
                                                    client.client_code
                                                }{' '}
                                                ·{' '}
                                                {
                                                    client.name
                                                }{' '}
                                                ·{' '}
                                                {
                                                    client.zone_name
                                                }{' '}
                                                ·{' '}
                                                {
                                                    client.device_count
                                                }{' '}
                                                device(s)
                                            </option>
                                        ),
                                    )}
                                </select>

                                <div className="mt-1 text-xs text-slate-500">
                                    {
                                        filteredClients.length
                                    }{' '}
                                    client(s)
                                    available
                                </div>
                            </Field>
                        </div>

                        {selectedClient && (
                            <div className="grid gap-3 rounded-2xl border border-cyan-100 bg-cyan-50/70 p-4 sm:grid-cols-2 lg:grid-cols-4">
                                <Info
                                    label="Client"
                                    value={
                                        selectedClient
                                            .name
                                    }
                                />

                                <Info
                                    label="Code"
                                    value={
                                        selectedClient
                                            .client_code
                                    }
                                />

                                <Info
                                    label="Current Zone"
                                    value={
                                        selectedClient
                                            .zone_name
                                    }
                                />

                                <Info
                                    label="Devices"
                                    value={
                                        selectedClient
                                            .device_count
                                    }
                                />
                            </div>
                        )}

                        <div className="grid gap-4 xl:grid-cols-2">
                            <Field
                                label="Destination Zone"
                                error={
                                    requestForm
                                        .errors
                                        .target_zone_id
                                }
                            >
                                <select
                                    value={
                                        requestForm
                                            .data
                                            .target_zone_id
                                    }
                                    onChange={(
                                        event,
                                    ) =>
                                        requestForm.setData(
                                            'target_zone_id',
                                            event
                                                .target
                                                .value,
                                        )
                                    }
                                    disabled={
                                        !selectedClient
                                    }
                                    className="w-full rounded-xl border-slate-300 disabled:bg-slate-100"
                                >
                                    <option value="">
                                        Select destination
                                        zone
                                    </option>

                                    {targetZones.map(
                                        (
                                            zone,
                                        ) => (
                                            <option
                                                key={
                                                    zone.id
                                                }
                                                value={
                                                    zone.id
                                                }
                                            >
                                                {
                                                    zone.name
                                                }
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>

                            <Field label="Transfer Note">
                                <input
                                    type="text"
                                    value={
                                        requestForm
                                            .data
                                            .request_note
                                    }
                                    onChange={(
                                        event,
                                    ) =>
                                        requestForm.setData(
                                            'request_note',
                                            event
                                                .target
                                                .value,
                                        )
                                    }
                                    placeholder="Optional note"
                                    className="w-full rounded-xl border-slate-300"
                                />
                            </Field>
                        </div>

                        <div className="flex justify-end">
                            <button
                                type="submit"
                                disabled={
                                    requestForm.processing
                                    || !requestForm
                                        .data
                                        .client_id
                                    || !requestForm
                                        .data
                                        .target_zone_id
                                }
                                className="rounded-xl bg-cyan-600 px-6 py-3 text-sm font-black text-white shadow-sm hover:bg-cyan-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                            >
                                {requestForm.processing
                                    ? 'SENDING...'
                                    : 'SEND TRANSFER REQUEST'}
                            </button>
                        </div>
                    </form>
                </section>

                <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 p-5 md:p-6">
                        <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                            <div>
                                <h2 className="text-xl font-black text-slate-900">
                                    Transfer Desk
                                </h2>

                                <p className="mt-1 text-sm text-slate-500">
                                    Incoming,
                                    outgoing and
                                    historical client
                                    transfers.
                                </p>
                            </div>

                            <input
                                type="text"
                                value={
                                    transferSearch
                                }
                                onChange={(
                                    event,
                                ) =>
                                    setTransferSearch(
                                        event
                                            .target
                                            .value,
                                    )
                                }
                                placeholder="Search name, code, MAC, IP or zone..."
                                className="w-full rounded-xl border-slate-300 xl:w-96"
                            />
                        </div>

                        <div className="mt-5 flex gap-2 overflow-x-auto pb-1">
                            {tabs.map(
                                (tab) => (
                                    <button
                                        key={
                                            tab.key
                                        }
                                        type="button"
                                        onClick={() =>
                                            setActiveTab(
                                                tab.key,
                                            )
                                        }
                                        className={`inline-flex items-center gap-2 whitespace-nowrap rounded-xl px-4 py-2 text-sm font-black transition ${
                                            activeTab
                                            === tab.key
                                                ? 'bg-slate-900 text-white'
                                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                        }`}
                                    >
                                        {
                                            tab.label
                                        }

                                        <span
                                            className={`min-w-6 rounded-full px-2 py-0.5 text-center text-xs ${
                                                activeTab
                                                === tab.key
                                                    ? 'bg-white/20 text-white'
                                                    : tab.count >
                                                        0
                                                      ? 'bg-cyan-600 text-white'
                                                      : 'bg-white text-slate-500'
                                            }`}
                                        >
                                            {
                                                tab.count
                                            }
                                        </span>
                                    </button>
                                ),
                            )}
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
                                        Route
                                    </Th>

                                    <Th>
                                        Billing
                                    </Th>

                                    <Th>
                                        Status
                                    </Th>

                                    <Th>
                                        Network
                                    </Th>

                                    <Th>
                                        Action
                                    </Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {visibleTransfers.length
                                === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={
                                                6
                                            }
                                            className="px-5 py-16 text-center"
                                        >
                                            <div className="text-3xl">
                                                ⇄
                                            </div>

                                            <div className="mt-3 font-black text-slate-700">
                                                No
                                                transfer
                                                records
                                                found
                                            </div>

                                            <div className="mt-1 text-sm text-slate-500">
                                                Try
                                                another
                                                tab or
                                                search
                                                term.
                                            </div>
                                        </td>
                                    </tr>
                                ) : (
                                    visibleTransfers.map(
                                        (
                                            transfer,
                                        ) => {
                                            const ranges =
                                                rangesFor(
                                                    transfer,
                                                );

                                            return (
                                                <tr
                                                    key={
                                                        transfer.id
                                                    }
                                                    className="align-top hover:bg-slate-50/70"
                                                >
                                                    <Td>
                                                        <div className="font-black text-slate-900">
                                                            {
                                                                transfer.client_name
                                                            }
                                                        </div>

                                                        <div className="mt-1 text-xs font-semibold text-slate-500">
                                                            {
                                                                transfer.client_code
                                                            }
                                                        </div>

                                                        <div className="mt-1 text-xs text-slate-500">
                                                            {
                                                                transfer.mac_address
                                                            }
                                                        </div>

                                                        <div className="mt-1 inline-flex rounded-lg bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600">
                                                            {
                                                                transfer.device_count
                                                            }{' '}
                                                            device(s)
                                                        </div>
                                                    </Td>

                                                    <Td>
                                                        <div className="flex min-w-48 items-center gap-2 font-black text-slate-800">
                                                            <span className="rounded-lg bg-slate-100 px-2 py-1">
                                                                {
                                                                    transfer.source_zone_name
                                                                }
                                                            </span>

                                                            <span className="text-cyan-600">
                                                                →
                                                            </span>

                                                            <span className="rounded-lg bg-cyan-50 px-2 py-1 text-cyan-700">
                                                                {
                                                                    transfer.target_zone_name
                                                                }
                                                            </span>
                                                        </div>

                                                        <div className="mt-2 text-xs text-slate-500">
                                                            Old IP:{' '}
                                                            {
                                                                transfer.source_ip_address ??
                                                                '-'
                                                            }
                                                        </div>

                                                        {transfer.target_ip_address && (
                                                            <div className="mt-1 text-xs font-bold text-emerald-700">
                                                                New IP:{' '}
                                                                {
                                                                    transfer.target_ip_address
                                                                }
                                                            </div>
                                                        )}
                                                    </Td>

                                                    <Td>
                                                        <div className="min-w-48 rounded-xl bg-amber-50 p-3">
                                                            <div className="text-xs font-black uppercase tracking-wide text-amber-700">
                                                                Current
                                                                period
                                                            </div>

                                                            <div className="mt-1 text-sm font-bold text-slate-700">
                                                                {
                                                                    transfer.source_zone_name
                                                                }
                                                            </div>

                                                            <div className="mt-2 text-xs text-slate-500">
                                                                Ends:{' '}
                                                                {
                                                                    transfer.source_service_end_date ??
                                                                    '-'
                                                                }
                                                            </div>
                                                        </div>

                                                        <div className="mt-2 text-xs font-black text-cyan-700">
                                                            Next renewal
                                                            →{' '}
                                                            {
                                                                transfer.target_zone_name
                                                            }
                                                        </div>
                                                    </Td>

                                                    <Td>
                                                        <StatusBadge
                                                            value={
                                                                transfer.status
                                                            }
                                                        />

                                                        <div className="mt-2 text-xs text-slate-500">
                                                            Requested
                                                        </div>

                                                        <div className="text-xs font-semibold text-slate-600">
                                                            {
                                                                transfer.requested_at
                                                            }
                                                        </div>

                                                        {transfer.approved_at && (
                                                            <>
                                                                <div className="mt-2 text-xs text-slate-500">
                                                                    Approved
                                                                </div>

                                                                <div className="text-xs font-semibold text-slate-600">
                                                                    {
                                                                        transfer.approved_at
                                                                    }
                                                                </div>
                                                            </>
                                                        )}
                                                    </Td>

                                                    <Td>
                                                        <StatusBadge
                                                            value={
                                                                transfer.network_status
                                                            }
                                                        />

                                                        {transfer.network_error && (
                                                            <div className="mt-2 max-w-72 whitespace-normal rounded-lg bg-red-50 p-2 text-xs font-semibold text-red-700">
                                                                {
                                                                    transfer.network_error
                                                                }
                                                            </div>
                                                        )}
                                                    </Td>

                                                    <Td>
                                                        <div className="min-w-60 space-y-2">
                                                            {transfer.can_approve && (
                                                                <>
                                                                    <select
                                                                        value={
                                                                            selectedRange(
                                                                                transfer,
                                                                            )
                                                                        }
                                                                        onChange={(
                                                                            event,
                                                                        ) =>
                                                                            setRangeSelections(
                                                                                (
                                                                                    current,
                                                                                ) => ({
                                                                                    ...current,
                                                                                    [transfer.id]:
                                                                                        event
                                                                                            .target
                                                                                            .value,
                                                                                }),
                                                                            )
                                                                        }
                                                                        className="w-full rounded-lg border-slate-300 text-sm"
                                                                    >
                                                                        {ranges.length ===
                                                                        0 ? (
                                                                            <option value="">
                                                                                No destination IP Pool
                                                                            </option>
                                                                        ) : (
                                                                            ranges.map(
                                                                                (
                                                                                    range,
                                                                                ) => (
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
                                                                                        }{' '}
                                                                                        ·{' '}
                                                                                        {
                                                                                            range.start_ip
                                                                                        }
                                                                                        -
                                                                                        {
                                                                                            range.end_ip
                                                                                        }
                                                                                    </option>
                                                                                ),
                                                                            )
                                                                        )}
                                                                    </select>

                                                                    <button
                                                                        type="button"
                                                                        disabled={
                                                                            ranges.length ===
                                                                            0
                                                                        }
                                                                        onClick={() =>
                                                                            approve(
                                                                                transfer,
                                                                            )
                                                                        }
                                                                        className="w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-black text-white hover:bg-emerald-700 disabled:bg-slate-300"
                                                                    >
                                                                        APPROVE
                                                                        & MOVE
                                                                    </button>
                                                                </>
                                                            )}

                                                            {transfer.can_reject && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        reject(
                                                                            transfer,
                                                                        )
                                                                    }
                                                                    className="w-full rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-black text-red-700 hover:bg-red-100"
                                                                >
                                                                    REJECT
                                                                </button>
                                                            )}

                                                            {transfer.can_cancel && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        cancel(
                                                                            transfer,
                                                                        )
                                                                    }
                                                                    className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-black text-slate-700 hover:bg-slate-100"
                                                                >
                                                                    CANCEL
                                                                    REQUEST
                                                                </button>
                                                            )}

                                                            {!transfer.can_approve
                                                                && !transfer.can_reject
                                                                && !transfer.can_cancel && (
                                                                <div className="rounded-lg bg-slate-100 px-3 py-2 text-center text-xs font-bold text-slate-500">
                                                                    No
                                                                    action
                                                                    required
                                                                </div>
                                                            )}
                                                        </div>
                                                    </Td>
                                                </tr>
                                            );
                                        },
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs font-semibold text-slate-500">
                        Showing{' '}
                        {
                            visibleTransfers.length
                        }{' '}
                        of{' '}
                        {
                            transfers.length
                        }{' '}
                        transfer record(s)
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

function Alert({
    type,
    children,
}) {
    const classes =
        type === 'success'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
            : type === 'error'
              ? 'border-red-200 bg-red-50 text-red-800'
              : 'border-amber-200 bg-amber-50 text-amber-800';

    return (
        <div
            className={`rounded-2xl border px-5 py-4 text-sm font-semibold ${classes}`}
        >
            {children}
        </div>
    );
}

function StatCard({
    label,
    value = 0,
    helper,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-xs font-black uppercase tracking-wider text-slate-400">
                {label}
            </div>

            <div className="mt-2 text-3xl font-black text-slate-900">
                {Number(value ?? 0)}
            </div>

            <div className="mt-1 text-xs font-semibold text-slate-500">
                {helper}
            </div>
        </div>
    );
}

function Info({
    label,
    value,
}) {
    return (
        <div>
            <div className="text-xs font-black uppercase tracking-wide text-slate-400">
                {label}
            </div>

            <div className="mt-1 break-words text-sm font-black text-slate-800">
                {value ?? '-'}
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
        <div>
            <label className="mb-1 block text-sm font-black text-slate-700">
                {label}
            </label>

            {children}

            {error && (
                <div className="mt-1 text-sm font-semibold text-red-600">
                    {error}
                </div>
            )}
        </div>
    );
}

function Th({ children }) {
    return (
        <th className="whitespace-nowrap px-5 py-3 text-left text-xs font-black uppercase tracking-wider text-slate-500">
            {children}
        </th>
    );
}

function Td({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-700">
            {children}
        </td>
    );
}

function StatusBadge({
    value,
}) {
    const normalized =
        String(
            value ?? '',
        ).toLowerCase();

    let classes =
        'bg-slate-100 text-slate-700';

    if (
        normalized ===
            'approved'
        || normalized ===
            'synced'
    ) {
        classes =
            'bg-emerald-100 text-emerald-700';
    }

    if (
        normalized ===
            'pending'
        || normalized ===
            'cleaning'
        || normalized ===
            'provisioning'
    ) {
        classes =
            'bg-amber-100 text-amber-800';
    }

    if (
        normalized ===
            'failed'
        || normalized ===
            'rejected'
    ) {
        classes =
            'bg-red-100 text-red-700';
    }

    if (
        normalized ===
        'cancelled'
    ) {
        classes =
            'bg-slate-200 text-slate-700';
    }

    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-black uppercase tracking-wide ${classes}`}
        >
            {value ?? '-'}
        </span>
    );
}
