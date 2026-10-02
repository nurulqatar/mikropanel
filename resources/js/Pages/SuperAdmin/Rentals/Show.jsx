import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

export default function Show({
    contract,
    invoices = [],
    payments = [],
    events = [],
    tickets = [],
    notifications = [],
}) {
    const settings = useForm({
        agreed_price: Number(contract.agreed_price ?? 0),
        billing_days: Number(contract.billing_days ?? 30),
        grace_days: Number(contract.grace_days ?? 0),
        deployment_status: contract.deployment_status ?? 'live',
        trial_ends_at: toInput(contract.trial_ends_at),
        retention_until: toInput(contract.retention_until),
        auto_invoice: Boolean(contract.auto_invoice),
        auto_notify: Boolean(contract.auto_notify),
        notes: contract.notes ?? '',
    });

    const invoice = useForm({
        period_start: dateOnly(contract.expires_at),
        period_end: addDays(contract.expires_at, contract.billing_days ?? 30),
        amount: Number(contract.agreed_price ?? 0),
        discount: 0,
        issue_date: today(),
        due_date: dateOnly(contract.expires_at) || today(),
        notes: '',
    });

    const ticket = useForm({ category:'general', priority:'normal', subject:'', message:'' });
    const cancel = useForm({ retention_until: toInput(contract.retention_until), notes:'' });

    return (
        <SuperAdminLayout title="Rental Contract">
            <Head title={`Rental - ${contract.customer_name}`} />
            <div className="space-y-7">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <Link href={route('superadmin.rentals.index')} className="text-sm font-black text-cyan-700">← Rental Management</Link>
                        <h1 className="mt-2 text-3xl font-black">{contract.customer_name}</h1>
                        <div className="mt-2 text-sm text-slate-500">{serviceLabel(contract.service_type)} · {contract.plan_name || 'No plan'}</div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a href={contract.native_url} className="rounded-xl border bg-white px-4 py-2.5 text-sm font-black">Native Service Control</a>
                        <a href={contract.portal_url} target="_blank" rel="noreferrer" className="rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-black text-slate-950">Customer Status Page</a>
                    </div>
                </div>

                <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Quick label="Status" value={contract.status} />
                    <Quick label="Plan" value={contract.plan_name} />
                    <Quick label="Rental Price" value={`${contract.currency} ${money(contract.agreed_price)}`} />
                    <Quick label="Expiry" value={dateText(contract.expires_at)} />
                    <Quick label="Grace Until" value={dateText(contract.grace_until)} />
                    <Quick label="Deployment" value={contract.deployment_status} />
                    <Quick label="Trial Until" value={dateText(contract.trial_ends_at)} />
                    <Quick label="Retention Until" value={dateText(contract.retention_until)} />
                </section>

                <form
                    onSubmit={(e) => { e.preventDefault(); settings.put(route('superadmin.rentals.update', contract.id), { preserveScroll:true }); }}
                    className="rounded-3xl border bg-white p-6"
                >
                    <SectionTitle title="Commercial Rental Settings" text="Customer-specific price, billing cycle, grace, trial, retention, deployment and automation controls." />
                    <Errors errors={settings.errors} />
                    <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <Field label="Agreed Price"><input type="number" step="0.01" value={settings.data.agreed_price} onChange={(e) => settings.setData('agreed_price', e.target.value)} className={inputClass} /></Field>
                        <Field label="Billing Days"><input type="number" value={settings.data.billing_days} onChange={(e) => settings.setData('billing_days', e.target.value)} className={inputClass} /></Field>
                        <Field label="Grace Days"><input type="number" value={settings.data.grace_days} onChange={(e) => settings.setData('grace_days', e.target.value)} className={inputClass} /></Field>
                        <Field label="Deployment">
                            <select value={settings.data.deployment_status} onChange={(e) => settings.setData('deployment_status', e.target.value)} className={inputClass}>
                                {['not_started','pending_hardware','router_connected','testing','live','problem'].map((value) => <option key={value} value={value}>{value}</option>)}
                            </select>
                        </Field>
                        <Field label="Trial Ends"><input type="datetime-local" value={settings.data.trial_ends_at} onChange={(e) => settings.setData('trial_ends_at', e.target.value)} className={inputClass} /></Field>
                        <Field label="Retention Until"><input type="datetime-local" value={settings.data.retention_until} onChange={(e) => settings.setData('retention_until', e.target.value)} className={inputClass} /></Field>
                        <Check label="Auto Invoice" checked={settings.data.auto_invoice} onChange={(v) => settings.setData('auto_invoice', v)} />
                        <Check label="Expiry Notifications" checked={settings.data.auto_notify} onChange={(v) => settings.setData('auto_notify', v)} />
                    </div>
                    <Field label="Admin Notes"><textarea value={settings.data.notes} onChange={(e) => settings.setData('notes', e.target.value)} rows="3" className={`${inputClass} mt-4`} /></Field>
                    <button className="mt-5 rounded-xl bg-slate-950 px-6 py-3 font-black text-white">Save Commercial Settings</button>
                </form>

                <form
                    onSubmit={(e) => { e.preventDefault(); invoice.post(route('superadmin.rentals.invoices.store', contract.id), { preserveScroll:true, onSuccess:() => invoice.reset('discount','notes') }); }}
                    className="rounded-3xl border bg-white p-6"
                >
                    <SectionTitle title="Create Rental Invoice" text="This invoice is for your software/service rental and stays separate from the Company's end-customer billing." />
                    <Errors errors={invoice.errors} />
                    <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <Field label="Period Start"><input type="date" value={invoice.data.period_start} onChange={(e) => invoice.setData('period_start', e.target.value)} className={inputClass} /></Field>
                        <Field label="Period End"><input type="date" value={invoice.data.period_end} onChange={(e) => invoice.setData('period_end', e.target.value)} className={inputClass} /></Field>
                        <Field label="Amount"><input type="number" step="0.01" value={invoice.data.amount} onChange={(e) => invoice.setData('amount', e.target.value)} className={inputClass} /></Field>
                        <Field label="Discount"><input type="number" step="0.01" value={invoice.data.discount} onChange={(e) => invoice.setData('discount', e.target.value)} className={inputClass} /></Field>
                        <Field label="Issue Date"><input type="date" value={invoice.data.issue_date} onChange={(e) => invoice.setData('issue_date', e.target.value)} className={inputClass} /></Field>
                        <Field label="Due Date"><input type="date" value={invoice.data.due_date} onChange={(e) => invoice.setData('due_date', e.target.value)} className={inputClass} /></Field>
                    </div>
                    <Field label="Invoice Notes"><textarea value={invoice.data.notes} onChange={(e) => invoice.setData('notes', e.target.value)} rows="2" className={`${inputClass} mt-4`} /></Field>
                    <button className="mt-5 rounded-xl bg-cyan-600 px-6 py-3 font-black text-white">Create Invoice</button>
                </form>

                <section className="overflow-hidden rounded-3xl border bg-white">
                    <div className="border-b px-6 py-5"><SectionTitle title="Invoices & Manual Payments" text="Record partial or full payment against each rental invoice." /></div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-xs uppercase text-slate-400"><tr><th className="px-4 py-3">Invoice</th><th className="px-4 py-3">Period</th><th className="px-4 py-3">Amount</th><th className="px-4 py-3">Paid / Due</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Payment</th></tr></thead>
                            <tbody>
                                {invoices.map((item) => <InvoiceRow key={item.id} invoice={item} currency={contract.currency} />)}
                                {!invoices.length && <tr><td colSpan="6" className="px-4 py-10 text-center text-slate-400">No rental invoice yet.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="rounded-3xl border bg-white p-6">
                    <SectionTitle title="Payment History" text="Commercial collection history for this rental." />
                    <div className="mt-5 overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="text-left text-xs uppercase text-slate-400"><tr><th className="px-3 py-3">Date</th><th className="px-3 py-3">Amount</th><th className="px-3 py-3">Method</th><th className="px-3 py-3">Reference</th></tr></thead>
                            <tbody>
                                {payments.map((item) => <tr key={item.id} className="border-t"><td className="px-3 py-3">{item.payment_date}</td><td className="px-3 py-3 font-black">{contract.currency} {money(item.amount)}</td><td className="px-3 py-3">{item.payment_method}</td><td className="px-3 py-3">{item.reference || '—'}</td></tr>)}
                                {!payments.length && <tr><td colSpan="4" className="px-3 py-8 text-center text-slate-400">No payment recorded.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>

                <form
                    onSubmit={(e) => { e.preventDefault(); ticket.post(route('superadmin.rentals.tickets.store', contract.id), { preserveScroll:true, onSuccess:() => ticket.reset('subject','message') }); }}
                    className="rounded-3xl border bg-white p-6"
                >
                    <SectionTitle title="Customer Support / Ticket" text="Track payment, network, router, Hotel, Compliance or account issues." />
                    <Errors errors={ticket.errors} />
                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                        <Field label="Category"><select value={ticket.data.category} onChange={(e) => ticket.setData('category', e.target.value)} className={inputClass}>{['general','payment','network','router','hotel','compliance','account'].map((v) => <option key={v} value={v}>{v}</option>)}</select></Field>
                        <Field label="Priority"><select value={ticket.data.priority} onChange={(e) => ticket.setData('priority', e.target.value)} className={inputClass}>{['low','normal','high','urgent'].map((v) => <option key={v} value={v}>{v}</option>)}</select></Field>
                        <Field label="Subject"><input value={ticket.data.subject} onChange={(e) => ticket.setData('subject', e.target.value)} className={inputClass} /></Field>
                        <Field label="Message"><textarea value={ticket.data.message} onChange={(e) => ticket.setData('message', e.target.value)} rows="3" className={inputClass} /></Field>
                    </div>
                    <button className="mt-5 rounded-xl bg-violet-600 px-6 py-3 font-black text-white">Create Ticket</button>
                </form>

                <section className="rounded-3xl border bg-white p-6">
                    <SectionTitle title="Tickets" text="Support workflow and resolution history." />
                    <div className="mt-5 space-y-4">{tickets.map((item) => <Ticket key={item.id} item={item} />)}{!tickets.length && <div className="text-sm text-slate-400">No support ticket.</div>}</div>
                </section>

                <section className="grid gap-6 xl:grid-cols-2">
                    <Timeline title="Rental History" items={events.map((item) => ({ id:item.id, title:item.event_type, text:item.notes, date:item.created_at }))} />
                    <Timeline title="Rental Notifications" items={notifications.map((item) => ({ id:item.id, title:item.title, text:item.message, date:item.created_at }))} />
                </section>

                <form
                    onSubmit={(e) => { e.preventDefault(); if (!window.confirm('Cancel this rental contract? Source service data will not be deleted.')) return; cancel.post(route('superadmin.rentals.cancel', contract.id), { preserveScroll:true }); }}
                    className="rounded-3xl border border-red-200 bg-red-50 p-6"
                >
                    <SectionTitle title="Cancel Rental" text="Cancellation does not delete source service or retained Compliance data. Set retention date when records must be kept." />
                    <Errors errors={cancel.errors} />
                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                        <Field label="Retention Until"><input type="datetime-local" value={cancel.data.retention_until} onChange={(e) => cancel.setData('retention_until', e.target.value)} className={inputClass} /></Field>
                        <Field label="Cancellation Notes"><textarea value={cancel.data.notes} onChange={(e) => cancel.setData('notes', e.target.value)} rows="3" className={inputClass} /></Field>
                    </div>
                    <button disabled={contract.status === 'cancelled'} className="mt-5 rounded-xl bg-red-700 px-6 py-3 font-black text-white disabled:opacity-40">
                        {contract.status === 'cancelled' ? 'Already Cancelled' : 'Cancel Rental'}
                    </button>
                </form>
            </div>
        </SuperAdminLayout>
    );
}

function InvoiceRow({ invoice, currency }) {
    const payment = useForm({ amount:Number(invoice.due_amount ?? 0), payment_date:today(), payment_method:'Cash', reference:'', notes:'' });
    return (
        <tr className="border-t align-top">
            <td className="px-4 py-4"><div className="font-black">{invoice.invoice_no}</div><a href={route('superadmin.rentals.invoices.print', invoice.id)} target="_blank" rel="noreferrer" className="mt-1 inline-block text-xs font-black text-cyan-700">Print Invoice</a></td>
            <td className="px-4 py-4">{invoice.period_start || '—'} → {invoice.period_end || '—'}</td>
            <td className="px-4 py-4">{currency} {money(invoice.amount)}</td>
            <td className="px-4 py-4"><div>Paid: {currency} {money(invoice.paid_amount)}</div><div className="font-black text-amber-700">Due: {currency} {money(invoice.due_amount)}</div></td>
            <td className="px-4 py-4 uppercase">{invoice.status}</td>
            <td className="min-w-72 px-4 py-4">
                {Number(invoice.due_amount) > 0 ? (
                    <form onSubmit={(e) => { e.preventDefault(); payment.post(route('superadmin.rentals.payments.store', invoice.id), { preserveScroll:true, onSuccess:() => payment.reset('reference','notes') }); }} className="space-y-2">
                        <input type="number" step="0.01" value={payment.data.amount} onChange={(e) => payment.setData('amount', e.target.value)} className={smallInput} />
                        <div className="grid grid-cols-2 gap-2">
                            <input type="date" value={payment.data.payment_date} onChange={(e) => payment.setData('payment_date', e.target.value)} className={smallInput} />
                            <select value={payment.data.payment_method} onChange={(e) => payment.setData('payment_method', e.target.value)} className={smallInput}>{['Cash','Bank Transfer','Card','Ooredoo Money','Manual Adjustment'].map((v) => <option key={v} value={v}>{v}</option>)}</select>
                        </div>
                        <input value={payment.data.reference} onChange={(e) => payment.setData('reference', e.target.value)} placeholder="Reference" className={smallInput} />
                        <button className="w-full rounded-lg bg-emerald-600 px-3 py-2 font-black text-white">Record Payment</button>
                    </form>
                ) : <span className="font-black text-emerald-700">Paid</span>}
            </td>
        </tr>
    );
}

function Ticket({ item }) {
    const form = useForm({ status:item.status, assigned_to:item.assigned_to ?? '', resolution:item.resolution ?? '' });
    return (
        <div className="rounded-2xl border bg-slate-50 p-4">
            <div className="font-black">#{item.id} {item.subject}</div>
            <div className="mt-1 text-xs uppercase text-slate-500">{item.category} · {item.priority} · {item.status}</div>
            <p className="mt-3 text-sm leading-6 text-slate-600">{item.message}</p>
            <form onSubmit={(e) => { e.preventDefault(); form.put(route('superadmin.rentals.tickets.update', item.id), { preserveScroll:true }); }} className="mt-4 grid gap-3 md:grid-cols-3">
                <select value={form.data.status} onChange={(e) => form.setData('status', e.target.value)} className={smallInput}>{['open','in_progress','waiting_customer','resolved','closed'].map((v) => <option key={v} value={v}>{v}</option>)}</select>
                <input type="number" value={form.data.assigned_to} onChange={(e) => form.setData('assigned_to', e.target.value)} placeholder="Assigned user ID" className={smallInput} />
                <input value={form.data.resolution} onChange={(e) => form.setData('resolution', e.target.value)} placeholder="Resolution" className={smallInput} />
                <button className="rounded-lg bg-slate-950 px-4 py-2 font-black text-white md:col-span-3">Update Ticket</button>
            </form>
        </div>
    );
}

function Timeline({ title, items }) { return <div className="rounded-3xl border bg-white p-6"><SectionTitle title={title} /><div className="mt-5 space-y-3">{items.map((item) => <div key={item.id} className="rounded-2xl bg-slate-50 p-4"><div className="font-black">{item.title}</div>{item.text && <p className="mt-2 text-sm text-slate-600">{item.text}</p>}<div className="mt-2 text-xs text-slate-400">{dateText(item.date)}</div></div>)}{!items.length && <div className="text-sm text-slate-400">No history yet.</div>}</div></div>; }
const inputClass = 'w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5';
const smallInput = 'w-full rounded-lg border border-slate-300 bg-white px-2 py-2 text-sm';
function Quick({ label, value }) { return <div className="rounded-2xl border bg-white p-5"><div className="text-xs font-black uppercase tracking-wide text-slate-400">{label}</div><div className="mt-2 break-words font-black">{value || '—'}</div></div>; }
function SectionTitle({ title, text }) { return <div><h2 className="text-2xl font-black">{title}</h2>{text && <p className="mt-2 max-w-4xl text-sm leading-6 text-slate-500">{text}</p>}</div>; }
function Field({ label, children }) { return <label className="block"><div className="mb-1.5 text-sm font-black text-slate-700">{label}</div>{children}</label>; }
function Check({ label, checked, onChange }) { return <label className="flex items-center gap-3 rounded-xl border bg-slate-50 px-4 py-3"><input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} /><span className="font-black">{label}</span></label>; }
function Errors({ errors }) { const values = Object.values(errors ?? {}); return values.length ? <div className="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">{values.map((v,i) => <div key={i}>{v}</div>)}</div> : null; }
function serviceLabel(v) { return { company:'MAC Client & Hotspot', hotel:'Hotel Hotspot', compliance:'Network Compliance' }[v] ?? v; }
function money(v) { return Number(v ?? 0).toLocaleString(undefined, { minimumFractionDigits:2, maximumFractionDigits:2 }); }
function dateText(v) { return v ? new Date(v).toLocaleString() : '—'; }
function dateOnly(v) { if (!v) return ''; const d = new Date(v); return Number.isNaN(d.getTime()) ? '' : d.toISOString().slice(0,10); }
function toInput(v) { if (!v) return ''; const d = new Date(v); return Number.isNaN(d.getTime()) ? '' : d.toISOString().slice(0,16); }
function addDays(v, days) { if (!v) return ''; const d = new Date(v); if (Number.isNaN(d.getTime())) return ''; d.setUTCDate(d.getUTCDate() + Number(days ?? 30)); return d.toISOString().slice(0,10); }
function today() { return new Date().toISOString().slice(0,10); }
