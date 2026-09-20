import ComplianceLayout from '@/Layouts/ComplianceLayout';
import { Head, router, useForm } from '@inertiajs/react';

export default function Index({ policies = [], holds = [] }) {
    const policy = useForm({ name: 'Compliance Retention', retention_days: '120', automatic_purge: true });
    const hold = useForm({ scope_type: 'organization', scope_value: 'All organization compliance records', ends_at: '' });

    return (
        <ComplianceLayout title="Retention & Legal Hold">
            <Head title="Compliance Retention" />
            <div className="grid gap-7 xl:grid-cols-2">
                <form onSubmit={(e) => { e.preventDefault(); policy.post(route('compliance.retention.policy.store')); }} className="rounded-3xl border border-white/10 bg-white/[0.04] p-6">
                    <h2 className="text-xl font-black">Retention Policy</h2>
                    <p className="mt-2 text-sm leading-6 text-slate-400">Set the period your organization requires under its applicable policy. The panel does not assume one universal period for every customer.</p>
                    <div className="mt-5 space-y-4">
                        <Field label="Policy Name" form={policy} field="name" />
                        <Field label="Retention Days" form={policy} field="retention_days" />
                        <Check label="Automatic purge after retention period" form={policy} field="automatic_purge" />
                        <button className="rounded-xl bg-cyan-400 px-5 py-3 font-black text-slate-950">Activate Policy</button>
                    </div>
                </form>

                <form onSubmit={(e) => { e.preventDefault(); hold.post(route('compliance.retention.holds.store')); }} className="rounded-3xl border border-amber-400/20 bg-amber-400/[0.06] p-6">
                    <h2 className="text-xl font-black">Legal Hold</h2>
                    <p className="mt-2 text-sm leading-6 text-slate-300">An active hold pauses automatic purge conservatively so records are not removed during an authorized investigation.</p>
                    <div className="mt-5 space-y-4">
                        <Select label="Scope" form={hold} field="scope_type" options={[
                            ['organization', 'Whole Organization'], ['case', 'Case'], ['public_ip', 'Public IP'], ['identity', 'Identity'],
                        ]} />
                        <Field label="Scope / Reference" form={hold} field="scope_value" />
                        <Field type="datetime-local" label="Optional End" form={hold} field="ends_at" />
                        <button className="rounded-xl bg-amber-300 px-5 py-3 font-black text-slate-950">Activate Hold</button>
                    </div>
                </form>
            </div>

            <div className="mt-8 grid gap-7 xl:grid-cols-2">
                <Panel title="Retention History">{policies.map((item) => <div key={item.id} className="rounded-2xl border border-white/10 p-4"><div className="font-black">{item.name}</div><div className="mt-1 text-sm text-slate-400">{item.retention_days} days · {item.is_active ? 'ACTIVE' : 'Inactive'} · Auto purge: {item.automatic_purge ? 'Yes' : 'No'}</div></div>)}</Panel>
                <Panel title="Legal Holds">{holds.map((item) => <div key={item.id} className="rounded-2xl border border-white/10 p-4"><div className="font-black">{item.scope_type}</div><div className="mt-1 break-all text-sm text-slate-400">{item.scope_value}</div><div className="mt-3 flex justify-between gap-3"><span className="text-xs font-bold uppercase text-slate-500">{item.active ? 'ACTIVE' : 'RELEASED'}</span>{Boolean(item.active) && <button type="button" onClick={() => router.post(route('compliance.retention.holds.release', item.id))} className="text-sm font-bold text-amber-300">Release Hold</button>}</div></div>)}</Panel>
            </div>
        </ComplianceLayout>
    );
}

function Field({ label, form, field, type = 'text' }) { return <label className="block"><span className="mb-2 block text-sm font-bold">{label}</span><input type={type} value={form.data[field]} onChange={(e) => form.setData(field, e.target.value)} className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3" /></label>; }
function Select({ label, form, field, options }) { return <label className="block"><span className="mb-2 block text-sm font-bold">{label}</span><select value={form.data[field]} onChange={(e) => form.setData(field, e.target.value)} className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3">{options.map(([id, text]) => <option key={id} value={id}>{text}</option>)}</select></label>; }
function Check({ label, form, field }) { return <label className="flex items-center gap-2 text-sm font-bold"><input type="checkbox" checked={Boolean(form.data[field])} onChange={(e) => form.setData(field, e.target.checked)} />{label}</label>; }
function Panel({ title, children }) { return <div className="rounded-3xl border border-white/10 bg-white/[0.04] p-6"><h2 className="text-xl font-black">{title}</h2><div className="mt-5 space-y-3">{children}</div></div>; }
