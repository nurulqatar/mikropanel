import {
    Head,
    Link,
} from '@inertiajs/react';

const services = [
    {
        key: 'company',
        badge: 'Company / ISP',
        title: 'ISP & Company Network Management',
        text: 'Manage customers, MikroTik routers, MAC access, Hotspot, packages, billing, staff and daily network operations from one web panel.',
        points: [
            'MAC, ARP and DHCP client control',
            'Hotspot users and vouchers',
            'Multiple Network Zones',
            'Packages, invoices and payments',
            'Managers, Operators and permissions',
            'MikroTik synchronization and reports',
        ],
        loginRoute: 'login',
        loginLabel: 'Company Login',
        secondaryHref: '#company-plans',
        secondaryLabel: 'View Company Plans',
    },
    {
        key: 'hotel',
        badge: 'Hotel Hotspot',
        title: 'Guest Wi-Fi Management for Hotels',
        text: 'Give hotel staff a dedicated workspace for guest Internet access without exposing raw router administration.',
        points: [
            'Guest and stay management',
            'Wi-Fi vouchers linked to stays',
            'Hotel MikroTik router support',
            'Live sessions and usage reports',
            'Reception and staff access',
            'Automatic expiry and voucher sync',
        ],
        loginRoute: 'hotel.login',
        loginLabel: 'Hotel Login',
        secondaryHref: '#contact',
        secondaryLabel: 'Ask About Hotel Service',
    },
    {
        key: 'compliance',
        badge: 'Network Compliance',
        title: 'Compliance Logging & Internet Filtering',
        text: 'Choose network metadata logging, Internet filtering, or both. Available as a standalone service or as an add-on for an existing Company or Hotel.',
        points: [
            'Public IP, port and time investigation',
            'Network metadata retention',
            'Domain, IP and protocol filtering',
            'Blocklist or allowlist mode',
            'External NAS / S3 archive support',
            'Standalone or existing-customer add-on',
        ],
        loginRoute: 'compliance.login',
        loginLabel: 'Compliance Login',
        secondaryHref: '#compliance-plans',
        secondaryLabel: 'View Compliance Plans',
    },
];

const steps = [
    {
        number: '01',
        title: 'Choose a service',
        text: 'Select Company / ISP Management, Hotel Hotspot, or Network Compliance.',
    },
    {
        number: '02',
        title: 'Create or activate the account',
        text: 'Company customers can register online. Hotel and Compliance services are activated for the customer account.',
    },
    {
        number: '03',
        title: 'Connect the network',
        text: 'Add the supported router, network and optional storage required by the selected service.',
    },
    {
        number: '04',
        title: 'Operate from the panel',
        text: 'Use the correct customer dashboard for daily management, monitoring, billing or compliance work.',
    },
];

const quickFacts = [
    ['Company / ISP', 'Clients, Hotspot, billing & MikroTik'],
    ['Hotel Hotspot', 'Guest Wi-Fi & voucher operations'],
    ['Network Compliance', 'Logging, filtering & investigation'],
];

