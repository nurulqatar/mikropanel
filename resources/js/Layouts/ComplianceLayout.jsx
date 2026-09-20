import {
    Link,
    router,
    usePage,
} from '@inertiajs/react';

export default function ComplianceLayout({
    title = 'Network Compliance',
    children,
}) {
    const page = usePage();

    const menu = [
        [
            'Dashboard',
            'compliance.dashboard',
        ],
        [
            'Networks',
            'compliance.networks.index',
        ],
        [
            'Routers',
            'compliance.routers.index',
        ],
    ];

    return (
        <div className="min-h-screen bg-slate-950 text-white">
            <header className="border-b border-white/10 bg-slate-950">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-4">
                    <div>
                        <div className="text-xl font-black">
                            Network Compliance
                        </div>

                        <div className="text-xs text-slate-400">
                            Logging & Filtering
                        </div>
                    </div>

                    <nav className="flex flex-wrap gap-2">
                        {menu.map(
                            ([label, name]) => (
                                <Link
                                    key={name}
                                    href={route(
                                        name,
                                    )}
                                    className={`rounded-xl px-4 py-2 text-sm font-bold ${
                                        route().current(
                                            name,
                                        )
                                            ? 'bg-cyan-400 text-slate-950'
                                            : 'bg-white/5 text-slate-300 hover:bg-white/10'
                                    }`}
                                >
                                    {label}
                                </Link>
                            ),
                        )}

                        <button
                            type="button"
                            onClick={() =>
                                router.post(
                                    route(
                                        'compliance.logout',
                                    ),
                                )
                            }
                            className="rounded-xl border border-white/10 px-4 py-2 text-sm font-bold text-slate-300 hover:bg-white/10"
                        >
                            Logout
                        </button>
                    </nav>
                </div>
            </header>

            <main className="mx-auto max-w-7xl px-5 py-8">
                <h1 className="mb-7 text-3xl font-black">
                    {title}
                </h1>

                {page.props.flash?.success && (
                    <div className="mb-6 rounded-2xl border border-emerald-400/30 bg-emerald-400/10 p-4 text-emerald-200">
                        {
                            page.props.flash
                                .success
                        }
                    </div>
                )}

                {children}
            </main>
        </div>
    );
}
