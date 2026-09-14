import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

const dt = (value) =>
    value
        ? new Date(value).toLocaleString()
        : '-';

const inputClass =
    'w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:border-cyan-500 focus:ring-cyan-500';

export default function Voucher({
    voucher,
    plans = [],
    flash = {},
}) {
    const renew = useForm({
        hotspot_plan_id:
            voucher.hotspot_plan_id,
        sale_type: 'paid',
        received_amount: '',
        payment_method: 'Cash',
        transaction_id: '',
    });

    const mac = useForm({
        mac_address:
            voucher.mac_address ?? '',
    });

    const selectedPlan =
        plans.find(
            (item) =>
                String(item.id) ===
                String(
                    renew.data
                        .hotspot_plan_id,
                ),
        );

    const submitRenew = (event) => {
        event.preventDefault();

        renew.post(
            route(
                'hotspot.vouchers.renew',
                voucher.id,
            ),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="Hotspot Voucher">
            <Head title={`Voucher ${voucher.username}`} />

            <div className="mx-auto max-w-6xl space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <Link
                            href={route(
                                'hotspot.index',
                            )}
                            className="text-sm font-semibold text-cyan-700"
                        >
                            ← Hotspot
                        </Link>

                        <h1 className="mt-2 text-3xl font-bold text-slate-900">
                            {voucher.username}
                        </h1>

                        <div className="mt-1 text-slate-500">
                            {voucher.plan?.name}
                            {' · '}
                            {voucher.status}
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <a
                            href={route(
                                'hotspot.vouchers.print',
                                voucher.id,
                            )}
                            target="_blank"
                            className="rounded-lg bg-slate-700 px-4 py-2.5 font-semibold text-white"
                        >
                            Print
                        </a>

                        <a
                            href={route(
                                'hotspot.vouchers.pdf',
                                voucher.id,
                            )}
                            className="rounded-lg bg-violet-600 px-4 py-2.5 font-semibold text-white"
                        >
                            PDF
                        </a>
                    </div>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-700">
                        {flash.success}
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Card
                        label="Username"
                        value={voucher.username}
                    />

                    <Card
                        label="Password"
                        value={voucher.password}
                    />

                    <Card
                        label="Status"
                        value={voucher.status}
                    />

                    <Card
                        label="MikroTik ID"
                        value={
                            voucher.mikrotik_user_id ??
                            'Pending'
                        }
                    />

                    <Card
                        label="MAC"
                        value={
                            voucher.mac_address ??
                            'Not bound'
                        }
                    />

                    <Card
                        label="Activated"
                        value={dt(
                            voucher.activated_at,
                        )}
                    />

                    <Card
                        label="Expires"
                        value={dt(
                            voucher.expires_at,
                        )}
                    />

                    <Card
                        label="Server"
                        value={
                            voucher.server?.name ??
                            '-'
                        }
                    />
                </div>

                {!voucher.deleted_at && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="text-xl font-bold">
                            Service Actions
                        </h2>

                        <div className="mt-5 flex flex-wrap gap-3">
                            {voucher.status !==
                                'suspended' && (
                                <button
                                    onClick={() =>
                                        router.post(
                                            route(
                                                'hotspot.vouchers.suspend',
                                                voucher.id,
                                            ),
                                            {},
                                            {
                                                preserveScroll: true,
                                            },
                                        )
                                    }
                                    className="rounded-lg bg-amber-500 px-4 py-2.5 font-semibold text-white"
                                >
                                    Suspend
                                </button>
                            )}

                            {voucher.status ===
                                'suspended' && (
                                <button
                                    onClick={() =>
                                        router.post(
                                            route(
                                                'hotspot.vouchers.activate',
                                                voucher.id,
                                            ),
                                            {},
                                            {
                                                preserveScroll: true,
                                            },
                                        )
                                    }
                                    className="rounded-lg bg-emerald-600 px-4 py-2.5 font-semibold text-white"
                                >
                                    Activate
                                </button>
                            )}

                            <button
                                onClick={() => {
                                    if (
                                        confirm(
                                            'Archive this voucher? Billing history will remain.',
                                        )
                                    ) {
                                        router.delete(
                                            route(
                                                'hotspot.vouchers.archive',
                                                voucher.id,
                                            ),
                                        );
                                    }
                                }}
                                className="rounded-lg bg-red-600 px-4 py-2.5 font-semibold text-white"
                            >
                                Archive
                            </button>
                        </div>
                    </section>
                )}

                {!voucher.deleted_at && (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            mac.put(
                                route(
                                    'hotspot.vouchers.mac',
                                    voucher.id,
                                ),
                                {
                                    preserveScroll: true,
                                },
                            );
                        }}
                        className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-xl font-bold">
                            Device / MAC Binding
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Leave blank and save to release
                            the currently bound device.
                        </p>

                        <div className="mt-4 flex max-w-xl gap-3">
                            <input
                                value={
                                    mac.data
                                        .mac_address
                                }
                                onChange={(e) =>
                                    mac.setData(
                                        'mac_address',
                                        e.target
                                            .value
                                            .toUpperCase(),
                                    )
                                }
                                placeholder="AA:BB:CC:DD:EE:FF"
                                className={inputClass}
                            />

                            <button
                                type="submit"
                                className="rounded-lg bg-cyan-600 px-5 font-semibold text-white"
                            >
                                Save MAC
                            </button>
                        </div>

                        {mac.errors.mac_address && (
                            <div className="mt-2 text-sm text-red-600">
                                {
                                    mac.errors
                                        .mac_address
                                }
                            </div>
                        )}
                    </form>
                )}

                {!voucher.deleted_at &&
                    voucher.sold_at && (
                    <form
                        onSubmit={
                            submitRenew
                        }
                        className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-xl font-bold">
                            Renew / Top-Up
                        </h2>

                        <div className="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <Field label="Plan">
                                <select
                                    value={
                                        renew.data
                                            .hotspot_plan_id
                                    }
                                    onChange={(e) =>
                                        renew.setData(
                                            'hotspot_plan_id',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                >
                                    {plans.map(
                                        (plan) => (
                                            <option
                                                key={
                                                    plan.id
                                                }
                                                value={
                                                    plan.id
                                                }
                                            >
                                                {
                                                    plan.name
                                                }{' '}
                                                - QAR{' '}
                                                {money(
                                                    plan.price,
                                                )}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>

                            <Field label="Billing">
                                <select
                                    value={
                                        renew.data
                                            .sale_type
                                    }
                                    onChange={(e) =>
                                        renew.setData(
                                            'sale_type',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                >
                                    <option value="paid">
                                        Paid
                                    </option>
                                    <option value="due">
                                        Due
                                    </option>
                                    <option value="partial">
                                        Partial
                                    </option>
                                </select>
                            </Field>

                            {renew.data
                                .sale_type ===
                                'partial' && (
                                <Field label="Received">
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={
                                            renew.data
                                                .received_amount
                                        }
                                        onChange={(
                                            e,
                                        ) =>
                                            renew.setData(
                                                'received_amount',
                                                e
                                                    .target
                                                    .value,
                                            )
                                        }
                                        className={
                                            inputClass
                                        }
                                    />
                                </Field>
                            )}

                            {renew.data
                                .sale_type !==
                                'due' && (
                                <Field label="Method">
                                    <select
                                        value={
                                            renew.data
                                                .payment_method
                                        }
                                        onChange={(
                                            e,
                                        ) =>
                                            renew.setData(
                                                'payment_method',
                                                e
                                                    .target
                                                    .value,
                                            )
                                        }
                                        className={
                                            inputClass
                                        }
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
                                        <option value="Card">
                                            Card
                                        </option>
                                    </select>
                                </Field>
                            )}
                        </div>

                        <div className="mt-4 text-sm text-slate-600">
                            Selected price: QAR{' '}
                            {money(
                                selectedPlan?.price,
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={
                                renew.processing
                            }
                            className="mt-4 rounded-lg bg-emerald-600 px-5 py-2.5 font-bold text-white disabled:opacity-50"
                        >
                            Renew Voucher
                        </button>
                    </form>
                )}

                <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b px-6 py-5">
                        <h2 className="text-xl font-bold">
                            Billing History
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Invoice</Th>
                                    <Th>Type</Th>
                                    <Th>Amount</Th>
                                    <Th>Paid</Th>
                                    <Th>Due</Th>
                                    <Th>Status</Th>
                                    <Th>Service Until</Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {voucher.invoices?.map(
                                    (invoice) => (
                                        <tr
                                            key={
                                                invoice.id
                                            }
                                        >
                                            <Td>
                                                {
                                                    invoice.invoice_no
                                                }
                                            </Td>
                                            <Td>
                                                {
                                                    invoice.invoice_type
                                                }
                                            </Td>
                                            <Td>
                                                QAR{' '}
                                                {money(
                                                    invoice.amount,
                                                )}
                                            </Td>
                                            <Td>
                                                QAR{' '}
                                                {money(
                                                    invoice.paid_amount,
                                                )}
                                            </Td>
                                            <Td>
                                                QAR{' '}
                                                {money(
                                                    invoice.due_amount,
                                                )}
                                            </Td>
                                            <Td>
                                                {
                                                    invoice.status
                                                }
                                            </Td>
                                            <Td>
                                                {dt(
                                                    invoice.service_until,
                                                )}
                                            </Td>
                                        </tr>
                                    ),
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

function Card({ label, value }) {
    return (
        <div className="rounded-xl border bg-white p-4 shadow-sm">
            <div className="text-xs font-bold uppercase text-slate-400">
                {label}
            </div>
            <div className="mt-2 break-all font-semibold text-slate-800">
                {value ?? '-'}
            </div>
        </div>
    );
}

function Field({ label, children }) {
    return (
        <label>
            <span className="mb-1 block text-sm font-semibold text-slate-700">
                {label}
            </span>
            {children}
        </label>
    );
}

function Th({ children }) {
    return (
        <th className="px-5 py-3 text-left text-xs font-bold uppercase text-slate-500">
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
