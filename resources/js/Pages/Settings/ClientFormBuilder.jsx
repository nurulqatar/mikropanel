import React, { useMemo, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';

const emptyForm = {
    name: '',
    type: 'text',
    placeholder: '',
    options: '',
    is_required: false,
    is_enabled: true,
    show_in_list: false,
    show_in_reports: true,
    show_in_invoice: false,
    sort_order: 10,
};

function Switch({
    checked,
    onChange,
    disabled = false,
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={() => {
                if (!disabled) {
                    onChange(!checked);
                }
            }}
            className={[
                'relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition',
                checked
                    ? 'bg-emerald-600'
                    : 'bg-slate-300',
                disabled
                    ? 'cursor-not-allowed opacity-50'
                    : 'cursor-pointer',
            ].join(' ')}
        >
            <span
                className={[
                    'inline-block h-4 w-4 rounded-full bg-white shadow transition',
                    checked
                        ? 'translate-x-6'
                        : 'translate-x-1',
                ].join(' ')}
            />
        </button>
    );
}

function Badge({
    children,
    tone = 'slate',
}) {
    const tones = {
        slate:
            'bg-slate-100 text-slate-700 ring-slate-200',
        blue:
            'bg-blue-50 text-blue-700 ring-blue-200',
        green:
            'bg-emerald-50 text-emerald-700 ring-emerald-200',
        amber:
            'bg-amber-50 text-amber-700 ring-amber-200',
        red:
            'bg-red-50 text-red-700 ring-red-200',
        violet:
            'bg-violet-50 text-violet-700 ring-violet-200',
    };

    return (
        <span
            className={[
                'inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                tones[tone] || tones.slate,
            ].join(' ')}
        >
            {children}
        </span>
    );
}

function SummaryCard({
    title,
    value,
    subtitle,
}) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {title}
            </div>

            <div className="mt-1 text-2xl font-bold text-slate-900">
                {value}
            </div>

            <div className="mt-1 text-xs text-slate-500">
                {subtitle}
            </div>
        </div>
    );
}

