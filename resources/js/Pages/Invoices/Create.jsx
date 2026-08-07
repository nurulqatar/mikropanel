import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

export default function Create({
    clients = [],
}) {
    const today = localDateString();
    const currentMonth =
        today.slice(0, 7) + '-01';

    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        client_id: '',
        billing_month: currentMonth,
        amount: '',
        discount: 0,
        issue_date: today,
        due_date: today,
        notes: '',
    });

    const selectedClient = clients.find(
        (client) =>
            String(client.id) ===
            String(data.client_id),
    );

    const netAmount = Math.max(
        0,
        numberValue(data.amount) -
            numberValue(data.discount),
    );

    const clientChanged = (event) => {
        const clientId = event.target.value;

        const client = clients.find(
            (item) =>
                String(item.id) ===
                String(clientId),
        );

        setData({
            ...data,
            client_id: clientId,
            amount:
                client?.package?.price ??
                data.amount,
        });
    };

    const submit = (event) => {
        event.preventDefault();

        post(route('invoices.store'));
    };

    const inputClass =
        'mt-1 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-800 shadow-sm outline-none transition focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100';

    return (
        <AppLayout title="Create Invoice">
            <Head title="Create Invoice" />

            <div className="mx-auto max-w-5xl space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-800">
                        Create Invoice
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Generate a new billing invoice for a client
                    </p>
                </div>

                {Object.keys(errors).length > 0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-5 py-4">
                        <p className="font-bold text-red-700">
                            Please correct the highlighted fields.
                        </p>
                    </div>
                )}

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
                                    Client Information
                                </h2>

                                <p className="text-sm text-slate-500">
                                    Select the client receiving this invoice
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
                                onChange={clientChanged}
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
                                        {client.package
                                            ? ` — ${client.package.name}`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        {selectedClient && (
                            <div className="mt-5 grid gap-4 rounded-xl border border-cyan-200 bg-cyan-50 p-4 sm:grid-cols-3">
                                <Info
                                    label="Client Code"
                                    value={
                                        selectedClient.client_code
                                    }
                                />

                                <Info
                                    label="Package"
                                    value={
                                        selectedClient.package
                                            ?.name || '-'
                                    }
                                />

                                <Info
                                    label="Package Price"
                                    value={formatMoney(
                                        selectedClient.package
                                            ?.price,
                                    )}
                                />
                            </div>
                        )}
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
                                    Set the billing period, amount and discount
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
                                    Net Payable
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
                                    Set issue date and payment due date
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
                                placeholder="Invoice notes or billing details"
                                className={inputClass}
                            />
                        </Field>
                    </section>

                    <div className="flex flex-wrap gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-xl bg-cyan-600 px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-cyan-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {processing
                                ? 'Creating Invoice...'
                                : 'Create Invoice'}
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

function Info({ label, value }) {
    return (
        <div>
            <p className="text-xs font-semibold uppercase tracking-wide text-cyan-700">
                {label}
            </p>

            <p className="mt-1 font-bold text-slate-800">
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

function localDateString() {
    const date = new Date();
    const offset =
        date.getTimezoneOffset() * 60 * 1000;

    return new Date(date.getTime() - offset)
        .toISOString()
        .slice(0, 10);
}
