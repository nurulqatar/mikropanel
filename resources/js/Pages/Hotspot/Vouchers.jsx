import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';
import { useState } from 'react';

const inputClass =
    'w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:border-cyan-500 focus:ring-cyan-500';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

export default function Vouchers({
    vouchers = [],
    servers = [],
    plans = [],
    capabilities = {},
}) {
    const [
        selected,
        setSelected,
    ] = useState(null);

    const generator =
        useForm({
            hotspot_server_id: '',
            hotspot_plan_id: '',
            quantity: 1,
            prefix: '',
        });

    const sale =
        useForm({
            customer_name: '',
            phone: '',
            sale_type: 'paid',
            received_amount: '',
            payment_method: 'Cash',
            transaction_id: '',
        });

    const openSale = (voucher) => {
        setSelected(
            voucher,
        );

        sale.setData({
            customer_name:
                voucher.customer_name ??
                '',
            phone:
                voucher.phone ?? '',
            sale_type: 'paid',
            received_amount: '',
            payment_method: 'Cash',
            transaction_id: '',
        });
    };

    return (
        <AppLayout title="Hotspot Vouchers">
            <Head title="Hotspot Vouchers" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-3xl font-black">
                            Hotspot Vouchers
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Generate, sell and manage
                            Hotspot access vouchers.
                        </p>
                    </div>

                    <Link
                        href={route(
                            'hotspot.batches.index',
                        )}
                        className="rounded-xl bg-violet-600 px-5 py-3 font-bold text-white"
                    >
                        Voucher Batches
                    </Link>
                </div>

                {capabilities.manage && (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            generator.post(
                                route(
                                    'hotspot.vouchers.generate',
                                ),
                                {
                                    preserveScroll:
                                        true,
                                },
                            );
                        }}
                        className="rounded-2xl border bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-xl font-bold">
                            Generate Vouchers
                        </h2>

                        <div className="mt-5 grid gap-4 md:grid-cols-4">
                            <Field label="Server">
                                <select
                                    value={
                                        generator.data
                                            .hotspot_server_id
                                    }
                                    onChange={(e) =>
                                        generator.setData(
                                            'hotspot_server_id',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                >
                                    <option value="">
                                        Select Server
                                    </option>

                                    {servers.map(
                                        (server) => (
                                            <option
                                                key={
                                                    server.id
                                                }
                                                value={
                                                    server.id
                                                }
                                            >
                                                {
                                                    server.name
                                                }
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>

                            <Field label="Plan">
                                <select
                                    value={
                                        generator.data
                                            .hotspot_plan_id
                                    }
                                    onChange={(e) =>
                                        generator.setData(
                                            'hotspot_plan_id',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                >
                                    <option value="">
                                        Select Plan
                                    </option>

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

                            <Field label="Quantity">
                                <input
                                    type="number"
                                    min="1"
                                    max="500"
                                    value={
                                        generator.data
                                            .quantity
                                    }
                                    onChange={(e) =>
                                        generator.setData(
                                            'quantity',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Prefix">
                                <input
                                    value={
                                        generator.data
                                            .prefix
                                    }
                                    onChange={(e) =>
                                        generator.setData(
                                            'prefix',
                                            e.target
                                                .value,
                                        )
                                    }
                                    placeholder="Optional"
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>
                        </div>

                        <button
                            type="submit"
                            disabled={
                                generator.processing
                            }
                            className="mt-5 rounded-lg bg-cyan-600 px-5 py-2.5 font-bold text-white"
                        >
                            Generate
                        </button>
                    </form>
                )}

                {selected &&
                    capabilities.sell && (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            sale.post(
                                route(
                                    'hotspot.vouchers.sell',
                                    selected.id,
                                ),
                                {
                                    preserveScroll:
                                        true,

                                    onSuccess:
                                        () =>
                                            setSelected(
                                                null,
                                            ),
                                },
                            );
                        }}
                        className="rounded-2xl border-2 border-emerald-200 bg-emerald-50 p-6"
                    >
                        <div className="flex items-center justify-between gap-3">
                            <div>
                                <h2 className="text-xl font-bold">
                                    Sell Voucher{' '}
                                    {selected.username}
                                </h2>

                                <div className="text-sm text-slate-600">
                                    QAR{' '}
                                    {money(
                                        selected.plan
                                            ?.price,
                                    )}
                                </div>
                            </div>

                            <button
                                type="button"
                                onClick={() =>
                                    setSelected(
                                        null,
                                    )
                                }
                                className="rounded bg-white px-3 py-2 font-semibold"
                            >
                                Close
                            </button>
                        </div>

                        <div className="mt-5 grid gap-4 md:grid-cols-3">
                            <Field label="Customer">
                                <input
                                    value={
                                        sale.data
                                            .customer_name
                                    }
                                    onChange={(e) =>
                                        sale.setData(
                                            'customer_name',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Phone">
                                <input
                                    value={
                                        sale.data
                                            .phone
                                    }
                                    onChange={(e) =>
                                        sale.setData(
                                            'phone',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Billing">
                                <select
                                    value={
                                        sale.data
                                            .sale_type
                                    }
                                    onChange={(e) =>
                                        sale.setData(
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

                            {sale.data
                                .sale_type ===
                                'partial' && (
                                <Field label="Received">
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={
                                            sale.data
                                                .received_amount
                                        }
                                        onChange={(
                                            e,
                                        ) =>
                                            sale.setData(
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

                            {sale.data
                                .sale_type !==
                                'due' && (
                                <Field label="Method">
                                    <select
                                        value={
                                            sale.data
                                                .payment_method
                                        }
                                        onChange={(
                                            e,
                                        ) =>
                                            sale.setData(
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

                        <button
                            type="submit"
                            className="mt-5 rounded-lg bg-emerald-600 px-5 py-2.5 font-bold text-white"
                        >
                            Confirm Sale
                        </button>
                    </form>
                )}

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Voucher</Th>
                                <Th>Server</Th>
                                <Th>Plan</Th>
                                <Th>Price</Th>
                                <Th>Status</Th>
                                <Th>Billing</Th>
                                <Th>Expiry</Th>
                                <Th>Actions</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {vouchers.map(
                                (voucher) => (
                                    <tr
                                        key={
                                            voucher.id
                                        }
                                    >
                                        <Td>
                                            <div className="font-mono font-bold">
                                                {
                                                    voucher.username
                                                }
                                            </div>

                                            <div className="font-mono text-xs text-slate-400">
                                                {
                                                    voucher.password
                                                }
                                            </div>
                                        </Td>

                                        <Td>
                                            {voucher.server?.name ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {voucher.plan?.name ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {money(
                                                voucher.plan
                                                    ?.price,
                                            )}
                                        </Td>

                                        <Td>
                                            {
                                                voucher.status
                                            }
                                        </Td>

                                        <Td>
                                            {voucher.invoice ? (
                                                <div>
                                                    {
                                                        voucher
                                                            .invoice
                                                            .status
                                                    }

                                                    <div className="text-xs text-slate-500">
                                                        Due QAR{' '}
                                                        {money(
                                                            voucher
                                                                .invoice
                                                                .due_amount,
                                                        )}
                                                    </div>
                                                </div>
                                            ) : (
                                                'Unsold'
                                            )}
                                        </Td>

                                        <Td>
                                            {voucher.expires_at ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            <div className="flex gap-2">
                                                <Link
                                                    href={route(
                                                        'hotspot.vouchers.show',
                                                        voucher.id,
                                                    )}
                                                    className="rounded bg-slate-700 px-3 py-2 text-sm font-bold text-white"
                                                >
                                                    Manage
                                                </Link>

                                                {capabilities.sell &&
                                                    !voucher.invoice &&
                                                    voucher.status ===
                                                        'unused' && (
                                                        <button
                                                            onClick={() =>
                                                                openSale(
                                                                    voucher,
                                                                )
                                                            }
                                                            className="rounded bg-emerald-600 px-3 py-2 text-sm font-bold text-white"
                                                        >
                                                            Sell
                                                        </button>
                                                    )}
                                            </div>
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {vouchers.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="8"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No vouchers generated.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </AppLayout>
    );
}

function Field({
    label,
    children,
}) {
    return (
        <label>
            <div className="mb-1 text-sm font-semibold">
                {label}
            </div>

            {children}
        </label>
    );
}

function Th({ children }) {
    return (
        <th className="whitespace-nowrap px-5 py-3 text-left text-xs font-bold uppercase text-slate-500">
            {children}
        </th>
    );
}

function Td({ children }) {
    return (
        <td className="whitespace-nowrap px-5 py-4 text-sm">
            {children}
        </td>
    );
}
