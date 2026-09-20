import ComplianceLayout from '@/Layouts/ComplianceLayout';
import {
    Head,
    useForm,
} from '@inertiajs/react';

export default function Index({
    routers = [],
    networks = [],
    supportedVendors = [],
}) {
    const form = useForm({
        network_id: '',
        name: '',
        vendor: 'mikrotik',
        host: '',
        management_port:
            '8728',
        management_protocol:
            'api',
        api_username: '',
        password: '',
        notes: '',
    });

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route(
                'compliance.routers.store',
            ),
            {
                preserveScroll:
                    true,

                onSuccess: () =>
                    form.reset(
                        'name',
                        'host',
                        'api_username',
                        'password',
                        'notes',
                    ),
            },
        );
    };

    return (
        <ComplianceLayout title="Router & Gateway Onboarding">
            <Head title="Compliance Routers" />

            <div className="rounded-3xl border border-cyan-400/20 bg-cyan-400/[0.06] p-6">
                <div className="text-sm font-black uppercase tracking-[0.18em] text-cyan-300">
                    Universal Router
                    Onboarding
                </div>

                <p className="mt-3 max-w-4xl leading-7 text-slate-300">
                    Register MikroTik,
                    OpenWrt, pfSense,
                    OPNsense, Linux
                    Gateway or another
                    router. Capability
                    detection and automatic
                    configuration are added
                    in the next deployment
                    block.
                </p>
            </div>

            <div className="mt-7 grid gap-7 xl:grid-cols-[.85fr_1.15fr]">
                <form
                    onSubmit={submit}
                    className="rounded-3xl border border-white/10 bg-white/[0.04] p-6"
                >
                    <h2 className="text-xl font-black">
                        Add Router /
                        Gateway
                    </h2>

                    <div className="mt-5 space-y-4">
                        <label className="block">
                            <span className="mb-2 block text-sm font-bold">
                                Network /
                                Site
                            </span>

                            <select
                                value={
                                    form.data
                                        .network_id
                                }
                                onChange={(
                                    event,
                                ) =>
                                    form.setData(
                                        'network_id',
                                        event.target
                                            .value,
                                    )
                                }
                                className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"
                            >
                                <option value="">
                                    No Network
                                </option>

                                {networks.map(
                                    (
                                        network,
                                    ) => (
                                        <option
                                            key={
                                                network.id
                                            }
                                            value={
                                                network.id
                                            }
                                        >
                                            {
                                                network.name
                                            }
                                        </option>
                                    ),
                                )}
                            </select>
                        </label>

                        <Input
                            label="Router Name"
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

                        <label className="block">
                            <span className="mb-2 block text-sm font-bold">
                                Vendor
                            </span>

                            <select
                                value={
                                    form.data
                                        .vendor
                                }
                                onChange={(
                                    event,
                                ) =>
                                    form.setData(
                                        'vendor',
                                        event.target
                                            .value,
                                    )
                                }
                                className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"
                            >
                                {supportedVendors.map(
                                    (
                                        vendor,
                                    ) => (
                                        <option
                                            key={
                                                vendor.value
                                            }
                                            value={
                                                vendor.value
                                            }
                                        >
                                            {
                                                vendor.label
                                            }
                                            {' — '}
                                            {
                                                vendor.mode
                                            }
                                        </option>
                                    ),
                                )}
                            </select>
                        </label>

                        <Input
                            label="Host / IP"
                            value={
                                form.data
                                    .host
                            }
                            onChange={(
                                value,
                            ) =>
                                form.setData(
                                    'host',
                                    value,
                                )
                            }
                        />

                        <Input
                            label="Management Port"
                            value={
                                form.data
                                    .management_port
                            }
                            onChange={(
                                value,
                            ) =>
                                form.setData(
                                    'management_port',
                                    value,
                                )
                            }
                        />

                        <label className="block">
                            <span className="mb-2 block text-sm font-bold">
                                Connection
                                Method
                            </span>

                            <select
                                value={
                                    form.data
                                        .management_protocol
                                }
                                onChange={(
                                    event,
                                ) =>
                                    form.setData(
                                        'management_protocol',
                                        event.target
                                            .value,
                                    )
                                }
                                className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3"
                            >
                                <option value="api">
                                    API
                                </option>
                                <option value="https">
                                    HTTPS API
                                </option>
                                <option value="ssh">
                                    SSH
                                </option>
                                <option value="agent">
                                    Agent
                                </option>
                                <option value="syslog">
                                    Syslog
                                </option>
                                <option value="manual">
                                    Guided
                                    Manual
                                </option>
                            </select>
                        </label>

                        <Input
                            label="Username"
                            value={
                                form.data
                                    .api_username
                            }
                            onChange={(
                                value,
                            ) =>
                                form.setData(
                                    'api_username',
                                    value,
                                )
                            }
                        />

                        <Input
                            type="password"
                            label="Password / Secret"
                            value={
                                form.data
                                    .password
                            }
                            onChange={(
                                value,
                            ) =>
                                form.setData(
                                    'password',
                                    value,
                                )
                            }
                        />

                        <button
                            type="submit"
                            disabled={
                                form.processing
                            }
                            className="rounded-xl bg-cyan-400 px-5 py-3 font-black text-slate-950"
                        >
                            Register
                            Router
                        </button>
                    </div>
                </form>

                <div className="space-y-4">
                    {routers.map(
                        (item) => (
                            <div
                                key={
                                    item.id
                                }
                                className="rounded-3xl border border-white/10 bg-white/[0.04] p-6"
                            >
                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <div className="text-xl font-black">
                                            {
                                                item.name
                                            }
                                        </div>

                                        <div className="mt-1 text-sm text-slate-400">
                                            {
                                                item.vendor
                                            }
                                            {' · '}
                                            {
                                                item.host
                                            }
                                            {' · '}
                                            {item
                                                .network
                                                ?.name
                                                || 'No site'}
                                        </div>
                                    </div>

                                    <span className="rounded-full bg-amber-400/10 px-3 py-1 text-xs font-black uppercase text-amber-300">
                                        {
                                            item.connection_status
                                        }
                                    </span>
                                </div>
                            </div>
                        ),
                    )}

                    {routers.length
                        === 0 && (
                        <div className="rounded-3xl border border-dashed border-white/15 p-10 text-center text-slate-400">
                            No router
                            registered yet.
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
    type = 'text',
}) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-bold">
                {label}
            </span>

            <input
                type={type}
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
