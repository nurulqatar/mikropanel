import ComplianceLayout from '@/Layouts/ComplianceLayout';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

export default function Index({ search = null, timezone = 'Asia/Qatar' }) {
    const [form, setForm] = useState({ public_ip: '', public_port: '', time: '', timezone, protocol: '' });

    return (
        <ComplianceLayout title="Public IP / Port Investigation">
            <Head title="Compliance Investigation" />
            <form onSubmit={(e) => { e.preventDefault(); router.get(route('compliance.investigation.index'), form, { preserveState: true, replace: true }); }} className="rounded-3xl border border-white/10 bg-white/[0.04] p-6">
                <div className="grid gap-4 md:grid-cols-5">
                    <Field label="Public IP" value={form.public_ip} onChange={(v) => setForm({ ...form, public_ip: v })} />
                    <Field label="Source Port" value={form.public_port} onChange={(v) => setForm({ ...form, public_port: v })} />
                    <Field type="datetime-local" label="Exact Time" value={form.time} onChange={(v) => setForm({ ...form, time: v })} />
                    <label><span className="mb-2 block text-sm font-bold">Protocol</span><select value={form.protocol} onChange={(e) => setForm({ ...form, protocol: e.target.value })} className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"><option value="">Any</option><option value="tcp">TCP</option><option value="udp">UDP</option></select></label>
                    <div className="flex items-end"><button className="w-full rounded-xl bg-cyan-400 px-5 py-3 font-black text-slate-950">Find Client</button></div>
                </div>
                <div className="mt-3 text-xs text-slate-500">Timezone: {form.timezone}. Reliable attribution normally requires public IP, translated source port and exact timestamp.</div>
            </form>

            {search && (
                <div className="mt-6 flex flex-wrap items-center justify-between gap-3">
                    <div className="text-sm text-slate-400">UTC search time: {search.requested_time_utc} · Matches: {search.count}</div>
                    <a href={route('compliance.investigation.csv', form)} className="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-cyan-300">Export CSV</a>
                </div>
            )}

            <div className="mt-7 space-y-4">
                {search?.results?.map((result) => (
                    <div key={result.mapping.id} className="rounded-3xl border border-white/10 bg-white/[0.04] p-6">
                        <div className="text-lg font-black">{result.attribution_level}</div>
                        <div className="mt-4 grid gap-4 md:grid-cols-4">
                            <Info label="Public" value={`${result.mapping.public_ip}:${result.mapping.public_port}`} />
                            <Info label="Private" value={`${result.mapping.private_ip}:${result.mapping.private_port || '-'}`} />
                            <Info label="MAC" value={result.identity?.mac_address || result.mapping.mac_address || '-'} />
                            <Info label="Identity" value={result.identity?.display_name || result.identity?.identity_key || 'Not registered'} />
                        </div>
                    </div>
                ))}
            </div>
        </ComplianceLayout>
    );
}
function Field({ label, value, onChange, type = 'text' }) { return <label><span className="mb-2 block text-sm font-bold">{label}</span><input type={type} value={value} onChange={(e) => onChange(e.target.value)} className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3" required /></label>; }
function Info({ label, value }) { return <div className="rounded-2xl bg-slate-900 p-4"><div className="text-xs text-slate-500">{label}</div><div className="mt-2 break-all font-bold">{value}</div></div>; }
