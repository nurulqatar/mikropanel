import ComplianceLayout from '@/Layouts/ComplianceLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Index({
    networks = [],
    routers = [],
    rules = [],
    signatures = [],
}) {
    const rule = useForm({
        network_id: '',
        name: '',
        rule_type: 'domain',
        action: 'block',
        target_value: '',
        priority: '100',
    });

    const app = useForm({
        name: '',
        category: '',
        domains_text: '',
        ip_ranges_text: '',
        confidence: 'limited',
    });

    const deploy = useForm({
        network_id: '',
        router_id: '',
    });

    return (
        <ComplianceLayout title="Network Filtering">
            <Head title="Compliance Filtering" />

            <div className="grid gap-7 xl:grid-cols-2">
                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        rule.post(route('compliance.filtering.rules.store'));
                    }}
                    className="rounded-3xl border border-white/10 bg-white/[0.04] p-6"
                >
                    <h2 className="text-xl font-black">Add Rule</h2>
                    <div className="mt-5 space-y-4">
                        <Select label="Network" form={rule} field="network_id" options={networks.map((x) => [x.id, `${x.name} — ${x.filter_mode}`])} />
                        <Input label="Rule Name" form={rule} field="name" />
                        <Select label="Type" form={rule} field="rule_type" options={[
                            ['domain', 'Domain'],
                            ['ip', 'IP'],
                            ['cidr', 'CIDR'],
                            ['server', 'Server'],
                            ['app', 'App Signature'],
                            ['category', 'Category'],
                            ['protocol', 'Protocol/Port'],
                            ['custom', 'Custom'],
                        ]} />
                        <Select label="Action" form={rule} field="action" options={[
                            ['block', 'Block'],
                            ['allow', 'Allow'],
                        ]} />
                        <textarea
                            rows="5"
                            value={rule.data.target_value}
                            onChange={(e) => rule.setData('target_value', e.target.value)}
                            placeholder={'example.com\n203.0.113.0/24\nor app-signature-slug'}
                            className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"
                        />
                        <Input label="Priority" form={rule} field="priority" />
                        <button className="rounded-xl bg-cyan-400 px-5 py-3 font-black text-slate-950">Save Rule</button>
                    </div>
                </form>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        app.post(route('compliance.filtering.signatures.store'));
                    }}
                    className="rounded-3xl border border-white/10 bg-white/[0.04] p-6"
                >
                    <h2 className="text-xl font-black">Third-Party App Signature</h2>
                    <div className="mt-5 space-y-4">
                        <Input label="App Name" form={app} field="name" />
                        <Input label="Category" form={app} field="category" />
                        <textarea
                            rows="4"
                            value={app.data.domains_text}
                            onChange={(e) => app.setData('domains_text', e.target.value)}
                            placeholder={'api.example.com\ncdn.example.com'}
                            className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"
                        />
                        <textarea
                            rows="4"
                            value={app.data.ip_ranges_text}
                            onChange={(e) => app.setData('ip_ranges_text', e.target.value)}
                            placeholder={'203.0.113.10\n198.51.100.0/24'}
                            className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"
                        />
                        <Select label="Confidence" form={app} field="confidence" options={[
                            ['high', 'High'],
                            ['medium', 'Medium'],
                            ['limited', 'Limited'],
                        ]} />
                        <button className="rounded-xl bg-violet-500 px-5 py-3 font-black text-white">Save Signature</button>
                    </div>
                </form>
            </div>

            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    deploy.post(route('compliance.filtering.deploy'));
                }}
                className="mt-7 rounded-3xl border border-emerald-400/20 bg-emerald-400/[0.06] p-6"
            >
                <h2 className="text-xl font-black">Deploy to MikroTik</h2>
                <div className="mt-5 grid gap-4 md:grid-cols-3">
                    <Select label="Network" form={deploy} field="network_id" options={networks.map((x) => [x.id, `${x.name} — ${x.filter_mode}`])} />
                    <Select label="Router" form={deploy} field="router_id" options={routers.map((x) => [x.id, x.name])} />
                    <div className="flex items-end">
                        <button className="w-full rounded-xl bg-emerald-400 px-5 py-3 font-black text-slate-950">Deploy Policy</button>
                    </div>
                </div>
            </form>

            <div className="mt-7 rounded-3xl border border-white/10 bg-white/[0.04] p-6">
                <h2 className="text-xl font-black">Current Rules</h2>
                <div className="mt-4 space-y-3">
                    {rules.map((x) => (
                        <div key={x.id} className="rounded-2xl border border-white/10 p-4">
                            <div className="font-black">{x.name}</div>
                            <div className="mt-1 text-sm text-slate-400">
                                {x.network?.name} · {x.action} · {x.rule_type} · {x.deployment_status}
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </ComplianceLayout>
    );
}

function Input({ label, form, field }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-bold">{label}</span>
            <input
                value={form.data[field]}
                onChange={(e) => form.setData(field, e.target.value)}
                className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"
            />
        </label>
    );
}

function Select({ label, form, field, options }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-bold">{label}</span>
            <select
                value={form.data[field]}
                onChange={(e) => form.setData(field, e.target.value)}
                className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"
            >
                <option value="">Select</option>
                {options.map(([id, text]) => (
                    <option key={id} value={id}>{text}</option>
                ))}
            </select>
        </label>
    );
}
