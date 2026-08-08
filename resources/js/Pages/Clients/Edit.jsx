import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import ClientCustomFieldsForm from '@/Components/Clients/ClientCustomFieldsForm';

export default function Edit({
    client,
    routers,
    packages,
    ipRanges,
}) {
    const {
        data,
        setData,
        put,
        processing,
        errors,
    } = useForm({
        custom_fields: {},
        ip_range_id: client.ip_range_id ?? '',
        package_id: client.package_id ?? '',
        name: client.name ?? '',
        mac_address: client.mac_address ?? '',
        phone: client.phone ?? '',
    });

    const submit = (event) => {
        event.preventDefault();

        put(route('clients.update', client.id));
    };

    const inputClass =
        'w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500';

    return (
        <AppLayout title="Edit Client">
            <Head title={`Edit - ${client.name}`} />

            <div className="mx-auto max-w-4xl space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-800">
                        Edit Client
                    </h1>

                    <p className="mt-1 text-slate-500">
                        {client.client_code} — {client.ip_address}
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6"
                >



                    <section className="rounded-xl bg-white p-6 shadow">
                        <h2 className="mb-5 text-xl font-bold text-slate-800">
                            Network Configuration
                        </h2>

                        <div className="grid gap-5 md:grid-cols-2">
<Field
                                label="IP Pool (Fixed)"
                                error={errors.ip_range_id}
                            >
                                <select
                                    value={data.ip_range_id}
                                    onChange={(event) =>
                                        setData(
                                            'ip_range_id',
                                            event.target.value,
                                        )
                                    }
                                    disabled
                                    className={inputClass}
                                >
                                    <option value="">
                                        Select IP Pool
                                    </option>

                                    {ipRanges.map(
                                        (range) => (
                                            <option
                                                key={range.id}
                                                value={range.id}
                                            >
                                                {range.name}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>
                        </div>

                        <div className="mt-5 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            Current IP Address:{' '}
                            <span className="font-mono font-semibold text-slate-800">
                                {client.ip_address}
                            </span>
                        </div>
                    </section>

                    <section className="rounded-xl bg-white p-6 shadow">
                        <h2 className="mb-5 text-xl font-bold text-slate-800">
                            Client Information
                        </h2>

                        <div className="grid gap-5 md:grid-cols-2">
                            <Field
                                label="Client Name"
                                error={errors.name}
                            >
                                <input
                                    type="text"
                                    value={data.name}
                                    onChange={(event) =>
                                        setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                    autoFocus
                                />
                            </Field>

                            <Field
                                label="Package"
                                error={errors.package_id}
                            >
                                <select
                                    value={data.package_id}
                                    onChange={(event) =>
                                        setData(
                                            'package_id',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                >
                                    <option value="">
                                        Select Package
                                    </option>

                                    {packages.map((pkg) => (
                                        <option
                                            key={pkg.id}
                                            value={pkg.id}
                                        >
                                            {pkg.name}
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <Field
                                label="MAC Address"
                                error={errors.mac_address}
                            >
                                <input
                                    type="text"
                                    value={data.mac_address}
                                    onChange={(event) =>
                                        setData(
                                            'mac_address',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="AA:BB:CC:DD:EE:FF"
                                    className={`${inputClass} font-mono`}
                                />
                            </Field>

                            <Field
                                label="Phone"
                                error={errors.phone}
                            >
                                <input
                                    type="text"
                                    value={data.phone}
                                    onChange={(event) =>
                                        setData(
                                            'phone',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>
                        </div>
                    </section>

                    {/* CLIENT_CUSTOM_FIELDS_EDIT */}
                                        <ClientCustomFieldsForm
                                            clientId={client.id}
                                            values={data.custom_fields || {}}
                                            onChange={(values) =>
                                                setData(
                                                    'custom_fields',
                                                    values,
                                                )
                                            }
                                            errors={errors}
                                        />


                    <div className="rounded-lg bg-cyan-50 px-4 py-3 text-sm text-cyan-800">
                        Package বা MAC পরিবর্তন করলে MikroTik DHCP
                        Lease, ARP এবং Simple Queue update হবে।
                        Installation date, billing day এবং expiry date
                        অপরিবর্তিত থাকবে।
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-cyan-600 px-6 py-3 font-medium text-white hover:bg-cyan-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Updating Client...'
                                : 'Update Client'}
                        </button>

                        <Link
                            href={route(
                                'clients.show',
                                client.id,
                            )}
                            className="rounded-lg bg-slate-600 px-6 py-3 font-medium text-white hover:bg-slate-700"
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
            <label className="mb-1.5 block font-medium text-slate-700">
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
