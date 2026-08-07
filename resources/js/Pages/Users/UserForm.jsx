import { Link, useForm } from '@inertiajs/react';

export default function UserForm({
    panelUser = null,
    permissionGroups = [],
}) {
    const editing = Boolean(panelUser);

    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
    } = useForm({
        name: panelUser?.name ?? '',
        email: panelUser?.email ?? '',
        role: panelUser?.role ?? 'operator',

        permissions:
            panelUser?.permissions ?? [],

        is_active:
            panelUser?.is_active ?? true,

        password: '',
        password_confirmation: '',
    });

    const submit = (event) => {
        event.preventDefault();

        if (editing) {
            put(
                route(
                    'users.update',
                    panelUser.id,
                ),
            );

            return;
        }

        post(route('users.store'));
    };

    const hasPermission = (permission) =>
        data.permissions.includes(
            permission,
        );

    const togglePermission = (
        permission,
    ) => {
        if (hasPermission(permission)) {
            setData(
                'permissions',
                data.permissions.filter(
                    (item) =>
                        item !== permission,
                ),
            );

            return;
        }

        setData('permissions', [
            ...data.permissions,
            permission,
        ]);
    };

    const groupPermissionKeys = (group) =>
        Object.keys(
            group.permissions ?? {},
        );

    const groupIsSelected = (group) => {
        const keys =
            groupPermissionKeys(group);

        return (
            keys.length > 0
            && keys.every(
                (key) =>
                    data.permissions.includes(
                        key,
                    ),
            )
        );
    };

    const toggleGroup = (group) => {
        const keys =
            groupPermissionKeys(group);

        if (groupIsSelected(group)) {
            setData(
                'permissions',
                data.permissions.filter(
                    (permission) =>
                        !keys.includes(
                            permission,
                        ),
                ),
            );

            return;
        }

        setData(
            'permissions',
            Array.from(
                new Set([
                    ...data.permissions,
                    ...keys,
                ]),
            ),
        );
    };

    return (
        <form
            onSubmit={submit}
            className="space-y-6"
        >
            <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 className="text-xl font-bold text-slate-900">
                    Account Information
                </h2>

                <div className="mt-6 grid gap-5 md:grid-cols-2">
                    <Input
                        label="Full Name"
                        value={data.name}
                        onChange={(value) =>
                            setData(
                                'name',
                                value,
                            )
                        }
                        error={errors.name}
                        required
                    />

                    <Input
                        label="Email Address"
                        type="email"
                        value={data.email}
                        onChange={(value) =>
                            setData(
                                'email',
                                value,
                            )
                        }
                        error={errors.email}
                        required
                    />

                    <div>
                        <label className="mb-1 block font-semibold text-slate-700">
                            User Role
                        </label>

                        <select
                            value={data.role}
                            onChange={(event) =>
                                setData(
                                    'role',
                                    event.target.value,
                                )
                            }
                            className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3"
                        >
                            <option value="operator">
                                Operator
                            </option>

                            <option value="admin">
                                Administrator
                            </option>
                        </select>

                        {errors.role && (
                            <Error
                                text={errors.role}
                            />
                        )}
                    </div>

                    <label className="flex items-center gap-3 rounded-xl border border-slate-200 p-4">
                        <input
                            type="checkbox"
                            checked={Boolean(
                                data.is_active,
                            )}
                            onChange={(event) =>
                                setData(
                                    'is_active',
                                    event.target
                                        .checked,
                                )
                            }
                            className="h-5 w-5 rounded border-slate-300 text-cyan-600"
                        />

                        <div>
                            <p className="font-bold text-slate-800">
                                Active Account
                            </p>

                            <p className="text-sm text-slate-500">
                                Inactive users cannot use the panel
                            </p>
                        </div>
                    </label>

                    <Input
                        label={
                            editing
                                ? 'New Password'
                                : 'Password'
                        }
                        type="password"
                        value={data.password}
                        onChange={(value) =>
                            setData(
                                'password',
                                value,
                            )
                        }
                        error={errors.password}
                        required={!editing}
                        placeholder={
                            editing
                                ? 'Leave blank to keep current password'
                                : ''
                        }
                    />

                    <Input
                        label="Confirm Password"
                        type="password"
                        value={
                            data.password_confirmation
                        }
                        onChange={(value) =>
                            setData(
                                'password_confirmation',
                                value,
                            )
                        }
                    />
                </div>
            </section>

            <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 className="text-xl font-bold text-slate-900">
                    User Permissions
                </h2>

                {data.role === 'admin' ? (
                    <div className="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-800">
                        Administrator has complete access to every panel feature and user account.
                    </div>
                ) : (
                    <div className="mt-6 grid gap-5 lg:grid-cols-2">
                        {permissionGroups.map(
                            (group) => (
                                <div
                                    key={group.key}
                                    className="rounded-2xl border border-slate-200 p-5"
                                >
                                    <label className="flex cursor-pointer items-center gap-3 border-b border-slate-200 pb-4">
                                        <input
                                            type="checkbox"
                                            checked={groupIsSelected(
                                                group,
                                            )}
                                            onChange={() =>
                                                toggleGroup(
                                                    group,
                                                )
                                            }
                                            className="h-5 w-5 rounded border-slate-300 text-cyan-600"
                                        />

                                        <span className="font-bold text-slate-900">
                                            {group.label}
                                        </span>
                                    </label>

                                    <div className="mt-4 space-y-3">
                                        {Object.entries(
                                            group.permissions ??
                                                {},
                                        ).map(
                                            ([
                                                permission,
                                                label,
                                            ]) => (
                                                <label
                                                    key={
                                                        permission
                                                    }
                                                    className="flex cursor-pointer items-start gap-3"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={hasPermission(
                                                            permission,
                                                        )}
                                                        onChange={() =>
                                                            togglePermission(
                                                                permission,
                                                            )
                                                        }
                                                        className="mt-0.5 h-4 w-4 rounded border-slate-300 text-cyan-600"
                                                    />

                                                    <span className="text-sm text-slate-700">
                                                        {
                                                            label
                                                        }
                                                    </span>
                                                </label>
                                            ),
                                        )}
                                    </div>
                                </div>
                            ),
                        )}
                    </div>
                )}

                {errors.permissions && (
                    <Error
                        text={errors.permissions}
                    />
                )}
            </section>

            <div className="flex justify-end gap-3">
                <Link
                    href={route('users.index')}
                    className="rounded-xl border border-slate-300 bg-white px-5 py-3 font-bold text-slate-700 hover:bg-slate-50"
                >
                    Cancel
                </Link>

                <button
                    type="submit"
                    disabled={processing}
                    className="rounded-xl bg-cyan-600 px-6 py-3 font-bold text-white hover:bg-cyan-700 disabled:opacity-50"
                >
                    {processing
                        ? 'Saving...'
                        : editing
                          ? 'Update User'
                          : 'Create User'}
                </button>
            </div>
        </form>
    );
}

function Input({
    label,
    type = 'text',
    value,
    onChange,
    error,
    required = false,
    placeholder = '',
}) {
    return (
        <div>
            <label className="mb-1 block font-semibold text-slate-700">
                {label}

                {required && (
                    <span className="ml-1 text-red-500">
                        *
                    </span>
                )}
            </label>

            <input
                type={type}
                value={value}
                onChange={(event) =>
                    onChange(
                        event.target.value,
                    )
                }
                placeholder={placeholder}
                className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3"
            />

            {error && <Error text={error} />}
        </div>
    );
}

function Error({ text }) {
    return (
        <p className="mt-1 text-sm font-semibold text-red-600">
            {text}
        </p>
    );
}
