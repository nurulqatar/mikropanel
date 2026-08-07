import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

export default function Index({
    expenses = [],
    summary = {},
    flash = {},
}) {
    const deleteExpense = (expense) => {
        if (
            !confirm(
                `Delete expense "${expense.title}"?`,
            )
        ) {
            return;
        }

        router.delete(
            route('expenses.destroy', expense.id),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="Expenses">
            <Head title="Expenses" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-800">
                            Expenses
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Track company operating expenses
                        </p>
                    </div>

                    <Link
                        href={route('expenses.create')}
                        className="rounded-xl bg-cyan-600 px-5 py-3 font-semibold text-white shadow-sm hover:bg-cyan-700"
                    >
                        + Add Expense
                    </Link>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-700">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-5 py-4 text-red-700">
                        {flash.error}
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label="Total Expenses"
                        value={`QAR ${formatMoney(
                            summary.total,
                        )}`}
                        description="All recorded expenses"
                        icon="💰"
                    />

                    <SummaryCard
                        label="This Month"
                        value={`QAR ${formatMoney(
                            summary.this_month,
                        )}`}
                        description="Current month spending"
                        icon="📅"
                    />

                    <SummaryCard
                        label="Today"
                        value={`QAR ${formatMoney(
                            summary.today,
                        )}`}
                        description="Today's expenses"
                        icon="🧾"
                    />

                    <SummaryCard
                        label="Expense Records"
                        value={summary.count ?? 0}
                        description="Total transactions"
                        icon="📊"
                    />
                </div>

                {expenses.length === 0 ? (
                    <div className="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
                        <div className="text-6xl">
                            💼
                        </div>

                        <h2 className="mt-5 text-2xl font-bold text-slate-800">
                            No Expense Found
                        </h2>

                        <p className="mt-2 text-slate-500">
                            Record your first company expense.
                        </p>

                        <Link
                            href={route('expenses.create')}
                            className="mt-6 inline-flex rounded-xl bg-cyan-600 px-6 py-3 font-semibold text-white hover:bg-cyan-700"
                        >
                            Add First Expense
                        </Link>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-6 py-5">
                            <h2 className="text-lg font-bold text-slate-800">
                                Expense History
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                All recorded operating expenses
                            </p>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Expense</TableHead>
                                        <TableHead>Method</TableHead>
                                        <TableHead>Amount</TableHead>
                                        <TableHead>Recorded By</TableHead>
                                        <TableHead>Actions</TableHead>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-200">
                                    {expenses.map((expense) => (
                                        <tr
                                            key={expense.id}
                                            className="hover:bg-slate-50"
                                        >
                                            <td className="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-700">
                                                {formatDate(
                                                    expense.expense_date,
                                                )}
                                            </td>

                                            <td className="px-5 py-4">
                                                <CategoryBadge
                                                    category={
                                                        expense.category
                                                    }
                                                />
                                            </td>

                                            <td className="px-5 py-4">
                                                <div className="font-bold text-slate-800">
                                                    {expense.title}
                                                </div>

                                                {expense.notes && (
                                                    <div className="mt-1 max-w-md truncate text-xs text-slate-400">
                                                        {expense.notes}
                                                    </div>
                                                )}
                                            </td>

                                            <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                                {expense.payment_method ||
                                                    '-'}
                                            </td>

                                            <td className="whitespace-nowrap px-5 py-4 text-lg font-bold text-red-600">
                                                QAR{' '}
                                                {formatMoney(
                                                    expense.amount,
                                                )}
                                            </td>

                                            <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                                {expense.user?.name ||
                                                    '-'}
                                            </td>

                                            <td className="px-5 py-4">
                                                <div className="flex flex-wrap gap-2">
                                                    <Link
                                                        href={route(
                                                            'expenses.edit',
                                                            expense.id,
                                                        )}
                                                        className="rounded-lg bg-amber-500 px-3 py-2 text-sm font-semibold text-white hover:bg-amber-600"
                                                    >
                                                        Edit
                                                    </Link>

                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            deleteExpense(
                                                                expense,
                                                            )
                                                        }
                                                        className="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700"
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function SummaryCard({
    label,
    value,
    description,
    icon,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold text-slate-500">
                        {label}
                    </p>

                    <p className="mt-2 text-2xl font-bold text-slate-800">
                        {value}
                    </p>

                    <p className="mt-1 text-xs text-slate-400">
                        {description}
                    </p>
                </div>

                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan-50 text-2xl">
                    {icon}
                </div>
            </div>
        </div>
    );
}

function TableHead({ children }) {
    return (
        <th className="whitespace-nowrap px-5 py-4 text-left text-xs font-bold uppercase tracking-wider text-slate-500">
            {children}
        </th>
    );
}

function CategoryBadge({ category }) {
    return (
        <span className="inline-flex whitespace-nowrap rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-700">
            {category || 'Other'}
        </span>
    );
}

function numberValue(value) {
    const amount = Number(value);

    return Number.isFinite(amount)
        ? amount
        : 0;
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
    return value
        ? String(value).slice(0, 10)
        : '-';
}
