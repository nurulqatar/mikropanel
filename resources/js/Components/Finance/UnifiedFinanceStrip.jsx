import { usePage } from '@inertiajs/react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

export default function UnifiedFinanceStrip() {
    const finance =
        usePage().props
            .unifiedFinance;

    if (!finance) {
        return null;
    }

    return (
        <section className="rounded-2xl border border-cyan-200 bg-white p-5 shadow-sm">
            <div className="mb-4">
                <h2 className="text-lg font-bold text-slate-900">
                    Unified Billing Summary
                </h2>

                <p className="text-sm text-slate-500">
                    Normal clients + Hotspot.
                    Each payment is counted once
                    from its own ledger.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Card
                    label="All-Time Collection"
                    value={
                        finance.combined_received
                    }
                    hint={`Clients QAR ${money(
                        finance.normal_received,
                    )} · Hotspot QAR ${money(
                        finance.hotspot_received,
                    )}`}
                />

                <Card
                    label="Today Collection"
                    value={
                        finance.combined_today
                    }
                    hint={`Clients QAR ${money(
                        finance.normal_today,
                    )} · Hotspot QAR ${money(
                        finance.hotspot_today,
                    )}`}
                />

                <Card
                    label="This Month"
                    value={
                        finance.combined_month
                    }
                    hint={`Clients QAR ${money(
                        finance.normal_month,
                    )} · Hotspot QAR ${money(
                        finance.hotspot_month,
                    )}`}
                />

                <Card
                    label="Total Outstanding"
                    value={
                        finance.combined_due
                    }
                    hint={`Clients QAR ${money(
                        finance.normal_due,
                    )} · Hotspot QAR ${money(
                        finance.hotspot_due,
                    )}`}
                />
            </div>
        </section>
    );
}

function Card({
    label,
    value,
    hint,
}) {
    return (
        <div className="rounded-xl bg-slate-50 p-4">
            <div className="text-xs font-bold uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-2xl font-bold text-slate-900">
                QAR {money(value)}
            </div>

            <div className="mt-1 text-xs text-slate-500">
                {hint}
            </div>
        </div>
    );
}
