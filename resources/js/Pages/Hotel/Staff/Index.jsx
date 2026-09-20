import HotelLayout from '@/Layouts/HotelLayout';
import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';

export default function Index({
    staff = [],
    permissionOptions = [],
}) {
    const form =
        useForm({
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
            permissions: [
                'guests.manage',
                'vouchers.issue',
                'vouchers.print',
            ],
        });

    const toggle = (permission) => {
        const values =
            form.data.permissions
            ?? [];

        form.setData(
            'permissions',
            values.includes(
                permission,
            )
                ? values.filter(
                      (item) =>
                          item
                          !== permission,
                  )
                : [
                      ...values,
                      permission,
                  ],
        );
    };

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route(
                'hotel.staff.store',
            ),
            {
                preserveScroll: true,

                onSuccess: () =>
                    form.reset(),
            },
        );
    };

    return (
        <HotelLayout title="Receptionists">
            <Head title="Receptionists" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black">
                        Receptionist Users
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Create Hotel staff accounts
                        with only required Guest WiFi
                        permissions.
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="rounded-2xl border bg-white p-6 shadow-sm"
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <Input
                            label="Name"
                            value={
                                form.data.name
                            }
                            onChange={(v) =>
                                form.setData(
                                    'name',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Email"
                            type="email"
                            value={
                                form.data.email
                            }
                            onChange={(v) =>
                                form.setData(
                                    'email',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Password"
                            type="password"
                            value={
                                form.data
                                    .password
                            }
                            onChange={(v) =>
                                form.setData(
                                    'password',
                                    v,
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
                            onChange={(v) =>
                                form.setData(
                                    'password_confirmation',
                                    v,
                                )
                            }
                        />
                    </div>

                    <div className="mt-5 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                        {permissionOptions.map(
                            (
                                permission,
                            ) => (
                                <label
                                    key={
                                        permission
                                    }
                                    className="flex items-center gap-2 rounded-xl bg-slate-50 p-3 text-sm font-bold"
                                >
                                    <input
                                        type="checkbox"
                                        checked={
                                            form.data.permissions.includes(
                                                permission,
                                            )
                                        }
                                        onChange={() =>
                                            toggle(
                                                permission,
                                            )
                                        }
                                        className="rounded"
                                    />

                                    {
                                        permission
                                    }
                                </label>
                            ),
                        )}
                    </div>

                    {Object.keys(
                        form.errors,
                    ).length > 0 && (
                        <div className="mt-4 rounded-xl bg-red-50 p-4 text-sm font-bold text-red-700">
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

                    <button
                        type="submit"
                        className="mt-5 rounded-xl bg-emerald-600 px-5 py-2.5 font-black text-white"
                    >
                        Add Receptionist
                    </button>
                </form>

                <div className="rounded-2xl border bg-white shadow-sm">
                    {staff.map(
                        (member) => (
                            <div
                                key={
                                    member.id
                                }
                                className="border-b p-5 last:border-b-0"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <div className="font-black">
                                            {
                                                member.name
                                            }
                                        </div>

                                        <div className="text-sm text-slate-500">
                                            {
                                                member.email
                                            }
                                        </div>

                                        <div className="mt-1 text-xs font-black uppercase text-emerald-700">
                                            {
                                                member.role
                                            }
                                        </div>
                                    </div>

                                    {member.role
                                        === 'receptionist' && (
                                        <div className="flex gap-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.put(
                                                        route(
                                                            'hotel.staff.update',
                                                            member.id,
                                                        ),
                                                        {
                                                            is_active:
                                                                !member.is_active,

                                                            permissions:
                                                                member.permissions
                                                                ?? [],
                                                        },
                                                        {
                                                            preserveScroll:
                                                                true,
                                                        },
                                                    )
                                                }
                                                className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-black"
                                            >
                                                {member.is_active
                                                    ? 'Disable'
                                                    : 'Enable'}
                                            </button>

                                            <button
                                                type="button"
                                                onClick={() => {
                                                    if (
                                                        confirm(
                                                            `Delete ${member.name}?`,
                                                        )
                                                    ) {
                                                        router.delete(
                                                            route(
                                                                'hotel.staff.destroy',
                                                                member.id,
                                                            ),
                                                            {
                                                                preserveScroll:
                                                                    true,
                                                            },
                                                        );
                                                    }
                                                }}
                                                className="rounded-lg bg-red-50 px-3 py-2 text-xs font-black text-red-700"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    )}
                                </div>

                                {member.role
                                    === 'receptionist' && (
                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {permissionOptions.map(
                                            (
                                                permission,
                                            ) => {
                                                const current =
                                                    member.permissions
                                                    ?? [];

                                                const selected =
                                                    current.includes(
                                                        permission,
                                                    );

                                                return (
                                                    <button
                                                        key={
                                                            permission
                                                        }
                                                        type="button"
                                                        onClick={() => {
                                                            const next =
                                                                selected
                                                                    ? current.filter(
                                                                          (
                                                                              item,
                                                                          ) =>
                                                                              item
                                                                              !== permission,
                                                                      )
                                                                    : [
                                                                          ...current,
                                                                          permission,
                                                                      ];

                                                            router.put(
                                                                route(
                                                                    'hotel.staff.update',
                                                                    member.id,
                                                                ),
                                                                {
                                                                    is_active:
                                                                        Boolean(
                                                                            member.is_active,
                                                                        ),

                                                                    permissions:
                                                                        next,
                                                                },
                                                                {
                                                                    preserveScroll:
                                                                        true,
                                                                },
                                                            );
                                                        }}
                                                        className={`rounded-full px-3 py-1.5 text-xs font-bold ${
                                                            selected
                                                                ? 'bg-emerald-100 text-emerald-700'
                                                                : 'bg-slate-100 text-slate-500'
                                                        }`}
                                                    >
                                                        {
                                                            permission
                                                        }
                                                    </button>
                                                );
                                            },
                                        )}
                                    </div>
                                )}
                            </div>
                        ),
                    )}
                </div>
            </div>
        </HotelLayout>
    );
}

function Input({
    label,
    value,
    onChange,
    type = 'text',
}) {
    return (
        <label>
            <div className="mb-1 text-sm font-bold">
                {label}
            </div>

            <input
                type={type}
                value={value}
                onChange={(e) =>
                    onChange(
                        e.target.value,
                    )
                }
                className="w-full rounded-xl border-slate-300"
            />
        </label>
    );
}
