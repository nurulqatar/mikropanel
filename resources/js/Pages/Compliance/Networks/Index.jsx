import ComplianceLayout from '@/Layouts/ComplianceLayout';
import {
    Head,
    useForm,
} from '@inertiajs/react';

export default function Index({
    networks = [],
}) {
    const form = useForm({
        name: '',
        site_code: '',
        local_networks_text: '',
        filter_mode:
            'blocklist',
    });

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route(
                'compliance.networks.store',
            ),
            {
                preserveScroll:
                    true,

                onSuccess: () =>
                    form.reset(),
            },
        );
    };

    return (
        <ComplianceLayout title="Networks & Sites">
            <Head title="Compliance Networks" />

            <div className="grid gap-7 lg:grid-cols-[.8fr_1.2fr]">
                <form
                    onSubmit={submit}
                    className="rounded-3xl border border-white/10 bg-white/[0.04] p-6"
                >
                    <h2 className="text-xl font-black">
                        Add Network
                    </h2>

                    <div className="mt-5 space-y-4">
                        <Input
                            label="Network Name"
                            value={
                                form.data
                                    .name
                            }
                            onChange={(
                                value,
                            ) =>
                                form.setData(
                                    'name',
                                    value,
                                )
                            }
                        />

                        <Input
                            label="Site Code"
                            value={
                                form.data
                                    .site_code
                            }
                            onChange={(
                                value,
                            ) =>
                                form.setData(
                                    'site_code',
                                    value,
                                )
                            }
                        />

                        <label className="block">
                            <span className="mb-2 block text-sm font-bold">
                                Filtering
                                Mode
                            </span>

                            <select
                                value={
                                    form.data
                                        .filter_mode
                                }
                                onChange={(
                                    event,
                                ) =>
                                    form.setData(
                                        'filter_mode',
                                        event.target
                                            .value,
                                    )
                                }
                                className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"
                            >
                                <option value="blocklist">
                                    Blocklist —
                                    Allow by
                                    Default
                                </option>

                                <option value="allowlist">
                                    Allowlist —
                                    Block by
                                    Default
                                </option>
                            </select>
                        </label>

                        <label className="block">
                            <span className="mb-2 block text-sm font-bold">
                                Local
                                Networks /
                                CIDRs
                            </span>

                            <textarea
                                rows="5"
                                value={
                                    form.data
                                        .local_networks_text
                                }
                                onChange={(
                                    event,
                                ) =>
                                    form.setData(
                                        'local_networks_text',
                                        event.target
                                            .value,
                                    )
                                }
                                placeholder={'192.168.10.0/24\n10.20.0.0/16'}
                                className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"
                            />
                        </label>

                        <button
                            type="submit"
                            disabled={
                                form.processing
                            }
                            className="rounded-xl bg-cyan-400 px-5 py-3 font-black text-slate-950"
                        >
                            Create
                            Network
                        </button>
                    </div>
                </form>

                <div className="space-y-4">
                    {networks.map(
                        (network) => (
                            <div
                                key={
                                    network.id
                                }
                                className="rounded-3xl border border-white/10 bg-white/[0.04] p-6"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <div className="text-xl font-black">
                                            {
                                                network.name
                                            }
                                        </div>

                                        <div className="mt-1 text-sm text-slate-400">
                                            {network
                                                .site_code
                                                || 'No site code'}
                                            {' · '}
                                            {
                                                network.routers_count
                                            }
                                            {' router(s)'}
                                        </div>
                                    </div>

                                    <span className="rounded-full bg-white/10 px-3 py-1 text-xs font-black uppercase">
                                        {
                                            network.filter_mode
                                        }
                                    </span>
                                </div>
                            </div>
                        ),
                    )}

                    {networks.length
                        === 0 && (
                        <div className="rounded-3xl border border-dashed border-white/15 p-10 text-center text-slate-400">
                            No network
                            added yet.
                        </div>
                    )}
                </div>
            </div>
        </ComplianceLayout>
    );
}

function Input({
    label,
    value,
    onChange,
}) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-bold">
                {label}
            </span>

            <input
                value={value}
                onChange={(
                    event,
                ) =>
                    onChange(
                        event.target
                            .value,
                    )
                }
                className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"
            />
        </label>
    );
}
