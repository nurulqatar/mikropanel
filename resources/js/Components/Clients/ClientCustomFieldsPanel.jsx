import {
    useEffect,
    useState,
} from 'react';

function displayValue(
    field,
    value
) {
    if (
        value === null
        || value === undefined
        || value === ''
    ) {
        return '-';
    }

    if (
        field.type === 'boolean'
        || field.type === 'checkbox'
    ) {
        return String(value) === '1'
            ? 'Yes'
            : 'No';
    }

    return String(value);
}

export default function ClientCustomFieldsPanel({
    clientId,
}) {
    const [fields, setFields] =
        useState([]);

    const [values, setValues] =
        useState({});

    const [loading, setLoading] =
        useState(true);

    useEffect(() => {
        let active = true;

        fetch(
            `/client-custom-fields/data?client_id=${clientId}`,
            {
                headers: {
                    Accept:
                        'application/json',
                    'X-Requested-With':
                        'XMLHttpRequest',
                },
                credentials:
                    'same-origin',
            }
        )
            .then((response) => {
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

                setFields(
                    Array.isArray(
                        payload.fields
                    )
                        ? payload.fields
                        : []
                );

                setValues(
                    payload.values
                    || {}
                );
            })
            .catch((error) => {
                console.error(
                    'Client custom field display failed:',
                    error
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

    if (
        loading
        || fields.length === 0
    ) {
        return null;
    }

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="mb-5">
                <h2 className="text-lg font-bold text-slate-900">
                    Additional Client Information
                </h2>

                <p className="mt-1 text-xs text-slate-500">
                    Dynamic information from
                    Client Form Builder.
                </p>
            </div>

            <div className="grid gap-x-8 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                {fields.map(
                    (field) => (
                        <div
                            key={
                                field.id
                            }
                            className="border-b border-slate-100 pb-3"
                        >
                            <div className="text-[11px] font-semibold uppercase tracking-wide text-slate-400">
                                {
                                    field.name
                                }
                            </div>

                            <div className="mt-1 break-words text-sm font-semibold text-slate-800">
                                {displayValue(
                                    field,
                                    values[
                                        String(
                                            field.id
                                        )
                                    ]
                                )}
                            </div>
                        </div>
                    )
                )}
            </div>
        </section>
    );
}
