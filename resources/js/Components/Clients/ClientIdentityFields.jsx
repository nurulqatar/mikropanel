import axios from 'axios';
import {
    useRef,
    useState,
} from 'react';

const inputClass =
    'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100';

const documentFields = [
    'qatar_id_number',
    'qatar_id_expiry_date',
    'nationality',
    'date_of_birth',
    'occupation',
    'passport_number',
    'passport_expiry_date',
    'document_serial_number',
    'residency_type',
    'employer',
    'gender',
    'place_of_birth',
    'passport_issue_date',
    'issuing_country',
    'issuing_authority',
];

function Field({
    label,
    children,
    error,
}) {
    return (
        <label className="block">
            <div className="mb-1 flex items-center gap-2">
                <span className="text-xs font-bold text-slate-700">
                    {label}
                </span>

                <span className="text-[10px] font-semibold uppercase text-slate-400">
                    Optional
                </span>
            </div>

            {children}

            {error && (
                <div className="mt-1 text-xs font-semibold text-red-600">
                    {error}
                </div>
            )}
        </label>
    );
}

export default function ClientIdentityFields({
    data,
    setData,
    errors = {},
}) {
    const fileInputRef =
        useRef(null);

    const [scanning, setScanning] =
        useState(false);

    const [message, setMessage] =
        useState('');

    const [scanOk, setScanOk] =
        useState(false);

    const updateField = (
        field,
        value,
    ) => {
        const next = {
            ...data,
            [field]: value,
        };

        if (
            field === 'qatar_id_number'
        ) {
            next.identity_type =
                value
                    ? 'qatar_id'
                    : next.identity_type;

            if (value) {
                next.identity_number =
                    value;
            }
        }

        if (
            field
            === 'qatar_id_expiry_date'
            && next.qatar_id_number
        ) {
            next.document_expiry_date =
                value;
        }

        if (
            field === 'passport_number'
            && value
            && !next.qatar_id_number
        ) {
            next.identity_type =
                'passport';

            next.identity_number =
                value;
        }

        if (
            field
            === 'passport_expiry_date'
            && !next.qatar_id_number
        ) {
            next.document_expiry_date =
                value;
        }

        setData(next);
    };

    const scanDocument = async (
        documentFile,
    ) => {
        if (!documentFile) {
            return;
        }

        setScanning(true);
        setScanOk(false);

        setMessage(
            'Reading Qatar ID / passport...',
        );

        try {
            const payload =
                new FormData();

            payload.append(
                'document',
                documentFile,
            );

            const response =
                await axios.post(
                    route(
                        'clients.identity-scan',
                    ),
                    payload,
                    {
                        headers: {
                            'Content-Type':
                                'multipart/form-data',
                        },
                    },
                );

            const detected = {
                ...(
                    response.data?.fields
                    ?? {}
                ),
            };

            /*
             * Compatibility with older generic
             * scanner fields.
             */
            if (
                detected.identity_type
                === 'qatar_id'
            ) {
                detected.qatar_id_number ??=
                    detected.identity_number;

                detected.qatar_id_expiry_date ??=
                    detected.document_expiry_date;
            }

            if (
                detected.identity_type
                === 'passport'
            ) {
                detected.passport_number ??=
                    detected.identity_number;

                detected.passport_expiry_date ??=
                    detected.document_expiry_date;
            }

            const merged = {
                ...data,
            };

            Object.entries(
                detected,
            ).forEach(
                ([key, value]) => {
                    if (
                        value === null
                        || value === ''
                        || value === undefined
                    ) {
                        return;
                    }

                    /*
                     * Preserve a manually typed
                     * client name.
                     */
                    if (
                        key === 'name'
                        && String(
                            merged.name
                            ?? '',
                        ).trim() !== ''
                    ) {
                        return;
                    }

                    merged[key] =
                        value;
                },
            );

            setData(
                merged,
            );

            setScanOk(true);

            setMessage(
                'Document read successfully. Detected information has been filled automatically. Please verify before saving.',
            );
        } catch (error) {
            setScanOk(false);

            const validationMessage =
                error?.response?.data
                    ?.errors?.document?.[0];

            setMessage(
                validationMessage
                || error?.response?.data
                    ?.message
                || 'Could not read this document. Try a clearer scan/photo.',
            );
        } finally {
            setScanning(false);
        }
    };

    const identityError =
        documentFields
            .map(
                (field) =>
                    errors[field],
            )
            .find(Boolean);

    return (
        <section className="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 className="font-black text-slate-900">
                        Qatar ID / Passport
                    </h3>

                    <p className="mt-1 text-xs leading-5 text-slate-500">
                        Scan the document to fill available information automatically. Every document field below is optional.
                    </p>
                </div>

                <button
                    type="button"
                    disabled={scanning}
                    onClick={() =>
                        fileInputRef
                            .current
                            ?.click()
                    }
                    className="shrink-0 rounded-xl bg-indigo-600 px-5 py-3 text-sm font-black text-white shadow-sm hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {scanning
                        ? 'READING...'
                        : 'SCAN QATAR ID / PASSPORT'}
                </button>

                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/*,.pdf"
                    className="hidden"
                    onChange={(event) => {
                        const file =
                            event.target
                                .files?.[0]
                            ?? null;

                        event.target.value =
                            '';

                        if (file) {
                            scanDocument(
                                file,
                            );
                        }
                    }}
                />
            </div>

            {message && (
                <div
                    className={`mt-3 rounded-lg px-4 py-3 text-sm font-bold ${
                        scanOk
                            ? 'bg-emerald-50 text-emerald-700'
                            : scanning
                              ? 'bg-sky-50 text-sky-700'
                              : 'bg-amber-50 text-amber-700'
                    }`}
                >
                    {message}
                </div>
            )}

            {identityError && (
                <div className="mt-3 rounded-lg bg-red-50 px-4 py-3 text-sm font-bold text-red-700">
                    Please check the optional document information below.
                </div>
            )}

            <div className="mt-5 border-t border-indigo-100 pt-4">
                <h4 className="text-sm font-black text-slate-800">
                    Personal Information
                </h4>

                <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Field
                        label="Nationality"
                        error={errors.nationality}
                    >
                        <input
                            type="text"
                            value={data.nationality ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'nationality',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Date of Birth"
                        error={errors.date_of_birth}
                    >
                        <input
                            type="date"
                            value={data.date_of_birth ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'date_of_birth',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Gender"
                        error={errors.gender}
                    >
                        <select
                            value={data.gender ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'gender',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        >
                            <option value="">
                                Select
                            </option>
                            <option value="M">
                                Male
                            </option>
                            <option value="F">
                                Female
                            </option>
                            <option value="X">
                                Other / X
                            </option>
                        </select>
                    </Field>
                </div>
            </div>

            <div className="mt-5 border-t border-indigo-100 pt-4">
                <h4 className="text-sm font-black text-slate-800">
                    Qatar Residency Permit
                </h4>

                <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Field
                        label="Qatar ID Number"
                        error={errors.qatar_id_number}
                    >
                        <input
                            type="text"
                            value={data.qatar_id_number ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'qatar_id_number',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Qatar ID Expiry"
                        error={errors.qatar_id_expiry_date}
                    >
                        <input
                            type="date"
                            value={data.qatar_id_expiry_date ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'qatar_id_expiry_date',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Occupation"
                        error={errors.occupation}
                    >
                        <input
                            type="text"
                            value={data.occupation ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'occupation',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Serial Number"
                        error={errors.document_serial_number}
                    >
                        <input
                            type="text"
                            value={data.document_serial_number ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'document_serial_number',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Residency Type"
                        error={errors.residency_type}
                    >
                        <input
                            type="text"
                            value={data.residency_type ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'residency_type',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Employer / Sponsor"
                        error={errors.employer}
                    >
                        <input
                            type="text"
                            value={data.employer ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'employer',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>
                </div>
            </div>

            <div className="mt-5 border-t border-indigo-100 pt-4">
                <h4 className="text-sm font-black text-slate-800">
                    Passport Information
                </h4>

                <div className="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Field
                        label="Passport Number"
                        error={errors.passport_number}
                    >
                        <input
                            type="text"
                            value={data.passport_number ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'passport_number',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Passport Issue Date"
                        error={errors.passport_issue_date}
                    >
                        <input
                            type="date"
                            value={data.passport_issue_date ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'passport_issue_date',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Passport Expiry"
                        error={errors.passport_expiry_date}
                    >
                        <input
                            type="date"
                            value={data.passport_expiry_date ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'passport_expiry_date',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Place of Birth"
                        error={errors.place_of_birth}
                    >
                        <input
                            type="text"
                            value={data.place_of_birth ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'place_of_birth',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Issuing Country"
                        error={errors.issuing_country}
                    >
                        <input
                            type="text"
                            value={data.issuing_country ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'issuing_country',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Issuing Authority"
                        error={errors.issuing_authority}
                    >
                        <input
                            type="text"
                            value={data.issuing_authority ?? ''}
                            onChange={(event) =>
                                updateField(
                                    'issuing_authority',
                                    event.target.value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>
                </div>
            </div>
        </section>
    );
}
