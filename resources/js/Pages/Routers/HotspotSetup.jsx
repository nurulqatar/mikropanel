import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router as inertiaRouter,
} from '@inertiajs/react';
import {
    useMemo,
    useState,
} from 'react';

/*
 * HOTSPOT_ROUTER_SETUP_WIZARD_PHASE1_V1
 *
 * Phase 1:
 * - API connectivity
 * - Router identity
 * - live interface discovery
 * - bridge/IP/DHCP-client caution indicators
 * - HOTSPOT_WAN_AUTO_HIDE_V1
 * - automatic WAN/Internet port exclusion
 * - local bridge-port selection only
 *
 * RouterOS configuration is not changed here.
 */
export default function HotspotSetup({
    router,
    discovery = {},
    wizardStep = 1,
    activeBridge = null,
    flash = {},
}) {
    const [selectedPorts, setSelectedPorts] =
        useState([]);

    /*
     * HOTSPOT_BRIDGE_SETUP_PHASE2_V2
     */
    const existingBridges =
        discovery?.bridges
        ?? [];

    const [bridgeMode, setBridgeMode] =
        useState(
            existingBridges.length
                ? 'existing'
                : 'new',
        );

    const [
        existingBridgeName,
        setExistingBridgeName,
    ] = useState(
        existingBridges[0]?.name
        ?? '',
    );

    const [
        newBridgeName,
        setNewBridgeName,
    ] = useState(
        `mp-hotspot-${router.id}`,
    );

    const [
        applyingBridge,
        setApplyingBridge,
    ] = useState(false);

    const ethernetInterfaces =
        useMemo(
            () =>
                discovery
                    ?.ethernet_interfaces
                    ?? [],
            [
                discovery
                    ?.ethernet_interfaces,
            ],
        );

    const togglePort = (name) => {
        setSelectedPorts(
            (current) =>
                current.includes(name)
                    ? current.filter(
                          (item) =>
                              item !== name,
                      )
                    : [
                          ...current,
                          name,
                      ],
        );
    };

    const riskySelected =
        ethernetInterfaces.filter(
            (item) =>
                selectedPorts.includes(
                    item.name,
                )
                && item.caution,
        );

    const applyBridge = () => {
        if (
            selectedPorts.length === 0
        ) {
            return;
        }

        const bridgeName =
            bridgeMode === 'existing'
                ? existingBridgeName
                : newBridgeName.trim();

        if (!bridgeName) {
            return;
        }

        inertiaRouter.post(
            route(
                'routers.hotspot-setup.bridge',
                router.id,
            ),
            {
                mode:
                    bridgeMode,

                bridge_name:
                    bridgeName,

                ports:
                    selectedPorts,
            },
            {
                preserveScroll:
                    true,

                onStart: () =>
                    setApplyingBridge(
                        true,
                    ),

                onFinish: () =>
                    setApplyingBridge(
                        false,
                    ),
            },
        );
    };

    const steps = [
        'Ports',
        'Bridge',
        'Gateway',
        'DHCP',
        'Hotspot',
        'Login',
        'Internet',
        'Portal',
        'Verify',
    ];

    return (
        <AppLayout title="Hotspot Setup Wizard">
            <Head title="Hotspot Setup Wizard" />

            <div className="mx-auto max-w-6xl space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <Link
                            href={route(
                                'routers.index',
                            )}
                            className="text-sm font-semibold text-cyan-700"
                        >
                            ← MikroTik Routers
                        </Link>

                        <h1 className="mt-2 text-3xl font-black text-slate-900">
                            Hotspot Setup Wizard
                        </h1>

                        <p className="mt-1 text-slate-500">
                            {router.name}
                            {' • '}
                            {router.zone?.name}
                            {' • '}
                            {router.host}:
                            {router.api_port}
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() =>
                            inertiaRouter.reload({
                                only: [
                                    'discovery',
                                ],
                            })
                        }
                        className="rounded-xl border border-slate-300 bg-white px-4 py-2 font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Refresh Interfaces
                    </button>
                </div>

                <div className="grid gap-2 sm:grid-cols-3 lg:grid-cols-9">
                    {steps.map(
                        (step, index) => (
                            <div
                                key={step}
                                className={`rounded-xl border px-3 py-3 text-center text-xs font-bold ${
                                    index + 1 ===
                                    Number(wizardStep)
                                        ? 'border-cyan-300 bg-cyan-50 text-cyan-800'
                                        : index + 1 <
                                            Number(wizardStep)
                                          ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                          : 'border-slate-200 bg-white text-slate-400'
                                }`}
                            >
                                <div>
                                    Step{' '}
                                    {index + 1}
                                </div>

                                <div className="mt-1">
                                    {step}
                                </div>
                            </div>
                        ),
                    )}
                </div>

                {flash?.success && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 font-semibold text-emerald-800">
                        {flash.success}
                    </div>
                )}

                {flash?.error && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 font-semibold text-red-800">
                        {flash.error}
                    </div>
                )}

                <section
                    className={`rounded-2xl border p-5 ${
                        discovery.success
                            ? 'border-emerald-200 bg-emerald-50'
                            : 'border-red-200 bg-red-50'
                    }`}
                >
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div
                                className={`text-lg font-black ${
                                    discovery.success
                                        ? 'text-emerald-800'
                                        : 'text-red-800'
                                }`}
                            >
                                {discovery.success
                                    ? '● RouterOS Connected'
                                    : '● RouterOS Connection Failed'}
                            </div>

                            <p className="mt-1 text-sm text-slate-700">
                                {discovery.message ||
                                    'No discovery result.'}
                            </p>
                        </div>

                        <div className="text-right text-xs text-slate-500">
                            <div>
                                Read-only discovery
                            </div>

                            <div className="mt-1">
                                Queries:{' '}
                                {discovery.routeros_query_count ??
                                    0}
                            </div>
                        </div>
                    </div>
                </section>

                {discovery.success && (
                    <>
                        <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                            <InfoCard
                                label="Identity"
                                value={
                                    discovery.identity ||
                                    router.name
                                }
                            />

                            <InfoCard
                                label="RouterOS"
                                value={
                                    discovery.version ||
                                    '-'
                                }
                            />

                            <InfoCard
                                label="Board"
                                value={
                                    discovery.board_name ||
                                    '-'
                                }
                            />

                            <InfoCard
                                label="Architecture"
                                value={
                                    discovery.architecture ||
                                    '-'
                                }
                            />

                            <InfoCard
                                label="Uptime"
                                value={
                                    discovery.uptime ||
                                    '-'
                                }
                            />
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-200 p-6">
                                <h2 className="text-xl font-black text-slate-900">
                                    Step 1 — Select Hotspot Bridge Ports
                                </h2>

                                <p className="mt-2 max-w-4xl text-sm text-slate-500">
                                    These are live LAN-capable physical Ethernet/SFP interfaces from this MikroTik.
                                    Internet/WAN interfaces are detected automatically from RouterOS and excluded from this list.
                                    Selecting a port does not change RouterOS yet.
                                    Remaining ports that already carry another bridge or local configuration are marked with a caution warning.
                                </p>
                            </div>

                            {(discovery.hidden_wan_count ??
                                0) > 0 && (
                                <div className="mx-6 mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                                    Internet/WAN interface protection active —{' '}
                                    {discovery.hidden_wan_count}{' '}
                                    WAN port
                                    {discovery.hidden_wan_count ===
                                    1
                                        ? ''
                                        : 's'}{' '}
                                    automatically excluded.
                                </div>
                            )}

                            {ethernetInterfaces.length ===
                            0 ? (
                                <div className="p-8 text-center text-slate-500">
                                    No physical Ethernet interfaces were returned by RouterOS.
                                </div>
                            ) : (
                                <div className="grid gap-4 p-6 md:grid-cols-2 xl:grid-cols-3">
                                    {ethernetInterfaces.map(
                                        (item) => {
                                            const checked =
                                                selectedPorts.includes(
                                                    item.name,
                                                );

                                            return (
                                                <label
                                                    key={
                                                        item.name
                                                    }
                                                    className={`cursor-pointer rounded-2xl border p-5 transition ${
                                                        checked
                                                            ? 'border-cyan-400 bg-cyan-50'
                                                            : item.caution
                                                              ? 'border-amber-300 bg-amber-50'
                                                              : 'border-slate-200 bg-white hover:border-cyan-200'
                                                    }`}
                                                >
                                                    <div className="flex items-start gap-3">
                                                        <input
                                                            type="checkbox"
                                                            checked={
                                                                checked
                                                            }
                                                            onChange={() =>
                                                                togglePort(
                                                                    item.name,
                                                                )
                                                            }
                                                            className="mt-1 h-5 w-5 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500"
                                                        />

                                                        <div className="min-w-0 flex-1">
                                                            <div className="flex flex-wrap items-center gap-2">
                                                                <span className="font-mono text-lg font-black text-slate-900">
                                                                    {
                                                                        item.name
                                                                    }
                                                                </span>

                                                                <StatusBadge
                                                                    running={
                                                                        item.running
                                                                    }
                                                                    disabled={
                                                                        item.disabled
                                                                    }
                                                                />
                                                            </div>

                                                            {item.default_name &&
                                                                item.default_name !==
                                                                    item.name && (
                                                                    <p className="mt-1 text-xs text-slate-400">
                                                                        Hardware:{' '}
                                                                        {
                                                                            item.default_name
                                                                        }
                                                                    </p>
                                                                )}

                                                            <div className="mt-3 space-y-1 text-xs text-slate-600">
                                                                <Line
                                                                    label="Type"
                                                                    value={
                                                                        item.type ||
                                                                        '-'
                                                                    }
                                                                />

                                                                <Line
                                                                    label="MAC"
                                                                    value={
                                                                        item.mac_address ||
                                                                        '-'
                                                                    }
                                                                />

                                                                <Line
                                                                    label="Bridge"
                                                                    value={
                                                                        item.existing_bridge ||
                                                                        'Not in bridge'
                                                                    }
                                                                />

                                                                <Line
                                                                    label="DHCP Client"
                                                                    value={
                                                                        item.dhcp_client
                                                                            ? item
                                                                                  .dhcp_client
                                                                                  .status ||
                                                                              'Configured'
                                                                            : 'None'
                                                                    }
                                                                />

                                                                <Line
                                                                    label="IP"
                                                                    value={
                                                                        item.ip_addresses
                                                                            ?.map(
                                                                                (
                                                                                    ip,
                                                                                ) =>
                                                                                    ip.address,
                                                                            )
                                                                            .filter(
                                                                                Boolean,
                                                                            )
                                                                            .join(
                                                                                ', ',
                                                                            ) ||
                                                                        'None'
                                                                    }
                                                                />
                                                            </div>

                                                            {item.caution && (
                                                                <div className="mt-3 rounded-lg border border-amber-300 bg-amber-100 px-3 py-2 text-xs font-bold text-amber-900">
                                                                    ⚠ In use. Review before adding this port to the Hotspot bridge.
                                                                </div>
                                                            )}
                                                        </div>
                                                    </div>
                                                </label>
                                            );
                                        },
                                    )}
                                </div>
                            )}
                        </section>

                        {Number(wizardStep) < 3 ? (
                            <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                                <div>
                                    <h3 className="font-black text-slate-900">
                                        Selected Ports:{' '}
                                        {selectedPorts.length}
                                    </h3>

                                    <p className="mt-1 font-mono text-sm text-slate-600">
                                        {selectedPorts.length
                                            ? selectedPorts.join(
                                                  ', ',
                                              )
                                            : 'No port selected'}
                                    </p>
                                </div>

                                {selectedPorts.length >
                                    0 && (
                                    <div className="mt-6 border-t border-slate-200 pt-6">
                                        <h3 className="text-lg font-black text-slate-900">
                                            Step 2 — Bridge
                                        </h3>

                                        <p className="mt-1 text-sm text-slate-500">
                                            Use an existing RouterOS bridge or create a new dedicated Hotspot bridge.
                                        </p>

                                        <div className="mt-5 grid gap-4 md:grid-cols-2">
                                            <label
                                                className={`rounded-xl border p-4 ${
                                                    bridgeMode ===
                                                    'existing'
                                                        ? 'border-cyan-400 bg-cyan-50'
                                                        : 'border-slate-200'
                                                } ${
                                                    existingBridges.length ===
                                                    0
                                                        ? 'opacity-50'
                                                        : ''
                                                }`}
                                            >
                                                <div className="flex items-center gap-2">
                                                    <input
                                                        type="radio"
                                                        name="bridge-mode"
                                                        checked={
                                                            bridgeMode ===
                                                            'existing'
                                                        }
                                                        disabled={
                                                            existingBridges.length ===
                                                            0
                                                        }
                                                        onChange={() =>
                                                            setBridgeMode(
                                                                'existing',
                                                            )
                                                        }
                                                    />

                                                    <span className="font-bold text-slate-800">
                                                        Use Existing Bridge
                                                    </span>
                                                </div>

                                                <select
                                                    className="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono"
                                                    value={
                                                        existingBridgeName
                                                    }
                                                    disabled={
                                                        bridgeMode !==
                                                            'existing' ||
                                                        existingBridges.length ===
                                                            0
                                                    }
                                                    onChange={(
                                                        event,
                                                    ) =>
                                                        setExistingBridgeName(
                                                            event
                                                                .target
                                                                .value,
                                                        )
                                                    }
                                                >
                                                    {existingBridges.map(
                                                        (
                                                            bridge,
                                                        ) => (
                                                            <option
                                                                key={
                                                                    bridge.name
                                                                }
                                                                value={
                                                                    bridge.name
                                                                }
                                                            >
                                                                {
                                                                    bridge.name
                                                                }
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                            </label>

                                            <label
                                                className={`rounded-xl border p-4 ${
                                                    bridgeMode ===
                                                    'new'
                                                        ? 'border-cyan-400 bg-cyan-50'
                                                        : 'border-slate-200'
                                                }`}
                                            >
                                                <div className="flex items-center gap-2">
                                                    <input
                                                        type="radio"
                                                        name="bridge-mode"
                                                        checked={
                                                            bridgeMode ===
                                                            'new'
                                                        }
                                                        onChange={() =>
                                                            setBridgeMode(
                                                                'new',
                                                            )
                                                        }
                                                    />

                                                    <span className="font-bold text-slate-800">
                                                        Create New Bridge
                                                    </span>
                                                </div>

                                                <input
                                                    className="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono"
                                                    value={
                                                        newBridgeName
                                                    }
                                                    disabled={
                                                        bridgeMode !==
                                                        'new'
                                                    }
                                                    onChange={(
                                                        event,
                                                    ) =>
                                                        setNewBridgeName(
                                                            event
                                                                .target
                                                                .value,
                                                        )
                                                    }
                                                />
                                            </label>
                                        </div>

                                        {riskySelected.length >
                                            0 && (
                                            <div className="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm font-semibold text-amber-900">
                                                Existing RouterOS configuration detected on:{' '}
                                                {riskySelected
                                                    .map(
                                                        (
                                                            item,
                                                        ) =>
                                                            item.name,
                                                    )
                                                    .join(
                                                        ', ',
                                                    )}
                                                . MikroPanel will not silently move a port from another bridge.
                                            </div>
                                        )}

                                        <div className="mt-6 flex flex-wrap items-center justify-between gap-4">
                                            <p className="text-xs text-slate-500">
                                                WAN detection is refreshed again on the server before any RouterOS bridge write.
                                            </p>

                                            <button
                                                type="button"
                                                onClick={
                                                    applyBridge
                                                }
                                                disabled={
                                                    applyingBridge ||
                                                    selectedPorts.length ===
                                                        0 ||
                                                    (bridgeMode ===
                                                        'existing' &&
                                                        !existingBridgeName) ||
                                                    (bridgeMode ===
                                                        'new' &&
                                                        !newBridgeName.trim())
                                                }
                                                className="rounded-xl bg-cyan-600 px-5 py-3 font-bold text-white hover:bg-cyan-700 disabled:opacity-50"
                                            >
                                                {applyingBridge
                                                    ? 'Applying Bridge...'
                                                    : 'Apply Bridge & Continue'}
                                            </button>
                                        </div>
                                    </div>
                                )}

                                <p className="mt-4 text-xs text-slate-400">
                                    Existing unrelated RouterOS configuration is never removed automatically.
                                </p>
                            </section>
                        ) : (
                            <section className="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm">
                                <div className="text-lg font-black text-emerald-800">
                                    ✓ Hotspot Bridge Ready
                                </div>

                                <p className="mt-2 text-slate-700">
                                    Bridge:{' '}
                                    <span className="font-mono font-bold">
                                        {activeBridge ||
                                            'Configured'}
                                    </span>
                                </p>

                                <p className="mt-3 text-sm text-slate-600">
                                    Next: Gateway IP, subnet and Hotspot address pool.
                                </p>

                                <button
                                    type="button"
                                    disabled
                                    className="mt-5 rounded-xl bg-slate-300 px-5 py-3 font-bold text-white"
                                >
                                    Step 3 — Gateway & Subnet
                                </button>
                            </section>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}

function InfoCard({
    label,
    value,
}) {
    return (
        <div className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p className="text-xs font-bold uppercase tracking-wide text-slate-400">
                {label}
            </p>

            <p className="mt-2 break-words font-black text-slate-800">
                {value}
            </p>
        </div>
    );
}

function StatusBadge({
    running,
    disabled,
}) {
    if (disabled) {
        return (
            <span className="rounded-full bg-slate-200 px-2 py-1 text-[10px] font-bold text-slate-700">
                DISABLED
            </span>
        );
    }

    return (
        <span
            className={`rounded-full px-2 py-1 text-[10px] font-bold ${
                running
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-amber-100 text-amber-700'
            }`}
        >
            {running
                ? 'RUNNING'
                : 'NO LINK'}
        </span>
    );
}

function Line({
    label,
    value,
}) {
    return (
        <div className="flex gap-2">
            <span className="font-semibold text-slate-400">
                {label}:
            </span>

            <span className="break-all font-medium text-slate-700">
                {value}
            </span>
        </div>
    );
}
