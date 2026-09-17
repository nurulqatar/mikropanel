import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

export default function Edit({
    operator,
    zones = [],
    permissionOptions = {},
}) {
    const form = useForm({
        name:
            operator.name ?? '',
        email:
            operator.email ?? '',
        zone_id:
            operator.zone_id
                ? String(
                      operator.zone_id,
                  )
                : '',
        password: '',
        password_confirmation: '',
        permissions:
            operator.permissions ?? [],
    });

    const toggle = (
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
        <AppLayout title="Edit Operator">
            <Head title="Edit Operator" />

            <div className="mx-auto max-w-4xl">
                <Link
                    href={route(
                        'reseller.operators.index',
                    )}
                    className="font-semibold text-cyan-700"
                >
                    ← Operators
                </Link>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();

                        form.put(
                            route(
                                'reseller.operators.update',
                                operator.id,
                            ),
                        );
                    }}
                    className="mt-5 rounded-2xl border bg-white p-6 shadow-sm"
                >
                    <h1 className="text-2xl font-black">
                        Edit Operator
                    </h1>

                    <div className="mt-5 grid gap-4 md:grid-cols-2">
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

                          <label>
                              <div className="mb-1 text-sm font-semibold">
                                  Network Zone
                              </div>

                              <select
                                  required
                                  className="w-full rounded-lg border-slate-300"
                                  value={
                                      form.data.zone_id
                                  }
                                  onChange={(e) =>
                                      form.setData(
                                          'zone_id',
                                          e.target.value,
                                      )
                                  }
                              >
                                  <option value="">
                                      Select Network Zone
                                  </option>

                                  {zones.map(
                                      (zone) => (
                                          <option
                                              key={zone.id}
                                              value={zone.id}
                                          >
                                              {zone.name}{' '}
                                              —{' '}
                                              {zone.service_type.toUpperCase()}
                                          </option>
                                      ),
                                  )}
                              </select>

                              {form.errors.zone_id && (
                                  <div className="mt-1 text-sm font-semibold text-red-600">
                                      {form.errors.zone_id}
                                  </div>
                              )}
                          </label>

                        <Input
                            label="New Password"
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
                                            toggle(
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
                        Save Operator
                    </button>
                </form>
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