export default function Home({
    brand = 'MikroPanel',
    site = {},
    plans = [],
    compliancePlans = [],
}) {
    const websiteName =
        brand
        || site.website_name
        || 'MikroPanel';

    const tagline =
        site.website_tagline
        || 'Network Management, Hotel Wi-Fi & Compliance Services';

    return (
        <div className="min-h-screen bg-white text-slate-900">
            <Head>
                <title>
                    {websiteName} - Network Management Services
                </title>

                <meta
                    name="description"
                    content="Company ISP management, Hotel Hotspot guest Wi-Fi and Network Compliance logging and filtering from one professional platform."
                />
            </Head>

            <header className="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-5 px-5 py-4">
                    <Link
                        href={route(
                            'website.home',
                        )}
                        className="flex min-w-0 items-center gap-3"
                    >
                        {site.logo_url ? (
                            <img
                                src={site.logo_url}
                                alt={websiteName}
                                className="h-10 w-10 rounded-xl object-contain"
                            />
                        ) : (
                            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-950 text-sm font-black text-white">
                                MP
                            </div>
                        )}

                        <div className="min-w-0">
                            <div className="truncate text-lg font-black">
                                {websiteName}
                            </div>

                            <div className="hidden truncate text-xs text-slate-500 sm:block">
                                {tagline}
                            </div>
                        </div>
                    </Link>

                    <nav className="hidden items-center gap-6 text-sm font-bold text-slate-600 lg:flex">
                        <a
                            href="#services"
                            className="hover:text-slate-950"
                        >
                            Services
                        </a>

                        <a
                            href="#company-plans"
                            className="hover:text-slate-950"
                        >
                            Company Plans
                        </a>

                        <a
                            href="#compliance-plans"
                            className="hover:text-slate-950"
                        >
                            Compliance
                        </a>

                        <a
                            href="#how-it-works"
                            className="hover:text-slate-950"
                        >
                            How It Works
                        </a>
                    </nav>

                    <div className="flex items-center gap-2">
                        <Link
                            href={route(
                                'website.login-center',
                            )}
                            className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-black text-slate-800 hover:bg-slate-50"
                        >
                            Login
                        </Link>

                        <Link
                            href={route(
                                'website.register',
                            )}
                            className="hidden rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-black text-slate-950 hover:bg-cyan-400 sm:inline-flex"
                        >
                            Register Company
                        </Link>
                    </div>
                </div>
            </header>

            <main>
                <section className="border-b border-slate-200 bg-slate-50">
                    <div className="mx-auto grid max-w-7xl gap-10 px-5 py-16 md:py-20 lg:grid-cols-[1.1fr_.9fr] lg:items-center">
                        <div>
                            <div className="inline-flex rounded-full border border-cyan-200 bg-cyan-50 px-4 py-2 text-xs font-black uppercase tracking-[0.16em] text-cyan-800">
                                Simple Network Operations
                            </div>

                            <h1 className="mt-6 max-w-4xl text-4xl font-black tracking-tight text-slate-950 sm:text-5xl lg:text-6xl">
                                Three network services.
                                <span className="block text-cyan-600">
                                    One easy platform.
                                </span>
                            </h1>

                            <p className="mt-6 max-w-3xl text-lg leading-8 text-slate-600">
                                {websiteName} provides ISP and Company network management, Hotel guest Wi-Fi management, and Network Compliance logging and filtering. Choose only the service your organization needs.
                            </p>

                            <div className="mt-8 flex flex-wrap gap-3">
                                <a
                                    href="#services"
                                    className="rounded-xl bg-slate-950 px-6 py-3.5 font-black text-white hover:bg-slate-800"
                                >
                                    Explore Services
                                </a>

                                <Link
                                    href={route(
                                        'website.login-center',
                                    )}
                                    className="rounded-xl border border-slate-300 bg-white px-6 py-3.5 font-black text-slate-900 hover:bg-slate-50"
                                >
                                    Customer Login
                                </Link>
                            </div>

                            <div className="mt-8 flex flex-wrap gap-x-6 gap-y-2 text-sm font-semibold text-slate-500">
                                <span>✓ Hosted web panel</span>
                                <span>✓ MikroTik integration</span>
                                <span>✓ Role-based access</span>
                                <span>✓ Manual commercial control</span>
                            </div>
                        </div>

                        <div className="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm md:p-8">
                            <div className="text-sm font-black uppercase tracking-[0.16em] text-slate-500">
                                What we provide
                            </div>

                            <div className="mt-5 space-y-4">
                                {quickFacts.map(
                                    ([title, text]) => (
                                        <div
                                            key={title}
                                            className="rounded-2xl border border-slate-200 p-5"
                                        >
                                            <div className="font-black text-slate-950">
                                                {title}
                                            </div>

                                            <div className="mt-1 text-sm leading-6 text-slate-500">
                                                {text}
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    id="services"
                    className="mx-auto max-w-7xl px-5 py-20"
                >
                    <SectionHeading
                        eyebrow="Our Services"
                        title="Choose the service that matches your operation"
                        text="Visitors can understand the platform at a glance. Each service has its own purpose, customer login and operational workspace."
                    />

                    <div className="mt-10 grid gap-6 lg:grid-cols-3">
                        {services.map(
                            (service) => (
                                <ServiceCard
                                    key={
                                        service.key
                                    }
                                    service={
                                        service
                                    }
                                />
                            ),
                        )}
                    </div>
                </section>

                <section
                    id="how-it-works"
                    className="border-y border-slate-200 bg-slate-50"
                >
                    <div className="mx-auto max-w-7xl px-5 py-20">
                        <SectionHeading
                            eyebrow="How It Works"
                            title="From signup to daily operation"
                            text="The customer journey is kept simple so each organization knows where to start and which panel to use."
                        />

                        <div className="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                            {steps.map(
                                (step) => (
                                    <div
                                        key={
                                            step.number
                                        }
                                        className="rounded-3xl border border-slate-200 bg-white p-6"
                                    >
                                        <div className="text-sm font-black text-cyan-600">
                                            STEP {step.number}
                                        </div>

                                        <h3 className="mt-3 text-xl font-black">
                                            {step.title}
                                        </h3>

                                        <p className="mt-3 text-sm leading-6 text-slate-600">
                                            {step.text}
                                        </p>
                                    </div>
                                ),
                            )}
                        </div>
                    </div>
                </section>

                <section
                    id="company-plans"
                    className="mx-auto max-w-7xl px-5 py-20"
                >
                    <SectionHeading
                        eyebrow="Company / ISP Plans"
                        title={
                            site.pricing_title
                            || 'Plans for Company and ISP operations'
                        }
                        text={
                            site.pricing_text
                            || 'Choose an available Company plan. Active plans and prices are controlled from the platform.'
                        }
                    />

                    {plans.length > 0 ? (
                        <div className="mt-10 grid gap-6 lg:grid-cols-3">
                            {plans.map(
                                (plan) => (
                                    <CompanyPlanCard
                                        key={
                                            plan.id
                                        }
                                        plan={
                                            plan
                                        }
                                        site={
                                            site
                                        }
                                    />
                                ),
                            )}
                        </div>
                    ) : (
                        <EmptyState
                            text="No Company plans are currently available."
                        />
                    )}
                </section>

                <section
                    id="compliance-plans"
                    className="border-y border-slate-200 bg-slate-50"
                >
                    <div className="mx-auto max-w-7xl px-5 py-20">
                        <SectionHeading
                            eyebrow="Network Compliance Plans"
                            title="Logging, Filtering, or both"
                            text="Compliance plans are available for standalone organizations and can also be linked to an existing Company or Hotel account."
                        />

                        {compliancePlans.length > 0 ? (
                            <div className="mt-10 grid gap-6 lg:grid-cols-3">
                                {compliancePlans.map(
                                    (plan) => (
                                        <CompliancePlanCard
                                            key={
                                                plan.id
                                            }
                                            plan={
                                                plan
                                            }
                                        />
                                    ),
                                )}
                            </div>
                        ) : (
                            <EmptyState
                                text="No Network Compliance plans are currently enabled."
                            />
                        )}

                        <div className="mt-8 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-950">
                            Compliance service uses network metadata and policy controls. HTTPS message contents, passwords and page bodies are not decrypted by default. Actual logging or filtering requires compatible network equipment to be connected.
                        </div>
                    </div>
                </section>

                <section
                    id="login"
                    className="mx-auto max-w-7xl px-5 py-20"
                >
                    <div className="grid gap-8 rounded-[2rem] bg-slate-950 p-7 text-white md:p-10 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <div className="text-sm font-black uppercase tracking-[0.18em] text-cyan-300">
                                Existing Customer?
                            </div>

                            <h2 className="mt-3 text-3xl font-black md:text-4xl">
                                Open the correct customer login in one place
                            </h2>

                            <p className="mt-4 max-w-3xl leading-7 text-slate-300">
                                Company, Hotel Hotspot and standalone Compliance customers each have the correct login option inside the Customer Login Center.
                            </p>
                        </div>

                        <Link
                            href={route(
                                'website.login-center',
                            )}
                            className="rounded-xl bg-cyan-400 px-7 py-4 text-center font-black text-slate-950 hover:bg-cyan-300"
                        >
                            Open Customer Login
                        </Link>
                    </div>
                </section>

                <section
                    id="contact"
                    className="border-t border-slate-200 bg-slate-50"
                >
                    <div className="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-14 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 className="text-2xl font-black">
                                Need help choosing a service?
                            </h2>

                            <p className="mt-2 text-sm leading-6 text-slate-600">
                                Contact us for Company, Hotel Hotspot or Network Compliance service information.
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            {site.contact_url && (
                                <a
                                    href={
                                        site.contact_url
                                    }
                                    className="rounded-xl bg-slate-950 px-6 py-3 font-black text-white"
                                >
                                    Contact Us
                                </a>
                            )}

                            <Link
                                href={route(
                                    'website.register',
                                )}
                                className="rounded-xl border border-slate-300 bg-white px-6 py-3 font-black text-slate-900"
                            >
                                Register Company
                            </Link>
                        </div>
                    </div>
                </section>
            </main>

            <footer className="border-t border-slate-200 bg-white">
                <div className="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-8 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="font-black">
                            {websiteName}
                        </div>

                        <div className="mt-1 text-sm text-slate-500">
                            {site.footer_text
                                || tagline}
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-5 text-sm font-bold text-slate-500">
                        <Link
                            href={route(
                                'website.login-center',
                            )}
                        >
                            Customer Login
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

function SectionHeading({
    eyebrow,
    title,
    text,
}) {
    return (
        <div className="max-w-3xl">
            <div className="text-sm font-black uppercase tracking-[0.18em] text-cyan-700">
                {eyebrow}
            </div>

            <h2 className="mt-3 text-3xl font-black tracking-tight text-slate-950 md:text-4xl">
                {title}
            </h2>

            <p className="mt-4 text-base leading-7 text-slate-600">
                {text}
            </p>
        </div>
    );
}

function ServiceCard({
    service,
}) {
    return (
        <article className="flex h-full flex-col rounded-3xl border border-slate-200 bg-white p-7 shadow-sm">
            <div className="inline-flex w-fit rounded-full bg-cyan-50 px-3 py-1 text-xs font-black uppercase tracking-wide text-cyan-800">
                {service.badge}
            </div>

            <h3 className="mt-5 text-2xl font-black text-slate-950">
                {service.title}
            </h3>

            <p className="mt-3 leading-7 text-slate-600">
                {service.text}
            </p>

            <div className="mt-6 space-y-3">
                {service.points.map(
                    (point) => (
                        <div
                            key={point}
                            className="flex gap-3 text-sm leading-6 text-slate-700"
                        >
                            <span className="font-black text-cyan-600">
                                ✓
                            </span>

                            <span>
                                {point}
                            </span>
                        </div>
                    ),
                )}
            </div>

            <div className="mt-auto flex flex-wrap gap-2 pt-7">
                <Link
                    href={route(
                        service.loginRoute,
                    )}
                    className="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-black text-white"
                >
                    {service.loginLabel}
                </Link>

                <a
                    href={
                        service.secondaryHref
                    }
                    className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-black text-slate-700"
                >
                    {service.secondaryLabel}
                </a>
            </div>
        </article>
    );
}

function CompanyPlanCard({
    plan,
    site,
}) {
    const features =
        Array.isArray(plan.features)
        && plan.features.length > 0
            ? plan.features
            : [
                plan.is_unlimited
                    ? 'Unlimited client capacity'
                    : `${plan.client_limit} client capacity`,
                'Company network operations',
                'MikroTik management',
                'Billing and reports',
            ];

    const price =
        plan.is_free_trial
            ? 'FREE'
            : (
                plan.is_unlimited
                && Number(
                    plan.price,
                ) <= 0
            )
              ? 'Call for Price'
              : `QAR ${money(
                    plan.price,
                )}`;

    return (
        <article className="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm">
            <div className="flex flex-wrap gap-2">
                {plan.is_free_trial && (
                    <Badge>
                        7-Day Free Trial
                    </Badge>
                )}

                {plan.is_unlimited && (
                    <Badge>
                        Unlimited
                    </Badge>
                )}
            </div>

            <h3 className="mt-4 text-2xl font-black">
                {plan.name}
            </h3>

            <div className="mt-4 text-3xl font-black text-cyan-700">
                {price}
            </div>

            <div className="mt-2 text-sm text-slate-500">
                {plan.validity_days} days
                {!plan.is_unlimited
                    && ` · ${plan.client_limit} clients`}
            </div>

            <div className="mt-6 space-y-2">
                {features
                    .slice(
                        0,
                        6,
                    )
                    .map(
                        (item) => (
                            <div
                                key={item}
                                className="flex gap-2 text-sm text-slate-600"
                            >
                                <span className="text-cyan-600">
                                    ✓
                                </span>

                                <span>
                                    {item}
                                </span>
                            </div>
                        ),
                    )}
            </div>

            {plan.is_unlimited ? (
                <a
                    href={
                        site.contact_url
                        || '#contact'
                    }
                    className="mt-7 block rounded-xl bg-slate-950 px-5 py-3 text-center font-black text-white"
                >
                    Contact Us
                </a>
            ) : (
                <Link
                    href={route(
                        'website.register',
                        {
                            plan:
                                plan.id,
                        },
                    )}
                    className="mt-7 block rounded-xl bg-cyan-500 px-5 py-3 text-center font-black text-slate-950"
                >
                    {plan.is_free_trial
                        ? 'Start Free Trial'
                        : 'Apply for this Plan'}
                </Link>
            )}
        </article>
    );
}

function CompliancePlanCard({
    plan,
}) {
    const price =
        plan.call_for_price
            ? 'Call for Price'
            : `QAR ${money(
                  plan.monthly_price,
              )} / month`;

    const typeLabel =
        plan.service_type
            === 'logging'
            ? 'Compliance Logging'
            : plan.service_type
                === 'filtering'
                ? 'Internet Filtering'
                : 'Logging + Filtering';

    return (
        <article className="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm">
            <div className="text-xs font-black uppercase tracking-[0.16em] text-violet-700">
                {typeLabel}
            </div>

            <h3 className="mt-3 text-2xl font-black">
                {plan.name}
            </h3>

            <div className="mt-4 text-3xl font-black text-cyan-700">
                {price}
            </div>

            <p className="mt-4 text-sm leading-6 text-slate-600">
                {plan.description
                    || 'Network Compliance service for authorized organization networks.'}
            </p>

            <Link
                href={route(
                    'compliance.login',
                )}
                className="mt-7 block rounded-xl border border-slate-300 px-5 py-3 text-center font-black text-slate-800"
            >
                Compliance Login
            </Link>
        </article>
    );
}

function Badge({
    children,
}) {
    return (
        <span className="rounded-full bg-cyan-50 px-3 py-1 text-xs font-black uppercase text-cyan-800">
            {children}
        </span>
    );
}

function EmptyState({
    text,
}) {
    return (
        <div className="mt-10 rounded-3xl border border-slate-200 bg-slate-50 p-8 text-center text-slate-600">
            {text}
        </div>
    );
}

function money(value) {
    return Number(
        value
        || 0,
    ).toLocaleString(
        undefined,
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        },
    );
}
