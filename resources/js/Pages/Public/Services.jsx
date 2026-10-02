import {
    Head,
    Link,
} from '@inertiajs/react';

const companyFeatures = [
    {
        title: 'MAC Client Management',
        text: 'Create customers, maintain device MAC addresses, assign IP addresses and manage service status from the web panel.',
    },
    {
        title: 'MikroTik ARP & DHCP Sync',
        text: 'Supported customer changes can synchronize to MikroTik ARP and DHCP lease records so the panel and router remain aligned.',
    },
    {
        title: 'Hotspot & Voucher Operations',
        text: 'Operate Hotspot servers, create vouchers, manage sessions and support voucher-based access from the same Company workspace.',
    },
    {
        title: 'Network Zones',
        text: 'Separate camps, buildings or sites into independent operating areas while keeping them inside one Company account.',
    },
    {
        title: 'Packages & Renewals',
        text: 'Create service packages, renew customers and keep service validity connected to customer billing activity.',
    },
    {
        title: 'Payments & Accounting',
        text: 'Record invoices, payments, expenses, collections, refunds and operational financial history.',
    },
    {
        title: 'Managers & Operators',
        text: 'Give staff controlled access based on their role instead of sharing a single unrestricted administrator account.',
    },
    {
        title: 'Reports & Visibility',
        text: 'Review customer, payment, accounting and operational information without combining separate manual records.',
    },
];

const hotelFeatures = [
    {
        title: 'Dedicated Hotel Workspace',
        text: 'Hotel staff work inside a separate guest Wi-Fi environment rather than the normal Company panel.',
    },
    {
        title: 'Guest & Stay Management',
        text: 'Keep guest records and stay periods connected to the Wi-Fi access workflow.',
    },
    {
        title: 'Guest Wi-Fi Vouchers',
        text: 'Create and manage guest vouchers from reception without maintaining credentials in separate notes.',
    },
    {
        title: 'Stay-Linked Expiry',
        text: 'Guest access can follow the stay lifecycle so Wi-Fi service can end around checkout.',
    },
    {
        title: 'Reception & Staff Access',
        text: 'Authorized hotel staff can handle supported Wi-Fi tasks without receiving unrestricted router administration.',
    },
    {
        title: 'Multi-Router Operations',
        text: 'Supported MikroTik Hotspot routers can participate in the same centralized hotel voucher workflow.',
    },
    {
        title: 'Live Sessions',
        text: 'Review active guest sessions and connected-user information from the Hotel panel.',
    },
    {
        title: 'Usage & Reports',
        text: 'Review usage, sessions and operational reports for better guest-network visibility.',
    },
];

const loggingFeatures = [
    {
        title: 'Network Metadata Logging',
        text: 'Retain supported network-flow and destination metadata for authorized organization networks.',
    },
    {
        title: 'Public IP Investigation',
        text: 'Search using public IP, translated source port, exact time and protocol when the required NAT evidence exists.',
    },
    {
        title: 'Device & User Correlation',
        text: 'Correlate NAT, private IP, DHCP, MAC and authenticated Hotspot identity when the source data supports that attribution.',
    },
    {
        title: 'Retention & Legal Hold',
        text: 'Define metadata retention periods and preserve records when a legal hold requires them to remain available.',
    },
    {
        title: 'External Archive Storage',
        text: 'Use mounted NAS/NFS or supported S3/S3-compatible storage for organization-controlled archives.',
    },
    {
        title: 'Archive Integrity',
        text: 'Archive objects can be tracked with SHA-256 integrity information for evidence-handling workflows.',
    },
];

