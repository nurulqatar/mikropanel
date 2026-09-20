import {
    Head,
    Link,
} from '@inertiajs/react';

const portals = [
    {
        title: 'Company / ISP Panel',
        badge: 'Company',
        path: '/login',
        routeName: 'login',
        button: 'Company Login',
        description:
            'Company owners, Managers and Operators use the main MikroPanel login. Access is controlled by the role and permissions assigned to the account.',
        features: [
            'MAC client management',
            'Hotspot and voucher operations',
            'Network Zones, billing and reports',
        ],
    },
    {
        title: 'Super Admin Panel',
        badge: 'Platform Admin',
        path: '/login',
        routeName: 'login',
        button: 'Super Admin Login',
        description:
            'Super Admin uses the secure main login. A Super Admin account is routed to the platform administration dashboard after authentication.',
        features: [
            'Company platform administration',
            'Hotel Hotspot administration',
            'Network Compliance rental control',
        ],
    },
    {
        title: 'Hotel Hotspot Panel',
        badge: 'Hotel',
        path: '/hotel/login',
        routeName: 'hotel.login',
        button: 'Hotel Login',
        description:
            'Hotel administrators and authorized Hotel staff use the dedicated Hotel Hotspot login for guest Internet operations.',
        features: [
            'Guest stays and Wi-Fi vouchers',
            'Hotel MikroTik operations',
            'Sessions, reports and billing',
        ],
    },
    {
        title: 'Standalone Compliance Panel',
        badge: 'Compliance',
        path: '/compliance/login',
        routeName: 'compliance.login',
        button: 'Compliance Login',
        description:
            'Standalone Network Compliance customers use the Compliance email and password created when Super Admin rents the service.',
        features: [
            'Compliance Logging',
            'Network Filtering',
            'Investigation, retention and storage',
        ],
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
        <div className="min-h-screen bg-slate-950 text-white">
            <Head
                title={`Login Center - ${websiteName}`}
            />

            <header className="border-b border-white/10">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-5">
                    <Link
                        href={route(
                            'website.home',
                        )}
                        className="text-xl font-black"
                    >
                        {websiteName}
                    </Link>

                    <Link
                        href={route(
                            'website.home',
                        )}
                        className="rounded-xl border border-white/15 px-4 py-2 text-sm font-black text-slate-200 hover:bg-white/5"
                    >
                        Back to Website
                    </Link>
                </div>
            </header>

            <main>
                <section className="mx-auto max-w-7xl px-5 py-16 md:py-20">
                    <div className="max-w-4xl">
                        <div className="text-sm font-black uppercase tracking-[0.2em] text-cyan-300">
                            All Panel Logins
                        </div>

                        <h1 className="mt-4 text-4xl font-black tracking-tight md:text-6xl">
                            Choose the panel you want to open
                        </h1>

                        <p className="mt-5 max-w-3xl text-lg leading-8 text-slate-300">
                            Company, Super Admin, Hotel Hotspot and standalone Network Compliance login portals are available together on this page.
                        </p>
                    </div>

                    <div className="mt-10 grid gap-6 md:grid-cols-2">
                        {portals.map(
                            (portal) => (
                                <PortalCard
                                    key={
                                        portal.title
                                    }
                                    portal={
                                        portal
                                    }
                                />
                            ),
                        )}
                    </div>
                </section>

                <section className="border-y border-white/10 bg-slate-900/60">
                    <div className="mx-auto max-w-7xl px-5 py-16">
                        <div className="max-w-4xl">
                            <div className="text-sm font-black uppercase tracking-[0.2em] text-violet-300">
                                Existing Customer Add-on
                            </div>

                            <h2 className="mt-3 text-3xl font-black md:text-4xl">
                                Compliance add-on uses the customer&apos;s existing panel
                            </h2>

                            <p className="mt-4 leading-7 text-slate-300">
                                A Company or Hotel customer with an activated Compliance add-on signs in to the normal panel first. The Compliance workspace is then opened from inside that authenticated panel.
                            </p>
                        </div>

                        <div className="mt-8 grid gap-5 lg:grid-cols-2">
                            <AccessPath
                                title="Company Compliance Add-on"
                                loginPath="/login"
                                entryPath="/reseller/compliance"
                                loginRoute="login"
                                button="Company Login"
                                text="Company owner signs in normally, then opens Network Compliance from the Company dashboard."
                            />

                            <AccessPath
                                title="Hotel Compliance Add-on"
                                loginPath="/hotel/login"
                                entryPath="/hotel/compliance"
                                loginRoute="hotel.login"
                                button="Hotel Login"
                                text="Hotel Admin signs in normally, then opens Network Compliance from the Hotel dashboard."
                            />
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-5 py-16">
                    <div className="flex flex-wrap items-end justify-between gap-5">
                        <div>
                            <div className="text-sm font-black uppercase tracking-[0.2em] text-cyan-300">
                                Network Compliance Services
                            </div>

                            <h2 className="mt-3 text-3xl font-black md:text-4xl">
                                Current plans managed by Super Admin
                            </h2>
                        </div>

                        <p className="max-w-xl text-sm leading-6 text-slate-400">
                            A rental can be created before MikroTik, collector or NAS/S3 is connected. Actual network logging or filtering starts after supported equipment is configured.
                        </p>
                    </div>

                    <div className="mt-8 grid gap-5 lg:grid-cols-3">
                        {compliancePlans.map(
                            (plan) => (
                                <PlanCard
                                    key={
                                        plan.id
                                    }
                                    plan={
                                        plan
                                    }
                                />
                            ),
                        )}

                        {!compliancePlans.length && (
                            <div className="rounded-3xl border border-white/10 bg-white/[0.04] p-7 text-slate-300 lg:col-span-3">
                                No public Compliance plan is currently enabled. Contact Super Admin for availability.
                            </div>
                        )}
                    </div>
                </section>

                <section className="border-t border-white/10 bg-slate-900/50">
                    <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-6 px-5 py-12">
                        <div>
                            <div className="text-2xl font-black">
                                Need a Company account?
                            </div>

                            <div className="mt-2 text-sm text-slate-400">
                                Company registration is available separately from the login portals.
                            </div>
                        </div>

                        <Link
                            href={route(
                                'website.register',
                            )}
                            className="rounded-xl bg-cyan-400 px-6 py-3 font-black text-slate-950"
                        >
                            Register Company
                        </Link>
                    </div>
                </section>
            </main>

            <footer className="border-t border-white/10">
                <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-8 text-sm text-slate-400">
                    <span>
                        {websiteName} · Central Portal Access
                    </span>

                    <div className="flex flex-wrap gap-4">
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

function PortalCard({
    portal,
}) {
    return (
        <article className="rounded-3xl border border-white/10 bg-white/[0.04] p-7">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <span className="rounded-full bg-cyan-400/10 px-3 py-1 text-xs font-black uppercase tracking-wide text-cyan-300">
                    {portal.badge}
                </span>

                <code className="rounded-lg bg-black/30 px-3 py-1 text-xs text-slate-300">
                    {portal.path}
                </code>
            </div>

            <h2 className="mt-5 text-2xl font-black">
                {portal.title}
            </h2>

            <p className="mt-3 leading-7 text-slate-300">
                {portal.description}
            </p>

            <div className="mt-5 space-y-2">
                {portal.features.map(
                    (feature) => (
                        <div
                            key={feature}
                            className="flex gap-3 text-sm text-slate-400"
                        >
                            <span className="text-cyan-300">
                                ✓
                            </span>

                            <span>
                                {feature}
                            </span>
                        </div>
                    ),
                )}
            </div>

            <Link
                href={route(
                    portal.routeName,
                )}
                className="mt-7 inline-flex rounded-xl bg-cyan-400 px-5 py-3 font-black text-slate-950"
            >
                {portal.button}
            </Link>
        </article>
    );
}

function AccessPath({
    title,
    loginPath,
    entryPath,
    loginRoute,
    button,
    text,
}) {
    return (
        <article className="rounded-3xl border border-white/10 bg-slate-950/60 p-7">
            <h3 className="text-xl font-black">
                {title}
            </h3>

            <p className="mt-3 text-sm leading-6 text-slate-400">
                {text}
            </p>

            <div className="mt-5 space-y-2 text-xs">
                <div>
                    <span className="text-slate-500">
                        Login URL:
                    </span>{' '}
                    <code className="text-cyan-300">
                        {loginPath}
                    </code>
                </div>

                <div>
                    <span className="text-slate-500">
                        Compliance entry:
                    </span>{' '}
                    <code className="text-violet-300">
                        {entryPath}
                    </code>
                </div>
            </div>

            <Link
                href={route(
                    loginRoute,
                )}
                className="mt-6 inline-flex rounded-xl border border-white/15 px-5 py-3 font-black text-white hover:bg-white/5"
            >
                {button}
            </Link>
        </article>
    );
}

function PlanCard({
    plan,
}) {
    const price =
        plan.call_for_price
            ? 'Call for Price'
            : `QAR ${Number(
                  plan.monthly_price
              ).toLocaleString(
                  undefined,
                  {
                      maximumFractionDigits: 2,
                  },
              )} / month`;

    return (
        <article className="rounded-3xl border border-white/10 bg-white/[0.04] p-7">
            <div className="text-xs font-black uppercase tracking-[0.18em] text-violet-300">
                {plan.service_type}
            </div>

            <h3 className="mt-3 text-2xl font-black">
                {plan.name}
            </h3>

            <div className="mt-4 text-xl font-black text-cyan-300">
                {price}
            </div>

            <p className="mt-4 text-sm leading-6 text-slate-400">
                {plan.description
                    || 'Network Compliance service controlled by Super Admin.'}
            </p>
        </article>
    );
}
