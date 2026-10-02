import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';

export default function ClientFormFields({
    fields = [],
    fieldTypes = [],
}) {
    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
    } = useForm({
        name: '',
        type: 'text',
        options: '',
        is_required: false,
        is_enabled: true,
        show_in_list: false,
        show_in_reports: false,
        show_in_invoice: false,
    });

    const submit = (event) => {
        event.preventDefault();

        post(
            route(
                'reseller.mac-clients.form-fields.store',
            ),
            {
                preserveScroll: true,
                onSuccess: () =>
                    reset(),
            },
        );
    };

    const toggle = (field) => {
        router.patch(
            route(
                'reseller.mac-clients.form-fields.toggle',
                field.id,
            ),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const remove = (field) => {
        if (
            !window.confirm(
                `Delete "${field.name}" and its stored client values?`,
            )
        ) {
            return;
        }

        router.delete(
            route(
                'reseller.mac-clients.form-fields.destroy',
                field.id,
            ),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="Client Form Fields">
            <Head title="Client Form Fields" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-3xl font-black text-slate-900">
                            Client Form Fields
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Add or remove reseller-specific client information fields
                        </p>
                    </div>

                    <Link
                        href={route(
                            'reseller.mac-clients.migration',
                        )}
                        className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-black text-slate-700"
                    >
                        ← IMPORT / EXPORT
                    </Link>
                </div>

                <section className="rounded-2xl border border-indigo-200 bg-white p-6 shadow-sm">
                    <h2 className="text-xl font-black text-slate-900">
                        Add Field
                    </h2>

                    <form
                        onSubmit={submit}
                        className="mt-5 grid gap-4 md:grid-cols-2"
                    >
                        <Field
                            label="Field Name"
                            error={
                                errors.name
                            }
                        >
                            <input
                                type="text"
                                value={
                                    data.name
                                }
                                onChange={(event) =>
                                    setData(
                                        'name',
                                        event.target.value,
                                    )
                                }
                                placeholder="Room No, Passport, Building..."
                                className={inputClass}
                            />
                        </Field>

                        <Field
                            label="Field Type"
                            error={
                                errors.type
                            }
                        >
                            <select
                                value={
                                    data.type
                                }
                                onChange={(event) =>
                                    setData(
                                        'type',
                                        event.target.value,
                                    )
                                }
                                className={inputClass}
                            >
                                {fieldTypes.map(
                                    (type) => (
                                        <option
                                            key={type}
                                            value={type}
                                        >
                                            {type}
                                        </option>
                                    ),
                                )}
                            </select>
                        </Field>

                        {data.type === 'select' && (
                            <div className="md:col-span-2">
                                <Field
                                    label="Dropdown Options"
                                    error={
                                        errors.options
                                    }
                                >
                                    <textarea
                                        value={
                                            data.options
                                        }
                                        onChange={(event) =>
                                            setData(
                                                'options',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="One, Two, Three"
                                        className={inputClass}
                                        rows={3}
                                    />
                                </Field>
                            </div>
                        )}

                        <div className="md:col-span-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            <Check
                                label="Required on manual form"
                                checked={
                                    data.is_required
                                }
                                onChange={(value) =>
                                    setData(
                                        'is_required',
                                        value,
                                    )
                                }
                            />

                            <Check
                                label="Enabled"
                                checked={
                                    data.is_enabled
                                }
                                onChange={(value) =>
                                    setData(
                                        'is_enabled',
                                        value,
                                    )
                                }
                            />

                            <Check
                                label="Show in client list"
                                checked={
                                    data.show_in_list
                                }
                                onChange={(value) =>
                                    setData(
                                        'show_in_list',
                                        value,
                                    )
                                }
                            />

                            <Check
                                label="Show in reports"
                                checked={
                                    data.show_in_reports
                                }
                                onChange={(value) =>
                                    setData(
                                        'show_in_reports',
                                        value,
                                    )
                                }
                            />

                            <Check
                                label="Show in invoice"
                                checked={
                                    data.show_in_invoice
                                }
                                onChange={(value) =>
                                    setData(
                                        'show_in_invoice',
                                        value,
                                    )
                                }
                            />
                        </div>

                        <div className="md:col-span-2">
                            <button
                                type="submit"
                                disabled={
                                    processing
                                }
                                className="rounded-xl bg-indigo-600 px-6 py-3 font-black text-white disabled:opacity-50"
                            >
                                ADD FIELD
                            </button>
                        </div>
                    </form>

                    <div className="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">
                        “Required” applies to normal manual Add/Edit Client. During legacy Excel migration only Client Name, Mobile Number and MAC Address are mandatory.
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-xl font-black text-slate-900">
                        Current Fields
                    </h2>

                    <div className="mt-4 space-y-3">
                        {fields.length === 0 && (
                            <div className="rounded-xl bg-slate-50 p-8 text-center text-sm text-slate-400">
                                No custom fields yet.
                            </div>
                        )}

                        {fields.map(
                            (field) => (
                                <div
                                    key={field.id}
                                    className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 p-4"
                                >
                                    <div>
                                        <div className="font-black text-slate-900">
                                            {field.name}
                                        </div>

                                        <div className="mt-1 text-xs text-slate-500">
                                            {field.field_key}
                                            {' · '}
                                            {field.type}
                                            {' · '}
                                            {field.values_count}
                                            {' stored values'}
                                        </div>

                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {field.is_required && (
                                                <Badge>
                                                    REQUIRED
                                                </Badge>
                                            )}

                                            <Badge>
                                                {field.is_enabled
                                                    ? 'ENABLED'
                                                    : 'DISABLED'}
                                            </Badge>

                                            {field.show_in_reports && (
                                                <Badge>
                                                    REPORT
                                                </Badge>
                                            )}

                                            {field.show_in_invoice && (
                                                <Badge>
                                                    INVOICE
                                                </Badge>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex gap-2">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                toggle(
                                                    field,
                                                )
                                            }
                                            className="rounded-lg bg-cyan-600 px-4 py-2 text-sm font-black text-white"
                                        >
                                            {field.is_enabled
                                                ? 'DISABLE'
                                                : 'ENABLE'}
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                remove(
                                                    field,
                                                )
                                            }
                                            className="rounded-lg bg-red-600 px-4 py-2 text-sm font-black text-white"
                                        >
                                            DELETE
                                        </button>
                                    </div>
                                </div>
                            ),
                        )}
                    </div>
                </section>
            </div>
        </AppLayout>
    );
}

const inputClass =
    'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100';

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

function Check({
    label,
    checked,
    onChange,
}) {
    return (
        <label className="flex items-center gap-2 rounded-xl bg-slate-50 p-3 text-sm font-bold text-slate-700">
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) =>
                    onChange(
                        event.target.checked,
                    )
                }
            />
            {label}
        </label>
    );
}

function Badge({
    children,
}) {
    return (
        <span className="rounded-md bg-slate-100 px-2 py-1 text-[10px] font-black text-slate-600">
            {children}
        </span>
    );
}
