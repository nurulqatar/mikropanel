import AppLayout from '@/Layouts/AppLayout';
import ClientIdentityFields from '@/Components/Clients/ClientIdentityFields';
import ClientCustomFieldsForm from '@/Components/Clients/ClientCustomFieldsForm';
import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';
import {
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

const formatMac = (value) =>
    value
        .toUpperCase()
        .replace(/[^0-9A-F]/g, '')
        .slice(0, 12)
        .replace(/(.{2})(?=.)/g, '$1:');

export default function MacClientPos({
    clients = [],
    packages = [],
    ipRanges = [],
    permissions = {},
    stats = {},
    recentRefunds = [],
}) {
    const [search, setSearch] =
        useState('');

    const [status, setStatus] =
        useState('all');

    const [modal, setModal] =
        useState(null);

    const [selected, setSelected] =
        useState(null);

    const visibleClients =
        useMemo(() => {
            const needle =
                search
                    .trim()
                    .toLowerCase();

            return clients.filter(
                (client) => {
                    if (
                        status === 'active'
                        && !client.enabled
                    ) {
                        return false;
                    }

                    if (
                        status === 'suspended'
                        && client.enabled
                    ) {
                        return false;
                    }

                    if (
                        status === 'due'
                        && Number(
                            client.total_due
                            ?? 0,
                        ) <= 0
                    ) {
                        return false;
                    }

                    if (!needle) {
                        return true;
                    }

                    const haystack = [
                        client.name,
                        client.client_code,
                        client.phone,
                        client.mac_address,
                        client.ip_address,
                        client.identity_number,
                        client.identity_barcode,
                        client.nationality,
                        client.package?.name,
                    ]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase();

                    return haystack.includes(
                        needle,
                    );
                },
            );
        }, [
            clients,
            search,
            status,
        ]);

    const openModal = (
        type,
        client = null,
    ) => {
        setSelected(client);
        setModal(type);
    };

    const closeModal = () => {
        setModal(null);
        setSelected(null);
    };

    const changeState = (
        client,
    ) => {
        const action =
            client.enabled
                ? 'suspend'
                : 'unsuspend';

        const label =
            client.enabled
                ? 'Suspend'
                : 'Activate';

        if (
            !window.confirm(
                `${label} ${client.name}?`,
            )
        ) {
            return;
        }

        router.post(
            route(
                `clients.${action}`,
                client.id,
            ),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="MAC Client POS">
            <Head title="MAC Client POS" />

            <div className="space-y-5">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-black text-slate-900">
                            MAC Client POS
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Daily MAC client operations from one screen
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route(
                                'reseller.mac-clients.migration',
                            )}
                            className="rounded-xl bg-violet-600 px-4 py-3 text-sm font-black text-white shadow-sm hover:bg-violet-700"
                        >
                            IMPORT / EXPORT
                        </Link>

                        {permissions.form_fields && (
                            <Link
                                href={route(
                                    'reseller.mac-clients.form-fields.index',
                                )}
                                className="rounded-xl bg-indigo-600 px-4 py-3 text-sm font-black text-white shadow-sm hover:bg-indigo-700"
                            >
                                FORM FIELDS
                            </Link>
                        )}
                    </div>

                    {permissions.create && (
                        <button
                            type="button"
                            onClick={() =>
                                openModal(
                                    'create',
                                )
                            }
                            className="rounded-xl bg-cyan-600 px-5 py-3 font-black text-white shadow-sm hover:bg-cyan-700"
                        >
                            + NEW CLIENT
                        </button>
                    )}
                </div>

                <QuickClientWorkspace
                    clients={clients}
                    permissions={permissions}
                    onRecharge={(client) =>
                        openModal(
                            'recharge',
                            client,
                        )
                    }
                    onEdit={(client) =>
                        openModal(
                            'edit',
                            client,
                        )
                    }
                    onToggle={
                        changeState
                    }
                    onRefund={(client) =>
                        openModal(
                            'refund',
                            client,
                        )
                    }
                                    onAddDevice={(client) =>
                        openModal(
                            'add-device',
                            client,
                        )
                    }
                    onRenewAll={(client) =>
                        openModal(
                            'renew-all',
                            client,
                        )
                    }
                />

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Today Collection"
                        value={`QAR ${money(
                            stats.today_collection,
                        )}`}
                    />

                    <StatCard
                        label="Today Due"
                        value={`QAR ${money(
                            stats.today_due_created,
                        )}`}
                    />

                    <StatCard
                        label="Renewed Today"
                        value={
                            stats.renewed_today
                        }
                    />

                    <StatCard
                        label="New Clients Today"
                        value={
                            stats.new_clients_today
                        }
                    />

                    <StatCard
                        label="Refund Today"
                        value={`QAR ${money(
                            stats.today_refund,
                        )}`}
                    />

                    <StatCard
                        label="Net Collection"
                        value={`QAR ${money(
                            stats.net_collection,
                        )}`}
                    />

                    <StatCard
                        label="Current Total Due"
                        value={`QAR ${money(
                            stats.current_due,
                        )}`}
                    />
                </div>


                <RefundHistory
                    rows={
                        recentRefunds
                    }
                />

            </div>

            {modal === 'create'
                && permissions.create && (
                <CreateClientModal
                    packages={packages}
                    ipRanges={ipRanges}
                    canReceivePayment={
                        permissions.receive_payment
                    }
                    onClose={
                        closeModal
                    }
                />
            )}

            {modal === 'add-device'
                && selected
                && permissions.create && (
                <AddDeviceModal
                    client={selected}
                    packages={packages}
                    ipRanges={ipRanges}
                    canReceivePayment={
                        permissions.receive_payment
                    }
                    onClose={
                        closeModal
                    }
                />
            )}

            {modal === 'renew-all'
                && selected
                && permissions.renew && (
                <RenewAllDevicesModal
                    client={selected}
                    clients={clients}
                    canReceivePayment={
                        permissions.receive_payment
                    }
                    onClose={
                        closeModal
                    }
                />
            )}

            {modal === 'recharge'
                && selected
                && permissions.renew && (
                <RechargeModal
                    client={selected}
                    packages={packages}
                    canReceivePayment={
                        permissions.receive_payment
                    }
                    onClose={
                        closeModal
                    }
                />
            )}

            {modal === 'edit'
                && selected
                && permissions.edit && (
                <EditClientModal
                    client={selected}
                    packages={packages}
                    ipRanges={ipRanges}
                    onClose={
                        closeModal
                    }
                />
            )}

            {modal === 'refund'
                && selected
                && permissions.refund && (
                <RefundModal
                    client={selected}
                    onClose={
                        closeModal
                    }
                />
            )}
        </AppLayout>
    );
}

function QuickClientWorkspace({
    clients = [],
    permissions = {},
    onRecharge,
    onEdit,
    onToggle,
    onRefund,
    onAddDevice,
    onRenewAll,
}) {
    const [search, setSearch] =
        useState('');

    const [selectedId, setSelectedId] =
        useState(null);

    const selectedClient =
        clients.find(
            (client) =>
                Number(client.id)
                === Number(selectedId),
        ) ?? null;

    /*
     * MULTI_DEVICE_UI_START
     */
    const clientDevices =
        useMemo(() => {
            if (!selectedClient) {
                return [];
            }

            return clients
                .filter(
                    (client) =>
                        Number(client.id)
                            === Number(
                                selectedClient.id,
                            )
                        || Number(
                            client
                                .parent_client_id,
                        ) === Number(
                            selectedClient.id,
                        ),
                )
                .sort(
                    (left, right) =>
                        Number(left.id)
                        - Number(right.id),
                );
        }, [
            clients,
            selectedClient,
        ]);

    const additionalDevices =
        clientDevices.filter(
            (device) =>
                Number(device.id)
                !== Number(
                    selectedClient?.id,
                ),
        );

    const accountDue =
        clientDevices.reduce(
            (total, device) =>
                total
                + Number(
                    device.total_due
                    ?? 0,
                ),
            0,
        );

    const results =
        useMemo(() => {
            const needle =
                search
                    .trim()
                    .toLowerCase();

            if (!needle) {
                return clients.slice(
                    0,
                    8,
                );
            }

            return clients
                .filter((client) => {
                    const haystack = [
                        client.name,
                        client.client_code,
                        client.phone,
                        client.mac_address,
                        client.ip_address,
                        client.identity_number,
                        client.identity_barcode,
                        client.nationality,
                        client.package?.name,
                    ]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase();

                    return haystack.includes(
                        needle,
                    );
                })
                .slice(0, 8);
        }, [
            clients,
            search,
        ]);

    const selectClient = (
        client,
    ) => {
        const primary =
            client?.parent_client_id
                ? (
                    clients.find(
                        (item) =>
                            Number(item.id)
                            === Number(
                                client
                                    .parent_client_id,
                            ),
                    )
                    ?? client
                )
                : client;

        setSelectedId(
            primary.id,
        );

        setSearch(
            `${primary.name}${
                primary.client_code
                    ? ` · ${primary.client_code}`
                    : ''
            }`,
        );
    };

    /*
     * AUTO_BARCODE_SCAN_START
     *
     * USB / Bluetooth barcode scanners normally
     * behave like a keyboard and finish with
     * Enter or Tab.
     */
    useEffect(() => {
        let buffer = '';
        let lastKeyAt = 0;

        const normalizeScan = (value) =>
            String(value ?? '')
                .trim()
                .toLowerCase();

        const handleBarcodeKey = (event) => {
            if (
                event.ctrlKey
                || event.altKey
                || event.metaKey
            ) {
                return;
            }

            const target = event.target;

            const tag = String(
                target?.tagName ?? '',
            ).toUpperCase();

            const editable =
                target?.isContentEditable
                || tag === 'INPUT'
                || tag === 'TEXTAREA'
                || tag === 'SELECT';

            const quickSearch =
                target?.dataset
                    ?.barcodeSearch
                === 'true';

            /*
             * Do not capture typing inside
             * create/edit/recharge/refund forms.
             */
            if (
                editable
                && !quickSearch
            ) {
                buffer = '';
                lastKeyAt = 0;
                return;
            }

            const now = Date.now();

            /*
             * Scanner keystrokes arrive rapidly.
             * Slow typing starts a new buffer.
             */
            if (
                lastKeyAt
                && now - lastKeyAt > 180
            ) {
                buffer = '';
            }

            lastKeyAt = now;

            if (
                event.key === 'Enter'
                || event.key === 'Tab'
            ) {
                const scanned =
                    buffer.trim();

                buffer = '';
                lastKeyAt = 0;

                if (scanned.length < 4) {
                    return;
                }

                const needle =
                    normalizeScan(
                        scanned,
                    );

                const client =
                    clients.find(
                        (item) => {
                            const barcode =
                                normalizeScan(
                                    item.identity_barcode,
                                );

                            const identity =
                                normalizeScan(
                                    item.identity_number,
                                );

                            return (
                                (
                                    barcode !== ''
                                    && barcode
                                        === needle
                                )
                                || (
                                    identity !== ''
                                    && identity
                                        === needle
                                )
                            );
                        },
                    );

                setSearch(scanned);

                if (!client) {
                    setSelectedId(null);
                    return;
                }

                event.preventDefault();

                selectClient(
                    client,
                );

                return;
            }

            if (
                event.key.length === 1
            ) {
                buffer += event.key;
            }
        };

        document.addEventListener(
            'keydown',
            handleBarcodeKey,
            true,
        );

        return () => {
            document.removeEventListener(
                'keydown',
                handleBarcodeKey,
                true,
            );
        };
    }, [clients]);

    /*
     * AUTO_BARCODE_SCAN_END
     */

    return (
        <section className="overflow-hidden rounded-2xl border border-cyan-200 bg-white shadow-sm">
            <div className="border-b border-cyan-100 bg-gradient-to-r from-cyan-50 to-sky-50 px-5 py-4">
                <div>
                    <h2 className="text-lg font-black text-slate-900">
                        Quick Client Work
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Search manually or scan client barcode
                    </p>
                </div>
            </div>

            <div className="grid gap-5 p-5 lg:grid-cols-2">
                <div>
                    <label className="text-xs font-black uppercase tracking-wide text-slate-500">
                        Search Client
                    </label>

                    <input
                        data-barcode-search="true"
                        type="text"
                        autoFocus
                        value={search}
                        onChange={(event) => {
                            setSearch(
                                event.target
                                    .value,
                            );

                            setSelectedId(
                                null,
                            );
                        }}
                        onKeyDown={(event) => {
                            if (
                                event.key !== 'Enter'
                            ) {
                                return;
                            }

                            event.preventDefault();

                            const needle =
                                search
                                    .trim()
                                    .toLowerCase();

                            const exact =
                                clients.find(
                                    (client) =>
                                        [
                                            client.identity_barcode,
                                            client.identity_number,
                                            client.client_code,
                                            client.phone,
                                            client.mac_address,
                                        ]
                                            .filter(Boolean)
                                            .some(
                                                (value) =>
                                                    String(
                                                        value,
                                                    )
                                                        .trim()
                                                        .toLowerCase()
                                                    === needle,
                                            ),
                                );

                            if (exact) {
                                selectClient(
                                    exact,
                                );
                                return;
                            }

                            if (
                                results.length === 1
                            ) {
                                selectClient(
                                    results[0],
                                );
                            }
                        }}
                        placeholder="Name / Phone / MAC / IP / Client ID / QID / Passport / Barcode"
                        className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
                    />

                    {!selectedClient && (
                        <div className="mt-2 max-h-72 overflow-y-auto rounded-xl border border-slate-200">
                            {results.map(
                                (client) => (
                                    <button
                                        key={
                                            client.id
                                        }
                                        type="button"
                                        onClick={() =>
                                            selectClient(
                                                client,
                                            )
                                        }
                                        className="flex w-full items-center justify-between gap-4 border-b border-slate-100 px-4 py-3 text-left transition last:border-b-0 hover:bg-cyan-50"
                                    >
                                        <span className="min-w-0">
                                            <span className="block truncate font-black text-slate-800">
                                                {
                                                    client.name
                                                }
                                            </span>

                                            <span className="mt-1 block truncate text-xs text-slate-500">
                                                {client.client_code || '-'}
                                                {' · '}
                                                {client.mac_address || '-'}
                                                {' · '}
                                                {client.ip_address || '-'}
                                            </span>
                                        </span>

                                        <span className="shrink-0 rounded-lg bg-cyan-50 px-3 py-1.5 text-xs font-black text-cyan-700">
                                            Select
                                        </span>
                                    </button>
                                ),
                            )}

                            {results.length ===
                                0 && (
                                <div className="p-7 text-center text-sm font-semibold text-slate-400">
                                    No matching client
                                </div>
                            )}
                        </div>
                    )}

                    {selectedClient && (
                        <button
                            type="button"
                            onClick={() => {
                                setSelectedId(
                                    null,
                                );

                                setSearch('');
                            }}
                            className="mt-3 text-sm font-bold text-cyan-700 hover:underline"
                        >
                            ← Select another client
                        </button>
                    )}
                </div>

                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    {!selectedClient ? (
                        <div className="flex min-h-72 items-center justify-center text-center">
                            <div>
                                <div className="text-4xl text-slate-300">
                                    ◇
                                </div>

                                <div className="mt-3 font-black text-slate-500">
                                    Select a client to start recharge
                                </div>

                                <div className="mt-1 text-xs text-slate-400">
                                    Search by name, phone, MAC, IP, Client ID, Qatar ID, passport or barcode
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h3 className="text-xl font-black text-slate-900">
                                        {
                                            selectedClient.name
                                        }
                                    </h3>

                                    <div className="mt-1 text-sm text-slate-500">
                                        {selectedClient.client_code
                                            || `#${selectedClient.id}`}
                                    </div>
                                </div>

                                <span
                                    className={`rounded-full px-3 py-1 text-xs font-black ${
                                        selectedClient.enabled
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-red-100 text-red-700'
                                    }`}
                                >
                                    {selectedClient.enabled
                                        ? 'ACTIVE'
                                        : 'SUSPENDED'}
                                </span>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2">
                                <QuickInfo
                                    label="MAC"
                                    value={
                                        selectedClient.mac_address
                                        || '-'
                                    }
                                />

                                <QuickInfo
                                    label="IP"
                                    value={
                                        selectedClient.ip_address
                                        || '-'
                                    }
                                />

                                <QuickInfo
                                    label="Package"
                                    value={
                                        selectedClient.package
                                            ?.name
                                        || '-'
                                    }
                                />

                                <QuickInfo
                                    label="Expiry"
                                    value={
                                        selectedClient.expiry_date
                                        || '-'
                                    }
                                />

                                {selectedClient.identity_number && (
                                    <QuickInfo
                                        label="QID / Passport"
                                        value={
                                            selectedClient.identity_number
                                        }
                                    />
                                )}

                                {selectedClient.nationality && (
                                    <QuickInfo
                                        label="Nationality"
                                        value={
                                            selectedClient.nationality
                                        }
                                    />
                                )}

                                <QuickInfo
                                    label="Phone"
                                    value={
                                        selectedClient.phone
                                        || '-'
                                    }
                                />

                                <QuickInfo
                                    label="Account Due"
                                    value={`QAR ${money(
                                        accountDue,
                                    )}`}
                                />
                            </div>

                            <div className="rounded-xl border border-cyan-100 bg-white p-4">
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <div className="font-black text-slate-800">
                                            Additional Devices
                                        </div>

                                        <div className="mt-1 text-xs text-slate-500">
                                            {additionalDevices.length} additional device(s)
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        {permissions.renew
                                            && clientDevices.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    onRenewAll(
                                                        selectedClient,
                                                    )
                                                }
                                                className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-black text-white hover:bg-emerald-700"
                                            >
                                                Renew All Devices
                                            </button>
                                        )}

                                        {permissions.create && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    onAddDevice(
                                                        selectedClient,
                                                    )
                                                }
                                                className="rounded-lg bg-cyan-600 px-4 py-2 text-xs font-black text-white hover:bg-cyan-700"
                                            >
                                                + ADD DEVICE
                                            </button>
                                        )}
                                    </div>
                                </div>

                                {additionalDevices.length === 0 ? (
                                    <div className="mt-3 rounded-lg bg-slate-50 p-4 text-sm font-semibold text-slate-400">
                                        No additional device yet.
                                    </div>
                                ) : (
                                    <div className="mt-3 space-y-3">
                                        {additionalDevices.map(
                                            (device) => (
                                                <div
                                                    key={device.id}
                                                    className="rounded-xl border border-slate-200 p-4"
                                                >
                                                    <div className="flex flex-wrap items-start justify-between gap-3">
                                                        <div>
                                                            <div className="font-black text-slate-800">
                                                                {device.device_label
                                                                    || 'Device'}
                                                            </div>

                                                            <div className="mt-1 text-xs text-slate-500">
                                                                {device.mac_address
                                                                    || '-'}
                                                                {' · '}
                                                                {device.ip_address
                                                                    || '-'}
                                                            </div>

                                                            <div className="mt-1 text-xs text-slate-500">
                                                                {device.package
                                                                    ?.name
                                                                    || '-'}
                                                                {' · Expiry '}
                                                                {device.expiry_date
                                                                    || '-'}
                                                                {' · Due QAR '}
                                                                {money(
                                                                    device.total_due,
                                                                )}
                                                            </div>
                                                        </div>

                                                        <span
                                                            className={`rounded-full px-3 py-1 text-xs font-black ${
                                                                !device.enabled
                                                                    ? 'bg-red-100 text-red-700'
                                                                    : device.connected
                                                                      ? 'bg-emerald-100 text-emerald-700'
                                                                      : 'bg-slate-100 text-slate-600'
                                                            }`}
                                                        >
                                                            {!device.enabled
                                                                ? 'SUSPENDED'
                                                                : device.connected
                                                                  ? 'ONLINE'
                                                                  : 'OFFLINE'}
                                                        </span>
                                                    </div>

                                                    <div className="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
                                                        {permissions.renew && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    onRecharge(
                                                                        device,
                                                                    )
                                                                }
                                                                className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-black text-white"
                                                            >
                                                                Renew
                                                            </button>
                                                        )}

                                                        {permissions.refund && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    onRefund(
                                                                        device,
                                                                    )
                                                                }
                                                                className="rounded-lg bg-amber-600 px-3 py-2 text-xs font-black text-white"
                                                            >
                                                                Refund
                                                            </button>
                                                        )}

                                                        {permissions.edit && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    onEdit(
                                                                        device,
                                                                    )
                                                                }
                                                                className="rounded-lg bg-blue-600 px-3 py-2 text-xs font-black text-white"
                                                            >
                                                                Edit
                                                            </button>
                                                        )}

                                                        {permissions.suspend && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    onToggle(
                                                                        device,
                                                                    )
                                                                }
                                                                className={`rounded-lg px-3 py-2 text-xs font-black text-white ${
                                                                    device.enabled
                                                                        ? 'bg-red-600'
                                                                        : 'bg-cyan-600'
                                                                }`}
                                                            >
                                                                {device.enabled
                                                                    ? 'Suspend'
                                                                    : 'Activate'}
                                                            </button>
                                                        )}

                                                        <Link
                                                            href={route(
                                                                'clients.show',
                                                                device.id,
                                                            )}
                                                            className="rounded-lg bg-slate-700 px-3 py-2 text-center text-xs font-black text-white"
                                                        >
                                                            Details
                                                        </Link>
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="grid gap-2 pt-2 sm:grid-cols-2">
                                {permissions.renew && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onRecharge(
                                                selectedClient,
                                            )
                                        }
                                        className="rounded-xl bg-emerald-600 px-4 py-3 font-black text-white hover:bg-emerald-700"
                                    >
                                        Recharge / Due Payment
                                    </button>
                                )}

                                {permissions.refund && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onRefund(
                                                selectedClient,
                                            )
                                        }
                                        className="rounded-xl bg-amber-600 px-4 py-3 font-black text-white hover:bg-amber-700"
                                    >
                                        Refund Service
                                    </button>
                                )}

                                {permissions.edit && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onEdit(
                                                selectedClient,
                                            )
                                        }
                                        className="rounded-xl bg-blue-600 px-4 py-3 font-black text-white hover:bg-blue-700"
                                    >
                                        Edit Client
                                    </button>
                                )}

                                {permissions.suspend && (
                                    <button
                                        type="button"
                                        onClick={() =>
                                            onToggle(
                                                selectedClient,
                                            )
                                        }
                                        className={`rounded-xl px-4 py-3 font-black text-white ${
                                            selectedClient.enabled
                                                ? 'bg-red-600 hover:bg-red-700'
                                                : 'bg-cyan-600 hover:bg-cyan-700'
                                        }`}
                                    >
                                        {selectedClient.enabled
                                            ? 'Suspend Client'
                                            : 'Activate Client'}
                                    </button>
                                )}

                                <Link
                                    href={route(
                                        'clients.show',
                                        selectedClient.id,
                                    )}
                                    className="rounded-xl bg-slate-700 px-4 py-3 text-center font-black text-white hover:bg-slate-800"
                                >
                                    Full Client Details
                                </Link>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}

