import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

export default function Index({
    users = [],
    currentUserId,
    flash = {},
    errors = {},
}) {
    const removeUser = (user) => {
        if (
            !confirm(
                `Delete panel user "${user.name}"?`,
            )
        ) {
            return;
        }

        router.delete(
            route(
                'users.destroy',
                user.id,
            ),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="Panel Users">
            <Head title="Panel Users" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900">
                            Panel Users
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Manage administrators, operators and permissions
                        </p>
                    </div>

                    <Link
                        href={route(
                            'users.create',
                        )}
                        className="rounded-xl bg-cyan-600 px-5 py-3 font-bold text-white hover:bg-cyan-700"
                    >
                        + Add Panel User
                    </Link>
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 font-semibold text-emerald-700">
                        {flash.success}
                    </div>
                )}

                {errors?.user && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-5 py-4 font-semibold text-red-700">
                        {errors.user}
                    </div>
                )}

                <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-800 text-white">
                            <tr>
                                <TableHead>
                                    User
                                </TableHead>

                                <TableHead>
                                    Role
                                </TableHead>

                                <TableHead>
                                    Permissions
                                </TableHead>

                                <TableHead>
                                    Status
                                </TableHead>

                                <TableHead>
                                    Created
                                </TableHead>

                                <TableHead>
                                    Actions
                                </TableHead>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-200">
                            {users.map((user) => (
                                <tr
                                    key={user.id}
                                    className="hover:bg-slate-50"
                                >
                                    <td className="px-5 py-4">
                                        <p className="font-bold text-slate-900">
                                            {user.name}

                                            {Number(
                                                currentUserId,
                                            ) ===
                                                Number(
                                                    user.id,
                                                ) && (
                                                <span className="ml-2 text-xs text-cyan-600">
                                                    You
                                                </span>
                                            )}
                                        </p>

                                        <p className="mt-1 text-sm text-slate-500">
                                            {user.email}
                                        </p>
                                    </td>

                                    <td className="px-5 py-4">
                                        <RoleBadge
                                            role={
                                                user.role
                                            }
                                        />
                                    </td>

                                    <td className="px-5 py-4">
                                        {user.role ===
                                        'admin' ? (
                                            <span className="font-bold text-emerald-600">
                                                Full Access
                                            </span>
                                        ) : (
                                            <span className="font-bold text-slate-700">
                                                {
                                                    (
                                                        user.permissions ??
                                                        []
                                                    )
                                                        .length
                                                }{' '}
                                                permissions
                                            </span>
                                        )}
                                    </td>

                                    <td className="px-5 py-4">
                                        <StatusBadge
                                            active={
                                                user.is_active
                                            }
                                        />
                                    </td>

                                    <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
                                        {formatDate(
                                            user.created_at,
                                        )}
                                    </td>

                                    <td className="px-5 py-4">
                                        <div className="flex gap-2">
                                            <Link
                                                href={route(
                                                    'users.edit',
                                                    user.id,
                                                )}
                                                className="rounded-lg bg-amber-500 px-3 py-2 text-sm font-bold text-white hover:bg-amber-600"
                                            >
                                                Edit
                                            </Link>

                                            {Number(
                                                currentUserId,
                                            ) !==
                                                Number(
                                                    user.id,
                                                ) && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        removeUser(
                                                            user,
                                                        )
                                                    }
                                                    className="rounded-lg bg-red-600 px-3 py-2 text-sm font-bold text-white hover:bg-red-700"
                                                >
                                                    Delete
                                                </button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}

                            {users.length === 0 && (
                                <tr>
                                    <td
                                        colSpan="6"
                                        className="px-6 py-12 text-center text-slate-500"
                                    >
                                        No panel users found.
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

function TableHead({ children }) {
    return (
        <th className="whitespace-nowrap px-5 py-4 text-left text-sm font-semibold">
            {children}
        </th>
    );
}

function RoleBadge({ role }) {
    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-bold capitalize ${
                role === 'admin'
                    ? 'bg-violet-100 text-violet-700'
                    : 'bg-cyan-100 text-cyan-700'
            }`}
        >
            {role}
        </span>
    );
}

function StatusBadge({ active }) {
    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-bold ${
                active
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-red-100 text-red-700'
            }`}
        >
            {active ? 'Active' : 'Disabled'}
        </span>
    );
}

function formatDate(value) {
    return value
        ? String(value).slice(0, 10)
        : '-';
}
