import {
    Head,
    Link,
} from '@inertiajs/react';

const portals = [
    {
        badge: 'Company / ISP',
        title: 'Company Login',
        text: 'For Company owners, Managers and Operators using ISP, MAC client, Hotspot, billing and network-management tools.',
        path: '/login',
        routeName: 'login',
    },
    {
        badge: 'Hotel Hotspot',
        title: 'Hotel Login',
        text: 'For Hotel administrators and authorized Hotel staff managing guest Wi-Fi, vouchers, stays, sessions and reports.',
        path: '/hotel/login',
        routeName: 'hotel.login',
    },
    {
        badge: 'Network Compliance',
        title: 'Compliance Login',
        text: 'For standalone Compliance customers using logging, filtering, investigation, retention and storage tools.',
        path: '/compliance/login',
        routeName: 'compliance.login',
    },
];

export default function LoginCenter({
    brand = 'MikroPanel',
    site = {},
    compliancePlans = [],
}) {
    const websiteName =
        brand
        || site.website_name
        || 'MikroPanel';

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900">
            <Head
                title={`Customer Login - ${websiteName}`}
            />

            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4">
                    <Link
                        href={route(
                            'website.home',
                        )}
                        className="font-black"
                    >
                        {websiteName}
                    </Link>

                    <Link
                        href={route(
                            'website.home',
                        )}
                        className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-black text-slate-700"
                    >
                        Back to Website
                    </Link>
                </div>
            </header>

            <main>
                <section className="mx-auto max-w-6xl px-5 py-14 md:py-20">
                    <div className="mx-auto max-w-3xl text-center">
                        <div className="text-sm font-black uppercase tracking-[0.18em] text-cyan-700">
                            Customer Login
                        </div>

                        <h1 className="mt-3 text-4xl font-black tracking-tight md:text-5xl">
                            Select your service
                        </h1>

                        <p className="mt-4 text-lg leading-8 text-slate-600">
                            Choose the customer panel that belongs to your service. You do not need to remember separate login URLs.
                        </p>
                    </div>

                    <div className="mt-10 grid gap-5 lg:grid-cols-3">
                        {portals.map(
                            (portal) => (
                                <article
                                    key={
                                        portal.title
                                    }
                                    className="flex flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm"
                                >
                                    <div className="text-xs font-black uppercase tracking-[0.16em] text-cyan-700">
                                        {portal.badge}
                                    </div>

                                    <h2 className="mt-3 text-2xl font-black">
                                        {portal.title}
                                    </h2>

                                    <p className="mt-3 flex-1 text-sm leading-6 text-slate-600">
                                        {portal.text}
                                    </p>

                                    <code className="mt-5 rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-600">
                                        {portal.path}
                                    </code>

                                    <Link
                                        href={route(
                                            portal.routeName,
                                        )}
                                        className="mt-5 rounded-xl bg-slate-950 px-5 py-3 text-center font-black text-white hover:bg-slate-800"
                                    >
                                        Open {portal.title}
                                    </Link>
                                </article>
                            ),
                        )}
                    </div>
                </section>

                <section className="border-y border-slate-200 bg-white">
                    <div className="mx-auto grid max-w-6xl gap-6 px-5 py-12 lg:grid-cols-2">
                        <div className="rounded-3xl bg-slate-50 p-6">
                            <div className="text-sm font-black uppercase tracking-[0.16em] text-violet-700">
                                Company Compliance Add-on
                            </div>

                            <h2 className="mt-2 text-xl font-black">
                                Use your existing Company login
                            </h2>

                            <p className="mt-3 text-sm leading-6 text-slate-600">
                                If Compliance was added to an existing Company account, sign in through Company Login and open Network Compliance from inside the Company panel.
                            </p>
                        </div>

                        <div className="rounded-3xl bg-slate-50 p-6">
                            <div className="text-sm font-black uppercase tracking-[0.16em] text-violet-700">
                                Hotel Compliance Add-on
                            </div>

                            <h2 className="mt-2 text-xl font-black">
                                Use your existing Hotel login
                            </h2>

                            <p className="mt-3 text-sm leading-6 text-slate-600">
                                If Compliance was added to a Hotel account, sign in through Hotel Login and open Network Compliance from inside the Hotel panel.
                            </p>
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-6xl px-5 py-14">
                    <div className="flex flex-col gap-5 rounded-3xl bg-slate-950 p-7 text-white md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 className="text-2xl font-black">
                                New Company customer?
                            </h2>

                            <p className="mt-2 text-sm leading-6 text-slate-300">
                                Create a Company account from the public registration page.
                            </p>
                        </div>

                        <Link
                            href={route(
                                'website.register',
                            )}
                            className="rounded-xl bg-cyan-400 px-6 py-3 text-center font-black text-slate-950"
                        >
                            Register Company
                        </Link>
                    </div>
                </section>

                {compliancePlans.length > 0 && (
                    <section className="border-t border-slate-200 bg-white">
                        <div className="mx-auto max-w-6xl px-5 py-14">
                            <div className="max-w-3xl">
                                <div className="text-sm font-black uppercase tracking-[0.16em] text-cyan-700">
                                    Compliance Services
                                </div>

                                <h2 className="mt-2 text-3xl font-black">
                                    Current Compliance plans
                                </h2>

                                <p className="mt-3 text-sm leading-6 text-slate-600">
                                    Logging, filtering and combined plans are managed from the platform and shown here for customer reference.
                                </p>
                            </div>

                            <div className="mt-7 grid gap-4 md:grid-cols-3">
                                {compliancePlans.map(
                                    (plan) => (
                                        <div
                                            key={
                                                plan.id
                                            }
                                            className="rounded-2xl border border-slate-200 p-5"
                                        >
                                            <div className="text-xs font-black uppercase text-violet-700">
                                                {plan.service_type}
                                            </div>

                                            <div className="mt-2 font-black">
                                                {plan.name}
                                            </div>

                                            <div className="mt-2 text-sm font-black text-cyan-700">
                                                {plan.call_for_price
                                                    ? 'Call for Price'
                                                    : `QAR ${Number(
                                                          plan.monthly_price,
                                                      ).toLocaleString(
                                                          undefined,
                                                          {
                                                              maximumFractionDigits: 2,
                                                          },
                                                      )} / month`}
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                    </section>
                )}
            </main>

            <footer className="border-t border-slate-200 bg-slate-50">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4 px-5 py-8 text-sm text-slate-500">
                    <span>
                        {websiteName}
                    </span>

                    <div className="flex gap-4 font-bold">
                        <Link
                            href={route(
                                'website.home',
                            )}
                        >
                            Home
                        </Link>

                        <Link
                            href={route(
                                'website.terms',
                            )}
                        >
                            Terms
                        </Link>

                        <Link
                            href={route(
                                'website.privacy',
                            )}
                        >
                            Privacy
                        </Link>
                    </div>
                </div>
            </footer>
        </div>
    );
}