const filteringFeatures = [
    {
        title: 'Domain & Website Policies',
        text: 'Create filtering policies for domains and supported destination patterns.',
    },
    {
        title: 'IP & CIDR Controls',
        text: 'Block selected destination addresses or networks where the supported gateway allows it.',
    },
    {
        title: 'Protocol & Port Rules',
        text: 'Apply selected protocol and port controls as part of organization network policy.',
    },
    {
        title: 'Blocklist Mode',
        text: 'Allow Internet access by default and block selected destinations.',
    },
    {
        title: 'Allowlist / Default-Deny',
        text: 'Block by default and permit only approved destinations for tightly controlled networks.',
    },
    {
        title: 'Application Signatures',
        text: 'Use supported signatures while recognizing that encryption, shared CDN infrastructure, VPNs and changing application networks can limit exact identification.',
    },
];

const serviceChoice = [
    {
        title: 'Choose Company / ISP Management when…',
        items: [
            'You sell or manage Internet access for customers.',
            'You use MikroTik for customer access or Hotspot.',
            'You need packages, renewals, billing and staff access.',
            'You operate one or more camps, buildings or network sites.',
        ],
    },
    {
        title: 'Choose Hotel Hotspot when…',
        items: [
            'Reception needs a simple guest Wi-Fi workflow.',
            'Internet access should follow guest stays or checkout.',
            'Hotel staff should not manage raw RouterOS for routine work.',
            'You need vouchers, sessions, usage and Hotel reports.',
        ],
    },
    {
        title: 'Choose Network Compliance when…',
        items: [
            'You need network metadata retention and investigation.',
            'You need domain, IP, protocol or allowlist-based filtering.',
            'You want Compliance as a standalone service or add-on.',
            'You can connect compatible network equipment when ready.',
        ],
    },
];

const faqs = [
    [
        'Can one organization use more than one MikroPanel service?',
        'Yes. A Company or Hotel can use its main service and later receive Network Compliance as an add-on when required.',
    ],
    [
        'Do Company, Hotel and Compliance customers use the same login?',
        'No. Each customer type uses the login designed for that service. The Customer Login Center lists the available customer portals in one place.',
    ],
    [
        'Does Network Compliance decrypt HTTPS messages or passwords?',
        'No. Compliance Logging is designed around network metadata. HTTPS message contents, passwords and page bodies are not decrypted by default.',
    ],
    [
        'Can a public IP always identify one exact person?',
        'No. Accurate attribution depends on source port, exact time, NAT mapping, DHCP or Hotspot identity evidence. ISP CGNAT can require upstream ISP records.',
    ],
    [
        'Can filtering identify every application perfectly?',
        'No. Shared CDNs, encrypted DNS, QUIC, VPNs, ECH and changing application infrastructure can limit exact network-only application identification.',
    ],
    [
        'Can Compliance be activated before the router or NAS is ready?',
        'Yes. The commercial account can exist first. Actual logging or filtering starts only after compatible network components are configured.',
    ],
];

