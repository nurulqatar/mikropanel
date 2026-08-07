import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

export default function Edit({
    invoice,
    clients = [],
}) {
    const {
        data,
        setData,
        put,
        processing,
        errors,
    } = useForm({
        client_id: invoice.client_id ?? '',
        billing_month: dateValue(
            invoice.billing_month,
        ),
        amount: invoice.amount ?? '',
        discount: invoice.discount ?? 0,
        issue_date: dateValue(
            invoice.issue_date,
        ),
        due_date: dateValue(
            invoice.due_date,
        ),
        notes: invoice.notes ?? '',
    });

    const netAmount = Math.max(
        0,
        numberValue(data.amount) -
            numberValue(data.discount),
    );

    const submit = (event) => {
        event.preventDefault();

        put(
            route(
                'invoices.update',
                invoice.id,
            ),
        );
    };

    const inputClass =
        'mt-1 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100';

    return (
        <AppLayout title="Edit Invoice">
            <Head title="Edit Invoice" />

            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-800">
                            Edit Invoice
                        </h1>

                        <p className="mt-1 font-mono text-slate-500">
                            {invoice.invoice_no}
                        </p>
                    </div>

                    <StatusBadge
                        status={invoice.status}
                    />
                </div>

                {Object.keys(errors).length > 0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-5 py-4">
                        <p className="font-bold text-red-700">
                            Please correct the highlighted fields.
                        </p>
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-3">
                    <MiniCard
                        label="Paid Amount"
                        value={formatMoney(
                            invoice.paid_amount,
                        )}
                    />

                    <MiniCard
                        label="Current Due"
                        value={formatMoney(
                            invoice.due_amount,
                        )}
                    />

                    <MiniCard
                        label="Invoice Status"
                        value={statusLabel(
                            invoice.status,
                        )}
                    />
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6"
                >
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="mb-6 flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-100 text-xl">
                                👤
                            </div>

                            <div>
                                <h2 className="text-xl font-bold text-slate-800">
                                    Client
                                </h2>

                                <p className="text-sm text-slate-500">
                                    Client assigned to this invoice
                                </p>
                            </div>
                        </div>

                        <Field
                            label="Client"
                            error={errors.client_id}
                            required
                        >
                            <select
                                value={data.client_id}
                                onChange={(event) =>
                                    setData(
                                        'client_id',
                                        event.target.value,
                                    )
                                }
                                className={inputClass}
                            >
                                <option value="">
                                    Select Client
                                </option>

                                {clients.map((client) => (
                                    <option
                                        key={client.id}
                                        value={client.id}
                                    >
                                        {client.client_code} —{' '}
                                        {client.name}
                                    </option>
                                ))}
                            </select>
                        </Field>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="mb-6 flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100 text-xl">
                                🧾
                            </div>

                            <div>
                                <h2 className="text-xl font-bold text-slate-800">
                                    Billing Details
                                </h2>

                                <p className="text-sm text-slate-500">
                                    Update billing month, amount and discount
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-5 md:grid-cols-2">
                            <Field
                                label="Billing Month"
                                error={errors.billing_month}
                                required
                            >
                                <input
                                    type="date"
                                    value={data.billing_month}
                                    onChange={(event) =>
                                        setData(
                                            'billing_month',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>

                            <Field
                                label="Invoice Amount"
                                error={errors.amount}
                                required
                            >
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.amount}
                                    onChange={(event) =>
                                        setData(
                                            'amount',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>

                            <Field
                                label="Discount"
                                error={errors.discount}
                            >
                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={data.discount}
                                    onChange={(event) =>
                                        setData(
                                            'discount',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>

                            <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                                <p className="text-sm font-semibold text-emerald-700">
                                    Updated Net Amount
                                </p>

                                <p className="mt-2 text-3xl font-bold text-emerald-900">
                                    {formatMoney(netAmount)}
                                </p>
                            </div>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div className="mb-6 flex items-center gap-3">
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100 text-xl">
                                📅
                            </div>

                            <div>
                                <h2 className="text-xl font-bold text-slate-800">
                                    Invoice Dates
                                </h2>

                                <p className="text-sm text-slate-500">
                                    Issue date and payment deadline
                                </p>
                            </div>
                        </div>

                        <div className="grid gap-5 md:grid-cols-2">
                            <Field
                                label="Issue Date"
                                error={errors.issue_date}
                                required
                            >
                                <input
                                    type="date"
                                    value={data.issue_date}
                                    onChange={(event) =>
                                        setData(
                                            'issue_date',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>

                            <Field
                                label="Due Date"
                                error={errors.due_date}
                                required
                            >
                                <input
                                    type="date"
                                    value={data.due_date}
                                    min={data.issue_date}
                                    onChange={(event) =>
                                        setData(
                                            'due_date',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <Field
                            label="Notes (Optional)"
                            error={errors.notes}
                        >
                            <textarea
                                rows="4"
                                value={data.notes}
                                onChange={(event) =>
                                    setData(
                                        'notes',
                                        event.target.value,
                                    )
                                }
                                className={inputClass}
                            />
                        </Field>
                    </section>

                    <div className="rounded-xl border border-cyan-200 bg-cyan-50 px-5 py-4 text-sm text-cyan-800">
                        Invoice status, paid amount and due amount will be recalculated automatically after update.
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-xl bg-cyan-600 px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing
                                ? 'Updating Invoice...'
                                : 'Update Invoice'}
                        </button>

                        <Link
                            href={route('invoices.index')}
                            className="rounded-xl bg-slate-600 px-6 py-3 font-semibold text-white transition hover:bg-slate-700"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
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

function MiniCard({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-sm font-semibold text-slate-500">
                {label}
            </p>

            <p className="mt-2 text-2xl font-bold text-slate-800">
                {value}
            </p>
        </div>
    );
}

function StatusBadge({ status }) {
    const styles = {
        paid: 'bg-emerald-100 text-emerald-700',
        partial: 'bg-amber-100 text-amber-700',
        unpaid: 'bg-red-100 text-red-700',
        overdue: 'bg-rose-100 text-rose-700',
        cancelled: 'bg-slate-200 text-slate-700',
    };

    return (
        <span
            className={`rounded-full px-4 py-2 text-sm font-bold ${
                styles[status] ||
                'bg-slate-100 text-slate-700'
            }`}
        >
            ● {statusLabel(status)}
        </span>
    );
}

function statusLabel(status) {
    const labels = {
        paid: 'Paid',
        partial: 'Partial',
        unpaid: 'Unpaid',
        overdue: 'Overdue',
        cancelled: 'Cancelled',
    };

    return labels[status] || status || 'Unknown';
}

function dateValue(value) {
    return value
        ? String(value).slice(0, 10)
        : '';
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
