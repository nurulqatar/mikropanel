import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

export default function ClientMigration({
    packages = [],
    ipRanges = [],
    customFields = [],
    permissions = {},
    importResult = null,
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
    } = useForm({
        file: null,
        default_package_id:
            packages[0]?.id
            ?? '',
    });

    const submit = (event) => {
        event.preventDefault();

        post(
            route(
                'reseller.mac-clients.import',
            ),
            {
                forceFormData: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="Client Migration">
            <Head title="Client Migration" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-3xl font-black text-slate-900">
                            MAC Client Migration
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Excel import/export for old clients with preserved recharge and expiry dates
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {permissions.form_builder && (
                            <Link
                                href={route(
                                    'reseller.mac-clients.form-fields.index',
                                )}
                                className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-black text-white"
                            >
                                FORM FIELDS
                            </Link>
                        )}

                        <Link
                            href={route(
                                'reseller.mac-pos',
                            )}
                            className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-black text-slate-700"
                        >
                            MAC POS
                        </Link>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3">
                    <Card
                        title="Only 3 Mandatory"
                        text="Client Name, Mobile Number and MAC Address."
                    />

                    <Card
                        title="No Opening Money"
                        text="Legacy import creates no invoice, payment or collection for the current old-system service period."
                    />

                    <Card
                        title="Automatic Network"
                        text="The package selects the Network Zone. MikroPanel automatically selects an enabled pool and a free IP in that zone."
                    />
                </div>

                <section className="rounded-2xl border border-cyan-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-xl font-black text-slate-900">
                                Excel Template & Export
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Every enabled custom client form field is added to the Excel template automatically.
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <a
                                href={route(
                                    'reseller.mac-clients.template',
                                )}
                                className="rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-black text-white"
                            >
                                DOWNLOAD TEMPLATE
                            </a>

                            {permissions.export && (
                                <a
                                    href={route(
                                        'reseller.mac-clients.export',
                                    )}
                                    className="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-black text-white"
                                >
                                    EXPORT CLIENTS
                                </a>
                            )}
                        </div>
                    </div>

                    <div className="mt-5 rounded-xl bg-slate-50 p-4">
                        <div className="text-sm font-black text-slate-700">
                            Dynamic Excel Fields
                        </div>

                        <div className="mt-2 flex flex-wrap gap-2">
                            {customFields.length === 0 ? (
                                <span className="text-sm text-slate-400">
                                    No custom form fields yet.
                                </span>
                            ) : (
                                customFields.map(
                                    (field) => (
                                        <span
                                            key={field.id}
                                            className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700"
                                        >
                                            {field.name}
                                        </span>
                                    ),
                                )
                            )}
                        </div>
                    </div>
                </section>

                {permissions.import && (
                    <section className="rounded-2xl border border-violet-200 bg-white p-6 shadow-sm">
                        <h2 className="text-xl font-black text-slate-900">
                            Bulk Import
                        </h2>

                        <form
                            onSubmit={submit}
                            className="mt-5 grid gap-4 lg:grid-cols-3"
                        >
                            <Field
                                label="Default Package"
                                error={
                                    errors.default_package_id
                                }
                            >
                                <select
                                    value={
                                        data.default_package_id
                                    }
                                    onChange={(event) =>
                                        setData(
                                            'default_package_id',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                >
                                    <option value="">
                                        Select Package
                                    </option>

                                    {packages.map(
                                        (pkg) => (
                                            <option
                                                key={pkg.id}
                                                value={pkg.id}
                                            >
                                                {pkg.name}
                                                {pkg.zone?.name
                                                    ? ` · ${pkg.zone.name}`
                                                    : ''}
                                                {' · '}
                                                {pkg.validity_days}
                                                {' days'}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>

                            <Field
                                label="Excel File"
                                error={
                                    errors.file
                                }
                            >
                                <input
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    onChange={(event) =>
                                        setData(
                                            'file',
                                            event.target.files?.[0]
                                            ?? null,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>

                            <div className="lg:col-span-3">
                                <button
                                    type="submit"
                                    disabled={
                                        processing
                                    }
                                    className="rounded-xl bg-violet-600 px-6 py-3 font-black text-white disabled:opacity-50"
                                >
                                    {processing
                                        ? 'IMPORTING...'
                                        : 'IMPORT CLIENTS'}
                                </button>
                            </div>
                        </form>

                        <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900">
                            Example: Recharge Date 02-09-2026 with a 30-day package becomes Expiry Date 02-10-2026. The client expires on 02-10-2026 itself. If both recharge and expiry are blank, the imported client remains suspended until edited or renewed.
                        </div>
                    </section>
                )}

                {importResult && (
                    <section className="rounded-2xl border border-emerald-200 bg-white p-6 shadow-sm">
                        <h2 className="text-xl font-black text-slate-900">
                            Last Import Result
                        </h2>

                        <div className="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                            <Result
                                label="Imported"
                                value={
                                    importResult.success
                                }
                            />

                            <Result
                                label="Failed"
                                value={
                                    importResult.failed
                                }
                            />

                            <Result
                                label="Active"
                                value={
                                    importResult.active
                                }
                            />

                            <Result
                                label="Suspended"
                                value={
                                    importResult.suspended
                                }
                            />

                            <Result
                                label="Invoices"
                                value={
                                    importResult.opening_invoices_created
                                }
                            />

                            <Result
                                label="Payments"
                                value={
                                    importResult.opening_payments_created
                                }
                            />
                        </div>

                        {importResult.errors?.length > 0 && (
                            <div className="mt-4 rounded-xl bg-red-50 p-4">
                                <div className="font-black text-red-700">
                                    Errors
                                </div>

                                {importResult.errors.map(
                                    (message, index) => (
                                        <div
                                            key={index}
                                            className="mt-1 text-sm text-red-700"
                                        >
                                            {message}
                                        </div>
                                    ),
                                )}
                            </div>
                        )}
                    </section>
                )}
            </div>
        </AppLayout>
    );
}

const inputClass =
    'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100';

function Card({
    title,
    text,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="font-black text-slate-900">
                {title}
            </div>

            <p className="mt-2 text-sm leading-6 text-slate-500">
                {text}
            </p>
        </div>
    );
}

function Field({
    label,
    error,
    children,
}) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-black text-slate-700">
                {label}
            </span>

            {children}

            {error && (
                <span className="mt-1 block text-xs font-bold text-red-600">
                    {error}
                </span>
            )}
        </label>
    );
}

function Result({
    label,
    value,
}) {
    return (
        <div className="rounded-xl bg-slate-50 p-4">
            <div className="text-xs font-black uppercase tracking-wide text-slate-400">
                {label}
            </div>

            <div className="mt-1 text-xl font-black text-slate-900">
                {value ?? 0}
            </div>
        </div>
    );
}
