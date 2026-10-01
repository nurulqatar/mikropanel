import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
} from '@inertiajs/react';

export default function HotspotHealthIndex({
    routers = [],
}) {
    return (
        <AppLayout title="Router Health">
            <Head title="Router Health" />

            <div className="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
                <div>
                    <div className="text-sm font-black uppercase tracking-wide text-cyan-600">
                        Hotspot Monitoring
                    </div>

                    <h1 className="mt-1 text-3xl font-black text-slate-900">
                        Router Health
                    </h1>

                    <p className="mt-2 text-sm text-slate-500">
                        Dedicated Hotspot router monitoring, realtime bandwidth, DHCP/IP Bind checks and repair.
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {routers.map(
                        (router) => (
                            <div
                                key={router.id}
                                className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div>
                                        <h2 className="text-lg font-black text-slate-900">
                                            {router.name}
                                        </h2>

                                        <div className="mt-1 text-sm text-slate-500">
                                            {router.zone?.name}
                                        </div>

                                        <div className="mt-1 font-mono text-xs text-slate-400">
                                            {router.host}
                                        </div>
                                    </div>

                                    <span
                                        className={`rounded-full px-3 py-1 text-xs font-black ${
                                            router.connected
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : 'bg-slate-100 text-slate-600'
                                        }`}
                                    >
                                        {router.connected
                                            ? 'ONLINE'
                                            : 'CHECK'}
                                    </span>
                                </div>

                                <div className="mt-5">
                                    <Link
                                        href={route(
                                            'hotspot.router-health.show',
                                            router.id,
                                        )}
                                        className="block w-full rounded-xl bg-slate-900 px-4 py-3 text-center font-black text-white hover:bg-slate-800"
                                    >
                                        Open Health
                                    </Link>
                                </div>
                            </div>
                        ),
                    )}

                    {routers.length ===
                        0 && (
                        <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-slate-500 md:col-span-2 xl:col-span-3">
                            No Hotspot router is available for this account.
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
