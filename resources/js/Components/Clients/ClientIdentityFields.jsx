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
];

const passportFields = [
    'passport_number',
    'passport_expiry_date',
    'place_of_birth',
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

    const iPadDesktopMode =
        navigator.platform === 'MacIntel'
        && navigator.maxTouchPoints > 1;

    return (
        /Android|iPhone|iPad|iPod|Mobile/i.test(
            ua
        )
        || iPadDesktopMode
    );
}

function scannerAgentFetch(
    path,
    options = {},
) {
    return fetch(
        `http://127.0.0.1:17873${path}`,
        {
            cache: 'no-store',
            targetAddressSpace:
                'loopback',
            ...options,
        },
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

    const scannerPollBusyRef =
        useRef(false);

    const scanDocumentRef =
        useRef(null);

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

    const [imagePreviews, setImagePreviews] =
        useState({
            face: null,
            qidFront: null,
            qidBack: null,
            passport: null,
        });

    const [mobile] =
        useState(
            () => detectMobile()
        );

    const [scannerAgent, setScannerAgent] =
        useState({
            online: false,
            scannerConnected: false,
            scannerName: null,
            autoSupported: false,
            feedReady: false,
            queueCount: 0,
            lastError: '',
            connectionError: '',
        });

    const scannerInstallCommand =
        typeof window === 'undefined'
            ? ''
            : `powershell -NoProfile -ExecutionPolicy Bypass -Command "$p=Join-Path $env:TEMP 'MikroPanelScannerAgent.ps1'; iwr '${window.location.origin}/scanner/MikroPanelScannerAgent.ps1' -OutFile $p; & $p -Install -PairOrigin '${window.location.origin}'"`;

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
                meta,
                imageToken = null,
                previewUrl = null,
                facePreviewUrl = null
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

                /*
                 * Passport direct-label fields must never
                 * keep values from an older scan.
                 *
                 * They are cleared first, then populated
                 * only when this scan actually reads the
                 * corresponding printed passport field.
                 */
                if (
                    meta?.type
                    === 'ordinary_passport'
                ) {
                    [
                        'name',
                        'place_of_birth',
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
                            key === 'phone'
                        ) {
                            /*
                             * Passport / ID scanning must never
                             * overwrite the customer's phone.
                             */
                            return;
                        }

                        if (
                            key === 'name'
                            && meta?.type
                                !== 'ordinary_passport'
                            && String(
                                merged.name
                                ?? '',
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

                /*
                 * Keep only opaque UUID tokens in
                 * the form. Actual files stay in
                 * private server storage.
                 */
                if (
                    imageToken
                    && meta?.type === 'qatar_id'
                ) {
                    /*
                     * A Qatar ID replaces any staged
                     * standalone passport image.
                     */
                    merged.passport_scan_token =
                        '';

                    if (
                        meta?.side === 'front'
                    ) {
                        merged.qatar_id_front_scan_token =
                            imageToken;
                    } else if (
                        meta?.side === 'back'
                    ) {
                        merged.qatar_id_back_scan_token =
                            imageToken;
                    } else if (
                        meta?.side === 'both'
                    ) {
                        /*
                         * Normally front/back are scanned
                         * separately. If a single image is
                         * classified as both, retain it as
                         * the next missing side.
                         */
                        if (
                            merged.qatar_id_front_scan_token
                        ) {
                            merged.qatar_id_back_scan_token =
                                imageToken;
                        } else {
                            merged.qatar_id_front_scan_token =
                                imageToken;
                        }
                    }
                }

                if (
                    imageToken
                    && meta?.type
                        === 'ordinary_passport'
                ) {
                    merged.passport_scan_token =
                        imageToken;

                    merged.qatar_id_front_scan_token =
                        '';

                    merged.qatar_id_back_scan_token =
                        '';
                }

                if (
                    previewUrl
                    && meta?.type === 'qatar_id'
                ) {
                    setImagePreviews(
                        (previous) => {
                            const next = {
                                ...previous,
                                passport: null,
                            };

                            if (
                                facePreviewUrl
                                && (
                                    meta?.side === 'front'
                                    || meta?.side === 'both'
                                )
                            ) {
                                next.face =
                                    facePreviewUrl;
                            }

                            if (
                                meta?.side === 'front'
                            ) {
                                next.qidFront =
                                    previewUrl;
                            } else if (
                                meta?.side === 'back'
                            ) {
                                next.qidBack =
                                    previewUrl;
                            } else if (
                                meta?.side === 'both'
                            ) {
                                if (
                                    previous.qidFront
                                ) {
                                    next.qidBack =
                                        previewUrl;
                                } else {
                                    next.qidFront =
                                        previewUrl;
                                }
                            }

                            return next;
                        }
                    );
                }

                if (
                    previewUrl
                    && meta?.type
                        === 'ordinary_passport'
                ) {
                    setImagePreviews({
                        face:
                            facePreviewUrl,
                        qidFront: null,
                        qidBack: null,
                        passport:
                            previewUrl,
                    });
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
                        : source === 'scanner'
                            ? 'Scanner image received. Reading document...'
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
                        meta,
                        response.data
                            ?.image_token
                            ?? null,
                        response.data
                            ?.image_preview_url
                            ?? null,
                        response.data
                            ?.face_preview_url
                            ?? null
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
                        if (
                            meta.side_complete
                            === false
                        ) {
                            setQidFrontDone(
                                false
                            );

                            setScanOk(
                                false
                            );

                            setMessage(
                                `Qatar ID front detected, but some information was not read: ${(meta.missing_fields ?? []).join(', ')}. Reposition or rescan the front side.`
                            );

                            waitForChangeRef.current =
                                true;

                            stableCountRef.current =
                                0;

                            return meta;
                        }

                        setQidFrontDone(
                            true
                        );

                        setMessage(
                            'Qatar ID front scanned completely. Turn the card over — waiting for the back side.'
                        );

                        setScanOk(
                            true
                        );

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
                        if (
                            meta.side_complete
                            === false
                        ) {
                            setQidBackDone(
                                false
                            );

                            setScanOk(
                                false
                            );

                            setMessage(
                                `Qatar ID back detected, but some information was not read: ${(meta.missing_fields ?? []).join(', ')}. Reposition or rescan the back side.`
                            );

                            waitForChangeRef.current =
                                true;

                            stableCountRef.current =
                                0;

                            return meta;
                        }

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
                        === 'qatar_id'
                        && meta.side
                        === 'both'
                    ) {
                        if (
                            meta.side_complete
                            === false
                        ) {
                            setScanOk(
                                false
                            );

                            setMessage(
                                `Qatar ID front/back detected, but some information was not read: ${(meta.missing_fields ?? []).join(', ')}. Please rescan the missing side.`
                            );

                            return meta;
                        }

                        setQidFrontDone(
                            true
                        );

                        setQidBackDone(
                            true
                        );

                        setScanOk(
                            true
                        );

                        setMessage(
                            'Qatar ID front and back scanned completely. Please verify before saving.'
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

    useEffect(
        () => {
            scanDocumentRef.current =
                scanDocument;
        },
        [
            scanDocument,
        ]
    );

    const requestScannerScan =
        async () => {
            try {
                const response =
                    await scannerAgentFetch(
                        '/scan-now'
                    );

                if (!response.ok) {
                    throw new Error(
                        `SCANNER_AGENT_${response.status}`
                    );
                }

                setMessage(
                    'Scanner command sent. Waiting for the document...'
                );
            } catch (error) {
                setMessage(
                    'Could not communicate with the Windows Scanner Agent.'
                );
            }
        };

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
     * =========================================
     * WINDOWS PC/LAPTOP SCANNER AGENT
     * =========================================
     *
     * Only localhost is contacted. The agent
     * monitors the connected Windows WIA scanner.
     */
    useEffect(
        () => {
            if (mobile) {
                return undefined;
            }

            let cancelled =
                false;

            const pollAgent =
                async () => {
                    if (
                        cancelled
                        || scannerPollBusyRef.current
                    ) {
                        return;
                    }

                    scannerPollBusyRef.current =
                        true;

                    try {
                        const pairResponse =
                            await scannerAgentFetch(
                                '/pair'
                            );

                        if (!pairResponse.ok) {
                            throw new Error(
                                `PAIR_${pairResponse.status}`
                            );
                        }

                        const statusResponse =
                            await scannerAgentFetch(
                                '/status'
                            );

                        if (!statusResponse.ok) {
                            throw new Error(
                                `STATUS_${statusResponse.status}`
                            );
                        }

                        const status =
                            await statusResponse.json();

                        if (cancelled) {
                            return;
                        }

                        setScannerAgent({
                            online: true,

                            scannerConnected:
                                Boolean(
                                    status.scannerConnected
                                ),

                            scannerName:
                                status.scannerName
                                ?? null,

                            autoSupported:
                                Boolean(
                                    status.autoSupported
                                ),

                            feedReady:
                                Boolean(
                                    status.feedReady
                                ),

                            queueCount:
                                Number(
                                    status.queueCount
                                    ?? 0
                                ),

                            lastError:
                                status.lastError
                                ?? '',

                            connectionError:
                                '',
                        });

                        if (
                            Number(
                                status.queueCount
                                ?? 0
                            ) > 0
                            && !scanningRef.current
                            && scanDocumentRef.current
                        ) {
                            const scanResponse =
                                await scannerAgentFetch(
                                    '/next-scan'
                                );

                            if (
                                scanResponse.status
                                === 200
                            ) {
                                const blob =
                                    await scanResponse.blob();

                                if (blob.size > 0) {
                                    const file =
                                        new File(
                                            [blob],
                                            `scanner-${Date.now()}.jpg`,
                                            {
                                                type:
                                                    blob.type
                                                    || 'image/jpeg',
                                            }
                                        );

                                    await scanDocumentRef
                                        .current(
                                            file,
                                            'scanner'
                                        );
                                }
                            }
                        }
                    } catch (error) {
                        if (!cancelled) {
                            setScannerAgent(
                                (previous) => ({
                                    ...previous,

                                    online:
                                        false,

                                    scannerConnected:
                                        false,

                                    feedReady:
                                        false,

                                    connectionError:
                                        String(
                                            error?.message
                                            ?? 'OFFLINE'
                                        ),
                                })
                            );
                        }
                    } finally {
                        scannerPollBusyRef.current =
                            false;
                    }
                };

            pollAgent();

            const timer =
                window.setInterval(
                    pollAgent,
                    1200
                );

            return () => {
                cancelled =
                    true;

                window.clearInterval(
                    timer
                );
            };
        },
        [
            mobile,
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

    const previewDocuments = [
        {
            key: 'qid-front',
            label: 'Qatar ID — Front',
            url: imagePreviews.qidFront,
        },
        {
            key: 'qid-back',
            label: 'Qatar ID — Back',
            url: imagePreviews.qidBack,
        },
        {
            key: 'passport',
            label: 'Passport',
            url: imagePreviews.passport,
        },
    ].filter(
        (document) =>
            Boolean(document.url),
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

                {!mobile && (
                    <div className="rounded-2xl border border-slate-200 bg-white p-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div className="flex items-center gap-2">
                                    <h4 className="text-sm font-black text-slate-900">
                                        Windows Scanner
                                    </h4>

                                    <span
                                        className={
                                            scannerAgent.online
                                                ? 'rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-black text-emerald-700'
                                                : 'rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-black text-slate-500'
                                        }
                                    >
                                        {scannerAgent.online
                                            ? 'AGENT ONLINE'
                                            : 'AGENT OFFLINE'}
                                    </span>
                                </div>

                                {scannerAgent.online
                                    && scannerAgent.scannerConnected
                                    ? (
                                        <div className="mt-2 text-xs leading-5 text-slate-600">
                                            <div className="font-bold text-slate-800">
                                                {scannerAgent.scannerName
                                                    ?? 'WIA Scanner'}
                                            </div>

                                            {scannerAgent.autoSupported
                                                ? (
                                                    <div>
                                                        {scannerAgent.feedReady
                                                            ? 'Document detected in feeder — automatic scan is starting.'
                                                            : 'Ready. Place a document in the scanner feeder; no Scan button is required.'}
                                                    </div>
                                                )
                                                : (
                                                    <div>
                                                        Scanner connected. This scanner driver does not expose automatic paper detection, so use the scanner control below.
                                                    </div>
                                                )}

                                            {scannerAgent.lastError && (
                                                <div className="mt-1 font-semibold text-red-600">
                                                    {scannerAgent.lastError}
                                                </div>
                                            )}
                                        </div>
                                    )
                                    : scannerAgent.online
                                        ? (
                                            <p className="mt-2 text-xs text-amber-700">
                                                Scanner Agent is running, but no Windows WIA scanner is detected.
                                            </p>
                                        )
                                        : (
                                            <p className="mt-2 text-xs leading-5 text-slate-500">
                                                Install the Scanner Agent once on this Windows PC. It will then start automatically with Windows.
                                            </p>
                                        )}
                            </div>

                            {scannerAgent.online
                                && scannerAgent.scannerConnected
                                && !scannerAgent.autoSupported
                                ? (
                                    <button
                                        type="button"
                                        disabled={scanning}
                                        onClick={
                                            requestScannerScan
                                        }
                                        className="shrink-0 rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-black text-white disabled:opacity-50"
                                    >
                                        SCAN FROM CONNECTED SCANNER
                                    </button>
                                )
                                : !scannerAgent.online
                                    ? (
                                        <div className="flex shrink-0 flex-wrap gap-2">
                                            <a
                                                href="/scanner/MikroPanelScannerAgent.ps1"
                                                download
                                                className="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-xs font-black text-indigo-700"
                                            >
                                                DOWNLOAD AGENT
                                            </a>

                                            <button
                                                type="button"
                                                onClick={() => {
                                                    navigator.clipboard
                                                        ?.writeText(
                                                            scannerInstallCommand
                                                        );

                                                    setMessage(
                                                        'Windows Scanner Agent install command copied.'
                                                    );
                                                }}
                                                className="rounded-xl bg-slate-900 px-4 py-2.5 text-xs font-black text-white"
                                            >
                                                COPY INSTALL COMMAND
                                            </button>
                                        </div>
                                    )
                                    : null}
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

                <div className="rounded-2xl border-2 border-cyan-200 bg-white p-5 shadow-sm">
                    <div className="mb-5">
                        <h4 className="text-lg font-black text-slate-900">
                            Client Photo & Document Preview
                        </h4>

                        <p className="mt-1 text-sm text-slate-500">
                            Scan Qatar ID front/back or passport. Face photo and full document will appear here before saving.
                        </p>
                    </div>

                    <div className="grid gap-5 lg:grid-cols-[180px_1fr]">
                        <div>
                            <p className="mb-2 text-xs font-black uppercase tracking-wide text-slate-500">
                                Client Face Photo
                            </p>

                            <div className="flex justify-center lg:justify-start">
                                {imagePreviews.face ? (
                                    <a
                                        href={imagePreviews.face}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="block"
                                    >
                                        <img
                                            src={imagePreviews.face}
                                            alt="Client Face"
                                            className="h-36 w-36 rounded-full border-4 border-white object-cover shadow-md ring-2 ring-cyan-100"
                                        />
                                    </a>
                                ) : (
                                    <div className="flex h-36 w-36 items-center justify-center rounded-full border-2 border-dashed border-slate-300 bg-slate-50 p-4 text-center text-xs font-bold text-slate-400">
                                        Face Preview
                                    </div>
                                )}
                            </div>

                            {imagePreviews.face ? (
                                <p className="mt-3 text-xs font-semibold text-emerald-600">
                                    Face detected successfully
                                </p>
                            ) : (
                                <p className="mt-3 text-xs text-slate-400">
                                    Qatar ID front or passport থেকে face নেওয়া হবে
                                </p>
                            )}
                        </div>

                        <div>
                            <p className="mb-2 text-xs font-black uppercase tracking-wide text-slate-500">
                                Full Document Preview
                            </p>

                            {previewDocuments.length > 0 ? (
                                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                    {previewDocuments.map(
                                        (document) => (
                                            <a
                                                key={document.key}
                                                href={document.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:border-cyan-400 hover:shadow-md"
                                            >
                                                <div className="flex h-44 items-center justify-center bg-slate-50 p-2">
                                                    <img
                                                        src={document.url}
                                                        alt={document.label}
                                                        className="max-h-full max-w-full object-contain"
                                                    />
                                                </div>

                                                <div className="border-t border-slate-200 px-3 py-2">
                                                    <p className="text-xs font-black text-slate-800">
                                                        {document.label}
                                                    </p>

                                                    <p className="mt-0.5 text-[10px] text-slate-400">
                                                        Click to enlarge
                                                    </p>
                                                </div>
                                            </a>
                                        ),
                                    )}
                                </div>
                            ) : (
                                <div className="flex min-h-44 items-center justify-center rounded-xl border-2 border-dashed border-slate-300 bg-slate-50 p-5 text-center">
                                    <div>
                                        <p className="text-sm font-bold text-slate-500">
                                            No document scanned yet
                                        </p>

                                        <p className="mt-1 text-xs text-slate-400">
                                            Qatar ID হলে Front + Back, Passport হলে full passport page এখানে দেখাবে
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    {(imagePreviews.qidFront
                        || imagePreviews.qidBack
                        || imagePreviews.passport) && (
                        <div className="mt-4 rounded-lg bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-700">
                            Preview ready — verify the image before saving the client.
                        </div>
                    )}
                </div>

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
                                Passport information detected from the back side.
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
                                : scannerAgent.online
                                    && scannerAgent.scannerConnected
                                    ? scannerAgent.autoSupported
                                        ? 'Place the Qatar ID or passport in the connected scanner feeder. Scanning starts automatically.'
                                        : 'Connected scanner is ready. Use the scanner control above for this scanner driver.'
                                    : 'Connect the Windows Scanner Agent or upload PDF, JPG, JPEG or PNG.'}
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}
