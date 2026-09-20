import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({
    stats = {},
    contracts = [],
    filters = {},
    recentNotifications = [],
}) {
    const [service, setService] = useState(filters.service ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [q, setQ] = useState(filters.q ?? '');

    const apply = () => router.get(
        route('superadmin.rentals.index'),
        { service, status, q },
        { preserveState: true, replace: true },
    );

    return (
        <SuperAdminLayout title="Rental Management">
            <Head title="Rental Management" />

            <div className="space-y-7">
                <section className="rounded-3xl bg-slate-950 p-7 text-white">
                    <div className="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                        <div>
                            <div className="text-sm font-black uppercase tracking-[0.2em] text-cyan-300">
                                Commercial Rental Master
                            </div>
                            <h1 className="mt-3 text-3xl font-black">
                                Company, Hotel & Compliance Rentals
                            </h1>
                            <p className="mt-3 max-w-4xl text-sm leading-6 text-slate-300">
                                Central commercial control for customer-specific rental price, expiry, grace, trial, invoices, payments, deployment, support, alerts and audit history. Native network operations remain inside each service module.
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <button
                                type="button"
                                onClick={() => router.post(route('superadmin.rentals.sync'))}
                                className="rounded-xl bg-cyan-400 px-5 py-3 text-sm font-black text-slate-950"
                            >
                                Sync & Maintain
                            </button>
                            <a
                                href={route('superadmin.rentals.export')}
                                className="rounded-xl border border-white/20 px-5 py-3 text-sm font-black text-white"
                            >
                                Export CSV
                            </a>
                        </div>
                    </div>

                    <div className="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-8">
                        <Stat label="Total" value={stats.total} />
                        <Stat label="Active" value={stats.active} />
                        <Stat label="Expired" value={stats.expired} />
                        <Stat label="Suspended" value={stats.suspended} />
                        <Stat label="Monthly Recurring" value={`QAR ${money(stats.mrr)}`} />
                        <Stat label="Due" value={`QAR ${money(stats.due)}`} />
                        <Stat label="Collected This Month" value={`QAR ${money(stats.collected_month)}`} />
                        <Stat label="Open Tickets" value={stats.open_tickets} />
                    </div>
                </section>

                <section className="rounded-3xl border bg-white p-6">
                    <div className="grid gap-4 md:grid-cols-4">
                        <Field label="Search">
                            <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Customer or plan" className={inputClass} />
                        </Field>
                        <Field label="Service">
                            <select value={service} onChange={(e) => setService(e.target.value)} className={inputClass}>
                                <option value="">All Services</option>
                                <option value="company">MAC Client & Hotspot</option>
                                <option value="hotel">Hotel Hotspot</option>
                                <option value="compliance">Network Compliance</option>
                            </select>
                        </Field>
                        <Field label="Status">
                            <select value={status} onChange={(e) => setStatus(e.target.value)} className={inputClass}>
                                <option value="">All Status</option>
                                {['trial','active','grace','expired','suspended','pending','replaced','cancelled'].map((item) => (
                                    <option key={item} value={item}>{item}</option>
                                ))}
                            </select>
                        </Field>
                        <div className="flex items-end">
                            <button type="button" onClick={apply} className="w-full rounded-xl bg-slate-950 px-5 py-3 font-black text-white">
                                Apply Filters
                            </button>
                        </div>
                    </div>
                </section>

                <section className="overflow-hidden rounded-3xl border bg-white">
                    <div className="border-b px-6 py-5">
                        <h2 className="text-2xl font-black">Rental Contracts</h2>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-400">
                                <tr>
                                    <th className="px-4 py-3">Customer</th>
                                    <th className="px-4 py-3">Service</th>
                                    <th className="px-4 py-3">Plan / Rental</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Expiry</th>
                                    <th className="px-4 py-3">Due</th>
                                    <th className="px-4 py-3">Deployment</th>
                                    <th className="px-4 py-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {contracts.map((contract) => (
                                    <tr key={contract.id} className="border-t">
                                        <td className="px-4 py-4">
                                            <div className="font-black">{contract.customer_name}</div>
                                            <div className="mt-1 text-xs text-slate-500">
                                                #{contract.id} · source {contract.source_id}
                                                {contract.linked_service_type ? ` · add-on ${contract.linked_service_type}` : ''}
                                            </div>
                                        </td>
                                        <td className="px-4 py-4"><ServiceBadge service={contract.service_type} /></td>
                                        <td className="px-4 py-4">
                                            <div className="font-bold">{contract.plan_name || 'No plan'}</div>
                                            <div className="text-xs text-slate-500">
                                                {contract.currency} {money(contract.agreed_price)} / {contract.billing_days} days
                                            </div>
                                        </td>
                                        <td className="px-4 py-4"><Status value={contract.status} /></td>
                                        <td className="whitespace-nowrap px-4 py-4">{dateText(contract.expires_at)}</td>
                                        <td className="whitespace-nowrap px-4 py-4 font-black text-amber-700">
                                            {contract.currency} {money(contract.total_due)}
                                        </td>
                                        <td className="px-4 py-4"><Status value={contract.deployment_status} soft /></td>
                                        <td className="px-4 py-4">
                                            <div className="flex flex-wrap gap-2">
                                                <Link href={route('superadmin.rentals.show', contract.id)} className="rounded-lg bg-slate-950 px-3 py-2 text-xs font-black text-white">
                                                    Manage
                                                </Link>
                                                <a href={contract.native_url} className="rounded-lg border px-3 py-2 text-xs font-black">Native</a>
                                                <a href={contract.portal_url} target="_blank" rel="noreferrer" className="rounded-lg border px-3 py-2 text-xs font-black">Customer View</a>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {!contracts.length && (
                                    <tr><td colSpan="8" className="px-4 py-12 text-center text-slate-400">No rental contract matches the filters.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="rounded-3xl border bg-white p-6">
                    <h2 className="text-2xl font-black">Recent Rental Alerts</h2>
                    <div className="mt-5 grid gap-3 lg:grid-cols-2">
                        {recentNotifications.map((item) => (
                            <div key={item.id} className="rounded-2xl border bg-slate-50 p-4">
                                <div className="flex items-center justify-between gap-3">
                                    <div className="font-black">{item.title}</div>
                                    <Status value={item.level} soft />
                                </div>
                                <p className="mt-2 text-sm leading-6 text-slate-600">{item.message}</p>
                                <div className="mt-2 text-xs text-slate-400">
                                    {item.customer_name} · {item.service_type} · {dateText(item.created_at)}
                                </div>
                            </div>
                        ))}
                        {!recentNotifications.length && <div className="text-sm text-slate-400">No rental alerts yet.</div>}
                    </div>
                </section>
            </div>
        </SuperAdminLayout>
    );
}

const inputClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5';
function Stat({ label, value }) { return <div className="rounded-2xl bg-white/10 p-4"><div className="text-xs font-black uppercase tracking-wide text-slate-400">{label}</div><div className="mt-2 text-xl font-black">{value ?? 0}</div></div>; }
function Field({ label, children }) { return <label className="block"><div className="mb-1.5 text-sm font-black text-slate-700">{label}</div>{children}</label>; }
function ServiceBadge({ service }) { const label = { company:'MAC Client & Hotspot', hotel:'Hotel Hotspot', compliance:'Network Compliance' }[service] ?? service; return <span className="rounded-full bg-cyan-50 px-3 py-1 text-xs font-black text-cyan-700">{label}</span>; }
function Status({ value, soft = false }) { return <span className={['inline-flex rounded-full px-3 py-1 text-xs font-black uppercase', soft ? 'bg-slate-100 text-slate-600' : value === 'active' ? 'bg-emerald-50 text-emerald-700' : value === 'trial' ? 'bg-cyan-50 text-cyan-700' : value === 'grace' ? 'bg-amber-50 text-amber-700' : value === 'expired' ? 'bg-red-50 text-red-700' : value === 'suspended' ? 'bg-orange-50 text-orange-700' : 'bg-slate-100 text-slate-600'].join(' ')}>{value || 'unknown'}</span>; }
function money(value) { return Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits:2, maximumFractionDigits:2 }); }
function dateText(value) { return value ? new Date(value).toLocaleString() : '—'; }
