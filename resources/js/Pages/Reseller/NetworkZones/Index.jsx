import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';

export default function Index({
    zones = [],
    operators = [],
    currentZoneId = null,
    isOwner = false,
}) {
    const form = useForm({
        name: '',
        service_type: 'mac',
        enabled: true,
    });

    const macZones = zones.filter(
        (zone) =>
            zone.service_type === 'mac' &&
            zone.enabled,
    );

    return (
        <AppLayout title="Network Zones">
            <Head title="Network Zones" />

            <div className="space-y-6">
                <div>
                    <Link
                        href={route(
                            'reseller.dashboard',
                        )}
                        className="font-semibold text-cyan-700"
                    >
                        ← Reseller Dashboard
                    </Link>

                    <h1 className="mt-2 text-3xl font-black">
                        Network Zones
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Separate remote sites and keep
                        MAC / Hotspot traffic isolated.
                    </p>
                </div>

                {isOwner && (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            form.post(
                                route(
                                    'reseller.zones.store',
                                ),
                                {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        form.reset(
                                            'name',
                                        ),
                                },
                            );
                        }}
                        className="rounded-2xl border bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-xl font-bold">
                            Add Network Zone
                        </h2>

                        <div className="mt-4 grid gap-4 md:grid-cols-3">
                            <Field label="Zone Name">
                                <input
                                    value={
                                        form.data.name
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'name',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className="w-full rounded-lg border-slate-300"
                                    required
                                />
                            </Field>

                            <Field label="Service Type">
                                <select
                                    value={
                                        form.data
                                            .service_type
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'service_type',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className="w-full rounded-lg border-slate-300"
                                >
                                    <option value="mac">
                                        MAC
                                    </option>
                                    <option value="hotspot">
                                        Hotspot
                                    </option>
                                </select>
                            </Field>

                            <label className="flex items-center gap-3 pt-7 font-semibold">
                                <input
                                    type="checkbox"
                                    checked={
                                        form.data
                                            .enabled
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'enabled',
                                            e.target
                                                .checked,
                                        )
                                    }
                                />
                                Active
                            </label>
                        </div>

                        {Object.values(
                            form.errors,
                        ).map((error) => (
                            <div
                                key={error}
                                className="mt-2 text-sm font-semibold text-red-600"
                            >
                                {error}
                            </div>
                        ))}

                        <button
                            disabled={
                                form.processing
                            }
                            className="mt-5 rounded-xl bg-cyan-700 px-5 py-3 font-bold text-white disabled:opacity-50"
                        >
                            CREATE ZONE
                        </button>
                    </form>
                )}

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Zone</Th>
                                <Th>Type</Th>
                                <Th>Status</Th>
                                <Th>Active Context</Th>
                                <Th>Actions</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {zones.map((zone) => {
                                const active =
                                    Number(
                                        currentZoneId,
                                    ) ===
                                    Number(
                                        zone.id,
                                    );

                                return (
                                    <tr
                                        key={
                                            zone.id
                                        }
                                    >
                                        <Td>
                                            <span className="font-bold">
                                                {
                                                    zone.name
                                                }
                                            </span>
                                        </Td>

                                        <Td>
                                            {zone.service_type}
                                        </Td>

                                        <Td>
                                            {zone.enabled
                                                ? 'ACTIVE'
                                                : 'DISABLED'}
                                        </Td>

                                        <Td>
                                            {active
                                                ? 'SELECTED'
                                                : '—'}
                                        </Td>

                                        <Td>
                                            <div className="flex flex-wrap gap-2">
                                                {zone.enabled && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                route(
                                                                    'reseller.zones.select',
                                                                    zone.id,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                        className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white"
                                                    >
                                                        SELECT
                                                    </button>
                                                )}

                                                {isOwner && (
                                                    <>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                router.put(
                                                                    route(
                                                                        'reseller.zones.update',
                                                                        zone.id,
                                                                    ),
                                                                    {
                                                                        name: zone.name,
                                                                        service_type:
                                                                            zone.service_type,
                                                                        enabled:
                                                                            !zone.enabled,
                                                                    },
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                )
                                                            }
                                                            className="rounded-lg bg-slate-700 px-3 py-2 text-xs font-bold text-white"
                                                        >
                                                            {zone.enabled
                                                                ? 'DISABLE'
                                                                : 'ENABLE'}
                                                        </button>

                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                if (
                                                                    !confirm(
                                                                        'Delete this unused Network Zone?',
                                                                    )
                                                                ) {
                                                                    return;
                                                                }

                                                                router.delete(
                                                                    route(
                                                                        'reseller.zones.destroy',
                                                                        zone.id,
                                                                    ),
                                                                    {
                                                                        preserveScroll: true,
                                                                    },
                                                                );
                                                            }}
                                                            className="rounded-lg bg-red-600 px-3 py-2 text-xs font-bold text-white"
                                                        >
                                                            DELETE
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        </Td>
                                    </tr>
                                );
                            })}

                            {zones.length === 0 && (
                                <tr>
                                    <td
                                        colSpan="5"
                                        className="px-4 py-8 text-center text-slate-500"
                                    >
                                        No Network
                                        Zones found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {isOwner && (
                    <div className="rounded-2xl border bg-white p-6 shadow-sm">
                        <h2 className="text-xl font-bold">
                            Operator Zone Assignment
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Operators are restricted
                            to one active MAC zone.
                        </p>

                        <div className="mt-4 overflow-x-auto">
                            <table className="min-w-full">
                                <thead>
                                    <tr>
                                        <Th>Operator</Th>
                                        <Th>Email</Th>
                                        <Th>Status</Th>
                                        <Th>MAC Zone</Th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y">
                                    {operators.map(
                                        (operator) => (
                                            <tr
                                                key={
                                                    operator.id
                                                }
                                            >
                                                <Td>
                                                    {
                                                        operator.name
                                                    }
                                                </Td>

                                                <Td>
                                                    {
                                                        operator.email
                                                    }
                                                </Td>

                                                <Td>
                                                    {operator.is_active
                                                        ? 'ACTIVE'
                                                        : 'SUSPENDED'}
                                                </Td>

                                                <Td>
                                                    <select
                                                        value={
                                                            operator.zone_id ??
                                                            ''
                                                        }
                                                        onChange={(e) => {
                                                            if (
                                                                !e
                                                                    .target
                                                                    .value
                                                            ) {
                                                                return;
                                                            }

                                                            router.patch(
                                                                route(
                                                                    'reseller.zones.operator',
                                                                    operator.id,
                                                                ),
                                                                {
                                                                    zone_id:
                                                                        e
                                                                            .target
                                                                            .value,
                                                                },
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            );
                                                        }}
                                                        className="rounded-lg border-slate-300"
                                                    >
                                                        <option value="">
                                                            Select
                                                            MAC
                                                            Zone
                                                        </option>

                                                        {macZones.map(
                                                            (
                                                                zone,
                                                            ) => (
                                                                <option
                                                                    key={
                                                                        zone.id
                                                                    }
                                                                    value={
                                                                        zone.id
                                                                    }
                                                                >
                                                                    {
                                                                        zone.name
                                                                    }
                                                                </option>
                                                            ),
                                                        )}
                                                    </select>
                                                </Td>
                                            </tr>
                                        ),
                                    )}

                                    {operators.length ===
                                        0 && (
                                        <tr>
                                            <td
                                                colSpan="4"
                                                className="px-4 py-8 text-center text-slate-500"
                                            >
                                                No
                                                operators
                                                found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

function Field({
    label,
    children,
}) {
    return (
        <label>
            <div className="mb-1 text-sm font-semibold">
                {label}
            </div>

            {children}
        </label>
    );
}

function Th({ children }) {
    return (
        <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-bold uppercase text-slate-500">
            {children}
        </th>
    );
}

function Td({ children }) {
    return (
        <td className="whitespace-nowrap px-4 py-4 text-sm">
            {children}
        </td>
    );
}
