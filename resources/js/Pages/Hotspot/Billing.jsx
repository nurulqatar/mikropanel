import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    useForm,
} from '@inertiajs/react';
import { useState } from 'react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

const inputClass =
    'w-full rounded-lg border border-slate-300 px-3 py-2.5';

export default function Billing({
    dueInvoices = [],
    recentPayments = [],
    summary = {},
    capabilities = {},
}) {
    const [
        selected,
        setSelected,
    ] = useState(null);

    const form =
        useForm({
            amount: '',
            payment_method: 'Cash',
            transaction_id: '',
        });

    const open = (invoice) => {
        setSelected(
            invoice,
        );

        form.setData({
            amount: money(
                invoice.due_amount,
            ),
            payment_method: 'Cash',
            transaction_id: '',
        });
    };

    return (
        <AppLayout title="Hotspot Billing & Dues">
            <Head title="Hotspot Billing & Dues" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black">
                        Billing & Dues
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Hotspot invoices, outstanding
                        balances and payment history.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Stat
                        label="Total Hotspot Received"
                        value={`QAR ${money(
                            summary.received,
                        )}`}
                    />

                    <Stat
                        label="Total Hotspot Due"
                        value={`QAR ${money(
                            summary.due,
                        )}`}
                    />
                </div>

                {selected &&
                    capabilities.payments && (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            form.post(
                                route(
                                    'hotspot.invoices.pay',
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
                        className="rounded-2xl border-2 border-cyan-200 bg-cyan-50 p-6"
                    >
                        <h2 className="text-xl font-bold">
                            Receive Payment
                        </h2>

                        <div className="mt-1 text-sm text-slate-600">
                            {selected.invoice_no}
                            {' · '}
                            Due QAR{' '}
                            {money(
                                selected.due_amount,
                            )}
                        </div>

                        <div className="mt-5 grid gap-4 md:grid-cols-3">
                            <Field label="Amount">
                                <input
                                    type="number"
                                    step="0.01"
                                    value={
                                        form.data
                                            .amount
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'amount',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Method">
                                <select
                                    value={
                                        form.data
                                            .payment_method
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'payment_method',
                                            e.target
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

                            <Field label="Transaction ID">
                                <input
                                    value={
                                        form.data
                                            .transaction_id
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'transaction_id',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>
                        </div>

                        <div className="mt-5 flex gap-3">
                            <button
                                type="submit"
                                className="rounded-lg bg-cyan-600 px-5 py-2.5 font-bold text-white"
                            >
                                Confirm Payment
                            </button>

                            <button
                                type="button"
                                onClick={() =>
                                    setSelected(
                                        null,
                                    )
                                }
                                className="rounded-lg bg-white px-5 py-2.5 font-bold text-slate-600"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                )}

                <section className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <Header title="Outstanding Bills" />

                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Invoice</Th>
                                <Th>Voucher</Th>
                                <Th>Plan</Th>
                                <Th>Amount</Th>
                                <Th>Paid</Th>
                                <Th>Due</Th>
                                <Th>Status</Th>
                                <Th>Action</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {dueInvoices.map(
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
                                            {invoice.voucher
                                                ?.username ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {invoice.voucher
                                                ?.plan?.name ||
                                                '-'}
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
                                            <strong>
                                                QAR{' '}
                                                {money(
                                                    invoice.due_amount,
                                                )}
                                            </strong>
                                        </Td>

                                        <Td>
                                            {
                                                invoice.status
                                            }
                                        </Td>

                                        <Td>
                                            {capabilities.payments ? (
                                                <button
                                                    onClick={() =>
                                                        open(
                                                            invoice,
                                                        )
                                                    }
                                                    className="rounded bg-cyan-600 px-3 py-2 text-sm font-bold text-white"
                                                >
                                                    Receive
                                                </button>
                                            ) : (
                                                '-'
                                            )}
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {dueInvoices.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="8"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No outstanding Hotspot bill.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </section>

                <section className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <Header title="Recent Payments" />

                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Date</Th>
                                <Th>Voucher</Th>
                                <Th>Invoice</Th>
                                <Th>Amount</Th>
                                <Th>Method</Th>
                                <Th>Received By</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {recentPayments.map(
                                (payment) => (
                                    <tr
                                        key={
                                            payment.id
                                        }
                                    >
                                        <Td>
                                            {
                                                payment.payment_date
                                            }
                                        </Td>

                                        <Td>
                                            {payment.voucher
                                                ?.username ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {payment.invoice
                                                ?.invoice_no ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {money(
                                                payment.amount,
                                            )}
                                        </Td>

                                        <Td>
                                            {
                                                payment.payment_method
                                            }
                                        </Td>

                                        <Td>
                                            {payment.received_by
                                                ? `User #${payment.received_by}`
                                                : '-'}
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {recentPayments.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="6"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No Hotspot payment yet.
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

function Stat({
    label,
    value,
}) {
    return (
        <div className="rounded-xl border bg-white p-5 shadow-sm">
            <div className="text-xs font-bold uppercase text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-2xl font-black">
                {value}
            </div>
        </div>
    );
}

function Header({ title }) {
    return (
        <div className="border-b px-5 py-4 text-lg font-bold">
            {title}
        </div>
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