export default function Services({
    brand = 'MikroPanel',
    site = {},
    plans = [],
    compliancePlans = [],
}) {
    const websiteName =
        brand
        || site.website_name
        || 'MikroPanel';

    return (
        <div className="min-h-screen bg-white text-slate-900">
            <Head
                title={`${websiteName} - Services`}
            />

            <header className="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
                <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-5 py-4">
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

                            <div className="hidden text-xs text-slate-500 sm:block">
                                Services
                            </div>
                        </div>
                    </Link>

                    <div className="flex items-center gap-2">
                        <Link
                            href={route(
                                'website.home',
                            )}
                            className="hidden rounded-xl px-4 py-2.5 text-sm font-black text-slate-600 sm:inline-flex"
                        >
                            Home
                        </Link>

                        <Link
                            href={route(
                                'website.login-center',
                            )}
                            className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-black text-slate-800"
                        >
                            Customer Login
                        </Link>
                    </div>
                </div>
            </header>

            <main>
                <section className="border-b border-slate-200 bg-slate-50">
                    <div className="mx-auto max-w-7xl px-5 py-16 md:py-20">
                        <div className="max-w-4xl">
                            <div className="text-sm font-black uppercase tracking-[0.18em] text-cyan-700">
                                Service Guide
                            </div>

                            <h1 className="mt-4 text-4xl font-black tracking-tight md:text-6xl">
                                Understand exactly what each service does
                            </h1>

                            <p className="mt-5 max-w-3xl text-lg leading-8 text-slate-600">
                                {websiteName} provides three main services: Company / ISP Network Management, Hotel Hotspot, and Network Compliance. This page explains what each service solves, its major features, and who it is designed for.
                            </p>

                            <div className="mt-8 flex flex-wrap gap-3">
                                <a
                                    href="#company"
                                    className="rounded-xl bg-slate-950 px-5 py-3 font-black text-white"
                                >
                                    Company / ISP
                                </a>

                                <a
                                    href="#hotel"
                                    className="rounded-xl border border-slate-300 bg-white px-5 py-3 font-black text-slate-800"
                                >
                                    Hotel Hotspot
                                </a>

                                <a
                                    href="#compliance"
                                    className="rounded-xl border border-slate-300 bg-white px-5 py-3 font-black text-slate-800"
                                >
                                    Network Compliance
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-5 py-16">
                    <SectionHeading
                        eyebrow="At a Glance"
                        title="Three services for three different operational needs"
                        text="Start by identifying the problem your organization is trying to solve."
                    />

                    <div className="mt-9 grid gap-5 lg:grid-cols-3">
                        {serviceChoice.map(
                            (service) => (
                                <article
                                    key={
                                        service.title
                                    }
                                    className="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm"
                                >
                                    <h2 className="text-xl font-black">
                                        {service.title}
                                    </h2>

                                    <div className="mt-5 space-y-3">
                                        {service.items.map(
                                            (item) => (
                                                <div
                                                    key={
                                                        item
                                                    }
                                                    className="flex gap-3 text-sm leading-6 text-slate-600"
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
                                </article>
                            ),
                        )}
                    </div>
                </section>

                <ServiceSection
                    id="company"
                    eyebrow="Company / ISP Network Management"
                    title="Operate customer Internet service from one Company panel"
                    intro="This service connects customer access, MikroTik operations, Hotspot, billing, staff permissions and reports into one day-to-day workflow."
                    features={companyFeatures}
                    tone="soft"
                >
                    <div className="mt-10 grid gap-5 lg:grid-cols-3">
                        {plans.map(
                            (plan) => (
                                <CompanyPlan
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

                        {!plans.length && (
                            <EmptyState
                                text="No Company plan is currently available."
                            />
                        )}
                    </div>

                    <div className="mt-8 flex flex-wrap gap-3">
                        <Link
                            href={route(
                                'website.register',
                            )}
                            className="rounded-xl bg-cyan-500 px-6 py-3 font-black text-slate-950"
                        >
                            Register Company
                        </Link>

                        <Link
                            href={route(
                                'login',
                            )}
                            className="rounded-xl border border-slate-300 bg-white px-6 py-3 font-black text-slate-800"
                        >
                            Company Login
                        </Link>
                    </div>
                </ServiceSection>

                <ServiceSection
                    id="hotel"
                    eyebrow="Hotel Hotspot"
                    title="Guest Wi-Fi management designed for hospitality staff"
                    intro="Hotel Hotspot gives reception and management a dedicated workspace for guest access, vouchers, stays, sessions, supported MikroTik operations and reporting."
                    features={hotelFeatures}
                >
                    <div className="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        <InfoCard
                            title="Hotels & Resorts"
                            text="Guest Internet tied more closely to the real stay lifecycle."
                        />

                        <InfoCard
                            title="Hotel Apartments"
                            text="Temporary resident Wi-Fi with organized voucher and expiry handling."
                        />

                        <InfoCard
                            title="Hostels & Guest Houses"
                            text="Simple guest credentials without forcing staff into router administration."
                        />

                        <InfoCard
                            title="Multi-Building Properties"
                            text="Centralized Hotel operations across supported Hotspot routers."
                        />
                    </div>

                    <div className="mt-8 flex flex-wrap gap-3">
                        <Link
                            href={route(
                                'hotel.login',
                            )}
                            className="rounded-xl bg-slate-950 px-6 py-3 font-black text-white"
                        >
                            Hotel Login
                        </Link>

                        {site.contact_url && (
                            <a
                                href={
                                    site.contact_url
                                }
                                className="rounded-xl border border-slate-300 bg-white px-6 py-3 font-black text-slate-800"
                            >
                                Ask About Hotel Service
                            </a>
                        )}
                    </div>
                </ServiceSection>

                <section
                    id="compliance"
                    className="border-y border-slate-200 bg-slate-50"
                >
                    <div className="mx-auto max-w-7xl px-5 py-16 md:py-20">
                        <SectionHeading
                            eyebrow="Network Compliance"
                            title="Logging and filtering can be used separately or together"
                            text="Compliance is designed for authorized organization networks that need metadata retention, investigation support, Internet filtering, or both."
                        />

                        <div className="mt-10 grid gap-8 xl:grid-cols-2">
                            <div>
                                <h3 className="text-2xl font-black">
                                    Compliance Logging
                                </h3>

                                <p className="mt-3 leading-7 text-slate-600">
                                    Focused on supported network metadata, time correlation, identity evidence and organization-controlled retention.
                                </p>

                                <FeatureGrid
                                    items={
                                        loggingFeatures
                                    }
                                />
                            </div>

                            <div>
                                <h3 className="text-2xl font-black">
                                    Network Filtering
                                </h3>

                                <p className="mt-3 leading-7 text-slate-600">
                                    Focused on supported destination and protocol policy controls without requiring Logging to be active.
                                </p>

                                <FeatureGrid
                                    items={
                                        filteringFeatures
                                    }
                                />
                            </div>
                        </div>

                        <div className="mt-10 grid gap-5 lg:grid-cols-2">
                            <div className="rounded-3xl border border-emerald-200 bg-emerald-50 p-7">
                                <h3 className="text-xl font-black text-emerald-950">
                                    Stronger attribution is possible when
                                </h3>

                                <div className="mt-5 space-y-3 text-sm leading-6 text-emerald-950">
                                    <div>✓ Public IP, source port and exact timestamp are available.</div>
                                    <div>✓ NAT mapping exists for the relevant time.</div>
                                    <div>✓ Private IP maps to DHCP or Hotspot records.</div>
                                    <div>✓ Authenticated identity exists where user attribution is required.</div>
                                </div>
                            </div>

                            <div className="rounded-3xl border border-amber-200 bg-amber-50 p-7">
                                <h3 className="text-xl font-black text-amber-950">
                                    Important limitations
                                </h3>

                                <div className="mt-5 space-y-3 text-sm leading-6 text-amber-950">
                                    <div>• A MAC address can identify a device record, not automatically a person.</div>
                                    <div>• Missing port or time evidence can produce multiple candidates.</div>
                                    <div>• ISP CGNAT may require upstream ISP mapping records.</div>
                                    <div>• Encrypted and shared infrastructure can limit exact application identification.</div>
                                </div>
                            </div>
                        </div>

                        <div className="mt-10 grid gap-5 lg:grid-cols-3">
                            {compliancePlans.map(
                                (plan) => (
                                    <CompliancePlan
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
                                <EmptyState
                                    text="No Compliance plan is currently enabled."
                                />
                            )}
                        </div>

                        <div className="mt-8 flex flex-wrap gap-3">
                            <Link
                                href={route(
                                    'compliance.login',
                                )}
                                className="rounded-xl bg-slate-950 px-6 py-3 font-black text-white"
                            >
                                Compliance Login
                            </Link>

                            {site.contact_url && (
                                <a
                                    href={
                                        site.contact_url
                                    }
                                    className="rounded-xl border border-slate-300 bg-white px-6 py-3 font-black text-slate-800"
                                >
                                    Ask About Compliance
                                </a>
                            )}
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-5 py-16">
                    <SectionHeading
                        eyebrow="Customer Access"
                        title="Each service has a clear customer login"
                        text="Visitors do not need to remember every URL. The Customer Login Center shows the available customer portals in one place."
                    />

                    <div className="mt-9 grid gap-5 md:grid-cols-3">
                        <LoginCard
                            title="Company Login"
                            text="For Company owners, Managers and Operators."
                            path="/login"
                            routeName="login"
                        />

                        <LoginCard
                            title="Hotel Login"
                            text="For Hotel administrators and authorized Hotel staff."
                            path="/hotel/login"
                            routeName="hotel.login"
                        />

                        <LoginCard
                            title="Compliance Login"
                            text="For standalone Network Compliance customers."
                            path="/compliance/login"
                            routeName="compliance.login"
                        />
                    </div>

                    <div className="mt-6 rounded-2xl border border-violet-200 bg-violet-50 p-5 text-sm leading-6 text-violet-950">
                        Existing Company or Hotel customers with a Compliance add-on continue using their normal Company or Hotel login and open the linked Compliance workspace from inside that account.
                    </div>
                </section>

                <section className="border-y border-slate-200 bg-slate-50">
                    <div className="mx-auto max-w-5xl px-5 py-16">
                        <SectionHeading
                            eyebrow="FAQ"
                            title="Questions visitors usually need answered"
                            text="These answers explain the service boundaries before a customer signs up."
                        />

                        <div className="mt-9 space-y-4">
                            {faqs.map(
                                ([question, answer]) => (
                                    <details
                                        key={
                                            question
                                        }
                                        className="rounded-2xl border border-slate-200 bg-white p-6"
                                    >
                                        <summary className="cursor-pointer list-none font-black">
                                            {question}
                                        </summary>

                                        <p className="mt-4 text-sm leading-7 text-slate-600">
                                            {answer}
                                        </p>
                                    </details>
                                ),
                            )}
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-7xl px-5 py-16">
                    <div className="grid gap-7 rounded-[2rem] bg-slate-950 p-8 text-white md:p-10 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <h2 className="text-3xl font-black">
                                Still not sure which service you need?
                            </h2>

                            <p className="mt-3 max-w-3xl leading-7 text-slate-300">
                                Review the sections above, then contact us for help choosing between Company / ISP Management, Hotel Hotspot and Network Compliance.
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            {site.contact_url && (
                                <a
                                    href={
                                        site.contact_url
                                    }
                                    className="rounded-xl bg-cyan-400 px-6 py-3 font-black text-slate-950"
                                >
                                    Contact Us
                                </a>
                            )}

                            <Link
                                href={route(
                                    'website.login-center',
                                )}
                                className="rounded-xl border border-white/20 px-6 py-3 font-black text-white"
                            >
                                Customer Login
                            </Link>
                        </div>
                    </div>
                </section>
            </main>

            <footer className="border-t border-slate-200 bg-white">
                <div className="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-9 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="font-black">
                            {websiteName}
                        </div>

                        <div className="mt-1 text-sm text-slate-500">
                            {site.footer_text
                                || site.website_tagline
                                || 'Network Management, Hotel Wi-Fi & Compliance Services'}
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-5 text-sm font-bold text-slate-500">
                        <Link
                            href={route(
                                'website.home',
                            )}
                        >
                            Home
                        </Link>

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

            <h2 className="mt-3 text-3xl font-black tracking-tight md:text-4xl">
                {title}
            </h2>

            <p className="mt-4 leading-7 text-slate-600">
                {text}
            </p>
        </div>
    );
}

function ServiceSection({
    id,
    eyebrow,
    title,
    intro,
    features,
    tone = 'white',
    children,
}) {
    return (
        <section
            id={id}
            className={
                tone === 'soft'
                    ? 'border-y border-slate-200 bg-slate-50'
                    : 'bg-white'
            }
        >
            <div className="mx-auto max-w-7xl px-5 py-16 md:py-20">
                <SectionHeading
                    eyebrow={eyebrow}
                    title={title}
                    text={intro}
                />

                <FeatureGrid
                    items={features}
                />

                {children}
            </div>
        </section>
    );
}

function FeatureGrid({
    items,
}) {
    return (
        <div className="mt-9 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            {items.map(
                (item) => (
                    <article
                        key={
                            item.title
                        }
                        className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
                    >
                        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-cyan-50 font-black text-cyan-700">
                            ✓
                        </div>

                        <h3 className="mt-4 text-lg font-black">
                            {item.title}
                        </h3>

                        <p className="mt-3 text-sm leading-6 text-slate-600">
                            {item.text}
                        </p>
                    </article>
                ),
            )}
        </div>
    );
}

function CompanyPlan({
    plan,
    site,
}) {
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
              : `QAR ${Number(
                    plan.price,
                ).toLocaleString(
                    undefined,
                    {
                        maximumFractionDigits: 2,
                    },
                )}`;

    return (
        <article className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 className="text-xl font-black">
                {plan.name}
            </h3>

            <div className="mt-3 text-3xl font-black text-cyan-700">
                {price}
            </div>

            <div className="mt-2 text-sm text-slate-500">
                {plan.validity_days} days
                {!plan.is_unlimited
                    && ` · ${plan.client_limit} clients`}
            </div>

            {plan.is_unlimited ? (
                <a
                    href={
                        site.contact_url
                        || '#company'
                    }
                    className="mt-6 block rounded-xl bg-slate-950 px-5 py-3 text-center font-black text-white"
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
                    className="mt-6 block rounded-xl bg-cyan-500 px-5 py-3 text-center font-black text-slate-950"
                >
                    {plan.is_free_trial
                        ? 'Start Free Trial'
                        : 'Apply for this Plan'}
                </Link>
            )}
        </article>
    );
}

function CompliancePlan({
    plan,
}) {
    const price =
        plan.call_for_price
            ? 'Call for Price'
            : `QAR ${Number(
                  plan.monthly_price,
              ).toLocaleString(
                  undefined,
                  {
                      maximumFractionDigits: 2,
                  },
              )} / month`;

    return (
        <article className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div className="text-xs font-black uppercase tracking-[0.16em] text-violet-700">
                {plan.service_type}
            </div>

            <h3 className="mt-2 text-xl font-black">
                {plan.name}
            </h3>

            <div className="mt-3 text-3xl font-black text-cyan-700">
                {price}
            </div>

            {plan.description && (
                <p className="mt-3 text-sm leading-6 text-slate-600">
                    {plan.description}
                </p>
            )}
        </article>
    );
}

function InfoCard({
    title,
    text,
}) {
    return (
        <article className="rounded-3xl border border-slate-200 bg-slate-50 p-6">
            <h3 className="font-black">
                {title}
            </h3>

            <p className="mt-2 text-sm leading-6 text-slate-600">
                {text}
            </p>
        </article>
    );
}

function LoginCard({
    title,
    text,
    path,
    routeName,
}) {
    return (
        <article className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 className="text-xl font-black">
                {title}
            </h3>

            <p className="mt-2 text-sm leading-6 text-slate-600">
                {text}
            </p>

            <code className="mt-4 block rounded-xl bg-slate-100 px-3 py-2 text-xs text-slate-600">
                {path}
            </code>

            <Link
                href={route(
                    routeName,
                )}
                className="mt-5 block rounded-xl bg-slate-950 px-5 py-3 text-center font-black text-white"
            >
                Open Login
            </Link>
        </article>
    );
}

function EmptyState({
    text,
}) {
    return (
        <div className="rounded-3xl border border-slate-200 bg-white p-8 text-slate-600 lg:col-span-3">
            {text}
        </div>
    );
}
