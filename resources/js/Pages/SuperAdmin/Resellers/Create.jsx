import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

const inputClass =
    'w-full rounded-lg border border-slate-300 px-3 py-2.5';

export default function Create({
    plans = [],
}) {
    const form = useForm({
        company_name: '',
        owner_name: '',
        email: '',
        phone: '',
        address: '',
        password: '',
        password_confirmation: '',
        reseller_plan_id: '',
        validity_days: '',
        client_limit_override: '',
        operator_limit_override: '',
        router_limit_override: '',
        expiry_mode: 'panel_lock',
    });

    return (
        <AppLayout title="Add Reseller">
            <Head title="Add Reseller" />

            <div className="mx-auto max-w-5xl space-y-6">
                <div>
                    <Link
                        href={route(
                            'superadmin.resellers.index',
                        )}
                        className="font-semibold text-cyan-700"
                    >
                        ← Resellers
                    </Link>

                    <h1 className="mt-2 text-3xl font-black">
                        Create Reseller
                    </h1>
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();

                        form.post(
                            route(
                                'superadmin.resellers.store',
                            ),
                        );
                    }}
                    className="rounded-2xl border bg-white p-6 shadow-sm"
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <Field label="Company Name">
                            <input
                                value={
                                    form.data
                                        .company_name
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'company_name',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Owner Name">
                            <input
                                value={
                                    form.data
                                        .owner_name
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'owner_name',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Login Email">
                            <input
                                type="email"
                                value={
                                    form.data.email
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'email',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Phone">
                            <input
                                value={
                                    form.data.phone
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'phone',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Password">
                            <input
                                type="password"
                                value={
                                    form.data.password
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'password',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Confirm Password">
                            <input
                                type="password"
                                value={
                                    form.data
                                        .password_confirmation
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Reseller Plan">
                            <select
                                value={
                                    form.data
                                        .reseller_plan_id
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'reseller_plan_id',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            >
                                <option value="">
                                    Select Plan
                                </option>

                                {plans.map(
                                    (plan) => (
                                        <option
                                            key={plan.id}
                                            value={plan.id}
                                        >
                                            {plan.name}
                                            {' - '}
                                            {plan.client_limit}
                                            {' clients - QAR '}
                                            {plan.price}
                                        </option>
                                    ),
                                )}
                            </select>
                        </Field>

                        <Field label="Custom Validity Days">
                            <input
                                type="number"
                                min="1"
                                placeholder="Use plan default"
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

                        <Field label="Custom Client Limit">
                            <input
                                type="number"
                                min="1"
                                placeholder="Optional"
                                value={
                                    form.data
                                        .client_limit_override
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'client_limit_override',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Custom Operator Limit">
                            <input
                                type="number"
                                min="1"
                                placeholder="Optional"
                                value={
                                    form.data
                                        .operator_limit_override
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'operator_limit_override',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Custom Router Limit">
                            <input
                                type="number"
                                min="1"
                                placeholder="Optional"
                                value={
                                    form.data
                                        .router_limit_override
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'router_limit_override',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            />
                        </Field>

                        <Field label="Expiry Policy">
                            <select
                                value={
                                    form.data
                                        .expiry_mode
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'expiry_mode',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            >
                                <option value="panel_lock">
                                    Panel Lock
                                </option>

                                <option value="read_only">
                                    Read Only
                                </option>

                                <option value="block_new_clients">
                                    Block New Clients
                                </option>

                                <option value="full_suspend">
                                    Full Suspend
                                </option>
                            </select>
                        </Field>
                    </div>

                    <Field label="Address">
                        <textarea
                            rows="3"
                            value={
                                form.data.address
                            }
                            onChange={(e) =>
                                form.setData(
                                    'address',
                                    e.target.value,
                                )
                            }
                            className={
                                inputClass
                            }
                        />
                    </Field>

                    <div className="mt-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">
                        Reseller owner account will
                        be created, but login remains
                        disabled until tenant isolation
                        is fully enabled.
                    </div>

                    <button
                        type="submit"
                        disabled={
                            form.processing
                        }
                        className="mt-5 rounded-xl bg-cyan-600 px-6 py-3 font-bold text-white"
                    >
                        Create Reseller
                    </button>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({
    label,
    children,
}) {
    return (
        <label className="mt-4 block">
            <div className="mb-1 text-sm font-semibold">
                {label}
            </div>

            {children}
        </label>
    );
}