export default function ClientFormBuilder({
    fields = [],
    fieldTypes = [],
    summary = {},
}) {
    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm(emptyForm);

    const [editingId, setEditingId] =
        useState(null);

    const [search, setSearch] =
        useState('');

    const editingField = useMemo(
        () =>
            fields.find(
                (field) =>
                    field.id === editingId
            ) || null,
        [fields, editingId]
    );

    const filteredFields = useMemo(() => {
        const needle = search
            .trim()
            .toLowerCase();

        if (!needle) {
            return fields;
        }

        return fields.filter((field) => {
            return [
                field.name,
                field.field_key,
                field.type,
            ]
                .join(' ')
                .toLowerCase()
                .includes(needle);
        });
    }, [fields, search]);

    const resetEditor = () => {
        setEditingId(null);
        reset();
        clearErrors();

        setData({
            ...emptyForm,
            sort_order:
                fields.length > 0
                    ? Math.max(
                          ...fields.map(
                              (field) =>
                                  Number(
                                      field.sort_order
                                  ) || 0
                          )
                      ) + 10
                    : 10,
        });
    };

    const startEdit = (field) => {
        setEditingId(field.id);
        clearErrors();

        setData({
            name: field.name || '',
            type: field.type || 'text',
            placeholder:
                field.placeholder || '',
            options: Array.isArray(
                field.options
            )
                ? field.options.join('\n')
                : '',
            is_required:
                Boolean(field.is_required),
            is_enabled:
                Boolean(field.is_enabled),
            show_in_list:
                Boolean(field.show_in_list),
            show_in_reports:
                Boolean(
                    field.show_in_reports
                ),
            show_in_invoice:
                Boolean(
                    field.show_in_invoice
                ),
            sort_order:
                Number(field.sort_order) ||
                0,
        });

        window.scrollTo({
            top: 0,
            behavior: 'smooth',
        });
    };

    const submit = (event) => {
        event.preventDefault();

        if (editingId) {
            put(
                `/settings/client-form-builder/${editingId}`,
                {
                    preserveScroll: true,
                    onSuccess: () =>
                        resetEditor(),
                }
            );

            return;
        }

        post(
            '/settings/client-form-builder',
            {
                preserveScroll: true,
                onSuccess: () =>
                    resetEditor(),
            }
        );
    };

    const toggleField = (
        field,
        property
    ) => {
        router.patch(
            `/settings/client-form-builder/${field.id}/toggle`,
            {
                property,
                value:
                    !Boolean(
                        field[property]
                    ),
            },
            {
                preserveScroll: true,
                preserveState: true,
            }
        );
    };

    const updateOrder = (
        field,
        value
    ) => {
        const order = Number(value);

        if (
            Number.isNaN(order) ||
            order < 0
        ) {
            return;
        }

        router.patch(
            `/settings/client-form-builder/${field.id}/order`,
            {
                sort_order: order,
            },
            {
                preserveScroll: true,
                preserveState: true,
            }
        );
    };

    const disableField = (field) => {
        const ok = window.confirm(
            `Turn OFF "${field.name}"?\n\nThe field will be hidden from Add/Edit Client, but all saved data will remain.`
        );

        if (!ok) {
            return;
        }

        router.patch(
            `/settings/client-form-builder/${field.id}/toggle`,
            {
                property: 'is_enabled',
                value: false,
            },
            {
                preserveScroll: true,
                preserveState: true,
            }
        );
    };

    const deleteField = (field) => {
        const dataWarning =
            Number(field.values_count || 0) > 0
                ? `\n\nWARNING: ${field.values_count} saved client value(s) will also be permanently deleted.`
                : '';

        const ok = window.confirm(
            `PERMANENTLY DELETE "${field.name}"?`
            + dataWarning
            + '\n\nThis action cannot be undone.'
        );

        if (!ok) {
            return;
        }

        router.delete(
            `/settings/client-form-builder/${field.id}`,
            {
                preserveScroll: true,
            }
        );
    };

    const typeLabel = (type) => {
        return (
            fieldTypes.find(
                (item) =>
                    item.value === type
            )?.label || type
        );
    };

    return (
        <AppLayout>
            <Head title="Client Form Builder" />

            <div className="space-y-5 p-4 sm:p-6">
                <div className="rounded-2xl border border-slate-200 bg-gradient-to-r from-slate-950 to-slate-800 p-5 text-white shadow-sm">
                    <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div className="text-xs font-bold uppercase tracking-[0.2em] text-sky-300">
                                MikroPanel
                            </div>

                            <h1 className="mt-1 text-2xl font-bold">
                                Client Form Builder
                            </h1>

                            <p className="mt-1 max-w-3xl text-sm text-slate-300">
                                Create and control custom
                                client information fields
                                without changing database
                                columns or application code.
                            </p>
                        </div>

                        <div className="rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-xs text-slate-300">
                            Core MikroTik, Package,
                            IP Pool and Billing fields are
                            system protected.
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <SummaryCard
                        title="Total Fields"
                        value={
                            summary.total || 0
                        }
                        subtitle="Custom information fields"
                    />

                    <SummaryCard
                        title="Enabled"
                        value={
                            summary.enabled || 0
                        }
                        subtitle="Visible in client forms"
                    />

                    <SummaryCard
                        title="Required"
                        value={
                            summary.required || 0
                        }
                        subtitle="Must be completed"
                    />

                    <SummaryCard
                        title="Reports"
                        value={
                            summary.report_fields ||
                            0
                        }
                        subtitle="Included in reports"
                    />
                </div>

                <div className="grid gap-5 xl:grid-cols-[380px_minmax(0,1fr)]">
                    <div className="h-fit rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-5 py-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h2 className="font-bold text-slate-900">
                                        {editingField
                                            ? 'Edit Field'
                                            : 'Add New Field'}
                                    </h2>

                                    <p className="mt-0.5 text-xs text-slate-500">
                                        {editingField
                                            ? `Editing: ${editingField.field_key}`
                                            : 'Create a new client information box'}
                                    </p>
                                </div>

                                {editingId && (
                                    <button
                                        type="button"
                                        onClick={
                                            resetEditor
                                        }
                                        className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                    >
                                        Cancel
                                    </button>
                                )}
                            </div>
                        </div>

                        <form
                            onSubmit={submit}
                            className="space-y-4 p-5"
                        >
                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700">
                                    Field Name *
                                </label>

                                <input
                                    type="text"
                                    value={
                                        data.name
                                    }
                                    onChange={(
                                        event
                                    ) =>
                                        setData(
                                            'name',
                                            event
                                                .target
                                                .value
                                        )
                                    }
                                    placeholder="Example: Passport Number"
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-sky-500 focus:ring-2 focus:ring-sky-100"
                                />

                                {errors.name && (
                                    <div className="mt-1 text-xs text-red-600">
                                        {
                                            errors.name
                                        }
                                    </div>
                                )}
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700">
                                    Field Type *
                                </label>

                                <select
                                    value={
                                        data.type
                                    }
                                    onChange={(
                                        event
                                    ) =>
                                        setData(
                                            'type',
                                            event
                                                .target
                                                .value
                                        )
                                    }
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-sky-500"
                                >
                                    {fieldTypes.map(
                                        (
                                            item
                                        ) => (
                                            <option
                                                key={
                                                    item.value
                                                }
                                                value={
                                                    item.value
                                                }
                                            >
                                                {
                                                    item.label
                                                }
                                            </option>
                                        )
                                    )}
                                </select>

                                {errors.type && (
                                    <div className="mt-1 text-xs text-red-600">
                                        {
                                            errors.type
                                        }
                                    </div>
                                )}
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700">
                                    Placeholder
                                </label>

                                <input
                                    type="text"
                                    value={
                                        data.placeholder
                                    }
                                    onChange={(
                                        event
                                    ) =>
                                        setData(
                                            'placeholder',
                                            event
                                                .target
                                                .value
                                        )
                                    }
                                    placeholder="Example: Enter passport number"
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-sky-500"
                                />
                            </div>

                            {data.type ===
                                'select' && (
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-slate-700">
                                        Dropdown
                                        Options
                                    </label>

                                    <textarea
                                        rows="5"
                                        value={
                                            data.options
                                        }
                                        onChange={(
                                            event
                                        ) =>
                                            setData(
                                                'options',
                                                event
                                                    .target
                                                    .value
                                            )
                                        }
                                        placeholder={
                                            'Residential\nCommercial\nVIP\nStaff'
                                        }
                                        className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-sky-500"
                                    />

                                    <p className="mt-1 text-[11px] text-slate-500">
                                        One option
                                        per line.
                                    </p>
                                </div>
                            )}

                            <div>
                                <label className="mb-1 block text-xs font-semibold text-slate-700">
                                    Display Order
                                </label>

                                <input
                                    type="number"
                                    min="0"
                                    max="9999"
                                    value={
                                        data.sort_order
                                    }
                                    onChange={(
                                        event
                                    ) =>
                                        setData(
                                            'sort_order',
                                            event
                                                .target
                                                .value
                                        )
                                    }
                                    className="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm outline-none focus:border-sky-500"
                                />
                            </div>

                            <div className="divide-y divide-slate-100 rounded-xl border border-slate-200">
                                {[
                                    [
                                        'is_enabled',
                                        'Enabled',
                                        'Show this field in client forms',
                                    ],
                                    [
                                        'is_required',
                                        'Required',
                                        'Client cannot be saved without this value',
                                    ],
                                    [
                                        'show_in_list',
                                        'Client List',
                                        'Allow this field as a client list column',
                                    ],
                                    [
                                        'show_in_reports',
                                        'Reports',
                                        'Include this information in client reports',
                                    ],
                                    [
                                        'show_in_invoice',
                                        'Invoice / Print',
                                        'Allow this field in invoice and printable documents',
                                    ],
                                ].map(
                                    ([
                                        key,
                                        title,
                                        description,
                                    ]) => (
                                        <div
                                            key={
                                                key
                                            }
                                            className="flex items-center justify-between gap-3 p-3"
                                        >
                                            <div>
                                                <div className="text-xs font-semibold text-slate-800">
                                                    {
                                                        title
                                                    }
                                                </div>

                                                <div className="text-[11px] leading-4 text-slate-500">
                                                    {
                                                        description
                                                    }
                                                </div>
                                            </div>

                                            <Switch
                                                checked={Boolean(
                                                    data[
                                                        key
                                                    ]
                                                )}
                                                onChange={(
                                                    value
                                                ) =>
                                                    setData(
                                                        key,
                                                        value
                                                    )
                                                }
                                            />
                                        </div>
                                    )
                                )}
                            </div>

                            <button
                                type="submit"
                                disabled={
                                    processing
                                }
                                className="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-slate-800 disabled:opacity-50"
                            >
                                {processing
                                    ? 'Saving...'
                                    : editingId
                                    ? 'Update Field'
                                    : 'Add Field'}
                            </button>
                        </form>
                    </div>

                    <div className="min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 className="font-bold text-slate-900">
                                    Custom Fields
                                </h2>

                                <p className="mt-0.5 text-xs text-slate-500">
                                    OFF fields keep
                                    all previously saved
                                    client data.
                                </p>
                            </div>

                            <input
                                type="search"
                                value={search}
                                onChange={(
                                    event
                                ) =>
                                    setSearch(
                                        event.target
                                            .value
                                    )
                                }
                                placeholder="Search fields..."
                                className="w-full rounded-lg border border-slate-300 px-3 py-2 text-xs outline-none focus:border-sky-500 sm:w-56"
                            />
                        </div>

                        {filteredFields.length ===
                        0 ? (
                            <div className="px-6 py-16 text-center">
                                <div className="text-lg font-bold text-slate-700">
                                    No custom fields
                                </div>

                                <p className="mt-1 text-sm text-slate-500">
                                    Add your first
                                    field from the
                                    form builder.
                                </p>
                            </div>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[1050px] text-left">
                                    <thead className="bg-slate-50">
                                        <tr className="border-b border-slate-200 text-[11px] uppercase tracking-wide text-slate-500">
                                            <th className="px-3 py-2">
                                                Field
                                            </th>

                                            <th className="px-3 py-2">
                                                Type
                                            </th>

                                            <th className="px-3 py-2 text-center">
                                                On
                                            </th>

                                            <th className="px-3 py-2 text-center">
                                                Req.
                                            </th>

                                            <th className="px-3 py-2 text-center">
                                                List
                                            </th>

                                            <th className="px-3 py-2 text-center">
                                                Report
                                            </th>

                                            <th className="px-3 py-2 text-center">
                                                Invoice
                                            </th>

                                            <th className="px-3 py-2 text-center">
                                                Order
                                            </th>

                                            <th className="px-3 py-2 text-center">
                                                Data
                                            </th>

                                            <th className="px-3 py-2 text-right">
                                                Action
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-slate-100">
                                        {filteredFields.map(
                                            (
                                                field
                                            ) => (
                                                <tr
                                                    key={
                                                        field.id
                                                    }
                                                    className={[
                                                        'text-xs transition hover:bg-slate-50',
                                                        !field.is_enabled
                                                            ? 'bg-slate-50/60 opacity-70'
                                                            : '',
                                                    ].join(
                                                        ' '
                                                    )}
                                                >
                                                    <td className="px-3 py-2">
                                                        <div className="font-semibold text-slate-900">
                                                            {
                                                                field.name
                                                            }
                                                        </div>

                                                        <div className="mt-0.5 font-mono text-[10px] text-slate-400">
                                                            {
                                                                field.field_key
                                                            }
                                                        </div>
                                                    </td>

                                                    <td className="px-3 py-2">
                                                        <Badge tone="blue">
                                                            {typeLabel(
                                                                field.type
                                                            )}
                                                        </Badge>
                                                    </td>

                                                    {[
                                                        'is_enabled',
                                                        'is_required',
                                                        'show_in_list',
                                                        'show_in_reports',
                                                        'show_in_invoice',
                                                    ].map(
                                                        (
                                                            property
                                                        ) => (
                                                            <td
                                                                key={
                                                                    property
                                                                }
                                                                className="px-3 py-2 text-center"
                                                            >
                                                                <Switch
                                                                    checked={Boolean(
                                                                        field[
                                                                            property
                                                                        ]
                                                                    )}
                                                                    onChange={() =>
                                                                        toggleField(
                                                                            field,
                                                                            property
                                                                        )
                                                                    }
                                                                />
                                                            </td>
                                                        )
                                                    )}

                                                    <td className="px-3 py-2 text-center">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            max="9999"
                                                            defaultValue={
                                                                field.sort_order
                                                            }
                                                            onBlur={(
                                                                event
                                                            ) =>
                                                                updateOrder(
                                                                    field,
                                                                    event
                                                                        .target
                                                                        .value
                                                                )
                                                            }
                                                            className="w-16 rounded-md border border-slate-200 px-2 py-1 text-center text-xs"
                                                        />
                                                    </td>

                                                    <td className="px-3 py-2 text-center">
                                                        <Badge
                                                            tone={
                                                                field.values_count >
                                                                0
                                                                    ? 'green'
                                                                    : 'slate'
                                                            }
                                                        >
                                                            {
                                                                field.values_count
                                                            }
                                                        </Badge>
                                                    </td>

                                                    <td className="px-3 py-2">
                                                        <div className="flex justify-end gap-1">
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    startEdit(
                                                                        field
                                                                    )
                                                                }
                                                                className="rounded-md border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100"
                                                            >
                                                                Edit
                                                            </button>

                                                            {field.is_enabled && (
                                                                <button
                                                                    type="button"
                                                                    onClick={() =>
                                                                        disableField(
                                                                            field
                                                                        )
                                                                    }
                                                                    className="rounded-md border border-red-200 px-2.5 py-1 text-[11px] font-semibold text-red-600 hover:bg-red-50"
                                                                >
                                                                    Off
                                                                </button>
                                                            )}

                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    deleteField(
                                                                        field
                                                                    )
                                                                }
                                                                className="rounded-md bg-red-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-red-700"
                                                            >
                                                                Delete
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            )
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>

                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <div className="text-sm font-bold text-amber-900">
                        System Protected Fields
                    </div>

                    <p className="mt-1 text-xs leading-5 text-amber-800">
                        Client Name, MAC Address,
                        IP Pool, Package, installation,
                        expiry and MikroTik provisioning
                        fields are core system fields.
                        They are intentionally not
                        removable from this builder.
                    </p>
                </div>
            </div>
        </AppLayout>
    );
}
