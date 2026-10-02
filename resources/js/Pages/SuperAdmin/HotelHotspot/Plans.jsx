import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';
import { useState } from 'react';

const defaults = {
    name: '',
    guest_limit: 500,
    is_guest_unlimited: false,
    router_limit: 2,
    is_router_unlimited: false,
    receptionist_limit: 3,
    is_receptionist_unlimited: false,
    concurrent_limit: 100,
    price: 0,
    validity_days: 30,
    active: true,
    notes: '',
};

const inputClass =
    'w-full rounded-xl border-slate-300';

export default function Plans({
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

            guest_limit:
                plan.guest_limit
                ?? '',

            is_guest_unlimited:
                Boolean(
                    plan.is_guest_unlimited,
                ),

            router_limit:
                plan.router_limit
                ?? '',

            is_router_unlimited:
                Boolean(
                    plan.is_router_unlimited,
                ),

            receptionist_limit:
                plan.receptionist_limit
                ?? '',

            is_receptionist_unlimited:
                Boolean(
                    plan.is_receptionist_unlimited,
                ),

            concurrent_limit:
                plan.concurrent_limit
                ?? '',

            price:
                plan.price,

            validity_days:
                plan.validity_days,

            active:
                Boolean(
                    plan.active,
                ),

            notes:
                plan.notes
                ?? '',
        });
    };

    const submit = (event) => {
        event.preventDefault();

        if (editing) {
            form.put(
                route(
                    'superadmin.hotel.plans.update',
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
                'superadmin.hotel.plans.store',
            ),
            {
                preserveScroll: true,
                onSuccess: reset,
            },
        );
    };

    return (
        <SuperAdminLayout title="Hotel Hotspot · Plans">
            <Head title="Hotel Plans" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black">
                        Hotel Subscription Plans
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Guest, MikroTik and
                        Receptionist limits are
                        independent from Company plans.
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="rounded-2xl border bg-white p-6 shadow-sm"
                >
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
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
                            />
                        </Field>

                        <Limit
                            label="Guest Registrations"
                            field="guest_limit"
                            unlimited="is_guest_unlimited"
                            form={form}
                        />

                        <Limit
                            label="MikroTik Routers"
                            field="router_limit"
                            unlimited="is_router_unlimited"
                            form={form}
                        />

                        <Limit
                            label="Receptionists"
                            field="receptionist_limit"
                            unlimited="is_receptionist_unlimited"
                            form={form}
                        />

                        <Field label="Concurrent Guests">
                            <input
                                type="number"
                                min="1"
                                value={
                                    form.data
                                        .concurrent_limit
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'concurrent_limit',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
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
                            />
                        </Field>

                        <Field label="Validity Days">
                            <input
                                type="number"
                                min="1"
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
                            />
                        </Field>

                        <label className="flex items-center gap-3 rounded-xl bg-slate-50 p-4">
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
                                className="rounded"
                            />

                            <strong>
                                Active Plan
                            </strong>
                        </label>
                    </div>

                    <textarea
                        rows="3"
                        placeholder="Plan notes"
                        value={
                            form.data.notes
                        }
                        onChange={(e) =>
                            form.setData(
                                'notes',
                                e.target.value,
                            )
                        }
                        className={`${inputClass} mt-5`}
                    />

                    {Object.keys(
                        form.errors,
                    ).length > 0 && (
                        <div className="mt-4 rounded-xl bg-red-50 p-4 text-red-700">
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
                            className="rounded-xl bg-emerald-600 px-5 py-2.5 font-black text-white"
                        >
                            {editing
                                ? 'Update Hotel Plan'
                                : 'Create Hotel Plan'}
                        </button>

                        {editing && (
                            <button
                                type="button"
                                onClick={reset}
                                className="rounded-xl bg-slate-200 px-5 py-2.5 font-black"
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
                                <Th>Guests</Th>
                                <Th>Routers</Th>
                                <Th>Staff</Th>
                                <Th>Concurrent</Th>
                                <Th>Price</Th>
                                <Th>Status</Th>
                                <Th>Action</Th>
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
                                            {plan.is_guest_unlimited
                                                ? 'Unlimited'
                                                : plan.guest_limit}
                                        </Td>

                                        <Td>
                                            {plan.is_router_unlimited
                                                ? 'Unlimited'
                                                : plan.router_limit}
                                        </Td>

                                        <Td>
                                            {plan.is_receptionist_unlimited
                                                ? 'Unlimited'
                                                : plan.receptionist_limit}
                                        </Td>

                                        <Td>
                                            {plan.concurrent_limit
                                                ?? '-'}
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {
                                                plan.price
                                            }
                                        </Td>

                                        <Td>
                                            {plan.active
                                                ? 'Active'
                                                : 'Disabled'}
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
                                                    className="font-black text-amber-600"
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
                                                                    'superadmin.hotel.plans.destroy',
                                                                    plan.id,
                                                                ),
                                                                {
                                                                    preserveScroll:
                                                                        true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                    className="font-black text-red-600"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </Td>
                                    </tr>
                                ),
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </SuperAdminLayout>
    );
}

function Limit({
    label,
    field,
    unlimited,
    form,
}) {
    return (
        <Field label={label}>
            <input
                type="number"
                min="1"
                disabled={
                    form.data[
                        unlimited
                    ]
                }
                value={
                    form.data[field]
                }
                onChange={(e) =>
                    form.setData(
                        field,
                        e.target.value,
                    )
                }
                className={`${inputClass} disabled:bg-slate-100`}
            />

            <label className="mt-2 flex items-center gap-2 text-xs font-bold">
                <input
                    type="checkbox"
                    checked={
                        form.data[
                            unlimited
                        ]
                    }
                    onChange={(e) =>
                        form.setData(
                            unlimited,
                            e.target.checked,
                        )
                    }
                    className="rounded"
                />

                Unlimited
            </label>
        </Field>
    );
}

function Field({
    label,
    children,
}) {
    return (
        <label>
            <div className="mb-1 text-sm font-bold">
                {label}
            </div>

            {children}
        </label>
    );
}

function Th({ children }) {
    return (
        <th className="px-4 py-3 text-left text-xs font-black uppercase text-slate-500">
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
