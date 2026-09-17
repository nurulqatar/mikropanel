import axios from 'axios';
import {
    useCallback,
    useEffect,
    useRef,
    useState,
} from 'react';

const inputClass =
    'w-full rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-800 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100';

const personalFields = [
    'nationality',
    'date_of_birth',
    'gender',
];

const qatarFrontFields = [
    'qatar_id_number',
    'qatar_id_expiry_date',
    'occupation',
];

const qatarBackFields = [
    'passport_number',
    'passport_expiry_date',
    'document_serial_number',
    'residency_type',
    'employer',
];

const passportFields = [
    'passport_number',
    'passport_issue_date',
    'passport_expiry_date',
    'place_of_birth',
    'issuing_country',
    'issuing_authority',
];

const allDocumentFields = [
    ...personalFields,
    ...qatarFrontFields,
    ...qatarBackFields,
    ...passportFields,
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

function detectMobile() {
    if (typeof navigator === 'undefined') {
        return false;
    }

    const ua =
        navigator.userAgent
        ?? '';

    return (
        /Android|iPhone|iPad|iPod|Mobile/i.test(
            ua
        )
        || (
            typeof window !== 'undefined'
            && window.matchMedia?.(
                '(pointer: coarse)'
            )?.matches
        )
    );
}

export default function ClientIdentityFields({
    data,
    setData,
    errors = {},
}) {
    const fileInputRef =
        useRef(null);

    const videoRef =
        useRef(null);

    const canvasRef =
        useRef(null);

    const streamRef =
        useRef(null);

    const scanningRef =
        useRef(false);

    const previousFrameRef =
        useRef(null);

    const stableCountRef =
        useRef(0);

    const waitForChangeRef =
        useRef(false);

    const [scanning, setScanning] =
        useState(false);

    const [cameraActive, setCameraActive] =
        useState(false);

    const [cameraError, setCameraError] =
        useState('');

    const [message, setMessage] =
        useState('');

    const [scanOk, setScanOk] =
        useState(false);

    const [documentType, setDocumentType] =
        useState(null);

    const [documentSide, setDocumentSide] =
        useState(null);

    const [qidFrontDone, setQidFrontDone] =
        useState(false);

    const [qidBackDone, setQidBackDone] =
        useState(false);

    const [mobile] =
        useState(
            () => detectMobile()
        );

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
            && value
        ) {
            next.identity_type =
                'qatar_id';

            next.identity_number =
                value;
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

    const stopCamera =
        useCallback(
            () => {
                if (
                    streamRef.current
                ) {
                    streamRef.current
                        .getTracks()
                        .forEach(
                            (track) =>
                                track.stop()
                        );

                    streamRef.current =
                        null;
                }

                setCameraActive(
                    false
                );
            },
            []
        );

    const mergeScanResult =
        useCallback(
            (
                detected,
                meta
            ) => {
                const merged = {
                    ...data,
                };

                /*
                 * Passport selected:
                 * remove stale Qatar-only values.
                 */
                if (
                    meta?.type
                    === 'ordinary_passport'
                ) {
                    [
                        'qatar_id_number',
                        'qatar_id_expiry_date',
                        'occupation',
                        'document_serial_number',
                        'residency_type',
                        'employer',
                    ].forEach(
                        (field) => {
                            merged[field] =
                                '';
                        }
                    );
                }

                Object.entries(
                    detected
                    ?? {}
                ).forEach(
                    ([key, value]) => {
                        if (
                            value === null
                            || value === ''
                            || value
                                === undefined
                        ) {
                            return;
                        }

                        /*
                         * Never replace a manually
                         * entered client name.
                         */
                        if (
                            key === 'name'
                            && String(
                                merged.name
                                ?? ''
                            ).trim() !== ''
                        ) {
                            return;
                        }

                        merged[key] =
                            value;
                    }
                );

                if (
                    meta?.type
                    === 'qatar_id'
                ) {
                    merged.identity_type =
                        'qatar_id';

                    /*
                     * A back-side scan may have been
                     * parsed as passport because it
                     * contains a passport number.
                     * Keep the already scanned QID
                     * as the primary identity.
                     */
                    if (
                        merged.qatar_id_number
                    ) {
                        merged.identity_number =
                            merged.qatar_id_number;
                    }
                }

                if (
                    meta?.type
                    === 'ordinary_passport'
                ) {
                    merged.identity_type =
                        'passport';

                    if (
                        merged.passport_number
                    ) {
                        merged.identity_number =
                            merged.passport_number;
                    }

                    if (
                        merged.passport_expiry_date
                    ) {
                        merged.document_expiry_date =
                            merged.passport_expiry_date;
                    }
                }

                setData(
                    merged
                );
            },
            [
                data,
                setData,
            ]
        );

    const scanDocument =
        useCallback(
            async (
                documentFile,
                source = 'upload'
            ) => {
                if (
                    !documentFile
                    || scanningRef.current
                ) {
                    return null;
                }

                scanningRef.current =
                    true;

                setScanning(
                    true
                );

                setScanOk(
                    false
                );

                setMessage(
                    source === 'camera'
                        ? 'Document detected. Reading...'
                        : 'Reading document...'
                );

                try {
                    const payload =
                        new FormData();

                    payload.append(
                        'document',
                        documentFile
                    );

                    const response =
                        await axios.post(
                            route(
                                'clients.identity-scan'
                            ),
                            payload,
                            {
                                headers: {
                                    'Content-Type':
                                        'multipart/form-data',
                                },
                            }
                        );

                    const detected = {
                        ...(
                            response.data
                                ?.fields
                            ?? {}
                        ),
                    };

                    const meta =
                        response.data
                            ?.document
                        ?? {
                            type:
                                'unknown',
                            side:
                                null,
                        };

                    mergeScanResult(
                        detected,
                        meta
                    );

                    setDocumentType(
                        meta.type
                    );

                    setDocumentSide(
                        meta.side
                    );

                    if (
                        meta.type
                        === 'qatar_id'
                        && meta.side
                        === 'front'
                    ) {
                        setQidFrontDone(
                            true
                        );

                        setMessage(
                            'Qatar ID front scanned. Turn the card over — waiting for the back side.'
                        );

                        setScanOk(
                            true
                        );

                        /*
                         * Camera must see an actual
                         * scene change before it is
                         * allowed to auto-capture
                         * the second side.
                         */
                        waitForChangeRef.current =
                            true;

                        stableCountRef.current =
                            0;

                        return meta;
                    }

                    if (
                        meta.type
                        === 'qatar_id'
                        && meta.side
                        === 'back'
                    ) {
                        setQidBackDone(
                            true
                        );

                        setMessage(
                            'Qatar ID front/back information completed. Please verify before saving.'
                        );

                        setScanOk(
                            true
                        );

                        stopCamera();

                        return meta;
                    }

                    if (
                        meta.type
                        === 'ordinary_passport'
                    ) {
                        setMessage(
                            'Ordinary passport detected and information filled. Please verify before saving.'
                        );

                        setScanOk(
                            true
                        );

                        stopCamera();

                        return meta;
                    }

                    setMessage(
                        'Document could not be identified confidently. Hold it flat inside the frame or upload a clearer image.'
                    );

                    waitForChangeRef.current =
                        true;

                    stableCountRef.current =
                        0;

                    return meta;
                } catch (error) {
                    const validationMessage =
                        error?.response
                            ?.data
                            ?.errors
                            ?.document?.[0];

                    setScanOk(
                        false
                    );

                    setMessage(
                        validationMessage
                        || error?.response
                            ?.data
                            ?.message
                        || 'Could not read this document. Try a clearer scan/photo.'
                    );

                    waitForChangeRef.current =
                        true;

                    return null;
                } finally {
                    scanningRef.current =
                        false;

                    setScanning(
                        false
                    );
                }
            },
            [
                mergeScanResult,
                stopCamera,
            ]
        );

    const captureCamera =
        useCallback(
            async () => {
                const video =
                    videoRef.current;

                const canvas =
                    canvasRef.current;

                if (
                    !video
                    || !canvas
                    || !video.videoWidth
                    || scanningRef.current
                ) {
                    return;
                }

                const maximumWidth =
                    1600;

                const scale =
                    Math.min(
                        1,
                        maximumWidth
                        / video.videoWidth
                    );

                canvas.width =
                    Math.round(
                        video.videoWidth
                        * scale
                    );

                canvas.height =
                    Math.round(
                        video.videoHeight
                        * scale
                    );

                const context =
                    canvas.getContext(
                        '2d'
                    );

                context.drawImage(
                    video,
                    0,
                    0,
                    canvas.width,
                    canvas.height
                );

                const blob =
                    await new Promise(
                        (resolve) =>
                            canvas.toBlob(
                                resolve,
                                'image/jpeg',
                                0.88
                            )
                    );

                if (!blob) {
                    return;
                }

                const file =
                    new File(
                        [blob],
                        'camera-document.jpg',
                        {
                            type:
                                'image/jpeg',
                        }
                    );

                await scanDocument(
                    file,
                    'camera'
                );
            },
            [
                scanDocument,
            ]
        );

    const startCamera =
        useCallback(
            async () => {
                setCameraError(
                    ''
                );

                if (
                    !window.isSecureContext
                ) {
                    setCameraError(
                        'Camera requires HTTPS. Manual file upload is still available.'
                    );

                    return;
                }

                if (
                    !navigator
                        .mediaDevices
                        ?.getUserMedia
                ) {
                    setCameraError(
                        'Camera is not available in this browser.'
                    );

                    return;
                }

                try {
                    const stream =
                        await navigator
                            .mediaDevices
                            .getUserMedia({
                                audio: false,
                                video: {
                                    facingMode: {
                                        ideal:
                                            'environment',
                                    },
                                    width: {
                                        ideal:
                                            1920,
                                    },
                                    height: {
                                        ideal:
                                            1080,
                                    },
                                },
                            });

                    streamRef.current =
                        stream;

                    if (
                        videoRef.current
                    ) {
                        videoRef.current
                            .srcObject =
                            stream;

                        await videoRef
                            .current
                            .play();
                    }

                    previousFrameRef.current =
                        null;

                    stableCountRef.current =
                        0;

                    setCameraActive(
                        true
                    );

                    setMessage(
                        'Camera ready. Place the Qatar ID or passport inside the frame — scanning is automatic.'
                    );
                } catch (error) {
                    setCameraError(
                        'Camera permission is required for automatic mobile scanning.'
                    );
                }
            },
            []
        );

    /*
     * Mobile:
     * open rear camera automatically.
     */
    useEffect(
        () => {
            if (mobile) {
                startCamera();
            }

            return () => {
                stopCamera();
            };
        },
        [
            mobile,
            startCamera,
            stopCamera,
        ]
    );

    /*
     * Automatic document stability detector.
     *
     * It works locally in the browser.
     * Only a stable capture is uploaded.
     */
    useEffect(
        () => {
            if (
                !cameraActive
            ) {
                return undefined;
            }

            const timer =
                window.setInterval(
                    () => {
                        if (
                            scanningRef.current
                        ) {
                            return;
                        }

                        const video =
                            videoRef.current;

                        const canvas =
                            canvasRef.current;

                        if (
                            !video
                            || !canvas
                            || video.readyState
                                < 2
                            || !video.videoWidth
                        ) {
                            return;
                        }

                        const width =
                            160;

                        const height =
                            100;

                        canvas.width =
                            width;

                        canvas.height =
                            height;

                        const context =
                            canvas.getContext(
                                '2d',
                                {
                                    willReadFrequently:
                                        true,
                                }
                            );

                        context.drawImage(
                            video,
                            0,
                            0,
                            width,
                            height
                        );

                        const pixels =
                            context.getImageData(
                                0,
                                0,
                                width,
                                height
                            ).data;

                        const frame =
                            new Uint8Array(
                                width
                                * height
                            );

                        let brightness =
                            0;

                        let edge =
                            0;

                        for (
                            let i = 0;
                            i < frame.length;
                            i++
                        ) {
                            const p =
                                i * 4;

                            const gray =
                                Math.round(
                                    (
                                        pixels[p]
                                        + pixels[p + 1]
                                        + pixels[p + 2]
                                    )
                                    / 3
                                );

                            frame[i] =
                                gray;

                            brightness +=
                                gray;

                            if (
                                i % width
                                !== 0
                            ) {
                                edge +=
                                    Math.abs(
                                        gray
                                        - frame[
                                            i - 1
                                        ]
                                    );
                            }
                        }

                        brightness /=
                            frame.length;

                        edge /=
                            frame.length;

                        let motion =
                            255;

                        const previous =
                            previousFrameRef
                                .current;

                        if (
                            previous
                            && previous.length
                                === frame.length
                        ) {
                            let diff =
                                0;

                            for (
                                let i = 0;
                                i < frame.length;
                                i += 4
                            ) {
                                diff +=
                                    Math.abs(
                                        frame[i]
                                        - previous[i]
                                    );
                            }

                            motion =
                                diff
                                / (
                                    frame.length
                                    / 4
                                );
                        }

                        previousFrameRef.current =
                            frame;

                        /*
                         * After front-side capture,
                         * card must actually move/flip
                         * before back-side capture.
                         */
                        if (
                            waitForChangeRef.current
                        ) {
                            if (
                                motion > 16
                            ) {
                                waitForChangeRef.current =
                                    false;

                                stableCountRef.current =
                                    0;

                                setMessage(
                                    'Card movement detected. Hold the new side steady...'
                                );
                            }

                            return;
                        }

                        const goodLight =
                            brightness > 42
                            && brightness < 225;

                        const enoughDetail =
                            edge > 6;

                        const stable =
                            motion < 5.5;

                        if (
                            goodLight
                            && enoughDetail
                            && stable
                        ) {
                            stableCountRef.current +=
                                1;
                        } else {
                            stableCountRef.current =
                                0;
                        }

                        if (
                            stableCountRef.current
                            >= 3
                        ) {
                            stableCountRef.current =
                                0;

                            captureCamera();
                        }
                    },
                    450
                );

            return () => {
                window.clearInterval(
                    timer
                );
            };
        },
        [
            cameraActive,
            captureCamera,
        ]
    );

    const identityError =
        allDocumentFields
            .map(
                (field) =>
                    errors[field]
            )
            .find(Boolean);

    const showPersonal =
        documentType
        === 'qatar_id'
        || documentType
            === 'ordinary_passport';

    const showQatar =
        documentType
        === 'qatar_id';

    const showPassport =
        documentType
        === 'ordinary_passport';

    return (
        <section className="rounded-2xl border border-indigo-200 bg-indigo-50/60 p-4">
            <div className="flex flex-col gap-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 className="font-black text-slate-900">
                            Qatar ID / Passport Scanner
                        </h3>

                        <p className="mt-1 max-w-2xl text-xs leading-5 text-slate-500">
                            Mobile camera scans automatically.
                            Qatar ID front and back are combined.
                            Ordinary personal passports are detected automatically.
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
                        className="shrink-0 rounded-xl border border-indigo-200 bg-white px-4 py-2.5 text-xs font-black text-indigo-700 shadow-sm hover:bg-indigo-50 disabled:opacity-50"
                    >
                        {scanning
                            ? 'READING...'
                            : 'UPLOAD PDF / IMAGE'}
                    </button>

                    <input
                        ref={fileInputRef}
                        type="file"
                        accept=".pdf,image/jpeg,image/png,image/webp"
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
                                    'upload'
                                );
                            }
                        }}
                    />
                </div>

                {mobile && (
                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-slate-950">
                        <div className="relative aspect-[4/3] w-full">
                            <video
                                ref={videoRef}
                                muted
                                playsInline
                                className="h-full w-full object-cover"
                            />

                            {cameraActive && (
                                <>
                                    <div className="pointer-events-none absolute inset-[10%] rounded-2xl border-2 border-white/90 shadow-[0_0_0_999px_rgba(0,0,0,0.25)]" />

                                    <div className="pointer-events-none absolute bottom-4 left-0 right-0 text-center">
                                        <span className="rounded-full bg-black/65 px-4 py-2 text-xs font-bold text-white">
                                            {scanning
                                                ? 'READING DOCUMENT...'
                                                : qidFrontDone
                                                    && !qidBackDone
                                                ? 'TURN QATAR ID TO BACK SIDE'
                                                : 'HOLD DOCUMENT STEADY — AUTO SCAN'}
                                        </span>
                                    </div>
                                </>
                            )}
                        </div>
                    </div>
                )}

                <canvas
                    ref={canvasRef}
                    className="hidden"
                />

                {cameraError && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs font-semibold text-amber-800">
                        {cameraError}

                        {mobile && (
                            <button
                                type="button"
                                onClick={
                                    startCamera
                                }
                                className="ml-2 font-black underline"
                            >
                                Try camera again
                            </button>
                        )}
                    </div>
                )}

                {message && (
                    <div
                        className={
                            scanOk
                                ? 'rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-800'
                                : 'rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-semibold text-slate-700'
                        }
                    >
                        {message}
                    </div>
                )}

                {showQatar && (
                    <div className="flex flex-wrap gap-2 text-[11px] font-black">
                        <span
                            className={
                                qidFrontDone
                                    ? 'rounded-full bg-emerald-100 px-3 py-1.5 text-emerald-700'
                                    : 'rounded-full bg-amber-100 px-3 py-1.5 text-amber-700'
                            }
                        >
                            FRONT {qidFrontDone ? '✓' : 'WAITING'}
                        </span>

                        <span
                            className={
                                qidBackDone
                                    ? 'rounded-full bg-emerald-100 px-3 py-1.5 text-emerald-700'
                                    : 'rounded-full bg-amber-100 px-3 py-1.5 text-amber-700'
                            }
                        >
                            BACK {qidBackDone ? '✓' : 'WAITING'}
                        </span>
                    </div>
                )}

                {identityError && (
                    <div className="text-xs font-bold text-red-600">
                        {identityError}
                    </div>
                )}

                {showPersonal && (
                    <div className="rounded-xl border border-slate-200 bg-white p-4">
                        <h4 className="mb-4 text-sm font-black text-slate-900">
                            Personal Information
                        </h4>

                        <div className="grid gap-4 md:grid-cols-3">
                            <Field
                                label="Nationality"
                                error={errors.nationality}
                            >
                                <input
                                    className={inputClass}
                                    value={data.nationality ?? ''}
                                    onChange={(event) =>
                                        updateField(
                                            'nationality',
                                            event.target.value
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                label="Date of Birth"
                                error={errors.date_of_birth}
                            >
                                <input
                                    type="date"
                                    className={inputClass}
                                    value={data.date_of_birth ?? ''}
                                    onChange={(event) =>
                                        updateField(
                                            'date_of_birth',
                                            event.target.value
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                label="Gender"
                                error={errors.gender}
                            >
                                <select
                                    className={inputClass}
                                    value={data.gender ?? ''}
                                    onChange={(event) =>
                                        updateField(
                                            'gender',
                                            event.target.value
                                        )
                                    }
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
                )}

                {showQatar && (
                    <>
                        <div className="rounded-xl border border-slate-200 bg-white p-4">
                            <h4 className="mb-1 text-sm font-black text-slate-900">
                                Qatar ID — Front Side
                            </h4>

                            <p className="mb-4 text-xs text-slate-500">
                                Information detected from the front side.
                            </p>

                            <div className="grid gap-4 md:grid-cols-3">
                                <Field
                                    label="Qatar ID Number"
                                    error={errors.qatar_id_number}
                                >
                                    <input
                                        className={inputClass}
                                        value={data.qatar_id_number ?? ''}
                                        onChange={(event) =>
                                            updateField(
                                                'qatar_id_number',
                                                event.target.value
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Qatar ID Expiry"
                                    error={errors.qatar_id_expiry_date}
                                >
                                    <input
                                        type="date"
                                        className={inputClass}
                                        value={data.qatar_id_expiry_date ?? ''}
                                        onChange={(event) =>
                                            updateField(
                                                'qatar_id_expiry_date',
                                                event.target.value
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Occupation"
                                    error={errors.occupation}
                                >
                                    <input
                                        className={inputClass}
                                        value={data.occupation ?? ''}
                                        onChange={(event) =>
                                            updateField(
                                                'occupation',
                                                event.target.value
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </div>

                        <div className="rounded-xl border border-slate-200 bg-white p-4">
                            <h4 className="mb-1 text-sm font-black text-slate-900">
                                Qatar ID — Back Side
                            </h4>

                            <p className="mb-4 text-xs text-slate-500">
                                Passport and residency information detected from the back side.
                            </p>

                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                <Field
                                    label="Passport Number"
                                    error={errors.passport_number}
                                >
                                    <input
                                        className={inputClass}
                                        value={data.passport_number ?? ''}
                                        onChange={(event) =>
                                            updateField(
                                                'passport_number',
                                                event.target.value
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Passport Expiry"
                                    error={errors.passport_expiry_date}
                                >
                                    <input
                                        type="date"
                                        className={inputClass}
                                        value={data.passport_expiry_date ?? ''}
                                        onChange={(event) =>
                                            updateField(
                                                'passport_expiry_date',
                                                event.target.value
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Serial Number"
                                    error={errors.document_serial_number}
                                >
                                    <input
                                        className={inputClass}
                                        value={data.document_serial_number ?? ''}
                                        onChange={(event) =>
                                            updateField(
                                                'document_serial_number',
                                                event.target.value
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Residency Type"
                                    error={errors.residency_type}
                                >
                                    <input
                                        className={inputClass}
                                        value={data.residency_type ?? ''}
                                        onChange={(event) =>
                                            updateField(
                                                'residency_type',
                                                event.target.value
                                            )
                                        }
                                    />
                                </Field>

                                <Field
                                    label="Employer / Sponsor"
                                    error={errors.employer}
                                >
                                    <input
                                        className={inputClass}
                                        value={data.employer ?? ''}
                                        onChange={(event) =>
                                            updateField(
                                                'employer',
                                                event.target.value
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </div>
                    </>
                )}

                {showPassport && (
                    <div className="rounded-xl border border-slate-200 bg-white p-4">
                        <div className="mb-4">
                            <h4 className="text-sm font-black text-slate-900">
                                Ordinary Passport Information
                            </h4>

                            <p className="mt-1 text-xs text-slate-500">
                                Ordinary / personal passport bio-page only.
                            </p>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            <Field
                                label="Passport Number"
                                error={errors.passport_number}
                            >
                                <input
                                    className={inputClass}
                                    value={data.passport_number ?? ''}
                                    onChange={(event) =>
                                        updateField(
                                            'passport_number',
                                            event.target.value
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                label="Passport Issue Date"
                                error={errors.passport_issue_date}
                            >
                                <input
                                    type="date"
                                    className={inputClass}
                                    value={data.passport_issue_date ?? ''}
                                    onChange={(event) =>
                                        updateField(
                                            'passport_issue_date',
                                            event.target.value
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                label="Passport Expiry"
                                error={errors.passport_expiry_date}
                            >
                                <input
                                    type="date"
                                    className={inputClass}
                                    value={data.passport_expiry_date ?? ''}
                                    onChange={(event) =>
                                        updateField(
                                            'passport_expiry_date',
                                            event.target.value
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                label="Place of Birth"
                                error={errors.place_of_birth}
                            >
                                <input
                                    className={inputClass}
                                    value={data.place_of_birth ?? ''}
                                    onChange={(event) =>
                                        updateField(
                                            'place_of_birth',
                                            event.target.value
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                label="Issuing Country"
                                error={errors.issuing_country}
                            >
                                <input
                                    className={inputClass}
                                    value={data.issuing_country ?? ''}
                                    onChange={(event) =>
                                        updateField(
                                            'issuing_country',
                                            event.target.value
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                label="Issuing Authority"
                                error={errors.issuing_authority}
                            >
                                <input
                                    className={inputClass}
                                    value={data.issuing_authority ?? ''}
                                    onChange={(event) =>
                                        updateField(
                                            'issuing_authority',
                                            event.target.value
                                        )
                                    }
                                />
                            </Field>
                        </div>
                    </div>
                )}

                {!showPersonal && (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-white/70 px-4 py-6 text-center">
                        <div className="text-sm font-black text-slate-800">
                            Waiting for document
                        </div>

                        <div className="mt-1 text-xs text-slate-500">
                            {mobile
                                ? 'Place a Qatar ID or ordinary passport inside the camera frame.'
                                : 'Upload PDF, JPG, JPEG or PNG. PC scanner integration is the next installation step.'}
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}
