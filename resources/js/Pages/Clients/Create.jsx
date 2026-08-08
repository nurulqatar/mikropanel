import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import ClientCustomFieldsForm from '@/Components/Clients/ClientCustomFieldsForm';

export default function Create({
    routers,
    packages,
    ipRanges,
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        custom_fields: {},
        ip_range_id: '',
        package_id: '',
        name: '',
        mac_address: '',
        phone: '',
    });

    const selectedPackage = packages.find(
        (pkg) =>
            String(pkg.id) ===
            String(data.package_id),
    );

    const submit = (event) => {
        event.preventDefault();

        post(route('clients.store'));
    };

    const inputClass =
        'w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500';

    return (
        <AppLayout title="Add New Client">
            <Head title="Add New Client" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-800">
                        Add New Client
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Create ISP customer account
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
                                label="IP Pool"
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

                        {selectedPackage && (
                            <div className="mt-6 grid gap-4 rounded-lg bg-cyan-50 p-4 sm:grid-cols-3">
                                <Info
                                    label="Package"
                                    value={selectedPackage.name}
                                />

                                <Info
                                    label="Price"
                                    value={selectedPackage.price}
                                />

                                <Info
                                    label="Validity"
                                    value={`${selectedPackage.validity_days} Days`}
                                />
                            </div>
                        )}
                    </section>

                    {/* CLIENT_CUSTOM_FIELDS_CREATE */}
                                        <ClientCustomFieldsForm
                                            values={data.custom_fields || {}}
                                            onChange={(values) =>
                                                setData(
                                                    'custom_fields',
                                                    values,
                                                )
                                            }
                                            errors={errors}
                                        />


                    <div className="rounded-lg bg-slate-100 px-4 py-3 text-sm text-slate-600">
                        Free IP, Client Code, installation date,
                        billing day, expiry date and MikroTik
                        configuration will be generated automatically.
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-cyan-600 px-6 py-3 font-medium text-white hover:bg-cyan-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Creating Client...'
                                : 'Create Client'}
                        </button>

                        <Link
                            href={route('clients.index')}
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

function Info({ label, value }) {
    return (
        <div>
            <p className="text-sm text-slate-500">
                {label}
            </p>

            <p className="mt-1 font-bold text-slate-800">
                {value ?? '-'}
            </p>
        </div>
    );
}
