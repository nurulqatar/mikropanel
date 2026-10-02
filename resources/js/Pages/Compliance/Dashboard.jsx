import ComplianceLayout from '@/Layouts/ComplianceLayout';
import { Head } from '@inertiajs/react';

export default function Dashboard({
    organization,
    complianceUser,
    services = {},
    subscriptions = [],
    stats = {},
}) {
    return (
        <ComplianceLayout title="Compliance Dashboard">
            <Head title="Compliance Dashboard" />

            <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                <Stat
                    label="Networks"
                    value={
                        stats.networks
                        ?? 0
                    }
                />

                <Stat
                    label="Routers"
                    value={
                        stats.routers
                        ?? 0
                    }
                />

                <Stat
                    label="Collectors"
                    value={
                        stats.collectors
                        ?? 0
                    }
                />

                <Stat
                    label="Open Alerts"
                    value={
                        stats.alerts
                        ?? 0
                    }
                />
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-2">
                <ServiceCard
                    title="Compliance Logging"
                    active={
                        services.logging
                    }
                    text="Public IP and source-port attribution, NAT mapping, user/device identity correlation, browsing metadata, retention, external storage and evidence search."
                />

                <ServiceCard
                    title="Network Filtering"
                    active={
                        services.filtering
                    }
                    text="Domain, IP, server and application filtering with Blocklist mode and Allowlist / Default-Deny mode."
                />
            </div>

            <div className="mt-8 rounded-3xl border border-white/10 bg-white/[0.04] p-6">
                <div className="text-sm font-black uppercase tracking-[0.16em] text-cyan-300">
                    Organization
                </div>

                <div className="mt-3 text-2xl font-black">
                    {organization?.name}
                </div>

                <div className="mt-2 text-sm text-slate-400">
                    Account:{' '}
                    {
                        organization
                            ?.account_type
                    }
                    {' · '}
                    Legal Profile:{' '}
                    {
                        organization
                            ?.legal_profile
                    }
                    {' · '}
                    User:{' '}
                    {
                        complianceUser
                            ?.name
                    }
                    {' · '}
                    Role:{' '}
                    {
                        complianceUser
                            ?.role
                    }
                </div>
            </div>

            <div className="mt-8 rounded-3xl border border-white/10 bg-white/[0.04] p-6">
                <h2 className="text-xl font-black">
                    Active Subscriptions
                </h2>

                <div className="mt-5 space-y-3">
                    {subscriptions.length
                        === 0 && (
                        <div className="text-slate-400">
                            No active
                            Compliance
                            subscription.
                        </div>
                    )}

                    {subscriptions.map(
                        (
                            subscription,
                        ) => (
                            <div
                                key={
                                    subscription.id
                                }
                                className="rounded-2xl border border-white/10 p-4"
                            >
                                <div className="font-black">
                                    {subscription
                                        .plan
                                        ?.name
                                        || `Subscription #${subscription.id}`}
                                </div>

                                <div className="mt-1 text-sm text-slate-400">
                                    Logging:{' '}
                                    {subscription
                                        .logging_enabled
                                        ? 'Active'
                                        : 'No'}
                                    {' · '}
                                    Filtering:{' '}
                                    {subscription
                                        .filtering_enabled
                                        ? 'Active'
                                        : 'No'}
                                </div>
                            </div>
                        ),
                    )}
                </div>
            </div>
        </ComplianceLayout>
    );
}

function Stat({
    label,
    value,
}) {
    return (
        <div className="rounded-3xl border border-white/10 bg-white/[0.04] p-6">
            <div className="text-sm text-slate-400">
                {label}
            </div>

            <div className="mt-2 text-3xl font-black">
                {value}
            </div>
        </div>
    );
}

function ServiceCard({
    title,
    active,
    text,
}) {
    return (
        <div className="rounded-3xl border border-white/10 bg-white/[0.04] p-6">
            <div className="flex items-center justify-between gap-4">
                <h2 className="text-xl font-black">
                    {title}
                </h2>

                <span
                    className={`rounded-full px-3 py-1 text-xs font-black ${
                        active
                            ? 'bg-emerald-400/15 text-emerald-300'
                            : 'bg-slate-700 text-slate-300'
                    }`}
                >
                    {active
                        ? 'ACTIVE'
                        : 'NOT SUBSCRIBED'}
                </span>
            </div>

            <p className="mt-4 leading-7 text-slate-400">
                {text}
            </p>
        </div>
    );
}
