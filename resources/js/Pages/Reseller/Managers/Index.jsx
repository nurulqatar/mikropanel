import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';

export default function Index({
    managers = [],
}) {
    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    return (
        <AppLayout title="Managers">
            <Head title="Managers" />

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
                        Managers
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Managers can view all zones,
                        but accounting mutation and
                        owner-only controls stay
                        restricted.
                    </p>
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();

                        form.post(
                            route(
                                'reseller.managers.store',
                            ),
                            {
                                preserveScroll: true,
                                onSuccess: () =>
                                    form.reset(),
                            },
                        );
                    }}
                    className="rounded-2xl border bg-white p-6 shadow-sm"
                >
                    <h2 className="text-xl font-bold">
                        Add Manager
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
                        className="mt-5 rounded-xl bg-violet-700 px-5 py-3 font-bold text-white disabled:opacity-50"
                    >
                        CREATE MANAGER
                    </button>
                </form>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Name</Th>
                                <Th>Email</Th>
                                <Th>Status</Th>
                                <Th>Access</Th>
                                <Th>Actions</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {managers.map(
                                (manager) => (
                                    <tr
                                        key={
                                            manager.id
                                        }
                                    >
                                        <Td>
                                            <span className="font-bold">
                                                {
                                                    manager.name
                                                }
                                            </span>
                                        </Td>

                                        <Td>
                                            {
                                                manager.email
                                            }
                                        </Td>

                                        <Td>
                                            {manager.is_active
                                                ? 'ACTIVE'
                                                : 'SUSPENDED'}
                                        </Td>

                                        <Td>
                                            ALL ZONES
                                        </Td>

                                        <Td>
                                            <div className="flex gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'reseller.managers.toggle',
                                                                manager.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                    className="rounded-lg bg-slate-700 px-3 py-2 text-xs font-bold text-white"
                                                >
                                                    {manager.is_active
                                                        ? 'SUSPEND'
                                                        : 'ACTIVATE'}
                                                </button>

                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        if (
                                                            !confirm(
                                                                'Delete this manager?',
                                                            )
                                                        ) {
                                                            return;
                                                        }

                                                        router.delete(
                                                            route(
                                                                'reseller.managers.destroy',
                                                                manager.id,
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
                                            </div>
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {managers.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="5"
                                        className="px-4 py-8 text-center text-slate-500"
                                    >
                                        No managers
                                        created yet.
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
                required
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
