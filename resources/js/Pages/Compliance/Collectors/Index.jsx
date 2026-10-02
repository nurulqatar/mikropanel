import ComplianceLayout from '@/Layouts/ComplianceLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Index({
    collectors = [],
    routers = [],
    newCollector = null,
    panelUrl = '',
}) {
    const form = useForm({
        name: '',
        router_id: '',
        listen_ip: '',
        ipfix_port: '2055',
    });

    const command = newCollector
        ? `curl -fsSL '${panelUrl}/compliance-agent/install.sh' | sudo bash -s -- '${panelUrl}' '${newCollector.uuid}' '${newCollector.token}' '${newCollector.ipfix_port}'`
        : '';

    return (
        <ComplianceLayout title="Logging Collectors">
            <Head title="Compliance Collectors" />

            {newCollector && (
                <div className="mb-7 rounded-3xl border border-amber-400/30 bg-amber-400/10 p-6">
                    <div className="font-black text-amber-200">
                        One-Time Install Command
                    </div>
                    <textarea
                        readOnly
                        rows="5"
                        value={command}
                        className="mt-4 w-full rounded-xl bg-slate-950 p-4 font-mono text-xs text-cyan-200"
                    />
                </div>
            )}

            <div className="grid gap-7 lg:grid-cols-2">
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(route('compliance.collectors.store'));
                    }}
                    className="rounded-3xl border border-white/10 bg-white/[0.04] p-6"
                >
                    <h2 className="text-xl font-black">Create Collector</h2>

                    <div className="mt-5 space-y-4">
                        <Input label="Name" form={form} field="name" />

                        <label className="block">
                            <span className="mb-2 block text-sm font-bold">Router</span>
                            <select
                                value={form.data.router_id}
                                onChange={(e) => form.setData('router_id', e.target.value)}
                                className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"
                            >
                                <option value="">Assign later</option>
                                {routers.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.name} — {item.vendor}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <Input label="Collector IP reachable by router" form={form} field="listen_ip" />
                        <Input label="IPFIX UDP Port" form={form} field="ipfix_port" />

                        <button className="rounded-xl bg-cyan-400 px-5 py-3 font-black text-slate-950">
                            Create Collector
                        </button>
                    </div>
                </form>

                <div className="space-y-4">
                    {collectors.map((item) => (
                        <div
                            key={item.id}
                            className="rounded-3xl border border-white/10 bg-white/[0.04] p-6"
                        >
                            <div className="text-xl font-black">{item.name}</div>
                            <div className="mt-2 text-sm text-slate-400">
                                ID {item.id} · {item.listen_ip}:{item.ipfix_port} · {item.status}
                            </div>
                            <div className="mt-2 text-xs text-slate-500">
                                Last seen: {item.last_seen_at || 'Never'}
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
