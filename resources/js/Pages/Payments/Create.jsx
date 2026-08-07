import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

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

export default function Create({
    invoices = [],
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        invoice_id: '',
        client_id: '',
        amount: '',
        payment_date: localDateString(),
        payment_method: 'Cash',
        transaction_id: '',
        notes: '',
    });

    const selectedInvoice = invoices.find(
        (invoice) =>
            String(invoice.id) ===
            String(data.invoice_id),
    );

    const invoiceChanged = (event) => {
        const invoiceId =
            event.target.value;

        const invoice = invoices.find(
            (item) =>
                String(item.id) ===
                String(invoiceId),
        );

        setData({
            ...data,
            invoice_id: invoiceId,
            client_id:
                invoice?.client_id ?? '',
            amount:
                invoice?.due_amount ?? '',
        });
    };

    const submit = (event) => {
        event.preventDefault();

        post(route('payments.store'));
    };

    const inputClass =
        'mt-1 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-emerald-500 focus:ring-2 focus:ring-emerald-100';

    return (
        <AppLayout title="Receive Payment">
            <Head title="Receive Payment" />

            <div className="mx-auto max-w-5xl space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-800">
                        Receive Payment
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Record full or partial payment against an unpaid invoice
                    </p>
                </div>

                {Object.keys(errors).length >
                    0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-5 py-4">
                        <p className="font-bold text-red-700">
                            Please correct the highlighted fields.
                        </p>
                    </div>
                )}

                {invoices.length === 0 ? (
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 p-10 text-center">
                        <div className="text-5xl">
                            🧾
                        </div>

                        <h2 className="mt-4 text-xl font-bold text-amber-900">
                            No Unpaid Invoice Found
                        </h2>

                        <p className="mt-2 text-amber-700">
                            Create an invoice before receiving a payment.
                        </p>

                        <Link
                            href={route(
                                'invoices.create',
                            )}
                            className="mt-5 inline-flex rounded-xl bg-cyan-600 px-6 py-3 font-semibold text-white hover:bg-cyan-700"
                        >
                            Create Invoice
                        </Link>
                    </div>
                ) : (
                    <form
                        onSubmit={submit}
                        className="space-y-6"
                    >
                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="mb-6 flex items-center gap-3">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-100 text-xl">
                                    🧾
                                </div>

                                <div>
                                    <h2 className="text-xl font-bold text-slate-800">
                                        Select Invoice
                                    </h2>

                                    <p className="text-sm text-slate-500">
                                        Only invoices with an outstanding balance are shown
                                    </p>
                                </div>
                            </div>

                            <Field
                                label="Invoice"
                                error={
                                    errors.invoice_id
                                }
                                required
                            >
                                <select
                                    value={
                                        data.invoice_id
                                    }
                                    onChange={
                                        invoiceChanged
                                    }
                                    className={
                                        inputClass
                                    }
                                >
                                    <option value="">
                                        Select Invoice
                                    </option>

                                    {invoices.map(
                                        (invoice) => (
                                            <option
                                                key={
                                                    invoice.id
                                                }
                                                value={
                                                    invoice.id
                                                }
                                            >
                                                {
                                                    invoice.invoice_no
                                                }{' '}
                                                —{' '}
                                                {
                                                    invoice
                                                        .client
                                                        ?.name
                                                }{' '}
                                                — Due:{' '}
                                                {formatMoney(
                                                    invoice.due_amount,
                                                )}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>

                            {selectedInvoice && (
                                <div className="mt-5 grid gap-4 rounded-xl border border-cyan-200 bg-cyan-50 p-5 sm:grid-cols-2 xl:grid-cols-4">
                                    <Info
                                        label="Client"
                                        value={
                                            selectedInvoice
                                                .client
                                                ?.name ||
                                            '-'
                                        }
                                    />

                                    <Info
                                        label="Client Code"
                                        value={
                                            selectedInvoice
                                                .client
                                                ?.client_code ||
                                            '-'
                                        }
                                    />

                                    <Info
                                        label="Invoice"
                                        value={
                                            selectedInvoice.invoice_no
                                        }
                                    />

                                    <Info
                                        label="Remaining Due"
                                        value={formatMoney(
                                            selectedInvoice.due_amount,
                                        )}
                                    />

                                    <Info
                                        label="Invoice Amount"
                                        value={formatMoney(
                                            selectedInvoice.amount,
                                        )}
                                    />

                                    <Info
                                        label="Already Paid"
                                        value={formatMoney(
                                            selectedInvoice.paid_amount,
                                        )}
                                    />

                                    <Info
                                        label="Due Date"
                                        value={formatDate(
                                            selectedInvoice.due_date,
                                        )}
                                    />

                                    <Info
                                        label="Status"
                                        value={
                                            selectedInvoice.status ||
                                            '-'
                                        }
                                    />
                                </div>
                            )}
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="mb-6 flex items-center gap-3">
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-xl">
                                    💰
                                </div>

                                <div>
                                    <h2 className="text-xl font-bold text-slate-800">
                                        Payment Details
                                    </h2>

                                    <p className="text-sm text-slate-500">
                                        Enter payment amount, date and method
                                    </p>
                                </div>
                            </div>

                            <div className="grid gap-5 md:grid-cols-2">
                                <Field
                                    label="Payment Amount"
                                    error={
                                        errors.amount
                                    }
                                    required
                                >
                                    <input
                                        type="number"
                                        min="0.01"
                                        max={
                                            selectedInvoice
                                                ?.due_amount ||
                                            undefined
                                        }
                                        step="0.01"
                                        value={
                                            data.amount
                                        }
                                        onChange={(
                                            event,
                                        ) =>
                                            setData(
                                                'amount',
                                                event
                                                    .target
                                                    .value,
                                            )
                                        }
                                        className={
                                            inputClass
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Payment Date"
                                    error={
                                        errors.payment_date
                                    }
                                    required
                                >
                                    <input
                                        type="date"
                                        value={
                                            data.payment_date
                                        }
                                        onChange={(
                                            event,
                                        ) =>
                                            setData(
                                                'payment_date',
                                                event
                                                    .target
                                                    .value,
                                            )
                                        }
                                        className={
                                            inputClass
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Payment Method"
                                    error={
                                        errors.payment_method
                                    }
                                    required
                                >
                                    <select
                                        value={
                                            data.payment_method
                                        }
                                        onChange={(
                                            event,
                                        ) =>
                                            setData(
                                                'payment_method',
                                                event
                                                    .target
                                                    .value,
                                            )
                                        }
                                        className={
                                            inputClass
                                        }
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
                                </Field>

                                <Field
                                    label="Transaction ID (Optional)"
                                    error={
                                        errors.transaction_id
                                    }
                                >
                                    <input
                                        type="text"
                                        value={
                                            data.transaction_id
                                        }
                                        onChange={(
                                            event,
                                        ) =>
                                            setData(
                                                'transaction_id',
                                                event
                                                    .target
                                                    .value,
                                            )
                                        }
                                        placeholder="Reference or transaction number"
                                        className={
                                            inputClass
                                        }
                                    />
                                </Field>
                            </div>

                            {selectedInvoice && (
                                <div className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <Info
                                            label="Current Due"
                                            value={formatMoney(
                                                selectedInvoice.due_amount,
                                            )}
                                        />

                                        <Info
                                            label="This Payment"
                                            value={formatMoney(
                                                data.amount,
                                            )}
                                        />

                                        <Info
                                            label="Remaining After Payment"
                                            value={formatMoney(
                                                Math.max(
                                                    0,
                                                    numberValue(
                                                        selectedInvoice.due_amount,
                                                    ) -
                                                        numberValue(
                                                            data.amount,
                                                        ),
                                                ),
                                            )}
                                        />
                                    </div>
                                </div>
                            )}
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <Field
                                label="Notes (Optional)"
                                error={
                                    errors.notes
                                }
                            >
                                <textarea
                                    rows="4"
                                    value={
                                        data.notes
                                    }
                                    onChange={(
                                        event,
                                    ) =>
                                        setData(
                                            'notes',
                                            event.target
                                                .value,
                                        )
                                    }
                                    placeholder="Payment notes"
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>
                        </section>

                        <div className="rounded-xl border border-cyan-200 bg-cyan-50 px-5 py-4 text-sm text-cyan-800">
                            Full payment হলে invoice Paid হবে, client expiry renew হবে এবং suspended client MikroTik-এ active হবে। Partial payment হলে invoice Partial থাকবে।
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <button
                                type="submit"
                                disabled={
                                    processing ||
                                    !data.invoice_id
                                }
                                className="rounded-xl bg-emerald-600 px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {processing
                                    ? 'Saving Payment...'
                                    : 'Save Payment'}
                            </button>

                            <Link
                                href={route(
                                    'payments.index',
                                )}
                                className="rounded-xl bg-slate-600 px-6 py-3 font-semibold text-white transition hover:bg-slate-700"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}

function Field({
    label,
    error,
    required = false,
    children,
}) {
    return (
        <div>
            <label className="block text-sm font-semibold text-slate-700">
                {label}

                {required && (
                    <span className="ml-1 text-red-500">
                        *
                    </span>
                )}
            </label>

            {children}

            {error && (
                <p className="mt-1 text-sm font-medium text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}

function Info({ label, value }) {
    return (
        <div>
            <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                {label}
            </p>

            <p className="mt-1 break-words font-bold capitalize text-slate-800">
                {value}
            </p>
        </div>
    );
}

function numberValue(value) {
    const number = Number(value);

    return Number.isFinite(number) ? number : 0;
}

function formatMoney(value) {
    return numberValue(value).toLocaleString(
        undefined,
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        },
    );
}

function formatDate(value) {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value).slice(0, 10);
    }

    return date.toLocaleDateString();
}

function localDateString() {
    const date = new Date();

    const offset =
        date.getTimezoneOffset() *
        60 *
        1000;

    return new Date(
        date.getTime() - offset,
    )
        .toISOString()
        .slice(0, 10);
}
