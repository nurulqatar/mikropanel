import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';

export default function Index({
    operators = [],
    permissionOptions = {},
    limit = 0,
    remaining = 0,
}) {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        permissions: [],
    });

    const togglePermission = (
        permission,
    ) => {
        const current =
            form.data.permissions;

        form.setData(
            'permissions',
            current.includes(permission)
                ? current.filter(
                    (item) =>
                        item !== permission,
                )
                : [
                    ...current,
                    permission,
                ],
        );
    };

    return (
        <AppLayout title="Reseller Operators">
            <Head title="Reseller Operators" />

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
                        Operators
                    </h1>

                    <p className="text-slate-500">
                        Limit {limit} · Remaining{' '}
                        {remaining}
                    </p>
                </div>

                {remaining > 0 && (
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            form.post(
                                route(
                                    'reseller.operators.store',
                                ),
                                {
                                    onSuccess: () =>
                                        form.reset(),
                                },
                            );
                        }}
                        className="rounded-2xl border bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-xl font-bold">
                            Add Operator
                        </h2>

                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            <Input
                                label="Name"
                                value={
                                    form.data.name
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'name',
                                        e.target.value,
                                    )
                                }
                            />

                            <Input
                                label="Email"
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
                            />

                            <Input
                                label="Password"
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
                            />

                            <Input
                                label="Confirm Password"
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
                            />
                        </div>

                        <div className="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {Object.entries(
                                permissionOptions,
                            ).map(
                                ([
                                    permission,
                                    label,
                                ]) => (
                                    <label
                                        key={
                                            permission
                                        }
                                        className="flex items-center gap-2 rounded-lg border p-3 text-sm"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={form.data.permissions.includes(
                                                permission,
                                            )}
                                            onChange={() =>
                                                togglePermission(
                                                    permission,
                                                )
                                            }
                                        />

                                        {label}
                                    </label>
                                ),
                            )}
                        </div>

                        <button
                            type="submit"
                            className="mt-5 rounded-xl bg-cyan-600 px-5 py-3 font-bold text-white"
                        >
                            Create Operator
                        </button>
                    </form>
                )}

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Name</Th>
                                <Th>Email</Th>
                                <Th>Permissions</Th>
                                <Th>Status</Th>
                                <Th>Actions</Th>
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
                                            {
                                                operator.permissions
                                                    ?.length ??
                                                0
                                            }
                                        </Td>

                                        <Td>
                                            {operator.is_active
                                                ? 'Active'
                                                : 'Disabled'}
                                        </Td>

                                        <Td>
                                            <div className="flex gap-2">
                                                <Link
                                                    href={route(
                                                        'reseller.operators.edit',
                                                        operator.id,
                                                    )}
                                                    className="rounded bg-amber-500 px-3 py-2 text-sm font-bold text-white"
                                                >
                                                    Edit
                                                </Link>

                                                <button
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'reseller.operators.toggle',
                                                                operator.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                            },
                                                        )
                                                    }
                                                    className="rounded bg-slate-600 px-3 py-2 text-sm font-bold text-white"
                                                >
                                                    {operator.is_active
                                                        ? 'Disable'
                                                        : 'Enable'}
                                                </button>

                                                <button
                                                    onClick={() => {
                                                        if (
                                                            confirm(
                                                                'Delete operator?',
                                                            )
                                                        ) {
                                                            router.delete(
                                                                route(
                                                                    'reseller.operators.destroy',
                                                                    operator.id,
                                                                ),
                                                                {
                                                                    preserveScroll:
                                                                        true,
                                                                },
                                                            );
                                                        }
                                                    }}
                                                    className="rounded bg-red-600 px-3 py-2 text-sm font-bold text-white"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {operators.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="5"
                                        className="p-8 text-center text-slate-400"
                                    >
                                        No operator yet.
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

function Input({
    label,
    ...props
}) {
    return (
        <label>
            <div className="mb-1 text-sm font-semibold">
                {label}
            </div>

            <input
                {...props}
                className="w-full rounded-lg border-slate-300"
            />
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
