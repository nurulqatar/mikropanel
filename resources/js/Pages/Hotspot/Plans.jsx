import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';
import { useState } from 'react';

const inputClass =
    'w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:border-cyan-500 focus:ring-cyan-500';

const defaults = {
    name: '',
    price: '',
    validity_value: 30,
    validity_unit: 'days',
    rate_limit: '',
    shared_users: 1,
    idle_timeout_minutes: 5,
    keepalive_timeout_minutes: 2,
    mac_binding: false,
    enabled: true,
};

export default function Plans({
    plans = [],
    capabilities = {},
}) {
    const [
        editing,
        setEditing,
    ] = useState(null);

    const form =
        useForm(defaults);

    const reset = () => {
        setEditing(null);

        form.setData({
            ...defaults,
        });

        form.clearErrors();
    };

    const edit = (plan) => {
        setEditing(
            plan.id,
        );

        form.setData({
            name:
                plan.name ?? '',
            price:
                plan.price ?? '',
            validity_value:
                plan.validity_value ?? 1,
            validity_unit:
                plan.validity_unit ??
                'days',
            rate_limit:
                plan.rate_limit ?? '',
            shared_users:
                plan.shared_users ?? 1,
            idle_timeout_minutes:
                plan.idle_timeout_minutes ??
                '',
            keepalive_timeout_minutes:
                plan.keepalive_timeout_minutes ??
                '',
            mac_binding:
                Boolean(
                    plan.mac_binding,
                ),
            enabled:
                Boolean(
                    plan.enabled,
                ),
        });
    };

    const submit = (event) => {
        event.preventDefault();

        if (editing) {
            form.put(
                route(
                    'hotspot.plans.update',
                    editing,
                ),
                {
                    preserveScroll: true,
                    onSuccess: reset,
                },
            );

            return;
        }

        form.post(
            route(
                'hotspot.plans.store',
            ),
            {
                preserveScroll: true,
                onSuccess: reset,
            },
        );
    };

    return (
        <AppLayout title="Hotspot Plans">
            <Head title="Hotspot Plans" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black">
                        Hotspot Plans
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Voucher price, validity,
                        bandwidth and device policy.
                    </p>
                </div>

                {capabilities.manage && (
                    <form
                        onSubmit={submit}
                        className="rounded-2xl border bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-xl font-bold">
                            {editing
                                ? 'Edit Plan'
                                : 'Create Plan'}
                        </h2>

                        <div className="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <Field label="Plan Name">
                                <input
                                    value={
                                        form.data
                                            .name
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'name',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Price (QAR)">
                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={
                                        form.data
                                            .price
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'price',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Validity">
                                <input
                                    type="number"
                                    min="1"
                                    value={
                                        form.data
                                            .validity_value
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'validity_value',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Unit">
                                <select
                                    value={
                                        form.data
                                            .validity_unit
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'validity_unit',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                >
                                    <option value="minutes">
                                        Minutes
                                    </option>

                                    <option value="hours">
                                        Hours
                                    </option>

                                    <option value="days">
                                        Days
                                    </option>
                                </select>
                            </Field>

                            <Field label="Rate Limit">
                                <input
                                    value={
                                        form.data
                                            .rate_limit
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'rate_limit',
                                            e.target
                                                .value,
                                        )
                                    }
                                    placeholder="10M/10M"
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Shared Users">
                                <input
                                    type="number"
                                    min="1"
                                    value={
                                        form.data
                                            .shared_users
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'shared_users',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Idle Timeout (min)">
                                <input
                                    type="number"
                                    min="1"
                                    value={
                                        form.data
                                            .idle_timeout_minutes
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'idle_timeout_minutes',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>

                            <Field label="Keepalive (min)">
                                <input
                                    type="number"
                                    min="1"
                                    value={
                                        form.data
                                            .keepalive_timeout_minutes
                                    }
                                    onChange={(e) =>
                                        form.setData(
                                            'keepalive_timeout_minutes',
                                            e.target
                                                .value,
                                        )
                                    }
                                    className={
                                        inputClass
                                    }
                                />
                            </Field>
                        </div>

                        <div className="mt-5 flex flex-wrap gap-5">
                            <Check
                                label="MAC Binding"
                                checked={
                                    form.data
                                        .mac_binding
                                }
                                onChange={(value) =>
                                    form.setData(
                                        'mac_binding',
                                        value,
                                    )
                                }
                            />

                            <Check
                                label="Enabled"
                                checked={
                                    form.data
                                        .enabled
                                }
                                onChange={(value) =>
                                    form.setData(
                                        'enabled',
                                        value,
                                    )
                                }
                            />
                        </div>

                        <div className="mt-5 flex gap-3">
                            <button
                                type="submit"
                                disabled={
                                    form.processing
                                }
                                className="rounded-lg bg-cyan-600 px-5 py-2.5 font-bold text-white"
                            >
                                {editing
                                    ? 'Update Plan'
                                    : 'Create Plan'}
                            </button>

                            {editing && (
                                <button
                                    type="button"
                                    onClick={
                                        reset
                                    }
                                    className="rounded-lg bg-slate-200 px-5 py-2.5 font-bold text-slate-700"
                                >
                                    Cancel
                                </button>
                            )}
                        </div>
                    </form>
                )}

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Name</Th>
                                <Th>Price</Th>
                                <Th>Validity</Th>
                                <Th>Speed</Th>
                                <Th>Shared</Th>
                                <Th>MAC</Th>
                                <Th>Status</Th>
                                <Th>Actions</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {plans.map(
                                (plan) => (
                                    <tr
                                        key={
                                            plan.id
                                        }
                                    >
                                        <Td>
                                            {
                                                plan.name
                                            }
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {Number(
                                                plan.price,
                                            ).toFixed(
                                                2,
                                            )}
                                        </Td>

                                        <Td>
                                            {
                                                plan.validity_value
                                            }{' '}
                                            {
                                                plan.validity_unit
                                            }
                                        </Td>

                                        <Td>
                                            {plan.rate_limit ||
                                                '-'}
                                        </Td>

                                        <Td>
                                            {
                                                plan.shared_users
                                            }
                                        </Td>

                                        <Td>
                                            {plan.mac_binding
                                                ? 'Yes'
                                                : 'No'}
                                        </Td>

                                        <Td>
                                            {plan.enabled
                                                ? 'Enabled'
                                                : 'Disabled'}
                                        </Td>

                                        <Td>
                                            {capabilities.manage ? (
                                                <div className="flex gap-2">
                                                    <button
                                                        onClick={() =>
                                                            edit(
                                                                plan,
                                                            )
                                                        }
                                                        className="rounded bg-amber-500 px-3 py-1.5 text-sm font-bold text-white"
                                                    >
                                                        Edit
                                                    </button>

                                                    <button
                                                        onClick={() => {
                                                            if (
                                                                confirm(
                                                                    `Delete "${plan.name}"?`,
                                                                )
                                                            ) {
                                                                router.delete(
                                                                    route(
                                                                        'hotspot.plans.destroy',
                                                                        plan.id,
                                                                    ),
                                                                    {
                                                                        preserveScroll:
                                                                            true,
                                                                    },
                                                                );
                                                            }
                                                        }}
                                                        className="rounded bg-red-600 px-3 py-1.5 text-sm font-bold text-white"
                                                    >
                                                        Delete
                                                    </button>
                                                </div>
                                            ) : (
                                                '-'
                                            )}
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {plans.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="8"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No Hotspot plan yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
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
            <div className="mb-1 text-sm font-semibold text-slate-700">
                {label}
            </div>

            {children}
        </label>
    );
}

function Check({
    label,
    checked,
    onChange,
}) {
    return (
        <label className="flex items-center gap-2 font-semibold text-slate-700">
            <input
                type="checkbox"
                checked={checked}
                onChange={(e) =>
                    onChange(
                        e.target.checked,
                    )
                }
                className="rounded border-slate-300 text-cyan-600"
            />

            {label}
        </label>
    );
}

function Th({ children }) {
    return (
        <th className="whitespace-nowrap px-5 py-3 text-left text-xs font-bold uppercase text-slate-500">
            {children}
        </th>
    );
}

function Td({ children }) {
    return (
        <td className="whitespace-nowrap px-5 py-4 text-sm">
            {children}
        </td>
    );
}
