import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';
import { useState } from 'react';

const defaults = {
    name: '',
    client_limit: 50,
    is_unlimited: false,
    price: 0,
    validity_days: 30,
    active: true,
    notes: '',
};

const inputClass =
    'w-full rounded-lg border border-slate-300 px-3 py-2.5';

export default function Index({
    plans = [],
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
        setEditing(plan.id);

        form.setData({
            name:
                plan.name ?? '',

            client_limit:
                plan.is_unlimited
                    ? ''
                    : plan.client_limit,

            is_unlimited:
                Boolean(
                    plan.is_unlimited,
                ),

            price:
                plan.price,

            validity_days:
                plan.validity_days,

            active:
                Boolean(
                    plan.active,
                ),

            notes:
                plan.notes ?? '',
        });
    };

    const submit = (event) => {
        event.preventDefault();

        if (editing) {
            form.put(
                route(
                    'superadmin.plans.update',
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
                'superadmin.plans.store',
            ),
            {
                preserveScroll: true,
                onSuccess: reset,
            },
        );
    };

    return (
        <SuperAdminLayout title="Company Plans">
            <Head title="Company Plans" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black">
                        Company Plans
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Create limited or Unlimited
                        client-capacity plans.
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="rounded-2xl border bg-white p-6 shadow-sm"
                >
                    <h2 className="text-xl font-bold">
                        {editing
                            ? 'Edit Plan'
                            : 'Create Plan'}
                    </h2>

                    <div className="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <Field label="Plan Name">
                            <input
                                value={
                                    form.data.name
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'name',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                                required
                            />
                        </Field>

                        <Field label="Client Capacity">
                            <input
                                type="number"
                                min="1"
                                max="1000000"
                                disabled={
                                    form.data
                                        .is_unlimited
                                }
                                value={
                                    form.data
                                        .client_limit
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'client_limit',
                                        e.target.value,
                                    )
                                }
                                placeholder={
                                    form.data
                                        .is_unlimited
                                        ? 'Unlimited'
                                        : '500'
                                }
                                className={`${inputClass} disabled:bg-slate-100`}
                            />
                        </Field>

                        <Field label="Price QAR">
                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                value={
                                    form.data.price
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'price',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                                required
                            />
                        </Field>

                        <Field label="Validity Days">
                            <input
                                type="number"
                                min="1"
                                max="3650"
                                value={
                                    form.data
                                        .validity_days
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'validity_days',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                                required
                            />
                        </Field>
                    </div>

                    <div className="mt-5 grid gap-4 md:grid-cols-2">
                        <label className="flex items-start gap-3 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
                            <input
                                type="checkbox"
                                checked={
                                    form.data
                                        .is_unlimited
                                }
                                onChange={(e) => {
                                    form.setData(
                                        'is_unlimited',
                                        e.target.checked,
                                    );
                                }}
                                className="mt-1 rounded"
                            />

                            <span>
                                <strong>
                                    Unlimited Client Plan
                                </strong>

                                <span className="mt-1 block text-sm text-slate-600">
                                    No client-capacity
                                    enforcement. Public
                                    website visitors must
                                    contact Super Admin
                                    instead of registering
                                    directly.
                                </span>
                            </span>
                        </label>

                        <label className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <input
                                type="checkbox"
                                checked={
                                    form.data.active
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'active',
                                        e.target.checked,
                                    )
                                }
                                className="mt-1 rounded"
                            />

                            <span>
                                <strong>
                                    Active Plan
                                </strong>

                                <span className="mt-1 block text-sm text-slate-600">
                                    Active plans appear on
                                    the public website.
                                </span>
                            </span>
                        </label>
                    </div>

                    <label className="mt-5 block">
                        <div className="mb-1 text-sm font-semibold">
                            Notes
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
                            className={
                                inputClass
                            }
                        />
                    </label>

                    {Object.keys(
                        form.errors,
                    ).length > 0 && (
                        <div className="mt-4 rounded-xl bg-red-50 p-4 text-sm font-semibold text-red-700">
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

                    <div className="mt-5 flex gap-3">
                        <button
                            type="submit"
                            disabled={
                                form.processing
                            }
                            className="rounded-lg bg-cyan-600 px-5 py-2.5 font-bold text-white disabled:opacity-50"
                        >
                            {editing
                                ? 'Update Plan'
                                : 'Create Plan'}
                        </button>

                        {editing && (
                            <button
                                type="button"
                                onClick={reset}
                                className="rounded-lg bg-slate-200 px-5 py-2.5 font-bold"
                            >
                                Cancel
                            </button>
                        )}
                    </div>
                </form>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Plan</Th>
                                <Th>Clients</Th>
                                <Th>Validity</Th>
                                <Th>Price</Th>
                                <Th>Status</Th>
                                <Th>Subscriptions</Th>
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
                                            <strong>
                                                {
                                                    plan.name
                                                }
                                            </strong>

                                            <div className="text-xs text-slate-400">
                                                {
                                                    plan.code
                                                }
                                            </div>

                                            {plan.is_unlimited && (
                                                <span className="mt-2 inline-flex rounded-full bg-indigo-100 px-2 py-1 text-xs font-black text-indigo-700">
                                                    UNLIMITED
                                                </span>
                                            )}
                                        </Td>

                                        <Td>
                                            {plan.is_unlimited
                                                ? 'Unlimited'
                                                : Number(
                                                      plan.client_limit,
                                                  ).toLocaleString()}
                                        </Td>

                                        <Td>
                                            {
                                                plan.validity_days
                                            }{' '}
                                            days
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
                                            {plan.active
                                                ? 'Active'
                                                : 'Disabled'}
                                        </Td>

                                        <Td>
                                            {
                                                plan.subscriptions_count
                                            }
                                        </Td>

                                        <Td>
                                            <div className="flex gap-2">
                                                <button
                                                    type="button"
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
                                                    type="button"
                                                    onClick={() => {
                                                        if (
                                                            confirm(
                                                                `Delete ${plan.name}?`,
                                                            )
                                                        ) {
                                                            router.delete(
                                                                route(
                                                                    'superadmin.plans.destroy',
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
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {plans.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="7"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No Company plans yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </SuperAdminLayout>
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
