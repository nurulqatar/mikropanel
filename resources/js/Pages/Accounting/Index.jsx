import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

export default function Index({
    filters = {},
    summary = {},
    monthlyTrend = [],
    paymentMethods = [],
    expenseCategories = [],
    invoiceStatuses = [],
    collections = [],
    expenses = [],
    receivables = [],
    transactions = [],
    clients = [],
    canViewClients = true,
}) {
    const {
        data,
        setData,
        get,
        processing,
        errors,
    } = useForm({
        preset:
            filters.preset ?? 'this_month',

        start_date:
            filters.start_date ?? '',

        end_date:
            filters.end_date ?? '',
    });

    const submitFilter = (event) => {
        event.preventDefault();

        get(route('accounting.index'), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const reportUrl = (
        routeName,
        report = 'full',
    ) => {
        return route(routeName, {
            report,
            preset: data.preset,
            start_date: data.start_date,
            end_date: data.end_date,
        });
    };

    const reports = [
        {
            key: 'profit-loss',
            title: 'Profit & Loss',
            description:
                'Collection, expenses and net profit/loss',
        },
        {
            key: 'collections',
            title: 'Collection Report',
            description:
                'All customer payments and payment methods',
        },
        {
            key: 'expenses',
            title: 'Expense Report',
            description:
                'All business expenses and categories',
        },
        {
            key: 'receivables',
            title: 'Customer Due',
            description:
                'Current client outstanding balances',
        },
        {
            key: 'transactions',
            title: 'Cash Flow',
            description:
                'Money in, money out and running balance',
        },
        {
            key: 'clients',
            title: 'All Client Details',
            description:
                'Complete customer, network, package, payment and due information',
        },
    ];

    const maxTrend = Math.max(
        1,
        ...monthlyTrend.flatMap((row) => [
            numberValue(row.collection),
            numberValue(row.expenses),
        ]),
    );

    return (
        <AppLayout title="Accounting">
            <Head title="Accounting" />

            <div className="space-y-7">
                <section className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900">
                            Accounting
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Financial reports, profit/loss,
                            collections, expenses and customer due
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <a
                            href={reportUrl(
                                'accounting.print',
                                'full',
                            )}
                            target="_blank"
                            rel="noreferrer"
                            className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-50"
                        >
                            Print Complete Report
                        </a>

                        <a
                            href={reportUrl(
                                'accounting.download',
                                'full',
                            )}
                            className="rounded-xl bg-violet-600 px-4 py-3 text-sm font-bold text-white hover:bg-violet-700"
                        >
                            Download Complete PDF
                        </a>
                    </div>
                </section>

                <form
                    onSubmit={submitFilter}
                    className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                        <Field
                            label="Report Period"
                            error={errors.preset}
                        >
                            <select
                                value={data.preset}
                                onChange={(event) =>
                                    setData(
                                        'preset',
                                        event.target.value,
                                    )
                                }
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
                            >
                                <option value="today">
                                    Today
                                </option>

                                <option value="this_month">
                                    This Month
                                </option>

                                <option value="last_month">
                                    Last Month
                                </option>

                                <option value="this_year">
                                    This Year
                                </option>

                                <option value="custom">
                                    Custom Date
                                </option>
                            </select>
                        </Field>

                        <Field
                            label="Start Date"
                            error={errors.start_date}
                        >
                            <input
                                type="date"
                                value={data.start_date}
                                onChange={(event) =>
                                    setData(
                                        'start_date',
                                        event.target.value,
                                    )
                                }
                                disabled={
                                    data.preset !== 'custom'
                                }
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100 disabled:bg-slate-100"
                            />
                        </Field>

                        <Field
                            label="End Date"
                            error={errors.end_date}
                        >
                            <input
                                type="date"
                                value={data.end_date}
                                onChange={(event) =>
                                    setData(
                                        'end_date',
                                        event.target.value,
                                    )
                                }
                                disabled={
                                    data.preset !== 'custom'
                                }
                                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100 disabled:bg-slate-100"
                            />
                        </Field>

                        <div className="xl:col-span-2">
                            <p className="mb-1 text-sm font-semibold text-slate-700">
                                Selected Range
                            </p>

                            <div className="flex h-[50px] items-center justify-between gap-3 rounded-xl bg-slate-100 px-4">
                                <span className="text-sm font-bold text-slate-700">
                                    {filters.label}
                                </span>

                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-bold text-white hover:bg-cyan-700 disabled:opacity-50"
                                >
                                    {processing
                                        ? 'Loading...'
                                        : 'View Report'}
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label="Cash Collection"
                        value={`QAR ${money(
                            summary.collection,
                        )}`}
                        description={`${summary.payment_count ?? 0} payment entries`}
                        tone="green"
                    />

                    <SummaryCard
                        label="Total Expenses"
                        value={`QAR ${money(
                            summary.expenses,
                        )}`}
                        description={`${summary.expense_count ?? 0} expense entries`}
                        tone="red"
                    />

                    <SummaryCard
                        label={
                            numberValue(
                                summary.net_profit,
                            ) >= 0
                                ? 'Net Profit'
                                : 'Net Loss'
                        }
                        value={`QAR ${money(
                            Math.abs(
                                numberValue(
                                    summary.net_profit,
                                ),
                            ),
                        )}`}
                        description={`Cash margin: ${money(
                            summary.profit_margin,
                        )}%`}
                        tone={
                            numberValue(
                                summary.net_profit,
                            ) >= 0
                                ? 'cyan'
                                : 'red'
                        }
                    />

                    <SummaryCard
                        label="Current Customer Due"
                        value={`QAR ${money(
                            summary.current_receivable,
                        )}`}
                        description={`${summary.due_client_count ?? 0} clients with due`}
                        tone="amber"
                    />

                    <SummaryCard
                        label="Gross Billed"
                        value={`QAR ${money(
                            summary.gross_billed,
                        )}`}
                        description={`${summary.invoice_count ?? 0} invoices`}
                    />

                    <SummaryCard
                        label="Invoice Discount"
                        value={`QAR ${money(
                            summary.discount,
                        )}`}
                        description="Discount in selected period"
                        tone="violet"
                    />

                    <SummaryCard
                        label="Net Billed"
                        value={`QAR ${money(
                            summary.net_billed,
                        )}`}
                        description="Gross bill minus discount"
                    />

                    <SummaryCard
                        label="Overdue Amount"
                        value={`QAR ${money(
                            summary.overdue_amount,
                        )}`}
                        description="Past due date"
                        tone="red"
                    />
                </section>

                <section>
                    <div className="mb-4">
                        <h2 className="text-xl font-bold text-slate-900">
                            Downloadable Reports
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Every report can be printed or downloaded as PDF
                        </p>
                    </div>

                    <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                        {reports
                            .filter(
                                (report) =>
                                    report.key !== 'clients'
                                    || canViewClients,
                            )
                            .map((report) => (
                            <div
                                key={report.key}
                                className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <h3 className="font-bold text-slate-900">
                                    {report.title}
                                </h3>

                                <p className="mt-2 min-h-[40px] text-sm leading-5 text-slate-500">
                                    {report.description}
                                </p>

                                <div className="mt-4 flex gap-2">
                                    <a
                                        href={reportUrl(
                                            'accounting.print',
                                            report.key,
                                        )}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-center text-sm font-bold text-slate-700 hover:bg-slate-50"
                                    >
                                        Print
                                    </a>

                                    <a
                                        href={reportUrl(
                                            'accounting.download',
                                            report.key,
                                        )}
                                        className="flex-1 rounded-lg bg-violet-600 px-3 py-2 text-center text-sm font-bold text-white hover:bg-violet-700"
                                    >
                                        PDF
                                    </a>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="grid gap-6 xl:grid-cols-3">
                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm xl:col-span-2">
                        <h2 className="text-xl font-bold text-slate-900">
                            Profit & Loss Summary
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Cash-basis calculation for {filters.label}
                        </p>

                        <div className="mt-6 overflow-x-auto">
                            <table className="min-w-full">
                                <tbody className="divide-y divide-slate-200">
                                    <AmountRow
                                        label="Cash Collection"
                                        value={summary.collection}
                                        positive
                                    />

                                    <AmountRow
                                        label="Less: Business Expenses"
                                        value={summary.expenses}
                                        negative
                                    />

                                    <AmountRow
                                        label={
                                            numberValue(
                                                summary.net_profit,
                                            ) >= 0
                                                ? 'Net Profit'
                                                : 'Net Loss'
                                        }
                                        value={Math.abs(
                                            numberValue(
                                                summary.net_profit,
                                            ),
                                        )}
                                        strong
                                        positive={
                                            numberValue(
                                                summary.net_profit,
                                            ) >= 0
                                        }
                                        negative={
                                            numberValue(
                                                summary.net_profit,
                                            ) < 0
                                        }
                                    />
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <h2 className="text-xl font-bold text-slate-900">
                            Billing Position
                        </h2>

                        <div className="mt-5 space-y-4">
                            <MiniStat
                                label="Gross Billing"
                                value={summary.gross_billed}
                            />

                            <MiniStat
                                label="Discount"
                                value={summary.discount}
                            />

                            <MiniStat
                                label="Net Billing"
                                value={summary.net_billed}
                            />

                            <MiniStat
                                label="Period Remaining Due"
                                value={summary.period_due}
                                danger
                            />
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-xl font-bold text-slate-900">
                        Monthly Financial Trend
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Collection compared with expenses
                    </p>

                    <div className="mt-6 space-y-5">
                        {monthlyTrend.map((row) => (
                            <div
                                key={row.month}
                                className="grid gap-3 md:grid-cols-[90px_1fr_180px]"
                            >
                                <div>
                                    <p className="font-bold text-slate-700">
                                        {row.label}
                                    </p>

                                    <p
                                        className={`text-xs font-bold ${
                                            numberValue(
                                                row.profit,
                                            ) >= 0
                                                ? 'text-emerald-600'
                                                : 'text-red-600'
                                        }`}
                                    >
                                        {numberValue(
                                            row.profit,
                                        ) >= 0
                                            ? 'Profit'
                                            : 'Loss'}{' '}
                                        {money(
                                            Math.abs(
                                                numberValue(
                                                    row.profit,
                                                ),
                                            ),
                                        )}
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <TrendBar
                                        value={row.collection}
                                        maximum={maxTrend}
                                        tone="green"
                                    />

                                    <TrendBar
                                        value={row.expenses}
                                        maximum={maxTrend}
                                        tone="red"
                                    />
                                </div>

                                <div className="text-right text-xs">
                                    <p className="font-bold text-emerald-600">
                                        Collection: QAR{' '}
                                        {money(
                                            row.collection,
                                        )}
                                    </p>

                                    <p className="mt-1 font-bold text-red-600">
                                        Expense: QAR{' '}
                                        {money(
                                            row.expenses,
                                        )}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                <section className="grid gap-6 xl:grid-cols-2">
                    <ReportTable
                        title="Collection by Payment Method"
                        description="How customer payments were received"
                        headers={[
                            'Method',
                            'Transactions',
                            'Amount',
                        ]}
                        empty={
                            paymentMethods.length === 0
                        }
                    >
                        {paymentMethods.map((row) => (
                            <tr key={row.name}>
                                <TableCell>
                                    {row.name}
                                </TableCell>

                                <TableCell>
                                    {
                                        row.transaction_count
                                    }
                                </TableCell>

                                <MoneyCell
                                    value={row.total}
                                    positive
                                />
                            </tr>
                        ))}
                    </ReportTable>

                    <ReportTable
                        title="Expenses by Category"
                        description="Where business money was spent"
                        headers={[
                            'Category',
                            'Transactions',
                            'Amount',
                        ]}
                        empty={
                            expenseCategories.length ===
                            0
                        }
                    >
                        {expenseCategories.map((row) => (
                            <tr key={row.name}>
                                <TableCell>
                                    {row.name}
                                </TableCell>

                                <TableCell>
                                    {
                                        row.transaction_count
                                    }
                                </TableCell>

                                <MoneyCell
                                    value={row.total}
                                    negative
                                />
                            </tr>
                        ))}
                    </ReportTable>
                </section>

                <ReportTable
                    title="Invoice Status Summary"
                    description="Billing, paid and outstanding amounts"
                    headers={[
                        'Status',
                        'Invoices',
                        'Gross',
                        'Discount',
                        'Paid',
                        'Due',
                    ]}
                    empty={
                        invoiceStatuses.length === 0
                    }
                >
                    {invoiceStatuses.map((row) => (
                        <tr key={row.status}>
                            <TableCell>
                                <StatusBadge
                                    status={row.status}
                                />
                            </TableCell>

                            <TableCell>
                                {row.invoice_count}
                            </TableCell>

                            <MoneyCell
                                value={
                                    row.gross_amount
                                }
                            />

                            <MoneyCell
                                value={
                                    row.discount_amount
                                }
                            />

                            <MoneyCell
                                value={
                                    row.paid_amount
                                }
                                positive
                            />

                            <MoneyCell
                                value={row.due_amount}
                                negative
                            />
                        </tr>
                    ))}
                </ReportTable>

                <ReportTable
                    title="Customer Due / Receivables"
                    description="Current unpaid customer balances"
                    headers={[
                        'Client',
                        'Phone',
                        'Invoices',
                        'Oldest Due',
                        'Total Due',
                        'Overdue',
                        'Action',
                    ]}
                    empty={receivables.length === 0}
                >
                    {receivables.map((row) => (
                        <tr key={row.client_id}>
                            <TableCell>
                                <Link
                                    href={route(
                                        'clients.show',
                                        row.client_id,
                                    )}
                                    className="font-bold text-cyan-700 hover:underline"
                                >
                                    {row.client_name ||
                                        '-'}
                                </Link>

                                <p className="mt-1 font-mono text-xs text-slate-400">
                                    {row.client_code ||
                                        '-'}
                                </p>
                            </TableCell>

                            <TableCell>
                                {row.phone || '-'}
                            </TableCell>

                            <TableCell>
                                {row.invoice_count}
                            </TableCell>

                            <TableCell>
                                {formatDate(
                                    row.oldest_due_date,
                                )}
                            </TableCell>

                            <MoneyCell
                                value={row.total_due}
                                negative
                            />

                            <MoneyCell
                                value={row.overdue_due}
                                negative
                            />

                            <TableCell>
                                <Link
                                    href={route(
                                        'clients.index',
                                    )}
                                    className="rounded-lg bg-orange-600 px-3 py-2 text-xs font-bold text-white hover:bg-orange-700"
                                >
                                    Receive Due
                                </Link>
                            </TableCell>
                        </tr>
                    ))}
                </ReportTable>

                <ReportTable
                    title="Collection Details"
                    description="Customer payments in selected period"
                    headers={[
                        'Date',
                        'Client',
                        'Invoice',
                        'Method',
                        'Transaction',
                        'Amount',
                    ]}
                    empty={collections.length === 0}
                >
                    {collections.map((row) => (
                        <tr key={row.id}>
                            <TableCell>
                                {formatDate(row.date)}
                            </TableCell>

                            <TableCell>
                                {row.client_name || '-'}
                                <p className="mt-1 font-mono text-xs text-slate-400">
                                    {row.client_code ||
                                        '-'}
                                </p>
                            </TableCell>

                            <TableCell>
                                {row.invoice_no || '-'}
                            </TableCell>

                            <TableCell>
                                {row.method || '-'}
                            </TableCell>

                            <TableCell>
                                {row.transaction_id ||
                                    '-'}
                            </TableCell>

                            <MoneyCell
                                value={row.amount}
                                positive
                            />
                        </tr>
                    ))}
                </ReportTable>

                <ReportTable
                    title="Expense Details"
                    description="Business expenses in selected period"
                    headers={[
                        'Date',
                        'Category',
                        'Title',
                        'Method',
                        'Notes',
                        'Amount',
                    ]}
                    empty={expenses.length === 0}
                >
                    {expenses.map((row) => (
                        <tr key={row.id}>
                            <TableCell>
                                {formatDate(row.date)}
                            </TableCell>

                            <TableCell>
                                {row.category || '-'}
                            </TableCell>

                            <TableCell>
                                {row.title || '-'}
                            </TableCell>

                            <TableCell>
                                {row.method || '-'}
                            </TableCell>

                            <TableCell>
                                {row.notes || '-'}
                            </TableCell>

                            <MoneyCell
                                value={row.amount}
                                negative
                            />
                        </tr>
                    ))}
                </ReportTable>

                <ReportTable
                    title="Cash Flow Transactions"
                    description="Money in, money out and running balance"
                    headers={[
                        'Date',
                        'Type',
                        'Description',
                        'Category',
                        'Reference',
                        'Money In',
                        'Money Out',
                        'Balance',
                    ]}
                    empty={transactions.length === 0}
                >
                    {transactions.map((row) => (
                        <tr
                            key={`${row.type}-${row.id}`}
                        >
                            <TableCell>
                                {formatDate(row.date)}
                            </TableCell>

                            <TableCell>
                                <TransactionBadge
                                    type={row.type}
                                />
                            </TableCell>

                            <TableCell>
                                {row.description ||
                                    '-'}
                            </TableCell>

                            <TableCell>
                                {row.category || '-'}
                            </TableCell>

                            <TableCell>
                                {row.reference || '-'}
                            </TableCell>

                            <MoneyCell
                                value={row.money_in}
                                positive
                            />

                            <MoneyCell
                                value={row.money_out}
                                negative
                            />

                            <MoneyCell
                                value={row.balance}
                                strong
                            />
                        </tr>
                    ))}
                </ReportTable>
            </div>
        </AppLayout>
    );
}

function Field({ label, error, children }) {
    return (
        <div>
            <label className="mb-1 block text-sm font-semibold text-slate-700">
                {label}
            </label>

            {children}

            {error && (
                <p className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}

function SummaryCard({
    label,
    value,
    description,
    tone = 'slate',
}) {
    const tones = {
        slate: 'bg-slate-100 text-slate-700',
        green: 'bg-emerald-100 text-emerald-700',
        red: 'bg-red-100 text-red-700',
        cyan: 'bg-cyan-100 text-cyan-700',
        amber: 'bg-amber-100 text-amber-700',
        violet: 'bg-violet-100 text-violet-700',
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-sm font-semibold text-slate-500">
                {label}
            </p>

            <p className="mt-2 text-2xl font-bold text-slate-900">
                {value}
            </p>

            <span
                className={`mt-3 inline-flex rounded-full px-3 py-1 text-xs font-bold ${tones[tone]}`}
            >
                {description}
            </span>
        </div>
    );
}

function AmountRow({
    label,
    value,
    positive = false,
    negative = false,
    strong = false,
}) {
    return (
        <tr
            className={
                strong ? 'bg-slate-50' : ''
            }
        >
            <td
                className={`px-5 py-4 ${
                    strong
                        ? 'text-lg font-bold text-slate-900'
                        : 'font-semibold text-slate-700'
                }`}
            >
                {label}
            </td>

            <td
                className={`px-5 py-4 text-right ${
                    strong
                        ? 'text-xl font-bold'
                        : 'font-bold'
                } ${
                    positive
                        ? 'text-emerald-600'
                        : negative
                          ? 'text-red-600'
                          : 'text-slate-800'
                }`}
            >
                QAR {money(value)}
            </td>
        </tr>
    );
}

function MiniStat({ label, value, danger = false }) {
    return (
        <div className="flex items-center justify-between rounded-xl bg-slate-50 p-4">
            <span className="text-sm font-semibold text-slate-600">
                {label}
            </span>

            <span
                className={`font-bold ${
                    danger
                        ? 'text-red-600'
                        : 'text-slate-900'
                }`}
            >
                QAR {money(value)}
            </span>
        </div>
    );
}

function TrendBar({
    value,
    maximum,
    tone,
}) {
    const percentage = Math.max(
        numberValue(value) > 0 ? 2 : 0,
        Math.min(
            100,
            (
                numberValue(value)
                / numberValue(maximum)
            ) * 100,
        ),
    );

    return (
        <div className="h-3 overflow-hidden rounded-full bg-slate-100">
            <div
                className={`h-full rounded-full ${
                    tone === 'green'
                        ? 'bg-emerald-500'
                        : 'bg-red-500'
                }`}
                style={{
                    width: `${percentage}%`,
                }}
            />
        </div>
    );
}

function ReportTable({
    title,
    description,
    headers,
    empty,
    children,
}) {
    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-200 px-6 py-5">
                <h2 className="text-lg font-bold text-slate-900">
                    {title}
                </h2>

                <p className="mt-1 text-sm text-slate-500">
                    {description}
                </p>
            </div>

            <div className="overflow-x-auto">
                <table className="min-w-full">
                    <thead className="bg-slate-50">
                        <tr>
                            {headers.map((header) => (
                                <th
                                    key={header}
                                    className="whitespace-nowrap px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500"
                                >
                                    {header}
                                </th>
                            ))}
                        </tr>
                    </thead>

                    <tbody className="divide-y divide-slate-200">
                        {empty ? (
                            <tr>
                                <td
                                    colSpan={
                                        headers.length
                                    }
                                    className="px-6 py-12 text-center text-slate-500"
                                >
                                    No records found for this report period.
                                </td>
                            </tr>
                        ) : (
                            children
                        )}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function TableCell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-700">
            {children}
        </td>
    );
}

function MoneyCell({
    value,
    positive = false,
    negative = false,
    strong = false,
}) {
    return (
        <td
            className={`whitespace-nowrap px-5 py-4 text-sm ${
                strong
                    ? 'font-black'
                    : 'font-bold'
            } ${
                positive
                    ? 'text-emerald-600'
                    : negative
                      ? 'text-red-600'
                      : 'text-slate-700'
            }`}
        >
            QAR {money(value)}
        </td>
    );
}

function StatusBadge({ status }) {
    const classes = {
        paid: 'bg-emerald-100 text-emerald-700',
        partial: 'bg-amber-100 text-amber-700',
        unpaid: 'bg-red-100 text-red-700',
        overdue: 'bg-red-100 text-red-700',
    };

    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-bold capitalize ${
                classes[status] ||
                'bg-slate-100 text-slate-700'
            }`}
        >
            {status || 'Unknown'}
        </span>
    );
}

function TransactionBadge({ type }) {
    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-bold ${
                type === 'collection'
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-red-100 text-red-700'
            }`}
        >
            {type === 'collection'
                ? 'Money In'
                : 'Money Out'}
        </span>
    );
}

function numberValue(value) {
    const number = Number(value);

    return Number.isFinite(number)
        ? number
        : 0;
}

function money(value) {
    return numberValue(value).toLocaleString(
        'en-US',
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

    const [year, month, day] = String(value)
        .slice(0, 10)
        .split('-');

    return day && month && year
        ? `${day}/${month}/${year}`
        : value;
}
