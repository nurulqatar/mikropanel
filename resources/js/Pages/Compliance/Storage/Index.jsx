import ComplianceLayout from '@/Layouts/ComplianceLayout';
import { Head, router, useForm } from '@inertiajs/react';

export default function Index({ targets = [], objects = [] }) {
    const form = useForm({
        name: '', driver: 'nfs', path: '', endpoint: '', bucket: '',
        region: 'us-east-1', access_key: '', secret_key: '',
        path_style: true, is_default: true,
    });
    const objectStorage = ['s3', 's3_compatible'].includes(form.data.driver);

    return (
        <ComplianceLayout title="External Log Storage">
            <Head title="Compliance Storage" />

            <div className="grid gap-7 xl:grid-cols-[.9fr_1.1fr]">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.post(route('compliance.storage.store'), { preserveScroll: true });
                    }}
                    className="rounded-3xl border border-white/10 bg-white/[0.04] p-6"
                >
                    <h2 className="text-xl font-black">Add Archive Target</h2>
                    <p className="mt-2 text-sm leading-6 text-slate-400">
                        Use a mounted NAS/NFS path, Amazon S3 or S3-compatible cloud/NAS storage.
                        Credentials are stored encrypted by the panel.
                    </p>

                    <div className="mt-5 space-y-4">
                        <Field label="Name" form={form} field="name" />
                        <Select label="Storage Type" form={form} field="driver" options={[
                            ['local', 'Mounted Local Disk'],
                            ['nfs', 'NAS / NFS Mount'],
                            ['s3', 'Amazon S3'],
                            ['s3_compatible', 'S3-Compatible Cloud / NAS'],
                        ]} />

                        {!objectStorage ? (
                            <Field label="Absolute Mounted Path" form={form} field="path" placeholder="/mnt/compliance-archive" />
                        ) : (
                            <>
                                <Field label="Endpoint" form={form} field="endpoint" placeholder="https://s3.example.com" />
                                <Field label="Bucket" form={form} field="bucket" />
                                <Field label="Region" form={form} field="region" />
                                <Field label="Access Key" form={form} field="access_key" />
                                <Field type="password" label="Secret Key" form={form} field="secret_key" />
                                <Check label="Path-style endpoint" form={form} field="path_style" />
                            </>
                        )}

                        <Check label="Use as default archive target" form={form} field="is_default" />
                        <button className="rounded-xl bg-cyan-400 px-5 py-3 font-black text-slate-950">Save Storage</button>
                    </div>
                </form>

                <div className="space-y-4">
                    {targets.map((target) => (
                        <div key={target.id} className="rounded-3xl border border-white/10 bg-white/[0.04] p-6">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <div className="text-xl font-black">{target.name}</div>
                                    <div className="mt-1 text-sm text-slate-400">
                                        {target.driver} · {target.is_default ? 'Default' : 'Secondary'}
                                    </div>
                                    {target.last_error && <div className="mt-2 text-sm text-rose-300">{target.last_error}</div>}
                                </div>
                                <span className="rounded-full bg-white/10 px-3 py-1 text-xs font-black uppercase">{target.health_status}</span>
                            </div>
                            <div className="mt-5 flex flex-wrap gap-2">
                                <button type="button" onClick={() => router.post(route('compliance.storage.test', target.id))} className="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold">Test Write/Delete</button>
                                <button type="button" onClick={() => router.delete(route('compliance.storage.destroy', target.id))} className="rounded-xl border border-rose-400/20 px-4 py-2 text-sm font-bold text-rose-300">Remove</button>
                            </div>
                        </div>
                    ))}

                    {targets.length === 0 && <div className="rounded-3xl border border-dashed border-white/15 p-8 text-slate-400">No external archive target yet.</div>}
                </div>
            </div>

            <div className="mt-8 rounded-3xl border border-white/10 bg-white/[0.04] p-6">
                <h2 className="text-xl font-black">Recent Archive Objects</h2>
                <div className="mt-5 overflow-x-auto">
                    <table className="min-w-full text-sm">
                        <thead className="text-left text-slate-400"><tr><th className="px-3 py-2">Stored</th><th className="px-3 py-2">Events</th><th className="px-3 py-2">Bytes</th><th className="px-3 py-2">SHA-256</th></tr></thead>
                        <tbody>
                            {objects.map((item) => (
                                <tr key={item.id} className="border-t border-white/10">
                                    <td className="px-3 py-3">{item.stored_at}</td>
                                    <td className="px-3 py-3">{item.event_count}</td>
                                    <td className="px-3 py-3">{item.size_bytes}</td>
                                    <td className="max-w-xs break-all px-3 py-3 font-mono text-xs text-slate-400">{item.sha256}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </ComplianceLayout>
    );
}

function Field({ label, form, field, placeholder = '', type = 'text' }) {
    return <label className="block"><span className="mb-2 block text-sm font-bold">{label}</span><input type={type} value={form.data[field]} placeholder={placeholder} onChange={(e) => form.setData(field, e.target.value)} className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3" /></label>;
}
function Select({ label, form, field, options }) {
    return <label className="block"><span className="mb-2 block text-sm font-bold">{label}</span><select value={form.data[field]} onChange={(e) => form.setData(field, e.target.value)} className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3">{options.map(([id, text]) => <option key={id} value={id}>{text}</option>)}</select></label>;
}
function Check({ label, form, field }) {
    return <label className="flex items-center gap-2 text-sm font-bold"><input type="checkbox" checked={Boolean(form.data[field])} onChange={(e) => form.setData(field, e.target.checked)} />{label}</label>;
}
