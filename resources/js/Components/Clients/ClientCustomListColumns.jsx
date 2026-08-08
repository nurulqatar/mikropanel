import {
    useEffect,
    useState,
} from 'react';

let cachedPromise = null;

function loadData() {
    if (!cachedPromise) {
        cachedPromise = fetch(
            '/client-custom-fields/list-data',
            {
                headers: {
                    Accept:
                        'application/json',
                    'X-Requested-With':
                        'XMLHttpRequest',
                },
                credentials:
                    'same-origin',
            },
        ).then((response) => {
            if (!response.ok) {
                throw new Error(
                    `HTTP ${response.status}`,
                );
            }

            return response.json();
        });
    }

    return cachedPromise;
}

function displayValue(
    field,
    value,
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

export default function ClientCustomListColumns({
    mode,
    clientId = null,
}) {
    const [payload, setPayload] =
        useState({
            fields: [],
            values: {},
        });

    useEffect(() => {
        let active = true;

        loadData()
            .then((result) => {
                if (!active) {
                    return;
                }

                setPayload({
                    fields:
                        Array.isArray(
                            result.fields,
                        )
                            ? result.fields
                            : [],
                    values:
                        result.values
                        && typeof result.values
                            === 'object'
                            ? result.values
                            : {},
                });
            })
            .catch((error) => {
                console.error(
                    'Custom list fields failed:',
                    error,
                );
            });

        return () => {
            active = false;
        };
    }, []);

    if (
        payload.fields.length === 0
    ) {
        return null;
    }

    if (mode === 'header') {
        return (
            <>
                {payload.fields.map(
                    (field) => (
                        <th
                            key={field.id}
                            className="whitespace-nowrap px-2 py-1.5 text-left text-xs font-semibold"
                        >
                            {field.name}
                        </th>
                    ),
                )}
            </>
        );
    }

    const clientValues =
        payload.values[
            String(clientId)
        ] || {};

    return (
        <>
            {payload.fields.map(
                (field) => (
                    <td
                        key={field.id}
                        className="whitespace-nowrap px-2 py-0.5 text-xs text-slate-700"
                    >
                        {displayValue(
                            field,
                            clientValues[
                                String(
                                    field.id,
                                )
                            ],
                        )}
                    </td>
                ),
            )}
        </>
    );
}