function RefundModal({
    client,
    onClose,
}) {
    const [preview, setPreview] =
        useState(null);

    const [loading, setLoading] =
        useState(true);

    const [error, setError] =
        useState('');

    const [reason, setReason] =
        useState('');

    const [submitting, setSubmitting] =
        useState(false);

    useEffect(() => {
        let active = true;

        setLoading(true);
        setError('');

        fetch(
            route(
                'payments.refund.preview',
                client.id,
            ),
            {
                headers: {
                    Accept:
                        'application/json',

                    'X-Requested-With':
                        'XMLHttpRequest',
                },
            },
        )
            .then(async (response) => {
                const data =
                    await response.json();

                if (!response.ok) {
                    throw new Error(
                        data.message
                        || 'Unable to calculate refund.',
                    );
                }

                return data;
            })
            .then((data) => {
                if (active) {
                    setPreview(data);
                }
            })
            .catch((exception) => {
                if (active) {
                    setError(
                        exception.message,
                    );
                }
            })
            .finally(() => {
                if (active) {
                    setLoading(false);
                }
            });

        return () => {
            active = false;
        };
    }, [
        client.id,
    ]);

    const submit = () => {
        if (
            !preview?.eligible
            || !reason.trim()
            || submitting
        ) {
            return;
        }

        const confirmed =
            window.confirm(
                `Refund QAR ${money(
                    preview.refund_amount,
                )} to ${client.name}? `
                + 'The client will be suspended and disconnected immediately.',
            );

        if (!confirmed) {
            return;
        }

        setSubmitting(true);

        router.post(
            route(
                'payments.refund.store',
                client.id,
            ),
            {
                invoice_id:
                    preview.invoice_id,

                reason:
                    reason.trim(),
            },
            {
                preserveScroll: true,

                onSuccess: () =>
                    onClose(),

                onFinish: () =>
                    setSubmitting(false),
            },
        );
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
            <div className="max-h-[92vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
                <div className="flex items-center justify-between border-b px-6 py-4">
                    <div>
                        <h2 className="text-xl font-black text-slate-900">
                            Service Refund
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            {client.name}
                            {' · '}
                            {client.client_code
                                || `#${client.id}`}
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg px-3 py-2 font-black text-slate-500 hover:bg-slate-100"
                    >
                        ✕
                    </button>
                </div>

                <div className="space-y-5 p-6">
                    {loading && (
                        <div className="rounded-xl bg-slate-50 p-8 text-center font-bold text-slate-500">
                            Calculating refund...
                        </div>
                    )}

                    {error && (
                        <div className="rounded-xl bg-red-50 p-4 font-bold text-red-700">
                            {error}
                        </div>
                    )}

                    {!loading
                        && preview
                        && !preview.eligible && (
                        <div className="rounded-xl bg-amber-50 p-5">
                            <div className="font-black text-amber-800">
                                No refund available
                            </div>

                            <div className="mt-1 text-sm text-amber-700">
                                {preview.message}
                            </div>
                        </div>
                    )}

                    {!loading
                        && preview?.eligible && (
                        <>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <QuickInfo
                                    label="Invoice"
                                    value={
                                        preview.invoice_no
                                    }
                                />

                                <QuickInfo
                                    label="Package Price"
                                    value={`QAR ${money(
                                        preview.service_price,
                                    )}`}
                                />

                                <QuickInfo
                                    label="Validity"
                                    value={`${preview.validity_days} days`}
                                />

                                <QuickInfo
                                    label="Used"
                                    value={`${preview.used_days} days`}
                                />

                                <QuickInfo
                                    label="Daily Rate"
                                    value={`QAR ${Number(
                                        preview.daily_rate
                                        ?? 0,
                                    ).toFixed(2)}`}
                                />

                                <QuickInfo
                                    label="Used Value"
                                    value={`QAR ${money(
                                        preview.used_value,
                                    )}`}
                                />

                                <QuickInfo
                                    label="Actually Paid"
                                    value={`QAR ${money(
                                        preview.net_paid,
                                    )}`}
                                />

                                <QuickInfo
                                    label="Unused Service"
                                    value={`QAR ${money(
                                        preview.unused_value,
                                    )}`}
                                />
                            </div>

                            <div className="rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-5 text-center">
                                <div className="text-xs font-black uppercase tracking-wider text-emerald-600">
                                    Customer Will Receive
                                </div>

                                <div className="mt-1 text-4xl font-black text-emerald-700">
                                    QAR {money(
                                        preview.refund_amount,
                                    )}
                                </div>

                                <div className="mt-2 text-sm font-bold text-emerald-700">
                                    {preview.used_days} days used
                                    {' · '}
                                    QAR {money(
                                        preview.used_value,
                                    )} retained
                                </div>
                            </div>

                            <div className="rounded-xl bg-red-50 p-4 text-sm font-bold text-red-700">
                                After refund, this client will be suspended and disconnected immediately. The original payment will remain permanently in payment history.
                            </div>

                            <div>
                                <label className="text-xs font-black uppercase tracking-wide text-slate-500">
                                    Refund Reason
                                </label>

                                <textarea
                                    rows={3}
                                    value={reason}
                                    onChange={(event) =>
                                        setReason(
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Enter refund reason"
                                    className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-100"
                                />
                            </div>

                            <button
                                type="button"
                                disabled={
                                    submitting
                                    || !reason.trim()
                                }
                                onClick={submit}
                                className="w-full rounded-xl bg-amber-600 px-5 py-3 font-black text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {submitting
                                    ? 'PROCESSING REFUND...'
                                    : `REFUND QAR ${money(
                                        preview.refund_amount,
                                    )} & DISCONNECT`}
                            </button>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}

function RefundHistory({
    rows = [],
}) {
    if (!rows.length) {
        return null;
    }

    return (
        <section className="overflow-hidden rounded-2xl border bg-white shadow-sm">
            <div className="border-b px-5 py-4">
                <h2 className="font-black text-slate-900">
                    Recent Refund History
                </h2>

                <p className="mt-1 text-xs text-slate-500">
                    Permanent refund audit history
                </p>
            </div>

            <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                    <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th className="px-4 py-3">
                                Time
                            </th>

                            <th className="px-4 py-3">
                                Client
                            </th>

                            <th className="px-4 py-3">
                                Invoice
                            </th>

                            <th className="px-4 py-3">
                                Used
                            </th>

                            <th className="px-4 py-3">
                                Refund
                            </th>

                            <th className="px-4 py-3">
                                Reason
                            </th>

                            <th className="px-4 py-3">
                                Operator
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        {rows.map((row) => (
                            <tr
                                key={
                                    row.batch_uuid
                                }
                                className="border-t"
                            >
                                <td className="whitespace-nowrap px-4 py-3">
                                    {row.created_at
                                        || row.date}
                                </td>

                                <td className="px-4 py-3">
                                    <div className="font-bold text-slate-800">
                                        {row.client_name
                                            || '-'}
                                    </div>

                                    <div className="text-xs text-slate-400">
                                        {row.client_code
                                            || ''}
                                    </div>
                                </td>

                                <td className="px-4 py-3">
                                    {row.invoice_no
                                        || '-'}
                                </td>

                                <td className="px-4 py-3">
                                    {row.used_days} days
                                </td>

                                <td className="whitespace-nowrap px-4 py-3 font-black text-amber-700">
                                    QAR {money(
                                        row.amount,
                                    )}
                                </td>

                                <td className="max-w-xs px-4 py-3 text-slate-600">
                                    {row.reason}
                                </td>

                                <td className="px-4 py-3">
                                    {row.refunded_by
                                        || '-'}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function QuickInfo({
    label,
    value,
}) {
    return (
        <div className="rounded-lg bg-white p-3">
            <div className="text-[10px] font-black uppercase tracking-wide text-slate-400">
                {label}
            </div>

            <div className="mt-1 break-all text-sm font-black text-slate-700">
                {value}
            </div>
        </div>
    );
}

function CreateClientModal({
    packages,
    ipRanges,
    canReceivePayment,
    onClose,
}) {
    const form = useForm({
        custom_fields: {},
        identity_type: '',
        identity_number: '',
        identity_barcode: '',
        nationality: '',
        date_of_birth: '',
        gender: '',
        document_expiry_date: '',
        qatar_id_number: '',
        qatar_id_expiry_date: '',
        occupation: '',
        passport_number: '',
        passport_expiry_date: '',
        document_serial_number: '',
        residency_type: '',
        employer: '',
        place_of_birth: '',
        package_id: '',
        name: '',
        mac_address: '',
        phone: '',
        connection_payment_status:
            canReceivePayment
                ? 'paid'
                : 'due',
        connection_payment_method:
            'Cash',
        connection_transaction_id:
            '',
        return_to: 'mac-pos',
    });

    const selectedPackage =
        packages.find(
            (item) =>
                String(item.id)
                === String(
                    form.data
                        .package_id,
                ),
        );

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route('clients.store'),
            {
                preserveScroll: true,
                onSuccess:
                    onClose,
            },
        );
    };

    return (
        <Modal
            title="New MAC Client"
            onClose={onClose}
        >
            <form
                onSubmit={submit}
                className="space-y-4"
            >
                        <div className="mb-5 space-y-4">
                            <ClientIdentityFields
                                data={form.data}
                                setData={form.setData}
                                errors={form.errors}
                            />

                            <ClientCustomFieldsForm
                                values={
                                    form.data.custom_fields
                                    || {}
                                }
                                onChange={(values) =>
                                    form.setData(
                                        'custom_fields',
                                        values,
                                    )
                                }
                                errors={form.errors}
                            />
                        </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        label="Client Name"
                        error={
                            form.errors.name
                        }
                    >
                        <input
                            value={
                                form.data.name
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'name',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Phone"
                        error={
                            form.errors.phone
                        }
                    >
                        <input
                            value={
                                form.data.phone
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'phone',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="MAC Address"
                        error={
                            form.errors
                                .mac_address
                        }
                    >
                        <input
                            value={
                                form.data
                                    .mac_address
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'mac_address',
                                    formatMac(
                                        event.target
                                            .value,
                                    ),
                                )
                            }
                            placeholder="AA:BB:CC:DD:EE:FF"
                            className={`${inputClass} font-mono`}
                        />
                    </Field>

                    <Field
                        label="Package"
                        error={
                            form.errors
                                .package_id
                        }
                    >
                        <select
                            value={
                                form.data
                                    .package_id
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'package_id',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        >
                            <option value="">
                                Select Package
                            </option>

                            {packages.map(
                                (pkg) => (
                                    <option
                                        key={
                                            pkg.id
                                        }
                                        value={
                                            pkg.id
                                        }
                                    >
                                        {pkg.name}
                                        {pkg.zone_name
                                            ? ` · ${pkg.zone_name}`
                                            : ''}
                                        {' · QAR '}
                                        {money(
                                            pkg.price,
                                        )}
                                        {' · '}
                                        {
                                            pkg.validity_days
                                        }
                                        {' Days'}
                                    </option>
                                ),
                            )}
                        </select>
                    </Field>

                    <Field label="Connection Amount">
                        <input
                            readOnly
                            value={
                                selectedPackage
                                    ? `QAR ${money(
                                        selectedPackage.price,
                                    )}`
                                    : ''
                            }
                            className={`${inputClass} bg-slate-100 font-black`}
                        />
                    </Field>

                    <Field
                        label="Payment"
                        error={
                            form.errors
                                .connection_payment_status
                        }
                    >
                        <select
                            value={
                                form.data
                                    .connection_payment_status
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'connection_payment_status',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        >
                            {canReceivePayment && (
                                <option value="paid">
                                    Paid
                                </option>
                            )}

                            <option value="due">
                                Due
                            </option>
                        </select>
                    </Field>

                    {form.data
                        .connection_payment_status
                        === 'paid' && (
                        <Field
                            label="Payment Method"
                            error={
                                form.errors
                                    .connection_payment_method
                            }
                        >
                            <PaymentMethod
                                value={
                                    form.data
                                        .connection_payment_method
                                }
                                onChange={(
                                    value,
                                ) =>
                                    form.setData(
                                        'connection_payment_method',
                                        value,
                                    )
                                }
                            />
                        </Field>
                    )}
                </div>

                <div className="flex justify-end gap-3 border-t pt-4">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg bg-slate-200 px-5 py-3 font-bold text-slate-700"
                    >
                        Cancel
                    </button>

                    <button
                        disabled={
                            form.processing
                        }
                        className="rounded-lg bg-cyan-600 px-6 py-3 font-black text-white disabled:opacity-50"
                    >
                        {form.processing
                            ? 'Creating...'
                            : 'CREATE & ACTIVATE'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}



/*
 * RENEW_ALL_DEVICES_UI_V1
 */
function RenewAllDevicesModal({
    client,
    clients = [],
    canReceivePayment = false,
    onClose,
}) {
    const devices =
        useMemo(
            () =>
                clients
                    .filter(
                        (item) =>
                            Number(item.id)
                                === Number(
                                    client.id,
                                )
                            || Number(
                                item.parent_client_id,
                            ) === Number(
                                client.id,
                            ),
                    )
                    .sort(
                        (left, right) =>
                            Number(left.id)
                            - Number(right.id),
                    ),
            [
                client.id,
                clients,
            ],
        );

    const totalAmount =
        devices.reduce(
            (total, device) =>
                total
                + Number(
                    device.package?.price
                    ?? 0,
                ),
            0,
        );

    const existingDue =
        devices.reduce(
            (total, device) =>
                total
                + Number(
                    device.total_due
                    ?? 0,
                ),
            0,
        );

    const form = useForm({
        payment_status:
            canReceivePayment
                ? 'paid'
                : 'due',
        payment_method:
            'Cash',
        transaction_id:
            '',
        notes:
            '',
    });

    const submit = (event) => {
        event.preventDefault();

        if (
            existingDue > 0
            || devices.length < 2
        ) {
            return;
        }

        form.post(
            route(
                'clients.renew-all',
                client.id,
            ),
            {
                preserveScroll: true,
                onSuccess:
                    onClose,
            },
        );
    };

    return (
        <Modal
            title={`Renew All Devices · ${client.name}`}
            onClose={onClose}
        >
            <form
                onSubmit={submit}
                className="space-y-5"
            >
                <div className="rounded-xl border border-emerald-100 bg-emerald-50 p-4">
                    <div className="text-xs font-black uppercase tracking-wide text-emerald-700">
                        Account Renewal
                    </div>

                    <div className="mt-1 text-lg font-black text-slate-900">
                        {devices.length} Devices
                    </div>

                    <div className="mt-1 text-sm font-bold text-slate-600">
                        Total QAR {money(totalAmount)}
                    </div>
                </div>

                <div className="space-y-2">
                    {devices.map(
                        (device, index) => (
                            <div
                                key={device.id}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3"
                            >
                                <div>
                                    <div className="font-black text-slate-800">
                                        {index === 0
                                            ? 'Primary Device'
                                            : (
                                                device.device_label
                                                || `Device ${index + 1}`
                                            )}
                                    </div>

                                    <div className="mt-1 text-xs text-slate-500">
                                        {device.mac_address || '-'}
                                        {' · '}
                                        {device.package?.name || '-'}
                                        {' · Expiry '}
                                        {device.expiry_date || '-'}
                                    </div>
                                </div>

                                <div className="font-black text-slate-700">
                                    QAR {money(
                                        device.package?.price,
                                    )}
                                </div>
                            </div>
                        ),
                    )}
                </div>

                {existingDue > 0 && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">
                        Existing due QAR {money(existingDue)} must be paid first. Renew All Devices will not create another service period while old due exists.
                    </div>
                )}

                {form.errors.renew_all && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700">
                        {form.errors.renew_all}
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        label="Payment Status"
                        error={
                            form.errors.payment_status
                        }
                    >
                        <select
                            value={
                                form.data.payment_status
                            }
                            onChange={(event) =>
                                form.setData(
                                    'payment_status',
                                    event.target.value,
                                )
                            }
                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                        >
                            {canReceivePayment && (
                                <option value="paid">
                                    Paid - Receive Full Amount
                                </option>
                            )}

                            <option value="due">
                                Due - Renew Now, Pay Later
                            </option>
                        </select>
                    </Field>

                    {form.data.payment_status
                        === 'paid' && (
                        <Field
                            label="Payment Method"
                            error={
                                form.errors.payment_method
                            }
                        >
                            <select
                                value={
                                    form.data.payment_method
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'payment_method',
                                        event.target.value,
                                    )
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-3"
                            >
                                <option value="Cash">
                                    Cash
                                </option>
                                <option value="Bank Transfer">
                                    Bank Transfer
                                </option>
                                <option value="bKash">
                                    bKash
                                </option>
                                <option value="Nagad">
                                    Nagad
                                </option>
                                <option value="Rocket">
                                    Rocket
                                </option>
                                <option value="Upay">
                                    Upay
                                </option>
                                <option value="Ooredoo Money">
                                    Ooredoo Money
                                </option>
                                <option value="iPay">
                                    iPay
                                </option>
                                <option value="Stripe">
                                    Stripe
                                </option>
                                <option value="PayPal">
                                    PayPal
                                </option>
                                <option value="Manual Adjustment">
                                    Manual Adjustment
                                </option>
                            </select>
                        </Field>
                    )}

                    {form.data.payment_status
                        === 'paid' && (
                        <Field
                            label="Transaction ID"
                            error={
                                form.errors.transaction_id
                            }
                        >
                            <input
                                value={
                                    form.data.transaction_id
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'transaction_id',
                                        event.target.value,
                                    )
                                }
                                placeholder="Optional"
                                className="w-full rounded-xl border border-slate-300 px-4 py-3"
                            />
                        </Field>
                    )}

                    <Field
                        label="Note"
                        error={
                            form.errors.notes
                        }
                    >
                        <input
                            value={
                                form.data.notes
                            }
                            onChange={(event) =>
                                form.setData(
                                    'notes',
                                    event.target.value,
                                )
                            }
                            placeholder="Optional"
                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                        />
                    </Field>
                </div>

                <div className="rounded-xl bg-slate-50 p-4">
                    <div className="flex justify-between gap-4 text-sm">
                        <span className="font-bold text-slate-500">
                            Devices
                        </span>

                        <span className="font-black text-slate-900">
                            {devices.length}
                        </span>
                    </div>

                    <div className="mt-2 flex justify-between gap-4">
                        <span className="font-bold text-slate-500">
                            Total
                        </span>

                        <span className="text-xl font-black text-emerald-700">
                            QAR {money(totalAmount)}
                        </span>
                    </div>
                </div>

                <div className="flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-xl border border-slate-300 px-5 py-3 font-bold text-slate-600"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        disabled={
                            form.processing
                            || existingDue > 0
                            || devices.length < 2
                        }
                        className="rounded-xl bg-emerald-600 px-5 py-3 font-black text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {form.processing
                            ? 'RENEWING...'
                            : form.data.payment_status
                                === 'paid'
                              ? `PAY QAR ${money(totalAmount)} & RENEW ALL`
                              : `RENEW ALL · DUE QAR ${money(totalAmount)}`}
                    </button>
                </div>
            </form>
        </Modal>
    );
}


function AddDeviceModal({
    client,
    packages = [],
    ipRanges = [],
    canReceivePayment = false,
    onClose,
}) {
    const form = useForm({
        parent_client_id: client.id,
        device_label: '',
        package_id: '',
        name: client.name ?? '',
        mac_address: '',
        phone: client.phone ?? '',
        connection_payment_status:
            canReceivePayment
                ? 'paid'
                : 'due',
        connection_payment_method:
            'Cash',
        connection_transaction_id: '',
        return_to: 'mac-pos',
    });

    const selectedPackage =
        packages.find(
            (item) =>
                String(item.id)
                === String(
                    form.data.package_id,
                ),
        ) ?? null;

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route('clients.store'),
            {
                preserveScroll: true,
                onSuccess: onClose,
            },
        );
    };

    return (
        <Modal
            title={`Add Device · ${client.name}`}
            onClose={onClose}
        >
            <form
                onSubmit={submit}
                className="space-y-4"
            >
                <div className="rounded-xl border border-cyan-100 bg-cyan-50 p-4">
                    <div className="font-black text-slate-900">
                        {client.name}
                    </div>

                    <div className="mt-1 text-xs text-slate-500">
                        {client.client_code
                            || `#${client.id}`}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        label="Device Name"
                        error={
                            form.errors.device_label
                        }
                    >
                        <input
                            autoFocus
                            value={
                                form.data.device_label
                            }
                            onChange={(event) =>
                                form.setData(
                                    'device_label',
                                    event.target.value,
                                )
                            }
                            placeholder="Mobile 2 / Room Router"
                            className="w-full rounded-xl border border-slate-300 px-4 py-3 outline-none focus:border-cyan-500"
                        />
                    </Field>

                    <Field
                        label="MAC Address"
                        error={
                            form.errors.mac_address
                        }
                    >
                        <input
                            value={
                                form.data.mac_address
                            }
                            onChange={(event) =>
                                form.setData(
                                    'mac_address',
                                    formatMac(
                                        event.target.value,
                                    ),
                                )
                            }
                            placeholder="AA:BB:CC:DD:EE:FF"
                            className="w-full rounded-xl border border-slate-300 px-4 py-3 font-mono outline-none focus:border-cyan-500"
                        />
                    </Field>

                    <Field
                        label="Package"
                        error={
                            form.errors.package_id
                        }
                    >
                        <select
                            value={
                                form.data.package_id
                            }
                            onChange={(event) =>
                                form.setData(
                                    'package_id',
                                    event.target.value,
                                )
                            }
                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                        >
                            <option value="">
                                Select Package
                            </option>

                            {packages
                            .filter(
                                (pkg) =>
                                    Number(
                                        pkg.zone_id
                                    )
                                    === Number(
                                        client.zone_id
                                    )
                            )
                            .map(
                                (pkg) => (
                                    <option
                                        key={pkg.id}
                                        value={pkg.id}
                                    >
                                        {pkg.name}
                                        {' · QAR '}
                                        {money(pkg.price)}
                                    </option>
                                ),
                            )}
                        </select>
                    </Field>

                    <Field
                        label="Payment Status"
                        error={
                            form.errors
                                .connection_payment_status
                        }
                    >
                        <select
                            value={
                                form.data
                                    .connection_payment_status
                            }
                            onChange={(event) =>
                                form.setData(
                                    'connection_payment_status',
                                    event.target.value,
                                )
                            }
                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                        >
                            {canReceivePayment && (
                                <option value="paid">
                                    Paid
                                </option>
                            )}

                            <option value="due">
                                Due
                            </option>
                        </select>
                    </Field>

                    {form.data
                        .connection_payment_status
                        === 'paid' && (
                        <Field
                            label="Payment Method"
                            error={
                                form.errors
                                    .connection_payment_method
                            }
                        >
                            <select
                                value={
                                    form.data
                                        .connection_payment_method
                                }
                                onChange={(event) =>
                                    form.setData(
                                        'connection_payment_method',
                                        event.target.value,
                                    )
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-3"
                            >
                                <option value="Cash">
                                    Cash
                                </option>

                                <option value="Bank Transfer">
                                    Bank Transfer
                                </option>

                                <option value="Ooredoo Money">
                                    Ooredoo Money
                                </option>

                                <option value="Manual Adjustment">
                                    Manual Adjustment
                                </option>
                            </select>
                        </Field>
                    )}
                </div>

                {selectedPackage && (
                    <div className="rounded-xl bg-slate-50 p-4 text-sm font-bold text-slate-600">
                        {selectedPackage.name}
                        {' · QAR '}
                        {money(
                            selectedPackage.price,
                        )}
                        {' · '}
                        {selectedPackage.validity_days}
                        {' days'}
                    </div>
                )}

                {form.errors.parent_client_id && (
                    <div className="rounded-xl bg-red-50 p-3 text-sm font-bold text-red-600">
                        {form.errors.parent_client_id}
                    </div>
                )}

                <div className="flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-xl border border-slate-300 px-5 py-3 font-bold"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        disabled={form.processing}
                        className="rounded-xl bg-cyan-600 px-5 py-3 font-black text-white disabled:opacity-50"
                    >
                        {form.processing
                            ? 'ADDING...'
                            : 'ADD DEVICE & ACTIVATE'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}


function RechargeModal({
    client,
    packages,
    canReceivePayment,
    onClose,
}) {
    const due =
        Number(
            client.total_due
            ?? 0,
        );

    const [mode, setMode] =
        useState(
            canReceivePayment
                ? 'paid'
                : 'due',
        );

    const form = useForm({
        package_id:
            String(
                client.package_id
                ?? '',
            ),
        received_amount:
            canReceivePayment
                ? (
                    due > 0
                        ? due
                        : Number(
                            client.package
                                ?.price
                            ?? 0,
                        )
                ).toFixed(2)
                : '0.00',
        payment_method: 'Cash',
        transaction_id: '',
        notes: '',
    });

    const selectedPackage =
        packages.find(
            (pkg) =>
                Number(pkg.id)
                === Number(
                    form.data
                        .package_id,
                ),
        )
        ?? client.package;

    const amount =
        due > 0
            ? due
            : Number(
                selectedPackage?.price
                ?? 0,
            );

    const setPaymentMode = (
        value,
    ) => {
        setMode(value);

        if (value === 'due') {
            form.setData(
                'received_amount',
                '0.00',
            );
        }

        if (value === 'paid') {
            form.setData(
                'received_amount',
                amount.toFixed(2),
            );
        }
    };

    const changePackage = (
        value,
    ) => {
        form.setData(
            'package_id',
            value,
        );

        const pkg =
            packages.find(
                (item) =>
                    Number(item.id)
                    === Number(value),
            );

        if (
            due <= 0
            && mode === 'paid'
            && pkg
        ) {
            form.setData(
                'received_amount',
                Number(
                    pkg.price,
                ).toFixed(2),
            );
        }
    };

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route(
                'clients.renew',
                client.id,
            ),
            {
                preserveScroll: true,
                onSuccess:
                    onClose,
            },
        );
    };

    return (
        <Modal
            title={
                due > 0
                    ? `Receive Due · ${client.name}`
                    : `Recharge · ${client.name}`
            }
            onClose={onClose}
        >
            <form
                onSubmit={submit}
                className="space-y-4"
            >
                <div className="grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2">
                    <MiniInfo
                        label="MAC"
                        value={
                            client.mac_address
                            || '-'
                        }
                    />

                    <MiniInfo
                        label="IP"
                        value={
                            client.ip_address
                            || '-'
                        }
                    />

                    <MiniInfo
                        label="Current Package"
                        value={
                            client.package
                                ?.name
                            || '-'
                        }
                    />

                    <MiniInfo
                        label="Current Due"
                        value={`QAR ${money(
                            due,
                        )}`}
                    />
                </div>

                <Field
                    label="Package"
                    error={
                        form.errors
                            .package_id
                    }
                >
                    <select
                        disabled={
                            due > 0
                        }
                        value={
                            form.data
                                .package_id
                        }
                        onChange={(
                            event,
                        ) =>
                            changePackage(
                                event.target
                                    .value,
                            )
                        }
                        className={`${inputClass} disabled:bg-slate-100`}
                    >
                        {packages
                            .filter(
                                (pkg) =>
                                    Number(
                                        pkg.zone_id
                                    )
                                    === Number(
                                        client.zone_id
                                    )
                            )
                            .map(
                            (pkg) => (
                                <option
                                    key={
                                        pkg.id
                                    }
                                    value={
                                        pkg.id
                                    }
                                >
                                    {pkg.name}
                                    {' · QAR '}
                                    {money(
                                        pkg.price,
                                    )}
                                    {' · '}
                                    {
                                        pkg.validity_days
                                    }
                                    {' Days'}
                                </option>
                            ),
                        )}
                    </select>
                </Field>

                {due > 0 && (
                    <div className="rounded-lg bg-amber-50 p-3 text-sm font-bold text-amber-700">
                        Previous due must be cleared before changing package or creating another service period.
                    </div>
                )}

                <div>
                    <div className="mb-2 text-xs font-bold uppercase text-slate-500">
                        Payment
                    </div>

                    <div className="grid grid-cols-3 gap-2">
                        {[
                            [
                                'paid',
                                'Paid',
                            ],
                            [
                                'partial',
                                'Partial',
                            ],
                            [
                                'due',
                                'Due',
                            ],
                        ].map(
                            ([
                                value,
                                label,
                            ]) => {
                                const disabled =
                                    (
                                        !canReceivePayment
                                        && value
                                            !== 'due'
                                    )
                                    || (
                                        due > 0
                                        && value
                                            === 'due'
                                    );

                                return (
                                    <button
                                        key={
                                            value
                                        }
                                        type="button"
                                        disabled={
                                            disabled
                                        }
                                        onClick={() =>
                                            setPaymentMode(
                                                value,
                                            )
                                        }
                                        className={`rounded-lg border px-3 py-2 font-bold ${
                                            mode
                                            === value
                                                ? 'border-cyan-600 bg-cyan-600 text-white'
                                                : 'bg-white text-slate-600'
                                        } disabled:opacity-30`}
                                    >
                                        {
                                            label
                                        }
                                    </button>
                                );
                            },
                        )}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        label="Received Amount"
                        error={
                            form.errors
                                .received_amount
                        }
                    >
                        <input
                            type="number"
                            min="0"
                            step="0.01"
                            disabled={
                                mode
                                === 'due'
                            }
                            value={
                                form.data
                                    .received_amount
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'received_amount',
                                    event.target
                                        .value,
                                )
                            }
                            className={`${inputClass} disabled:bg-slate-100`}
                        />
                    </Field>

                    <Field
                        label="Payment Method"
                        error={
                            form.errors
                                .payment_method
                        }
                    >
                        <PaymentMethod
                            disabled={
                                mode
                                === 'due'
                            }
                            value={
                                form.data
                                    .payment_method
                            }
                            onChange={(
                                value,
                            ) =>
                                form.setData(
                                    'payment_method',
                                    value,
                                )
                            }
                        />
                    </Field>
                </div>

                {form.errors.renewal && (
                    <div className="rounded-lg bg-red-50 p-3 text-sm font-bold text-red-700">
                        {
                            form.errors
                                .renewal
                        }
                    </div>
                )}

                <div className="flex justify-end gap-3 border-t pt-4">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg bg-slate-200 px-5 py-3 font-bold text-slate-700"
                    >
                        Cancel
                    </button>

                    <button
                        disabled={
                            form.processing
                        }
                        className="rounded-lg bg-emerald-600 px-6 py-3 font-black text-white disabled:opacity-50"
                    >
                        {form.processing
                            ? 'Processing...'
                            : due > 0
                              ? 'RECEIVE PAYMENT'
                              : 'RECHARGE & ACTIVATE'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

function EditClientModal({
    client,
    packages,
    ipRanges,
    onClose,
}) {
    const form = useForm({
        custom_fields: {},
        identity_type: client.identity_type ?? '',
        identity_number: client.identity_number ?? '',
        identity_barcode: client.identity_barcode ?? '',
        nationality: client.nationality ?? '',
        date_of_birth: client.date_of_birth ?? '',
        gender: client.gender ?? '',
        document_expiry_date: client.document_expiry_date ?? '',
        qatar_id_number: client.qatar_id_number ?? '',
        qatar_id_expiry_date: client.qatar_id_expiry_date ?? '',
        occupation: client.occupation ?? '',
        passport_number: client.passport_number ?? '',
        passport_expiry_date: client.passport_expiry_date ?? '',
        document_serial_number: client.document_serial_number ?? '',
        residency_type: client.residency_type ?? '',
        employer: client.employer ?? '',
        place_of_birth: client.place_of_birth ?? '',
        ip_range_id:
            String(
                client.ip_range_id
                ?? '',
            ),
        package_id:
            String(
                client.package_id
                ?? '',
            ),
        name:
            client.name ?? '',
        mac_address:
            client.mac_address ?? '',
        phone:
            client.phone ?? '',
        email:
            client.email ?? '',
        address:
            client.address ?? '',
        return_to: 'mac-pos',
    });

    const submit = (event) => {
        event.preventDefault();

        form.put(
            route(
                'clients.update',
                client.id,
            ),
            {
                preserveScroll: true,
                onSuccess:
                    onClose,
            },
        );
    };

    return (
        <Modal
            title={`Edit · ${client.name}`}
            onClose={onClose}
        >
            <form
                onSubmit={submit}
                className="space-y-4"
            >
                        <div className="mb-5 space-y-4">
                            <ClientIdentityFields
                                data={form.data}
                                setData={form.setData}
                                errors={form.errors}
                            />

                            <ClientCustomFieldsForm
                                clientId={client.id}
                                values={
                                    form.data.custom_fields
                                    || {}
                                }
                                onChange={(values) =>
                                    form.setData(
                                        'custom_fields',
                                        values,
                                    )
                                }
                                errors={form.errors}
                            />
                        </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Field
                        label="Client Name"
                        error={
                            form.errors.name
                        }
                    >
                        <input
                            value={
                                form.data.name
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'name',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="Phone"
                        error={
                            form.errors.phone
                        }
                    >
                        <input
                            value={
                                form.data.phone
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'phone',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>

                    <Field
                        label="MAC Address"
                        error={
                            form.errors
                                .mac_address
                        }
                    >
                        <input
                            value={
                                form.data
                                    .mac_address
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'mac_address',
                                    formatMac(
                                        event.target
                                            .value,
                                    ),
                                )
                            }
                            className={`${inputClass} font-mono`}
                        />
                    </Field>

                    <Field
                        label="Package"
                        error={
                            form.errors
                                .package_id
                        }
                    >
                        <select
                            value={
                                form.data
                                    .package_id
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'package_id',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        >
                            {packages
                            .filter(
                                (pkg) =>
                                    Number(
                                        pkg.zone_id
                                    )
                                    === Number(
                                        client.zone_id
                                    )
                            )
                            .map(
                                (pkg) => (
                                    <option
                                        key={
                                            pkg.id
                                        }
                                        value={
                                            pkg.id
                                        }
                                    >
                                        {
                                            pkg.name
                                        }
                                    </option>
                                ),
                            )}
                        </select>
                    </Field>

                    <Field label="IP Pool">
                        <select
                            disabled
                            value={
                                form.data
                                    .ip_range_id
                            }
                            className={`${inputClass} bg-slate-100`}
                        >
                            {ipRanges.map(
                                (range) => (
                                    <option
                                        key={
                                            range.id
                                        }
                                        value={
                                            range.id
                                        }
                                    >
                                        {
                                            range.name
                                        }
                                    </option>
                                ),
                            )}
                        </select>
                    </Field>

                    <Field
                        label="Email"
                        error={
                            form.errors.email
                        }
                    >
                        <input
                            type="email"
                            value={
                                form.data.email
                            }
                            onChange={(
                                event,
                            ) =>
                                form.setData(
                                    'email',
                                    event.target
                                        .value,
                                )
                            }
                            className={inputClass}
                        />
                    </Field>
                </div>

                {form.errors
                    .mac_address && (
                    <div className="rounded-lg bg-red-50 p-3 text-sm font-bold text-red-700">
                        {
                            form.errors
                                .mac_address
                        }
                    </div>
                )}

                <div className="flex justify-end gap-3 border-t pt-4">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-lg bg-slate-200 px-5 py-3 font-bold text-slate-700"
                    >
                        Cancel
                    </button>

                    <button
                        disabled={
                            form.processing
                        }
                        className="rounded-lg bg-blue-600 px-6 py-3 font-black text-white disabled:opacity-50"
                    >
                        {form.processing
                            ? 'Saving...'
                            : 'SAVE CLIENT'}
                    </button>
                </div>
            </form>
        </Modal>
    );
}

const inputClass =
    'w-full rounded-lg border border-slate-300 px-4 py-3 focus:border-cyan-500 focus:ring-cyan-500';

function PaymentMethod({
    value,
    onChange,
    disabled = false,
}) {
    return (
        <select
            disabled={disabled}
            value={value}
            onChange={(event) =>
                onChange(
                    event.target.value,
                )
            }
            className={`${inputClass} disabled:bg-slate-100`}
        >
            <option value="Cash">
                Cash
            </option>

            <option value="Bank Transfer">
                Bank Transfer
            </option>

            <option value="Ooredoo Money">
                Ooredoo Money
            </option>

            <option value="bKash">
                bKash
            </option>

            <option value="Nagad">
                Nagad
            </option>

            <option value="Rocket">
                Rocket
            </option>

            <option value="Upay">
                Upay
            </option>

            <option value="iPay">
                iPay
            </option>

            <option value="Manual Adjustment">
                Manual Adjustment
            </option>
        </select>
    );
}

function Modal({
    title,
    children,
    onClose,
}) {
    const scrollRef =
        useRef(null);

    useEffect(() => {
        const frame =
            window.requestAnimationFrame(
                () => {
                    if (
                        scrollRef.current
                    ) {
                        scrollRef.current.scrollTop =
                            0;
                    }
                },
            );

        return () =>
            window.cancelAnimationFrame(
                frame,
            );
    }, []);

    return (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/70 p-4">
            <div
                ref={scrollRef}
                className="max-h-[94vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white shadow-2xl"
            >
                <div className="sticky top-0 z-10 flex items-center justify-between border-b bg-white px-5 py-4">
                    <h2 className="text-xl font-black text-slate-900">
                        {title}
                    </h2>

                    <button
                        type="button"
                        onClick={onClose}
                        className="flex h-9 w-9 items-center justify-center rounded-lg bg-slate-100 text-xl font-black text-slate-500 hover:bg-slate-200"
                    >
                        ×
                    </button>
                </div>

                <div className="p-5">
                    {children}
                </div>
            </div>
        </div>
    );
}

function Field({
    label,
    error,
    children,
}) {
    return (
        <label className="block">
            <span className="text-xs font-bold uppercase tracking-wide text-slate-500">
                {label}
            </span>

            <div className="mt-2">
                {children}
            </div>

            {error && (
                <div className="mt-1 text-xs font-bold text-red-600">
                    {error}
                </div>
            )}
        </label>
    );
}

function MiniInfo({
    label,
    value,
}) {
    return (
        <div>
            <div className="text-[10px] font-bold uppercase text-slate-400">
                {label}
            </div>

            <div className="mt-1 break-all text-sm font-black text-slate-700">
                {value}
            </div>
        </div>
    );
}

function StatCard({
    label,
    value,
}) {
    return (
        <div className="rounded-xl border bg-white p-4 shadow-sm">
            <div className="text-xs font-bold uppercase text-slate-400">
                {label}
            </div>

            <div className="mt-2 text-2xl font-black text-slate-800">
                {value ?? 0}
            </div>
        </div>
    );
}

function Th({ children }) {
    return (
        <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-black uppercase text-slate-500">
            {children}
        </th>
    );
}

function Td({ children }) {
    return (
        <td className="whitespace-nowrap px-4 py-4 align-top text-sm">
            {children}
        </td>
    );
}
