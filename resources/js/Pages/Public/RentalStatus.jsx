import { Head, Link, useForm } from '@inertiajs/react';

export default function RentalStatus({
    contract,
    invoices = [],
    payments = [],
    notifications = [],
    tickets = [],
    token,
}) {
    const ticket = useForm({
        category: 'general',
        priority: 'normal',
        subject: '',
        message: '',
    });

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            <Head title="Rental Status" />
            <header className="border-b bg-white">
                <div className="mx-auto flex max-w-5xl items-center justify-between px-5 py-5">
                    <Link href={route('website.home')} className="font-black">MikroPanel</Link>
                    <Link href={route('website.login-center')} className="rounded-xl border px-4 py-2 text-sm font-black">Customer Login</Link>
                </div>
            </header>

            <main className="mx-auto max-w-5xl space-y-7 px-5 py-10">
                <section className="rounded-3xl bg-slate-950 p-7 text-white">
                    <div className="text-sm font-black uppercase tracking-[0.18em] text-cyan-300">Rental Status</div>
                    <h1 className="mt-3 text-3xl font-black">{contract.customer_name}</h1>
                    <div className="mt-2 text-slate-300">{serviceLabel(contract.service_type)}</div>
                    <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <Info label="Plan" value={contract.plan_name} />
                        <Info label="Status" value={contract.status} />
                        <Info label="Expiry" value={dateText(contract.expires_at)} />
                        <Info label="Rental" value={`${contract.currency} ${money(contract.agreed_price)}`} />
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-3">
                    <Card title="Billing Cycle" value={`${contract.billing_days} days`} />
                    <Card title="Grace Until" value={dateText(contract.grace_until)} />
                    <Card title="Deployment" value={contract.deployment_status} />
                </section>

                {notifications.length > 0 && (
                    <section className="rounded-3xl border border-amber-200 bg-amber-50 p-6">
                        <h2 className="text-xl font-black text-amber-950">Rental Alerts</h2>
                        <div className="mt-4 space-y-3">
                            {notifications.map((item, index) => (
                                <div key={index} className="rounded-xl bg-white/70 p-4">
                                    <div className="font-black">{item.title}</div>
                                    <p className="mt-1 text-sm leading-6 text-slate-700">{item.message}</p>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                <section className="overflow-hidden rounded-3xl border bg-white">
                    <div className="border-b px-6 py-5"><h2 className="text-2xl font-black">Recent Rental Invoices</h2></div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-400"><tr><th className="px-4 py-3">Invoice</th><th className="px-4 py-3">Issue / Due</th><th className="px-4 py-3">Amount</th><th className="px-4 py-3">Paid</th><th className="px-4 py-3">Due</th><th className="px-4 py-3">Status</th></tr></thead>
                            <tbody>
                                {invoices.map((item) => (
                                    <tr key={item.id} className="border-t">
                                        <td className="px-4 py-4 font-black">{item.invoice_no}</td>
                                        <td className="px-4 py-4">{item.issue_date} / {item.due_date}</td>
                                        <td className="px-4 py-4">{contract.currency} {money(item.amount)}</td>
                                        <td className="px-4 py-4">{contract.currency} {money(item.paid_amount)}</td>
                                        <td className="px-4 py-4 font-black text-amber-700">{contract.currency} {money(item.due_amount)}</td>
                                        <td className="px-4 py-4 uppercase">{item.status}</td>
                                    </tr>
                                ))}
                                {!invoices.length && <tr><td colSpan="6" className="px-4 py-10 text-center text-slate-400">No rental invoice.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="rounded-3xl border bg-white p-6">
                    <h2 className="text-2xl font-black">Recent Payments</h2>
                    <div className="mt-5 grid gap-3 md:grid-cols-2">
                        {payments.map((item, index) => (
                            <div key={index} className="rounded-2xl bg-slate-50 p-4">
                                <div className="font-black text-emerald-700">{contract.currency} {money(item.amount)}</div>
                                <div className="mt-1 text-sm text-slate-600">{item.payment_date} · {item.payment_method}</div>
                            </div>
                        ))}
                        {!payments.length && <div className="text-sm text-slate-400">No payment recorded.</div>}
                    </div>
                </section>

                <form
                    onSubmit={(e) => { e.preventDefault(); ticket.post(route('rental.status.ticket', token), { preserveScroll:true, onSuccess:() => ticket.reset('subject','message') }); }}
                    className="rounded-3xl border bg-white p-6"
                >
                    <h2 className="text-2xl font-black">Support Ticket</h2>
                    <p className="mt-2 text-sm text-slate-500">Send a rental-related support request to the service operator.</p>
                    {Object.keys(ticket.errors).length > 0 && <div className="mt-4 rounded-xl bg-red-50 p-4 text-sm font-bold text-red-700">{Object.values(ticket.errors).map((v,i) => <div key={i}>{v}</div>)}</div>}
                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                        <Field label="Category"><select value={ticket.data.category} onChange={(e) => ticket.setData('category', e.target.value)} className={inputClass}>{['general','payment','network','router','hotel','compliance','account'].map((v) => <option key={v} value={v}>{v}</option>)}</select></Field>
                        <Field label="Priority"><select value={ticket.data.priority} onChange={(e) => ticket.setData('priority', e.target.value)} className={inputClass}>{['low','normal','high','urgent'].map((v) => <option key={v} value={v}>{v}</option>)}</select></Field>
                        <Field label="Subject"><input value={ticket.data.subject} onChange={(e) => ticket.setData('subject', e.target.value)} className={inputClass} /></Field>
                        <Field label="Message"><textarea value={ticket.data.message} onChange={(e) => ticket.setData('message', e.target.value)} rows="3" className={inputClass} /></Field>
                    </div>
                    <button disabled={ticket.processing} className="mt-5 rounded-xl bg-slate-950 px-6 py-3 font-black text-white disabled:opacity-50">Submit Ticket</button>
                </form>

                <section className="rounded-3xl border bg-white p-6">
                    <h2 className="text-2xl font-black">My Support Requests</h2>
                    <div className="mt-5 space-y-3">
                        {tickets.map((item) => (
                            <div key={item.id} className="rounded-2xl bg-slate-50 p-4">
                                <div className="flex flex-wrap items-center justify-between gap-3"><div className="font-black">#{item.id} {item.subject}</div><span className="rounded-full bg-white px-3 py-1 text-xs font-black uppercase">{item.status}</span></div>
                                <div className="mt-1 text-xs uppercase text-slate-500">{item.category} · {item.priority}</div>
                                {item.resolution && <p className="mt-3 text-sm text-slate-700"><strong>Resolution:</strong> {item.resolution}</p>}
                            </div>
                        ))}
                        {!tickets.length && <div className="text-sm text-slate-400">No support request yet.</div>}
                    </div>
                </section>

                <section className="rounded-3xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
                    This page shows commercial rental status. Actual network availability can also depend on the connected router, collector, storage or other service-specific requirements. Rental payment records do not silently change RouterOS configuration.
                </section>
            </main>
        </div>
    );
}

const inputClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5';
function Field({ label, children }) { return <label className="block"><div className="mb-1.5 text-sm font-black text-slate-700">{label}</div>{children}</label>; }
function Info({ label, value }) { return <div className="rounded-2xl bg-white/10 p-4"><div className="text-xs font-black uppercase tracking-wide text-slate-400">{label}</div><div className="mt-2 font-black">{value || '—'}</div></div>; }
function Card({ title, value }) { return <div className="rounded-3xl border bg-white p-6"><div className="text-xs font-black uppercase tracking-wide text-slate-400">{title}</div><div className="mt-3 text-lg font-black">{value || '—'}</div></div>; }
function serviceLabel(v) { return { company:'MAC Client & Hotspot', hotel:'Hotel Hotspot', compliance:'Network Compliance' }[v] ?? v; }
function money(v) { return Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits:2, maximumFractionDigits:2 }); }
function dateText(v) { return v ? new Date(v).toLocaleString() : '—'; }
