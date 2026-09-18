import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';

const money = (value) =>
    Number(value || 0).toLocaleString(
        undefined,
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        },
    );

export default function Index({
    mode,
    today,
    month,
    managers = [],
    ledgerEntries = [],
    handovers = [],
    managerExpenses = [],
}) {
    const isOwner =
        mode === 'owner';

    const handoverForm = useForm({
        amount: '',
        handover_date: today,
        notes: '',
    });

    const submitHandover = (event) => {
        event.preventDefault();

        handoverForm.post(
            route(
                'reseller.cash.handovers.store',
            ),
            {
                preserveScroll: true,
                onSuccess: () =>
                    handoverForm.reset(
                        'amount',
                        'notes',
                    ),
            },
        );
    };

    const approveHandover = (id) => {
        if (
            !window.confirm(
                'Approve this cash handover? Manager cash will be reduced immediately.',
            )
        ) {
            return;
        }

        router.post(
            route(
                'reseller.cash.handovers.approve',
                id,
            ),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const rejectHandover = (id) => {
        const reason =
            window.prompt(
                'Reason for rejecting this handover:',
                '',
            );

        if (reason === null) {
            return;
        }

        router.post(
            route(
                'reseller.cash.handovers.reject',
                id,
            ),
            {
                reason,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const rejectExpense = (id) => {
        const reason =
            window.prompt(
                'Why is this expense being rejected?',
                '',
            );

        if (
            !reason
            || !reason.trim()
        ) {
            return;
        }

        router.post(
            route(
                'reseller.cash.expenses.reject',
                id,
            ),
            {
                reason: reason.trim(),
            },
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="Manager Cash">
            <Head title="Manager Cash" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">
                        Manager Cash
                    </h1>

                    <p className="mt-1 text-sm text-slate-500">
                        Physical cash collection,
                        expenses, handovers and
                        reconciliation · {month}
                    </p>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    {managers.map(
                        (manager) => (
                            <section
                                key={manager.id}
                                className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <h2 className="text-xl font-bold text-slate-900">
                                            {
                                                manager.name
                                            }
                                        </h2>

                                        <p className="text-sm text-slate-500">
                                            {
                                                manager.email
                                            }
                                        </p>
                                    </div>

                                    <div className="text-right">
                                        <div className="text-xs font-semibold uppercase text-slate-500">
                                            Cash in hand
                                        </div>

                                        <div className="text-3xl font-black text-emerald-700">
                                            QAR{' '}
                                            {money(
                                                manager.cash_balance,
                                            )}
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-5 grid gap-3 sm:grid-cols-3">
                                    <Stat
                                        label="Today Collection"
                                        value={
                                            manager.today_collection
                                        }
                                    />

                                    <Stat
                                        label="Today Expense"
                                        value={
                                            manager.today_expense
                                        }
                                    />

                                    <Stat
                                        label="Today Handover"
                                        value={
                                            manager.today_handover
                                        }
                                    />
                                </div>

                                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                    <Stat
                                        label="Pending Handover"
                                        value={
                                            manager.pending_handover
                                        }
                                    />

                                    <Stat
                                        label="Available to Handover"
                                        value={
                                            manager.available_to_handover
                                        }
                                    />
                                </div>

                                <div className="mt-5 rounded-xl bg-slate-50 p-4">
                                    <div className="mb-3 font-bold text-slate-800">
                                        Monthly Reconciliation
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                        <Mini
                                            label="Opening"
                                            value={
                                                manager.month_opening
                                            }
                                        />

                                        <Mini
                                            label="+ Collection"
                                            value={
                                                manager.month_collection
                                            }
                                        />

                                        <Mini
                                            label="- Expense"
                                            value={
                                                manager.month_expense
                                            }
                                        />

                                        <Mini
                                            label="- Handover"
                                            value={
                                                manager.month_handover
                                            }
                                        />

                                        <Mini
                                            label="All Credits"
                                            value={
                                                manager.month_credit
                                            }
                                        />

                                        <Mini
                                            label="All Debits"
                                            value={
                                                manager.month_debit
                                            }
                                        />

                                        <Mini
                                            label="Closing"
                                            value={
                                                manager.month_closing
                                            }
                                        />

                                        <div
                                            className={`rounded-lg p-3 ${
                                                manager.reconciliation_ok
                                                    ? 'bg-emerald-100 text-emerald-800'
                                                    : 'bg-red-100 text-red-800'
                                            }`}
                                        >
                                            <div className="text-xs font-semibold uppercase">
                                                Reconciliation
                                            </div>

                                            <div className="mt-1 font-black">
                                                {manager.reconciliation_ok
                                                    ? 'MATCHED'
                                                    : 'MISMATCH'}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        ),
                    )}
                </div>

                {!isOwner && (
                    <section className="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <h2 className="text-xl font-bold text-slate-900">
                            Submit Cash Handover
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Balance changes only after
                            Reseller Admin approves.
                        </p>

                        <form
                            onSubmit={
                                submitHandover
                            }
                            className="mt-5 grid gap-4 lg:grid-cols-4"
                        >
                            <Field
                                label="Amount"
                                error={
                                    handoverForm
                                        .errors
                                        .amount
                                }
                            >
                                <input
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    value={
                                        handoverForm
                                            .data
                                            .amount
                                    }
                                    onChange={(
                                        event,
                                    ) =>
                                        handoverForm.setData(
                                            'amount',
                                            event
                                                .target
                                                .value,
                                        )
                                    }
                                    className="w-full rounded-lg border-slate-300"
                                    required
                                />
                            </Field>

                            <Field
                                label="Handover Date"
                                error={
                                    handoverForm
                                        .errors
                                        .handover_date
                                }
                            >
                                <input
                                    type="date"
                                    max={today}
                                    value={
                                        handoverForm
                                            .data
                                            .handover_date
                                    }
                                    onChange={(
                                        event,
                                    ) =>
                                        handoverForm.setData(
                                            'handover_date',
                                            event
                                                .target
                                                .value,
                                        )
                                    }
                                    className="w-full rounded-lg border-slate-300"
                                    required
                                />
                            </Field>

                            <Field
                                label="Notes"
                                error={
                                    handoverForm
                                        .errors
                                        .notes
                                }
                            >
                                <input
                                    value={
                                        handoverForm
                                            .data
                                            .notes
                                    }
                                    onChange={(
                                        event,
                                    ) =>
                                        handoverForm.setData(
                                            'notes',
                                            event
                                                .target
                                                .value,
                                        )
                                    }
                                    className="w-full rounded-lg border-slate-300"
                                />
                            </Field>

                            <div className="flex items-end">
                                <button
                                    type="submit"
                                    disabled={
                                        handoverForm.processing
                                    }
                                    className="w-full rounded-lg bg-emerald-600 px-4 py-2.5 font-bold text-white disabled:opacity-50"
                                >
                                    Submit Handover
                                </button>
                            </div>
                        </form>
                    </section>
                )}

                <section className="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                    <Header
                        title="Cash Handovers"
                        subtitle="Pending requests reduce cash only after Admin approval."
                    />

                    <Table>
                        <thead>
                            <tr>
                                <Th>Date</Th>
                                <Th>Manager</Th>
                                <Th>Amount</Th>
                                <Th>Status</Th>
                                <Th>Notes</Th>
                                {isOwner && (
                                    <Th>Action</Th>
                                )}
                            </tr>
                        </thead>

                        <tbody>
                            {handovers.map(
                                (handover) => (
                                    <tr
                                        key={
                                            handover.id
                                        }
                                        className="border-t"
                                    >
                                        <Td>
                                            {
                                                handover.handover_date
                                            }
                                        </Td>

                                        <Td>
                                            {handover
                                                .manager
                                                ?.name ||
                                                '—'}
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {money(
                                                handover.amount,
                                            )}
                                        </Td>

                                        <Td>
                                            <Badge
                                                value={
                                                    handover.status
                                                }
                                            />
                                        </Td>

                                        <Td>
                                            {handover.notes ||
                                                handover.review_notes ||
                                                '—'}
                                        </Td>

                                        {isOwner && (
                                            <Td>
                                                {handover.status ===
                                                    'pending' && (
                                                    <div className="flex gap-2">
                                                        <button
                                                            onClick={() =>
                                                                approveHandover(
                                                                    handover.id,
                                                                )
                                                            }
                                                            className="rounded bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white"
                                                        >
                                                            Approve
                                                        </button>

                                                        <button
                                                            onClick={() =>
                                                                rejectHandover(
                                                                    handover.id,
                                                                )
                                                            }
                                                            className="rounded bg-red-600 px-3 py-1.5 text-xs font-bold text-white"
                                                        >
                                                            Reject
                                                        </button>
                                                    </div>
                                                )}
                                            </Td>
                                        )}
                                    </tr>
                                ),
                            )}

                            {handovers.length ===
                                0 && (
                                <Empty
                                    colSpan={
                                        isOwner
                                            ? 6
                                            : 5
                                    }
                                />
                            )}
                        </tbody>
                    </Table>
                </section>

                <section className="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                    <Header
                        title="Manager Expenses"
                        subtitle="Manager expenses are auto-approved. Admin rejection restores Manager cash through a reversal ledger entry."
                    />

                    <Table>
                        <thead>
                            <tr>
                                <Th>Date</Th>
                                <Th>Manager</Th>
                                <Th>Expense</Th>
                                <Th>Amount</Th>
                                <Th>Status</Th>
                                {isOwner && (
                                    <Th>Action</Th>
                                )}
                            </tr>
                        </thead>

                        <tbody>
                            {managerExpenses.map(
                                (expense) => (
                                    <tr
                                        key={
                                            expense.id
                                        }
                                        className="border-t"
                                    >
                                        <Td>
                                            {
                                                expense.expense_date
                                            }
                                        </Td>

                                        <Td>
                                            {expense
                                                .manager
                                                ?.name ||
                                                '—'}
                                        </Td>

                                        <Td>
                                            <div className="font-semibold">
                                                {
                                                    expense.title
                                                }
                                            </div>

                                            <div className="text-xs text-slate-500">
                                                {
                                                    expense.category
                                                }
                                            </div>
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {money(
                                                expense.amount,
                                            )}
                                        </Td>

                                        <Td>
                                            <Badge
                                                value={
                                                    expense.approval_status
                                                }
                                            />

                                            {expense.rejection_reason && (
                                                <div className="mt-1 text-xs text-red-600">
                                                    {
                                                        expense.rejection_reason
                                                    }
                                                </div>
                                            )}
                                        </Td>

                                        {isOwner && (
                                            <Td>
                                                {expense.approval_status !==
                                                    'rejected' && (
                                                    <button
                                                        onClick={() =>
                                                            rejectExpense(
                                                                expense.id,
                                                            )
                                                        }
                                                        className="rounded bg-red-600 px-3 py-1.5 text-xs font-bold text-white"
                                                    >
                                                        Reject Expense
                                                    </button>
                                                )}
                                            </Td>
                                        )}
                                    </tr>
                                ),
                            )}

                            {managerExpenses.length ===
                                0 && (
                                <Empty
                                    colSpan={
                                        isOwner
                                            ? 6
                                            : 5
                                    }
                                />
                            )}
                        </tbody>
                    </Table>
                </section>

                <section className="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
                    <Header
                        title="Immutable Cash Ledger"
                        subtitle="Original entries are never edited or deleted; corrections appear as reversal entries."
                    />

                    <Table>
                        <thead>
                            <tr>
                                <Th>Date</Th>
                                <Th>Manager</Th>
                                <Th>Zone</Th>
                                <Th>Type</Th>
                                <Th>Direction</Th>
                                <Th>Amount</Th>
                                <Th>Reference</Th>
                            </tr>
                        </thead>

                        <tbody>
                            {ledgerEntries.map(
                                (entry) => (
                                    <tr
                                        key={
                                            entry.id
                                        }
                                        className="border-t"
                                    >
                                        <Td>
                                            {
                                                entry.entry_date
                                            }
                                        </Td>

                                        <Td>
                                            {entry
                                                .manager
                                                ?.name ||
                                                '—'}
                                        </Td>

                                        <Td>
                                            {entry
                                                .zone
                                                ?.name ||
                                                'All / N/A'}
                                        </Td>

                                        <Td>
                                            {
                                                entry.entry_type
                                            }
                                        </Td>

                                        <Td>
                                            <span
                                                className={
                                                    entry.direction ===
                                                    'credit'
                                                        ? 'font-bold text-emerald-700'
                                                        : 'font-bold text-red-700'
                                                }
                                            >
                                                {
                                                    entry.direction
                                                }
                                            </span>
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {money(
                                                entry.amount,
                                            )}
                                        </Td>

                                        <Td>
                                            {entry.reference ||
                                                '—'}
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {ledgerEntries.length ===
                                0 && (
                                <Empty colSpan={7} />
                            )}
                        </tbody>
                    </Table>
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
        <div className="rounded-xl bg-slate-50 p-4">
            <div className="text-xs font-semibold uppercase text-slate-500">
                {label}
            </div>

            <div className="mt-1 text-xl font-black text-slate-900">
                QAR {money(value)}
            </div>
        </div>
    );
}

function Mini({
    label,
    value,
}) {
    return (
        <div className="rounded-lg bg-white p-3 ring-1 ring-slate-200">
            <div className="text-xs font-semibold text-slate-500">
                {label}
            </div>

            <div className="mt-1 font-black text-slate-800">
                QAR {money(value)}
            </div>
        </div>
    );
}

function Header({
    title,
    subtitle,
}) {
    return (
        <div className="border-b px-5 py-4">
            <h2 className="text-lg font-bold text-slate-900">
                {title}
            </h2>

            <p className="mt-1 text-sm text-slate-500">
                {subtitle}
            </p>
        </div>
    );
}

function Table({
    children,
}) {
    return (
        <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
                {children}
            </table>
        </div>
    );
}

function Th({
    children,
}) {
    return (
        <th className="whitespace-nowrap bg-slate-50 px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">
            {children}
        </th>
    );
}

function Td({
    children,
}) {
    return (
        <td className="whitespace-nowrap px-4 py-3 align-top text-slate-700">
            {children}
        </td>
    );
}

function Empty({
    colSpan,
}) {
    return (
        <tr>
            <td
                colSpan={colSpan}
                className="px-4 py-10 text-center text-slate-400"
            >
                No records yet.
            </td>
        </tr>
    );
}

function Badge({
    value,
}) {
    const cls =
        value === 'approved'
            ? 'bg-emerald-100 text-emerald-700'
            : value === 'rejected'
              ? 'bg-red-100 text-red-700'
              : 'bg-amber-100 text-amber-700';

    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold uppercase ${cls}`}
        >
            {value}
        </span>
    );
}

function Field({
    label,
    error,
    children,
}) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-semibold text-slate-700">
                {label}
            </span>

            {children}

            {error && (
                <span className="mt-1 block text-xs text-red-600">
                    {error}
                </span>
            )}
        </label>
    );
}
