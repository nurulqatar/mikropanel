import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function Index({
    transfers = [],
    clients = [],
    zones = [],
    ipRanges = [],
    currentZoneId = null,
    isOwner = false,
    isManager = false,
}) {
    const requestForm = useForm({
        client_id: '',
        target_zone_id: '',
        request_note: '',
    });

    const [
        rangeSelections,
        setRangeSelections,
    ] = useState({});

    const selectedClient =
        useMemo(
            () =>
                clients.find(
                    (client) =>
                        String(client.id) ===
                        String(
                            requestForm.data
                                .client_id,
                        ),
                ) ?? null,
            [
                clients,
                requestForm.data
                    .client_id,
            ],
        );

    const targetZones =
        useMemo(
            () =>
                zones.filter(
                    (zone) =>
                        !selectedClient
                        || String(zone.id) !==
                            String(
                                selectedClient
                                    .zone_id,
                            ),
                ),
            [
                zones,
                selectedClient,
            ],
        );

    const submitRequest = (event) => {
        event.preventDefault();

        requestForm.post(
            route(
                'reseller.transfers.store',
            ),
            {
                preserveScroll: true,

                onSuccess: () =>
                    requestForm.reset(),
            },
        );
    };

    const rangesFor = (transfer) =>
        ipRanges.filter(
            (range) =>
                String(range.zone_id) ===
                String(
                    transfer.target_zone_id,
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

    const approve = (transfer) => {
        const ipRangeId =
            selectedRange(
                transfer,
            );

        if (!ipRangeId) {
            window.alert(
                'Destination zone has no available IP Pool.',
            );

            return;
        }

        if (
            !window.confirm(
                `Approve transfer of ${transfer.client_name} to ${transfer.target_zone_name}?`,
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
                preserveScroll: true,
            },
        );
    };

    const reject = (transfer) => {
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
                preserveScroll: true,
            },
        );
    };

    const cancel = (transfer) => {
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
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="Client Transfers">
            <Head title="Client Transfers" />

            <div className="space-y-6">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h1 className="text-2xl font-black text-slate-900">
                                Client Zone Transfer
                            </h1>

                            <p className="mt-1 max-w-3xl text-sm text-slate-500">
                                Source office sends
                                the request. Destination
                                office approves it and
                                selects its IP Pool.
                                Current service-period
                                billing remains with the
                                source zone. Future
                                renewals start under the
                                destination zone.
                            </p>
                        </div>

                        {!isOwner && (
                            <div className="rounded-xl bg-cyan-50 px-4 py-3 text-sm font-bold text-cyan-800">
                                Active Zone ID:{' '}
                                {currentZoneId ??
                                    '-'}
                                {isManager
                                    ? ' · Manager'
                                    : ' · Operator'}
                            </div>
                        )}
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-black text-slate-900">
                        New Transfer Request
                    </h2>

                    <form
                        onSubmit={
                            submitRequest
                        }
                        className="mt-4 grid gap-4 lg:grid-cols-3"
                    >
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

                                {clients.map(
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
                        </Field>

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
                                className="w-full rounded-xl border-slate-300"
                                disabled={
                                    !selectedClient
                                }
                            >
                                <option value="">
                                    Select destination
                                </option>

                                {targetZones.map(
                                    (zone) => (
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

                        <div className="flex items-end">
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
                                className="w-full rounded-xl bg-cyan-600 px-5 py-3 font-black text-white disabled:bg-slate-300"
                            >
                                {requestForm.processing
                                    ? 'SENDING...'
                                    : 'SEND TRANSFER REQUEST'}
                            </button>
                        </div>

                        <div className="lg:col-span-3">
                            <label className="mb-1 block text-sm font-bold text-slate-700">
                                Note
                            </label>

                            <textarea
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
                                rows={2}
                                className="w-full rounded-xl border-slate-300"
                                placeholder="Optional transfer note"
                            />
                        </div>
                    </form>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 p-5">
                        <h2 className="text-lg font-black text-slate-900">
                            Transfer List
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>
                                        Client
                                    </Th>
                                    <Th>
                                        Transfer
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
                                {transfers.length ===
                                0 ? (
                                    <tr>
                                        <td
                                            colSpan={
                                                6
                                            }
                                            className="px-5 py-10 text-center text-slate-500"
                                        >
                                            No transfer
                                            request yet.
                                        </td>
                                    </tr>
                                ) : (
                                    transfers.map(
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
                                                    className="align-top"
                                                >
                                                    <Td>
                                                        <div className="font-black text-slate-900">
                                                            {
                                                                transfer.client_name
                                                            }
                                                        </div>

                                                        <div className="text-xs text-slate-500">
                                                            {
                                                                transfer.client_code
                                                            }
                                                            {' · '}
                                                            {
                                                                transfer.mac_address
                                                            }
                                                        </div>

                                                        <div className="mt-1 text-xs text-slate-500">
                                                            {
                                                                transfer.device_count
                                                            }{' '}
                                                            device(s)
                                                        </div>
                                                    </Td>

                                                    <Td>
                                                        <div className="font-bold text-slate-700">
                                                            {
                                                                transfer.source_zone_name
                                                            }
                                                            {' → '}
                                                            {
                                                                transfer.target_zone_name
                                                            }
                                                        </div>

                                                        <div className="mt-1 text-xs text-slate-500">
                                                            Old IP:{' '}
                                                            {
                                                                transfer.source_ip_address
                                                            }
                                                        </div>

                                                        {transfer.target_ip_address && (
                                                            <div className="text-xs text-emerald-700">
                                                                New IP:{' '}
                                                                {
                                                                    transfer.target_ip_address
                                                                }
                                                            </div>
                                                        )}
                                                    </Td>

                                                    <Td>
                                                        <div className="text-sm font-semibold text-slate-700">
                                                            Current period:
                                                            source zone
                                                        </div>

                                                        <div className="mt-1 text-xs text-slate-500">
                                                            Service ends:{' '}
                                                            {
                                                                transfer.source_service_end_date ??
                                                                '-'
                                                            }
                                                        </div>

                                                        <div className="mt-1 text-xs font-semibold text-cyan-700">
                                                            Next renewal:
                                                            destination zone
                                                        </div>
                                                    </Td>

                                                    <Td>
                                                        <StatusBadge
                                                            value={
                                                                transfer.status
                                                            }
                                                        />

                                                        <div className="mt-2 text-xs text-slate-500">
                                                            {
                                                                transfer.requested_at
                                                            }
                                                        </div>
                                                    </Td>

                                                    <Td>
                                                        <StatusBadge
                                                            value={
                                                                transfer.network_status
                                                            }
                                                        />

                                                        {transfer.network_error && (
                                                            <div className="mt-2 max-w-xs whitespace-normal text-xs text-red-600">
                                                                {
                                                                    transfer.network_error
                                                                }
                                                            </div>
                                                        )}
                                                    </Td>

                                                    <Td>
                                                        <div className="min-w-56 space-y-2">
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
                                                                                        }-
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
                                                                        onClick={() =>
                                                                            approve(
                                                                                transfer,
                                                                            )
                                                                        }
                                                                        disabled={
                                                                            ranges.length ===
                                                                            0
                                                                        }
                                                                        className="w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-black text-white disabled:bg-slate-300"
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
                                                                    className="w-full rounded-lg bg-red-600 px-3 py-2 text-sm font-black text-white"
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
                                                                    className="w-full rounded-lg bg-slate-700 px-3 py-2 text-sm font-black text-white"
                                                                >
                                                                    CANCEL
                                                                    REQUEST
                                                                </button>
                                                            )}

                                                            {!transfer.can_approve &&
                                                                !transfer.can_reject &&
                                                                !transfer.can_cancel && (
                                                                    <span className="text-xs text-slate-500">
                                                                        No action
                                                                    </span>
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
                </section>
            </div>
        </AppLayout>
    );
}

function Field({
    label,
    error,
    children,
}) {
    return (
        <div>
            <label className="mb-1 block text-sm font-bold text-slate-700">
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
        <th className="whitespace-nowrap px-5 py-3 text-left text-xs font-black uppercase tracking-wide text-slate-500">
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

function StatusBadge({ value }) {
    const normalized =
        String(
            value ?? '',
        ).toLowerCase();

    const classes =
        normalized === 'approved'
        || normalized === 'synced'
            ? 'bg-emerald-100 text-emerald-700'
            : normalized === 'pending'
              || normalized === 'cleaning'
              || normalized === 'provisioning'
                ? 'bg-amber-100 text-amber-800'
                : normalized === 'failed'
                  || normalized === 'rejected'
                    ? 'bg-red-100 text-red-700'
                    : 'bg-slate-100 text-slate-700';

    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-black uppercase ${classes}`}
        >
            {value ?? '-'}
        </span>
    );
}
