import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

export default function Create() {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        name: '',
        host: '',
        api_port: 8728,
        username: '',
        password: '',
        client_interface: '',
        dhcp_server: '',
        use_ssl: false,
        enabled: true,
    });

    const submit = (event) => {
        event.preventDefault();

        post(route('routers.store'));
    };

    const inputClass =
        'mt-1 w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500';

    return (
        <AppLayout title="Add Router">
            <Head title="Add Router" />

            <div className="mx-auto max-w-3xl space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-800">
                        Add MikroTik Router
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Add RouterOS API connection
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
                                placeholder="Fruits Camp"
                                autoFocus
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
                                placeholder="10.10.10.2"
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
                            label="Password"
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
                            />
                        </Field>

                        <Field
                            label="Client Interface"
                            error={errors.client_interface}
                        >
                            <input
                                className={inputClass}
                                value={data.client_interface}
                                onChange={(event) =>
                                    setData(
                                        'client_interface',
                                        event.target.value,
                                    )
                                }
                                placeholder="bridge-LAN"
                            />

                            <p className="mt-1 text-xs text-slate-500">
                                LAN/bridge interface used for static ARP.
                            </p>
                        </Field>

                        <Field
                            label="DHCP Server"
                            error={errors.dhcp_server}
                        >
                            <input
                                className={inputClass}
                                value={data.dhcp_server}
                                onChange={(event) =>
                                    setData(
                                        'dhcp_server',
                                        event.target.value,
                                    )
                                }
                                placeholder="dhcp-client-server"
                            />

                            <p className="mt-1 text-xs text-slate-500">
                                Existing RouterOS DHCP server name.
                            </p>
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

                    <div className="flex gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-cyan-600 px-6 py-3 font-semibold text-white hover:bg-cyan-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Testing & Saving...'
                                : 'Save Router'}
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
