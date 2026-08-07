import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

export default function Edit({ router }) {
    const {
        data,
        setData,
        put,
        processing,
        errors,
    } = useForm({
        name: router.name ?? '',
        host: router.host ?? '',
        api_port: router.api_port ?? 8728,
        username: router.username ?? '',
        password: '',
        use_ssl: Boolean(router.use_ssl),
        enabled: Boolean(router.enabled),
    });

    const submit = (event) => {
        event.preventDefault();

        put(route('routers.update', router.id));
    };

    const inputClass =
        'mt-1 w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500';

    return (
        <AppLayout title="Edit Router">
            <Head title="Edit Router" />

            <div className="mx-auto max-w-3xl space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-800">
                        Edit MikroTik Router
                    </h1>

                    <p className="mt-1 text-slate-500">
                        {router.identity ||
                            router.name} — {router.host}
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-xl bg-white p-6 shadow"
                >
                    <div className="grid gap-5 md:grid-cols-2">
                        <Field
                            label="Router Name"
                            error={errors.name}
                        >
                            <input
                                className={inputClass}
                                value={data.name}
                                onChange={(event) =>
                                    setData(
                                        'name',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>

                        <Field
                            label="Router IP"
                            error={errors.host}
                        >
                            <input
                                className={inputClass}
                                value={data.host}
                                onChange={(event) =>
                                    setData(
                                        'host',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>

                        <Field
                            label="API Port"
                            error={errors.api_port}
                        >
                            <input
                                type="number"
                                className={inputClass}
                                value={data.api_port}
                                onChange={(event) =>
                                    setData(
                                        'api_port',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>

                        <Field
                            label="Username"
                            error={errors.username}
                        >
                            <input
                                className={inputClass}
                                value={data.username}
                                onChange={(event) =>
                                    setData(
                                        'username',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>

                        <Field
                            label="New Password"
                            error={errors.password}
                        >
                            <input
                                type="password"
                                className={inputClass}
                                value={data.password}
                                onChange={(event) =>
                                    setData(
                                        'password',
                                        event.target.value,
                                    )
                                }
                                placeholder="Leave blank to keep current password"
                            />
                        </Field>
                    </div>

                    <div className="flex flex-wrap gap-6 rounded-lg bg-slate-50 p-4">
                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={data.use_ssl}
                                onChange={(event) =>
                                    setData(
                                        'use_ssl',
                                        event.target.checked,
                                    )
                                }
                            />

                            Use SSL
                        </label>

                        <label className="flex items-center gap-2">
                            <input
                                type="checkbox"
                                checked={data.enabled}
                                onChange={(event) =>
                                    setData(
                                        'enabled',
                                        event.target.checked,
                                    )
                                }
                            />

                            Enabled
                        </label>
                    </div>

                    <div className="rounded-lg bg-cyan-50 px-4 py-3 text-sm text-cyan-800">
                        Update করার পর MikroTik API connection
                        automatic test এবং sync হবে।
                    </div>

                    <div className="flex gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-cyan-600 px-6 py-3 font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Testing & Updating...'
                                : 'Update Router'}
                        </button>

                        <Link
                            href={route('routers.index')}
                            className="rounded-lg bg-slate-600 px-6 py-3 font-semibold text-white hover:bg-slate-700"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

function Field({ label, error, children }) {
    return (
        <div>
            <label className="block text-sm font-semibold text-slate-700">
                {label}
            </label>

            {children}

            {error && (
                <p className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}
