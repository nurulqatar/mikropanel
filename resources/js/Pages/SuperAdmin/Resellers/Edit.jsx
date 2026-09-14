import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

const inputClass =
    'w-full rounded-lg border border-slate-300 px-3 py-2.5';

export default function Edit({
    reseller,
}) {
    const form = useForm({
        company_name:
            reseller.company_name ?? '',
        owner_name:
            reseller.owner_name ?? '',
        email:
            reseller.email ?? '',
        phone:
            reseller.phone ?? '',
        address:
            reseller.address ?? '',
        password: '',
        password_confirmation: '',
        client_limit_override:
            reseller.client_limit_override ??
            '',
        operator_limit_override:
            reseller.operator_limit_override ??
            '',
        router_limit_override:
            reseller.router_limit_override ??
            '',
        expiry_mode:
            reseller.expiry_mode ??
            'panel_lock',
    });

    return (
        <AppLayout title="Edit Reseller">
            <Head title="Edit Reseller" />

            <div className="mx-auto max-w-5xl">
                <Link
                    href={route(
                        'superadmin.resellers.show',
                        reseller.id,
                    )}
                    className="font-semibold text-cyan-700"
                >
                    ← Reseller
                </Link>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();

                        form.put(
                            route(
                                'superadmin.resellers.update',
                                reseller.id,
                            ),
                        );
                    }}
                    className="mt-5 rounded-2xl border bg-white p-6 shadow-sm"
                >
                    <h1 className="text-2xl font-black">
                        Edit {reseller.company_name}
                    </h1>

                    <div className="mt-5 grid gap-4 md:grid-cols-2">
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

                        <Field label="Email">
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

                        <Field label="New Password">
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
                                placeholder="Leave blank to keep"
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

                        <Field label="Custom Client Limit">
                            <input
                                type="number"
                                min="1"
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

                        <Field label="Expiry Mode">
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

                    <button
                        type="submit"
                        className="mt-5 rounded-xl bg-cyan-600 px-6 py-3 font-bold text-white"
                    >
                        Save Changes
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
