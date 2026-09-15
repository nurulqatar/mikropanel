import axios from 'axios';
import { useState } from 'react';

export default function ClientIdentityFields({
    data,
    setData,
    errors = {},
}) {
    const [document, setDocument] =
        useState(null);

    const [scanning, setScanning] =
        useState(false);

    const [message, setMessage] =
        useState('');

    const scanDocument = async () => {
        if (!document) {
            setMessage(
                'Choose a Qatar ID or passport image/PDF first.',
            );
            return;
        }

        setScanning(true);
        setMessage('');

        try {
            const payload =
                new FormData();

            payload.append(
                'document',
                document,
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

            const detected =
                response.data?.fields
                ?? {};

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
                    ) {
                        return;
                    }

                    /*
                     * Never silently replace a name
                     * the operator already typed.
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

            setData(merged);

            setMessage(
                'Document read. Verify the detected information before saving.',
            );
        } catch (error) {
            setMessage(
                error?.response?.data
                    ?.message
                || 'Document could not be read. Enter the information manually.',
            );
        } finally {
            setScanning(false);
        }
    };

    return (
        <section className="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4">
            <div>
                <h3 className="font-black text-slate-900">
                    Qatar ID / Passport
                </h3>

                <p className="mt-1 text-xs leading-5 text-slate-500">
                    Manual entry or local document scan. Standard machine-readable passports from any country are read from MRZ when available.
                </p>
            </div>

            <div className="mt-4 grid gap-3 sm:grid-cols-[1fr_auto]">
                <input
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp,.pdf"
                    onChange={(event) =>
                        setDocument(
                            event.target
                                .files?.[0]
                            ?? null,
                        )
                    }
                    className={inputClass}
                />

                <button
                    type="button"
                    onClick={
                        scanDocument
                    }
                    disabled={
                        scanning
                    }
                    className="rounded-lg bg-indigo-600 px-4 py-3 text-sm font-black text-white disabled:opacity-50"
                >
                    {scanning
                        ? 'SCANNING...'
                        : 'SCAN ID / PASSPORT'}
                </button>
            </div>

            {message && (
                <div className="mt-2 text-xs font-bold text-indigo-700">
                    {message}
                </div>
            )}

            <div className="mt-4 grid gap-3 sm:grid-cols-2">
                <Field
                    label="Identity Type"
                    error={
                        errors.identity_type
                    }
                >
                    <select
                        value={
                            data.identity_type
                            ?? ''
                        }
                        onChange={(event) =>
                            setData(
                                'identity_type',
                                event.target.value,
                            )
                        }
                        className={inputClass}
                    >
                        <option value="">
                            Optional
                        </option>

                        <option value="qatar_id">
                            Qatar ID
                        </option>

                        <option value="passport">
                            Passport
                        </option>

                        <option value="other">
                            Other
                        </option>
                    </select>
                </Field>

                <Field
                    label="QID / Passport Number"
                    error={
                        errors.identity_number
                    }
                >
                    <input
                        type="text"
                        value={
                            data.identity_number
                            ?? ''
                        }
                        onChange={(event) =>
                            setData(
                                'identity_number',
                                event.target.value,
                            )
                        }
                        className={inputClass}
                    />
                </Field>

                <Field
                    label="Barcode / QR Data"
                    error={
                        errors.identity_barcode
                    }
                >
                    <input
                        type="text"
                        value={
                            data.identity_barcode
                            ?? ''
                        }
                        onChange={(event) =>
                            setData(
                                'identity_barcode',
                                event.target.value,
                            )
                        }
                        placeholder="USB/Bluetooth scanner can type here"
                        className={inputClass}
                    />
                </Field>

                <Field
                    label="Nationality"
                    error={
                        errors.nationality
                    }
                >
                    <input
                        type="text"
                        value={
                            data.nationality
                            ?? ''
                        }
                        onChange={(event) =>
                            setData(
                                'nationality',
                                event.target.value,
                            )
                        }
                        className={inputClass}
                    />
                </Field>

                <Field
                    label="Date of Birth"
                    error={
                        errors.date_of_birth
                    }
                >
                    <input
                        type="date"
                        value={
                            data.date_of_birth
                            ?? ''
                        }
                        onChange={(event) =>
                            setData(
                                'date_of_birth',
                                event.target.value,
                            )
                        }
                        className={inputClass}
                    />
                </Field>

                <Field
                    label="Gender"
                    error={
                        errors.gender
                    }
                >
                    <select
                        value={
                            data.gender
                            ?? ''
                        }
                        onChange={(event) =>
                            setData(
                                'gender',
                                event.target.value,
                            )
                        }
                        className={inputClass}
                    >
                        <option value="">
                            Optional
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

                <Field
                    label="Document Expiry"
                    error={
                        errors.document_expiry_date
                    }
                >
                    <input
                        type="date"
                        value={
                            data.document_expiry_date
                            ?? ''
                        }
                        onChange={(event) =>
                            setData(
                                'document_expiry_date',
                                event.target.value,
                            )
                        }
                        className={inputClass}
                    />
                </Field>
            </div>
        </section>
    );
}

const inputClass =
    'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100';

function Field({
    label,
    error,
    children,
}) {
    return (
        <label className="block">
            <span className="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500">
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
