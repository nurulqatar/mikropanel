import axios from 'axios';
import {
    useRef,
    useState,
} from 'react';

const identityKeys = [
    'identity_type',
    'identity_number',
    'identity_barcode',
    'nationality',
    'date_of_birth',
    'gender',
    'document_expiry_date',
];

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
                        || value === undefined
                    ) {
                        return;
                    }

                    /*
                     * Do not replace a client name
                     * already manually entered.
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

            const detectedNumber =
                detected.identity_number
                ?? '';

            const detectedName =
                detected.name
                ?? '';

            let success =
                'Document read successfully. Information loaded automatically.';

            if (
                detectedName
                || detectedNumber
            ) {
                const parts = [
                    detectedName,
                    detectedNumber,
                ].filter(Boolean);

                success +=
                    ` Detected: ${parts.join(
                        ' · ',
                    )}`;
            }

            setMessage(
                success,
            );
        } catch (error) {
            setScanOk(false);

            setMessage(
                error?.response?.data
                    ?.message
                || 'Could not read this document. Try a clearer scan/photo.',
            );
        } finally {
            setScanning(false);
        }
    };

    const identityError =
        identityKeys
            .map(
                (key) =>
                    errors[key],
            )
            .find(Boolean);

    return (
        <section className="rounded-xl border border-indigo-200 bg-indigo-50/60 p-4">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 className="font-black text-slate-900">
                        Qatar ID / Passport Scan
                    </h3>

                    <p className="mt-1 text-xs leading-5 text-slate-500">
                        Scan or choose the Qatar ID / passport. Name, document number, nationality, date of birth and expiry information will be loaded automatically.
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

                        /*
                         * Allow the same file to be
                         * selected again if required.
                         */
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
                    Scanned identity information contains an invalid value. Please scan the document again.
                </div>
            )}
        </section>
    );
}
