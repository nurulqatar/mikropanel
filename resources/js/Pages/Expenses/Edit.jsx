import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

export default function Edit({
    expense,
    categories = [],
    paymentMethods = [],
}) {
    const {
        data,
        setData,
        put,
        processing,
        errors,
    } = useForm({
        expense_date: expense.expense_date ?? '',
        category: expense.category ?? '',
        title: expense.title ?? '',
        amount: expense.amount ?? '',
        payment_method:
            expense.payment_method ?? 'Cash',
        notes: expense.notes ?? '',
    });

    const submit = (event) => {
        event.preventDefault();

        put(
            route(
                'expenses.update',
                expense.id,
            ),
        );
    };

    const inputClass =
        'mt-1 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100';

    return (
        <AppLayout title="Edit Expense">
            <Head title="Edit Expense" />

            <div className="mx-auto max-w-5xl space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-800">
                        Edit Expense
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Update {expense.title}
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6"
                >
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="mb-5 text-xl font-bold text-slate-800">
                            Expense Information
                        </h2>

                        <div className="grid gap-5 md:grid-cols-2">
                            <Field
                                label="Expense Date"
                                error={errors.expense_date}
                                required
                            >
                                <input
                                    type="date"
                                    value={data.expense_date}
                                    onChange={(event) =>
                                        setData(
                                            'expense_date',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>

                            <Field
                                label="Category"
                                error={errors.category}
                                required
                            >
                                <select
                                    value={data.category}
                                    onChange={(event) =>
                                        setData(
                                            'category',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                >
                                    <option value="">
                                        Select Category
                                    </option>

                                    {categories.map((category) => (
                                        <option
                                            key={category}
                                            value={category}
                                        >
                                            {category}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <Field
                                label="Expense Title"
                                error={errors.title}
                                required
                            >
                                <input
                                    type="text"
                                    value={data.title}
                                    onChange={(event) =>
                                        setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                    autoFocus
                                />
                            </Field>

                            <Field
                                label="Amount (QAR)"
                                error={errors.amount}
                                required
                            >
                                <input
                                    type="number"
                                    min="0.01"
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
                                label="Payment Method"
                                error={errors.payment_method}
                                required
                            >
                                <select
                                    value={data.payment_method}
                                    onChange={(event) =>
                                        setData(
                                            'payment_method',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                >
                                    {paymentMethods.map((method) => (
                                        <option
                                            key={method}
                                            value={method}
                                        >
                                            {method}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        </div>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <Field
                            label="Notes (Optional)"
                            error={errors.notes}
                        >
                            <textarea
                                rows="5"
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

                    <div className="flex flex-wrap gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-xl bg-cyan-600 px-6 py-3 font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Updating Expense...'
                                : 'Update Expense'}
                        </button>

                        <Link
                            href={route('expenses.index')}
                            className="rounded-xl bg-slate-600 px-6 py-3 font-semibold text-white hover:bg-slate-700"
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
