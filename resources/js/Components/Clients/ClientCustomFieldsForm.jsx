import {
    useEffect,
    useState,
} from 'react';

function FieldError({
    message,
}) {
    if (!message) {
        return null;
    }

    return (
        <div className="mt-1 text-xs font-medium text-red-600">
            {message}
        </div>
    );
}

function LoadingCard() {
    return (
        <section className="rounded-xl border border-slate-200 bg-white p-6 shadow">
            <div className="text-sm font-semibold text-slate-600">
                Loading additional client information...
            </div>
        </section>
    );
}

export default function ClientCustomFieldsForm({
    clientId = null,
    values = {},
    onChange,
    errors = {},
}) {
    const [fields, setFields] =
        useState([]);

    const [localValues, setLocalValues] =
        useState(values || {});

    const [loading, setLoading] =
        useState(true);

    const [loadError, setLoadError] =
        useState('');

    useEffect(() => {
        let active = true;

        const url = clientId
            ? `/client-custom-fields/data?client_id=${clientId}`
            : '/client-custom-fields/data';

        fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With':
                    'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(
                        `HTTP ${response.status}`
                    );
                }

                return response.json();
            })
            .then((payload) => {
                if (!active) {
                    return;
                }

                const definitions =
                    Array.isArray(
                        payload.fields
                    )
                        ? payload.fields
                        : [];

                const serverValues =
                    payload.values
                    && typeof payload.values
                        === 'object'
                        ? payload.values
                        : {};

                const merged = {
                    ...serverValues,
                    ...(values || {}),
                };

                setFields(
                    definitions
                );

                setLocalValues(
                    merged
                );

                if (
                    typeof onChange
                    === 'function'
                ) {
                    onChange(
                        merged
                    );
                }

                setLoadError('');
            })
            .catch((error) => {
                if (!active) {
                    return;
                }

                console.error(
                    'Client custom field load failed:',
                    error
                );

                setLoadError(
                    'Could not load additional client fields.'
                );
            })
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, [clientId]);

    const updateValue = (
        fieldId,
        value
    ) => {
        const key = String(
            fieldId
        );

        const next = {
            ...localValues,
            [key]: value,
        };

        setLocalValues(
            next
        );

        if (
            typeof onChange
            === 'function'
        ) {
            onChange(
                next
            );
        }
    };

    const errorFor = (
        field
    ) => {
        return (
            errors[
                `custom_fields.${field.id}`
            ] ||
            errors[
                `custom_fields.${String(
                    field.id
                )}`
            ] ||
            null
        );
    };

    const commonClass =
        'w-full rounded-lg border border-slate-300 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100';

    const renderInput = (
        field
    ) => {
        const key = String(
            field.id
        );

        const value =
            localValues[key]
            ?? '';

        switch (field.type) {
            case 'textarea':
                return (
                    <textarea
                        rows={4}
                        value={value}
                        required={
                            field.is_required
                        }
                        placeholder={
                            field.placeholder
                            || ''
                        }
                        onChange={(
                            event
                        ) =>
                            updateValue(
                                field.id,
                                event
                                    .target
                                    .value
                            )
                        }
                        className={
                            commonClass
                        }
                    />
                );

            case 'select':
                return (
                    <select
                        value={value}
                        required={
                            field.is_required
                        }
                        onChange={(
                            event
                        ) =>
                            updateValue(
                                field.id,
                                event
                                    .target
                                    .value
                            )
                        }
                        className={
                            commonClass
                        }
                    >
                        <option value="">
                            Select {field.name}
                        </option>

                        {(
                            field.options
                            || []
                        ).map(
                            (option) => (
                                <option
                                    key={
                                        option
                                    }
                                    value={
                                        option
                                    }
                                >
                                    {
                                        option
                                    }
                                </option>
                            )
                        )}
                    </select>
                );

            case 'boolean':
                return (
                    <select
                        value={value}
                        required={
                            field.is_required
                        }
                        onChange={(
                            event
                        ) =>
                            updateValue(
                                field.id,
                                event
                                    .target
                                    .value
                            )
                        }
                        className={
                            commonClass
                        }
                    >
                        <option value="">
                            Select
                        </option>

                        <option value="1">
                            Yes
                        </option>

                        <option value="0">
                            No
                        </option>
                    </select>
                );

            case 'checkbox':
                return (
                    <label className="flex min-h-[48px] cursor-pointer items-center gap-3 rounded-lg border border-slate-300 px-4 py-3">
                        <input
                            type="checkbox"
                            checked={
                                value === true
                                || value === 1
                                || value === '1'
                            }
                            onChange={(
                                event
                            ) =>
                                updateValue(
                                    field.id,
                                    event
                                        .target
                                        .checked
                                        ? '1'
                                        : '0'
                                )
                            }
                            className="h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                        />

                        <span className="text-sm text-slate-700">
                            Yes
                        </span>
                    </label>
                );

            case 'number':
                return (
                    <input
                        type="number"
                        value={value}
                        required={
                            field.is_required
                        }
                        placeholder={
                            field.placeholder
                            || ''
                        }
                        onChange={(
                            event
                        ) =>
                            updateValue(
                                field.id,
                                event
                                    .target
                                    .value
                            )
                        }
                        className={
                            commonClass
                        }
                    />
                );

            case 'email':
                return (
                    <input
                        type="email"
                        value={value}
                        required={
                            field.is_required
                        }
                        placeholder={
                            field.placeholder
                            || ''
                        }
                        onChange={(
                            event
                        ) =>
                            updateValue(
                                field.id,
                                event
                                    .target
                                    .value
                            )
                        }
                        className={
                            commonClass
                        }
                    />
                );

            case 'phone':
                return (
                    <input
                        type="tel"
                        value={value}
                        required={
                            field.is_required
                        }
                        placeholder={
                            field.placeholder
                            || ''
                        }
                        onChange={(
                            event
                        ) =>
                            updateValue(
                                field.id,
                                event
                                    .target
                                    .value
                            )
                        }
                        className={
                            commonClass
                        }
                    />
                );

            case 'date':
                return (
                    <input
                        type="date"
                        value={value}
                        required={
                            field.is_required
                        }
                        onChange={(
                            event
                        ) =>
                            updateValue(
                                field.id,
                                event
                                    .target
                                    .value
                            )
                        }
                        className={
                            commonClass
                        }
                    />
                );

            case 'text':
            default:
                return (
                    <input
                        type="text"
                        value={value}
                        required={
                            field.is_required
                        }
                        placeholder={
                            field.placeholder
                            || ''
                        }
                        onChange={(
                            event
                        ) =>
                            updateValue(
                                field.id,
                                event
                                    .target
                                    .value
                            )
                        }
                        className={
                            commonClass
                        }
                    />
                );
        }
    };

    if (loading) {
        return (
            <LoadingCard />
        );
    }

    if (
        loadError
    ) {
        return (
            <section className="rounded-xl border border-red-200 bg-red-50 p-5">
                <div className="font-semibold text-red-700">
                    Additional Client Information
                </div>

                <div className="mt-1 text-sm text-red-600">
                    {loadError}
                </div>
            </section>
        );
    }

    if (
        fields.length === 0
    ) {
        return null;
    }

    return (
        <section className="rounded-xl border border-slate-200 bg-white p-6 shadow">
            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 className="text-xl font-bold text-slate-800">
                        Additional Client Information
                    </h2>

                    <p className="mt-1 text-xs text-slate-500">
                        Custom fields managed from
                        Client Form Builder.
                    </p>
                </div>

                <div className="rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-700">
                    {fields.length} Custom Fields
                </div>
            </div>

            <div className="grid gap-5 md:grid-cols-2">
                {fields.map(
                    (field) => (
                        <div
                            key={
                                field.id
                            }
                            className={
                                field.type
                                === 'textarea'
                                    ? 'md:col-span-2'
                                    : ''
                            }
                        >
                            <label className="mb-1.5 block text-sm font-semibold text-slate-700">
                                {
                                    field.name
                                }

                                {field.is_required && (
                                    <span className="ml-1 text-red-600">
                                        *
                                    </span>
                                )}
                            </label>

                            {
                                renderInput(
                                    field
                                )
                            }

                            <FieldError
                                message={
                                    errorFor(
                                        field
                                    )
                                }
                            />
                        </div>
                    )
                )}
            </div>
        </section>
    );
}
