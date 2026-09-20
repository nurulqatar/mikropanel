import HotelLayout from '@/Layouts/HotelLayout';
import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';
import {
    useState,
} from 'react';

const inputClass =
    'w-full rounded-xl border-slate-300';

const defaults = {
    name: '',
    host: '',
    api_port: 8728,
    username: 'admin',
    password: '',
    use_ssl: false,
    enabled: true,

    guest_interface:
        'bridge',

    guest_gateway_cidr:
        '10.55.0.1/24',

    guest_pool_start:
        '10.55.0.10',

    guest_pool_end:
        '10.55.0.254',

    hotspot_server_name:
        'hotel-hotspot',

    hotspot_profile_name:
        'hotel-guest-profile',

    dns_name:
        'guest.wifi',

    notes: '',
};

export default function Index({
    routers = [],
    quota = {},
}) {
    const [
        editingId,
        setEditingId,
    ] = useState(null);

    const [
        copiedId,
        setCopiedId,
    ] = useState(null);

    const form =
        useForm({
            ...defaults,
        });

    const canAdd =
        Boolean(
            quota.usable,
        )
        && (
            quota.router_unlimited
            || Number(
                quota.router_usage
                ?? 0,
            )
                < Number(
                    quota.router_limit
                    ?? 0,
                )
        );

    const resetForm = () => {
        setEditingId(
            null,
        );

        form.setData({
            ...defaults,
        });

        form.clearErrors();
    };

    const editRouter = (
        item,
    ) => {
        setEditingId(
            item.id,
        );

        form.setData({
            name:
                item.name ?? '',

            host:
                item.host ?? '',

            api_port:
                item.api_port
                ?? 8728,

            username:
                item.username
                ?? 'admin',

            password: '',

            use_ssl:
                Boolean(
                    item.use_ssl,
                ),

            enabled:
                Boolean(
                    item.enabled,
                ),

            guest_interface:
                item.guest_interface
                ?? 'bridge',

            guest_gateway_cidr:
                item.guest_gateway_cidr
                ?? '10.55.0.1/24',

            guest_pool_start:
                item.guest_pool_start
                ?? '10.55.0.10',

            guest_pool_end:
                item.guest_pool_end
                ?? '10.55.0.254',

            hotspot_server_name:
                item.hotspot_server_name
                ?? 'hotel-hotspot',

            hotspot_profile_name:
                item.hotspot_profile_name
                ?? 'hotel-guest-profile',

            dns_name:
                item.dns_name
                ?? 'guest.wifi',

            notes:
                item.notes
                ?? '',
        });

        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        });
    };

    const submit = (
        event,
    ) => {
        event.preventDefault();

        if (editingId) {
            form.put(
                route(
                    'hotel.routers.update',
                    editingId,
                ),
                {
                    preserveScroll:
                        true,

                    onSuccess:
                        resetForm,
                },
            );

            return;
        }

        form.post(
            route(
                'hotel.routers.store',
            ),
            {
                preserveScroll:
                    true,

                onSuccess:
                    resetForm,
            },
        );
    };

    const copyCommand =
        async (
            item,
        ) => {
            if (
                !item.setup_command
            ) {
                return;
            }

            await navigator
                .clipboard
                .writeText(
                    item.setup_command,
                );

            setCopiedId(
                item.id,
            );

            setTimeout(
                () =>
                    setCopiedId(
                        null,
                    ),
                1800,
            );
        };

    return (
        <HotelLayout title="MikroTik Routers">
            <Head title="MikroTik Routers" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black">
                            MikroTik Routers
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Manage all Hotel Hotspot routers from one panel.
                        </p>
                    </div>

                    <div className="rounded-full bg-indigo-50 px-4 py-2 text-sm font-black text-indigo-700">
                        {quota.router_usage ?? 0}
                        {' / '}
                        {quota.router_unlimited
                            ? 'Unlimited'
                            : quota.router_limit
                                ?? 0}
                        {' Routers'}
                    </div>
                </div>

                {!quota.usable && (
                    <div className="rounded-2xl border border-red-200 bg-red-50 p-5 font-bold text-red-700">
                        Hotel subscription is inactive or expired.
                    </div>
                )}

                {!canAdd
                    && quota.usable
                    && !editingId && (
                    <div className="rounded-2xl border border-amber-200 bg-amber-50 p-5 font-bold text-amber-800">
                        Your Hotel MikroTik router limit has been reached.
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="rounded-2xl border bg-white p-6 shadow-sm"
                >
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-xl font-black">
                                {editingId
                                    ? 'Edit MikroTik Router'
                                    : 'Add MikroTik Router'}
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                API password is stored encrypted.
                            </p>
                        </div>

                        {editingId && (
                            <button
                                type="button"
                                onClick={
                                    resetForm
                                }
                                className="rounded-xl bg-slate-100 px-4 py-2 text-sm font-black"
                            >
                                Cancel Edit
                            </button>
                        )}
                    </div>

                    <div className="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        <Input
                            label="Router Name"
                            value={
                                form.data.name
                            }
                            onChange={(v) =>
                                form.setData(
                                    'name',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Router IPv4"
                            placeholder="10.10.10.2"
                            value={
                                form.data.host
                            }
                            onChange={(v) =>
                                form.setData(
                                    'host',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="API Port"
                            type="number"
                            value={
                                form.data
                                    .api_port
                            }
                            onChange={(v) =>
                                form.setData(
                                    'api_port',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="API Username"
                            value={
                                form.data
                                    .username
                            }
                            onChange={(v) =>
                                form.setData(
                                    'username',
                                    v,
                                )
                            }
                        />

                        <Input
                            label={
                                editingId
                                    ? 'API Password (blank = keep current)'
                                    : 'API Password'
                            }
                            type="password"
                            value={
                                form.data
                                    .password
                            }
                            onChange={(v) =>
                                form.setData(
                                    'password',
                                    v,
                                )
                            }
                        />

                        <label className="flex items-center gap-3 rounded-xl bg-slate-50 p-4">
                            <input
                                type="checkbox"
                                checked={
                                    form.data
                                        .use_ssl
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'use_ssl',
                                        e.target.checked,
                                    )
                                }
                                className="rounded"
                            />

                            <span className="font-bold">
                                API SSL
                            </span>
                        </label>

                        <label className="flex items-center gap-3 rounded-xl bg-slate-50 p-4">
                            <input
                                type="checkbox"
                                checked={
                                    form.data
                                        .enabled
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'enabled',
                                        e.target.checked,
                                    )
                                }
                                className="rounded"
                            />

                            <span className="font-bold">
                                Enabled
                            </span>
                        </label>
                    </div>

                    <div className="my-7 border-t" />

                    <div>
                        <h3 className="text-lg font-black">
                            Guest Hotspot Network
                        </h3>

                        <p className="mt-1 text-sm text-slate-500">
                            The panel generates the MikroTik base setup command from these values.
                        </p>
                    </div>

                    <div className="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        <Input
                            label="Guest Interface"
                            value={
                                form.data
                                    .guest_interface
                            }
                            onChange={(v) =>
                                form.setData(
                                    'guest_interface',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Gateway / CIDR"
                            value={
                                form.data
                                    .guest_gateway_cidr
                            }
                            onChange={(v) =>
                                form.setData(
                                    'guest_gateway_cidr',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="DHCP Pool Start"
                            value={
                                form.data
                                    .guest_pool_start
                            }
                            onChange={(v) =>
                                form.setData(
                                    'guest_pool_start',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="DHCP Pool End"
                            value={
                                form.data
                                    .guest_pool_end
                            }
                            onChange={(v) =>
                                form.setData(
                                    'guest_pool_end',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Hotspot Server"
                            value={
                                form.data
                                    .hotspot_server_name
                            }
                            onChange={(v) =>
                                form.setData(
                                    'hotspot_server_name',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Hotspot Profile"
                            value={
                                form.data
                                    .hotspot_profile_name
                            }
                            onChange={(v) =>
                                form.setData(
                                    'hotspot_profile_name',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Hotspot DNS Name"
                            value={
                                form.data
                                    .dns_name
                            }
                            onChange={(v) =>
                                form.setData(
                                    'dns_name',
                                    v,
                                )
                            }
                        />
                    </div>

                    <textarea
                        rows="3"
                        value={
                            form.data.notes
                        }
                        onChange={(e) =>
                            form.setData(
                                'notes',
                                e.target.value,
                            )
                        }
                        placeholder="Optional router notes"
                        className={`${inputClass} mt-5`}
                    />

                    {Object.keys(
                        form.errors,
                    ).length > 0 && (
                        <div className="mt-5 rounded-xl bg-red-50 p-4 text-sm font-bold text-red-700">
                            {Object.values(
                                form.errors,
                            ).map(
                                (
                                    error,
                                    index,
                                ) => (
                                    <div
                                        key={
                                            index
                                        }
                                    >
                                        {error}
                                    </div>
                                ),
                            )}
                        </div>
                    )}

                    <button
                        type="submit"
                        disabled={
                            form.processing
                            || (
                                !editingId
                                && !canAdd
                            )
                        }
                        className="mt-5 rounded-xl bg-emerald-600 px-6 py-3 font-black text-white disabled:opacity-50"
                    >
                        {editingId
                            ? 'Update MikroTik'
                            : 'Add MikroTik'}
                    </button>
                </form>

                <div className="space-y-5">
                    {routers.map(
                        (item) => (
                            <section
                                key={
                                    item.id
                                }
                                className="rounded-2xl border bg-white p-6 shadow-sm"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <div className="flex flex-wrap items-center gap-3">
                                            <h2 className="text-xl font-black">
                                                {
                                                    item.name
                                                }
                                            </h2>

                                            <Status
                                                value={
                                                    item.status
                                                }
                                            />

                                            {!item.enabled && (
                                                <span className="rounded-full bg-slate-200 px-3 py-1 text-xs font-black text-slate-600">
                                                    Disabled
                                                </span>
                                            )}
                                        </div>

                                        <div className="mt-1 font-mono text-sm text-slate-500">
                                            {item.host}
                                            :
                                            {
                                                item.api_port
                                            }
                                        </div>

                                        {item.router_identity && (
                                            <div className="mt-2 text-sm text-slate-600">
                                                Identity:{' '}
                                                <strong>
                                                    {
                                                        item.router_identity
                                                    }
                                                </strong>

                                                {' · RouterOS '}

                                                {
                                                    item.routeros_version
                                                    ?? '-'
                                                }

                                                {' · '}

                                                {
                                                    item.architecture
                                                    ?? '-'
                                                }
                                            </div>
                                        )}

                                        {item.last_tested_at && (
                                            <div className="mt-1 text-xs text-slate-400">
                                                Last tested:{' '}

                                                {new Date(
                                                    item.last_tested_at,
                                                ).toLocaleString()}
                                            </div>
                                        )}
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            disabled={
                                                !item.enabled
                                            }
                                            onClick={() =>
                                                router.post(
                                                    route(
                                                        'hotel.routers.test',
                                                        item.id,
                                                    ),
                                                    {},
                                                    {
                                                        preserveScroll:
                                                            true,
                                                    },
                                                )
                                            }
                                            className="rounded-lg bg-emerald-50 px-4 py-2 text-sm font-black text-emerald-700 disabled:opacity-40"
                                        >
                                            Test Connection
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                editRouter(
                                                    item,
                                                )
                                            }
                                            className="rounded-lg bg-amber-50 px-4 py-2 text-sm font-black text-amber-700"
                                        >
                                            Edit
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() => {
                                                if (
                                                    window.confirm(
                                                        `Remove ${item.name} from this Hotel?`,
                                                    )
                                                ) {
                                                    router.delete(
                                                        route(
                                                            'hotel.routers.destroy',
                                                            item.id,
                                                        ),
                                                        {
                                                            preserveScroll:
                                                                true,
                                                        },
                                                    );
                                                }
                                            }}
                                            className="rounded-lg bg-red-50 px-4 py-2 text-sm font-black text-red-700"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>

                                {item.last_error && (
                                    <div className="mt-4 rounded-xl bg-red-50 p-4 text-sm font-bold text-red-700">
                                        {
                                            item.last_error
                                        }
                                    </div>
                                )}

                                <div className="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                    <Info
                                        label="Guest Interface"
                                        value={
                                            item.guest_interface
                                        }
                                    />

                                    <Info
                                        label="Gateway"
                                        value={
                                            item.guest_gateway_cidr
                                        }
                                    />

                                    <Info
                                        label="DHCP Pool"
                                        value={
                                            `${item.guest_pool_start} - ${item.guest_pool_end}`
                                        }
                                    />

                                    <Info
                                        label="Guest Portal"
                                        value={
                                            item.portal_url
                                            ?? '-'
                                        }
                                    />
                                </div>

                                <details className="mt-5 overflow-hidden rounded-xl border bg-slate-50">
                                    <summary className="cursor-pointer px-4 py-3 font-black">
                                        MikroTik Auto Setup Command
                                    </summary>

                                    <div className="border-t p-4">
                                        {item.setup_error ? (
                                            <div className="rounded-xl bg-red-50 p-4 font-bold text-red-700">
                                                {
                                                    item.setup_error
                                                }
                                            </div>
                                        ) : (
                                            <>
                                                <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                                                    <p className="max-w-2xl text-sm text-slate-500">
                                                        Review the guest interface and IP range, then paste this command into the correct MikroTik terminal.
                                                    </p>

                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            copyCommand(
                                                                item,
                                                            )
                                                        }
                                                        className="rounded-lg bg-slate-900 px-4 py-2 text-sm font-black text-white"
                                                    >
                                                        {copiedId
                                                            === item.id
                                                            ? 'Copied'
                                                            : 'Copy Command'}
                                                    </button>
                                                </div>

                                                <pre
                                                    dir="ltr"
                                                    className="max-h-[600px] overflow-auto whitespace-pre-wrap rounded-xl bg-slate-950 p-4 text-xs leading-6 text-emerald-300"
                                                >
                                                    {
                                                        item.setup_command
                                                    }
                                                </pre>
                                            </>
                                        )}
                                    </div>
                                </details>
                            </section>
                        ),
                    )}

                    {routers.length ===
                        0 && (
                        <div className="rounded-2xl border border-dashed bg-white p-12 text-center text-slate-400">
                            No Hotel MikroTik routers added yet.
                        </div>
                    )}
                </div>
            </div>
        </HotelLayout>
    );
}

function Input({
    label,
    value,
    onChange,
    type = 'text',
    placeholder = '',
}) {
    return (
        <label>
            <div className="mb-1 text-sm font-bold">
                {label}
            </div>

            <input
                type={type}
                value={value}
                placeholder={
                    placeholder
                }
                onChange={(e) =>
                    onChange(
                        e.target.value,
                    )
                }
                className={
                    inputClass
                }
            />
        </label>
    );
}

function Info({
    label,
    value,
}) {
    return (
        <div className="rounded-xl bg-slate-50 p-3">
            <div className="text-xs font-black uppercase text-slate-400">
                {label}
            </div>

            <div className="mt-1 break-all text-sm font-semibold text-slate-700">
                {value}
            </div>
        </div>
    );
}

function Status({
    value,
}) {
    const classes =
        value === 'online'
            ? 'bg-emerald-100 text-emerald-700'
            : value === 'offline'
                ? 'bg-red-100 text-red-700'
                : 'bg-slate-100 text-slate-600';

    return (
        <span
            className={`rounded-full px-3 py-1 text-xs font-black uppercase ${classes}`}
        >
            {value}
        </span>
    );
}
