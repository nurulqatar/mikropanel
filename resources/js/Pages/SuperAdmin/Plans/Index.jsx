import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';
import { useState } from 'react';

const defaults = {
    name: '',
    client_limit: 50,
    operator_limit: 5,
    router_limit: 1,
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
            name: plan.name,
            client_limit:
                plan.client_limit,
            operator_limit:
                plan.operator_limit,
            router_limit:
                plan.router_limit,
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
        <AppLayout title="Reseller Plans">
            <Head title="Reseller Plans" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black">
                        Reseller Plans
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Sell panel access by
                        client capacity and validity.
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
                            />
                        </Field>

                        <Field label="Client Limit">
                            <input
                                type="number"
                                min="1"
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
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Operator Limit">
                            <input
                                type="number"
                                min="1"
                                value={
                                    form.data
                                        .operator_limit
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'operator_limit',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Router Limit">
                            <input
                                type="number"
                                min="1"
                                value={
                                    form.data
                                        .router_limit
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'router_limit',
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
                    </div>

                    <label className="mt-4 flex items-center gap-2 font-semibold">
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
                        />

                        Active Plan
                    </label>

                    <div className="mt-5 flex gap-3">
                        <button
                            type="submit"
                            className="rounded-lg bg-cyan-600 px-5 py-2.5 font-bold text-white"
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
                                <Th>Operators</Th>
                                <Th>Routers</Th>
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
                                    <tr key={plan.id}>
                                        <Td>
                                            <strong>
                                                {plan.name}
                                            </strong>

                                            <div className="text-xs text-slate-400">
                                                {plan.code}
                                            </div>
                                        </Td>

                                        <Td>
                                            {
                                                plan.client_limit
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                plan.operator_limit
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                plan.router_limit
                                            }
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
                                            ).toFixed(2)}
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
                                        colSpan="9"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No reseller plan yet.
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
