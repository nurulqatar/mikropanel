import {
    Head,
    Link,
} from '@inertiajs/react';

const featureCards = [
    {
        title: 'MAC Client Control',
        text: 'Create clients, allocate IP addresses, manage MAC changes, renew service and synchronize ARP/DHCP from one panel.',
    },
    {
        title: 'Multi-Zone Operations',
        text: 'Separate camps, buildings and network areas with independent Network Zones while managing them from one reseller account.',
    },
    {
        title: 'Hotspot & Vouchers',
        text: 'Operate Hotspot servers, generate vouchers, manage sessions, billing, branding and usage from the same platform.',
    },
    {
        title: 'Manager Cash Accounting',
        text: 'Track daily collections, expenses, cash in hand, Manager handovers and monthly reconciliation without losing audit history.',
    },
    {
        title: 'Operators & Permissions',
        text: 'Create staff accounts with controlled access. Operators work only inside their assigned operational scope.',
    },
    {
        title: 'Reports & Finance',
        text: 'Payments, invoices, expenses, collections, refunds and downloadable accounting reports remain connected to client activity.',
    },
];

const workflow = [
    'Choose an available reseller package.',
    'Create your reseller account from this website.',
    '7-day free trial accounts activate instantly.',
    'Other packages are reviewed by Super Admin.',
    'Login and create your first Network Zone.',
    'Add MikroTik routers, clients, packages and operators.',
];

const faqs = [
    [
        'Do I need to install MikroPanel on my own server?',
        'No. Your reseller account works inside the hosted MikroPanel platform after activation.',
    ],
    [
        'How does the 7-day free trial work?',
        'Any active package configured as 7 days with a price of 0 QAR is treated as the free trial and is activated automatically after registration.',
    ],
    [
        'Do paid packages activate immediately?',
        'No. Paid and non-trial package registrations remain pending until Super Admin reviews and approves the reseller account.',
    ],
    [
        'Can I manage multiple sites?',
        'Yes. The platform is designed around Network Zones so separate camps, sites or customer networks can be managed independently.',
    ],
    [
        'Can staff collect customer payments?',
        'Yes. Permissions can be assigned to operators and Managers. Manager cash collections and expenses can also be reconciled through the Manager Cash ledger.',
    ],
    [
        'Is MikroTik synchronization automatic?',
        'The panel includes MikroTik API integration and queued synchronization for supported client, ARP, DHCP and router operations.',
    ],
];

const money = (value) =>
    Number(value || 0).toLocaleString(
        undefined,
        {
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        },
    );


/*
 * HOTEL_HOTSPOT_PUBLIC_DETAILS_V1
 *
 * Public marketing content reflects the
 * Hotel Hotspot capabilities already present
 * in MikroPanel.
 */
const hotelHotspotFeatures = [
    {
        title: 'Dedicated Hotel Management',
        text: 'Hotels receive a separate login and operational workspace instead of sharing the normal Company panel. Hotel administrators can manage their own Wi-Fi operation from one focused dashboard.',
    },
    {
        title: 'Receptionist & Staff Access',
        text: 'Create receptionist and hotel staff accounts with controlled permissions so front-desk teams can handle guest Wi-Fi tasks without receiving unrestricted administrative access.',
    },
    {
        title: 'Guest & Stay Management',
        text: 'Keep guest records, room or stay information, check-in and checkout-linked Wi-Fi access together so internet access follows the guest stay instead of being managed separately.',
    },
    {
        title: 'Guest Wi-Fi Vouchers',
        text: 'Create guest vouchers linked to Wi-Fi profiles and stays. Voucher lifecycle can follow checkout time, reducing the need for staff to manually remember when guest access should end.',
    },
    {
        title: 'Automatic Checkout Expiry',
        text: 'When a guest stay reaches checkout, MikroPanel can expire the related Hotel Hotspot voucher and queue MikroTik cleanup automatically.',
    },
    {
        title: 'Multi-Router Voucher Sync',
        text: 'A hotel can operate multiple supported MikroTik Hotspot routers while MikroPanel keeps hotel vouchers synchronized across the enabled router set.',
    },
    {
        title: 'MikroTik Router Management',
        text: 'Add Hotel MikroTik routers, test connectivity, apply router quotas and generate Hotspot setup information from the Hotel management system.',
    },
    {
        title: 'Automatic Sync & Recovery',
        text: 'Queued voucher synchronization, retry handling, stale-job protection, failed-sync recovery, router backfill and periodic reconciliation help keep the panel and RouterOS state aligned.',
    },
    {
        title: 'Live Connected Guests',
        text: 'Hotel telemetry can track active Hotspot sessions so staff can see connected users and current guest network activity from the Hotel panel.',
    },
    {
        title: 'Usage & Session History',
        text: 'Track session history, reconnect activity, per-guest usage, per-router usage and accumulated voucher traffic for operational visibility.',
    },
    {
        title: 'Reports & CSV Export',
        text: 'Hotel reporting includes usage information and downloadable CSV data so management can review activity outside the dashboard when required.',
    },
    {
        title: '184-Language Guest Portal',
        text: 'The Hotel guest portal includes a global language catalog with 184 supported language entries, helping international properties provide a more accessible Wi-Fi experience.',
    },
    {
        title: 'Hotel Branding',
        text: 'Use hotel-specific branding and logo settings so the Hotel Wi-Fi experience can reflect the property identity rather than a generic network page.',
    },
    {
        title: 'Wi-Fi Profiles',
        text: 'Create Hotel Wi-Fi profiles for different guest access requirements and use those profiles when generating or managing vouchers.',
    },
    {
        title: 'Billing & Subscription Control',
        text: 'Hotel commercial management supports subscription billing, renewal workflows, invoice records and Hotel service lifecycle management.',
    },
    {
        title: 'Manual Payments',
        text: 'Record Hotel subscription payments manually today while maintaining invoice and payment history inside the platform.',
    },
    {
        title: 'Automatic Invoice Operations',
        text: 'Commercial maintenance can support scheduled invoice operations and subscription state handling without requiring daily manual administration.',
    },
    {
        title: 'Suspend & Reactivate',
        text: 'Hotel service access can follow subscription status, including suspension and reactivation workflows controlled from the platform.',
    },
    {
        title: 'Advanced Voucher Control',
        text: 'Hotel staff can manage voucher state and authorized network access while the backend coordinates the required MikroTik synchronization.',
    },
    {
        title: 'Live Session Disconnect',
        text: 'Authorized Hotel operations can terminate an active guest Hotspot session when access must be stopped immediately.',
    },
    {
        title: 'Router & Sync Alerts',
        text: 'Operational alerts can surface router and voucher synchronization problems so staff are not required to discover failures only after a guest reports an issue.',
    },
    {
        title: 'Hotel Notifications',
        text: 'Hotel-specific notifications provide a central place for important operational events and service information.',
    },
    {
        title: 'Audit History',
        text: 'Important Hotel operations can be recorded in an audit trail, with audit CSV export available for management and accountability.',
    },
    {
        title: 'Safer RouterOS Ownership',
        text: 'Voucher synchronization uses ownership-aware RouterOS handling to reduce the risk of changing an unrelated manually created Hotspot user with the same username.',
    },
];

const hotelHotspotBenefits = [
    'Faster guest Wi-Fi onboarding at reception',
    'Automatic checkout-linked access expiry',
    'One dashboard for guests, vouchers, routers and usage',
    'Controlled receptionist and staff permissions',
    'Multi-router operation for larger properties',
    'Hotel-branded guest Wi-Fi experience',
    'International guest portal language support',
    'Usage, session and management reporting',
    'Subscription, invoice and payment administration',
    'Alerts, audit history and operational visibility',
];

const hotelHotspotWorkflow = [
    {
        step: '01',
        title: 'Create Hotel',
        text: 'Super Admin creates the Hotel account, selects the Hotel plan and activates the required subscription.',
    },
    {
        step: '02',
        title: 'Configure Hotel',
        text: 'Hotel administrator signs in, adds branding, staff permissions and Wi-Fi profiles for the property.',
    },
    {
        step: '03',
        title: 'Connect MikroTik',
        text: 'Add the Hotel MikroTik router or routers, verify connectivity and configure the Hotel Hotspot environment.',
    },
    {
        step: '04',
        title: 'Register Guest',
        text: 'Reception records the guest and stay details, including the expected checkout period.',
    },
    {
        step: '05',
        title: 'Issue Wi-Fi Access',
        text: 'Create the guest voucher and let MikroPanel queue synchronization to the appropriate Hotel routers.',
    },
    {
        step: '06',
        title: 'Monitor & Close',
        text: 'Track sessions and usage during the stay. At checkout, access can expire and RouterOS cleanup is handled through the maintenance workflow.',
    },
];

export default function Home({
    brand = 'MikroPanel',
    site = {},
    plans = [],

    compliancePlans = [],
}) {
    return (
        <div className="min-h-screen bg-slate-950 text-white">
            <Head
                title={`${brand} — ISP & MikroTik Reseller Platform`}
            >
                <meta
                    name="description"
                    content="Professional MikroTik ISP, Company and Hotel Hotspot management platform with guest Wi-Fi vouchers, MikroTik automation, billing, live usage, reports and multi-router control."
                />
            </Head>

            <header className="sticky top-0 z-50 border-b border-white/10 bg-slate-950/90 backdrop-blur">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-4">
                    <Link
                        href={route('website.home')}
                        className="flex items-center gap-3 text-2xl font-black tracking-tight"
                    >
                        {site.logo_url && (
                            <img
                                src={site.logo_url}
                                alt={site.website_name || brand}
                                className="h-10 w-auto max-w-44 object-contain"
                            />
                        )}

                        <span>
                            {site.website_name || brand}
                        </span>
                    </Link>

                    <nav className="hidden items-center gap-7 text-sm font-semibold text-slate-300 lg:flex">
                        <a href="#features">
                            Features
                        </a>
                        <a href="#hotel-hotspot">
                            Hotel Hotspot
                        </a>
                        <a href="#network-compliance" className="transition hover:text-white">Network Compliance</a>
                        <a href="#plans">
                            Pricing
                        </a>
                        <a href="#workflow">
                            How it works
                        </a>
                        <a href="#faq">
                            FAQ
                        </a>
                    </nav>

                    <div className="flex items-center gap-3">
                        <Link
                            href={route('login')}
                            className="rounded-xl border border-white/15 px-4 py-2 text-sm font-bold text-white hover:bg-white/10"
                        >
                            Login
                        </Link>

                        <Link
                            href={route('website.register')}
                            className="rounded-xl bg-cyan-400 px-4 py-2 text-sm font-black text-slate-950 hover:bg-cyan-300"
                        >
                            Become a Reseller
                        </Link>
                    </div>
                </div>
            </header>

            <main>
                <section className="relative overflow-hidden">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(34,211,238,0.20),_transparent_35%),radial-gradient(circle_at_bottom_left,_rgba(99,102,241,0.18),_transparent_35%)]" />

                    <div className="relative mx-auto grid max-w-7xl gap-14 px-5 py-24 lg:grid-cols-[1.1fr_.9fr] lg:py-32">
                        <div>
                            <div className="mb-5 inline-flex rounded-full border border-cyan-400/30 bg-cyan-400/10 px-4 py-2 text-sm font-bold text-cyan-300">
                                {site.hero_badge ||
                                    'MikroTik ISP Operations · Company SaaS'}
                            </div>

                            <h1 className="max-w-4xl text-5xl font-black leading-[1.05] tracking-tight sm:text-6xl">
                                {site.hero_title ||
                                    'Run your network, clients and cash flow from one professional control panel.'}
                            </h1>

                            <p className="mt-7 max-w-3xl text-lg leading-8 text-slate-300">
                                {site.hero_text ||
                                    `${brand} provides professional ISP and MikroTik operations from one platform.`}
                            </p>

                            <div className="mt-9 flex flex-wrap gap-4">
                                <Link
                                    href={route('website.register')}
                                    className="rounded-2xl bg-cyan-400 px-7 py-4 font-black text-slate-950 shadow-xl shadow-cyan-500/20 hover:bg-cyan-300"
                                >
                                    Start Reseller Account
                                </Link>

                                <a
                                    href="#plans"
                                    className="rounded-2xl border border-white/15 bg-white/5 px-7 py-4 font-bold hover:bg-white/10"
                                >
                                    View Packages
                                </a>
                            </div>

                            <div className="mt-10 grid max-w-2xl grid-cols-3 gap-3">
                                <HeroStat
                                    value="MAC + Hotspot"
                                    label="One platform"
                                />
                                <HeroStat
                                    value="Multi-Zone"
                                    label="Site isolation"
                                />
                                <HeroStat
                                    value="Audit Ready"
                                    label="Cash & finance"
                                />
                            </div>
                        </div>

                        <div className="rounded-[2rem] border border-white/10 bg-white/[0.06] p-5 shadow-2xl backdrop-blur">
                            <div className="rounded-3xl bg-slate-900 p-6 ring-1 ring-white/10">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <div className="text-sm font-bold text-slate-400">
                                            Operations Overview
                                        </div>
                                        <div className="mt-1 text-2xl font-black">
                                            Live Network Control
                                        </div>
                                    </div>

                                    <div className="rounded-full bg-emerald-400/15 px-3 py-1 text-xs font-black text-emerald-300">
                                        ● ONLINE
                                    </div>
                                </div>

                                <div className="mt-6 grid grid-cols-2 gap-3">
                                    <DemoCard
                                        label="Active Clients"
                                        value="248"
                                    />
                                    <DemoCard
                                        label="Network Zones"
                                        value="06"
                                    />
                                    <DemoCard
                                        label="Today's Collection"
                                        value="QAR 4,820"
                                    />
                                    <DemoCard
                                        label="Manager Cash"
                                        value="QAR 2,140"
                                    />
                                </div>

                                <div className="mt-5 rounded-2xl bg-slate-950 p-5">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-slate-400">
                                            Router Sync
                                        </span>
                                        <span className="font-bold text-cyan-300">
                                            Healthy
                                        </span>
                                    </div>

                                    <div className="mt-3 h-2 rounded-full bg-slate-800">
                                        <div className="h-2 w-[88%] rounded-full bg-cyan-400" />
                                    </div>

                                    <div className="mt-6 space-y-3 text-sm">
                                        <Activity
                                            title="Client renewed"
                                            detail="Zone A · Cash payment"
                                        />
                                        <Activity
                                            title="ARP / DHCP synchronized"
                                            detail="MikroTik queue completed"
                                        />
                                        <Activity
                                            title="Manager expense recorded"
                                            detail="Cash ledger updated"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section
                    id="features"
                    className="border-y border-white/10 bg-slate-900/60 py-24"
                >
                    <div className="mx-auto max-w-7xl px-5">
                        <SectionTitle
                            eyebrow="Platform"
                            title="Everything needed to operate a reseller ISP network"
                            text="Built for day-to-day network operations, customer billing and financial accountability."
                        />

                        <div className="mt-12 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                            {featureCards.map((item) => (
                                <div
                                    key={item.title}
                                    className="rounded-3xl border border-white/10 bg-white/[0.04] p-7"
                                >
                                    <div className="mb-5 flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-400/15 text-xl text-cyan-300">
                                        ✓
                                    </div>

                                    <h3 className="text-xl font-black">
                                        {item.title}
                                    </h3>

                                    <p className="mt-3 leading-7 text-slate-400">
                                        {item.text}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>

                <section className="py-24">
                    <div className="mx-auto grid max-w-7xl gap-12 px-5 lg:grid-cols-2">
                        <div>
                            <SectionTitle
                                eyebrow="Network Control"
                                title="Manage separate sites without mixing customer data"
                                text="Network Zones keep site operations separated while the reseller account stays centralized."
                            />

                            <div className="mt-8 space-y-4">
                                {[
                                    'Create independent MAC and Hotspot Network Zones.',
                                    'Maintain zone-local client and IP pool operations.',
                                    'Assign ordinary operators to their working zone.',
                                    'Managers can switch operational zones where permitted.',
                                    'Synchronize supported client changes to MikroTik.',
                                ].map((text) => (
                                    <CheckLine
                                        key={text}
                                        text={text}
                                    />
                                ))}
                            </div>
                        </div>

                        <div className="rounded-3xl border border-white/10 bg-gradient-to-br from-cyan-400/10 to-indigo-500/10 p-8">
                            <div className="text-sm font-black uppercase tracking-[0.2em] text-cyan-300">
                                Finance Control
                            </div>

                            <h3 className="mt-4 text-3xl font-black">
                                Know where every cash amount came from.
                            </h3>

                            <p className="mt-4 leading-7 text-slate-300">
                                Customer payments, expenses, handovers,
                                refunds and Manager cash movements remain
                                traceable instead of being reduced to a
                                single unexplained balance.
                            </p>

                            <div className="mt-8 grid gap-4 sm:grid-cols-2">
                                <InfoBox
                                    title="Daily Collection"
                                    text="Track cash collected during the day."
                                />
                                <InfoBox
                                    title="Cash in Hand"
                                    text="See current Manager physical cash."
                                />
                                <InfoBox
                                    title="Admin Handover"
                                    text="Approval-based Manager to Admin transfer."
                                />
                                <InfoBox
                                    title="Reconciliation"
                                    text="Opening + movements = closing balance."
                                />
                            </div>
                        </div>
                    </div>
                </section>

                {/* HOTEL_HOTSPOT_PUBLIC_DETAILS_V1 */}
                <section
                    id="hotel-hotspot"
                    className="relative overflow-hidden border-y border-white/10 bg-slate-900/60 py-24"
                >
                    <div className="pointer-events-none absolute inset-0 overflow-hidden">
                        <div className="absolute -left-32 top-16 h-80 w-80 rounded-full bg-cyan-500/10 blur-3xl" />
                        <div className="absolute -right-32 bottom-0 h-96 w-96 rounded-full bg-emerald-500/10 blur-3xl" />
                    </div>

                    <div className="relative mx-auto max-w-7xl px-5">
                        <SectionTitle
                            eyebrow="Hotel Hotspot"
                            title="Complete guest Wi-Fi management for hotels"
                            text="Manage Hotel Wi-Fi as a complete operational service — from Hotel administration and receptionist access to guest stays, vouchers, MikroTik routers, live sessions, reporting, billing and automatic expiry."
                        />

                        <div className="mt-12 grid gap-6 lg:grid-cols-[1.25fr_.75fr]">
                            <div className="rounded-[2rem] border border-cyan-400/20 bg-gradient-to-br from-cyan-400/10 via-white/[0.04] to-emerald-400/10 p-7 md:p-10">
                                <div className="inline-flex rounded-full border border-cyan-300/20 bg-cyan-300/10 px-4 py-2 text-xs font-black uppercase tracking-[0.18em] text-cyan-200">
                                    Built for hospitality operations
                                </div>

                                <h3 className="mt-6 max-w-3xl text-3xl font-black leading-tight md:text-5xl">
                                    Guest Internet from check-in
                                    to checkout — managed from
                                    one Hotel panel.
                                </h3>

                                <p className="mt-6 max-w-3xl text-base leading-8 text-slate-300 md:text-lg">
                                    MikroPanel Hotel Hotspot combines
                                    guest management, stay-based Wi-Fi
                                    vouchers, MikroTik synchronization,
                                    live network visibility and commercial
                                    controls in one dedicated Hotel
                                    management system.
                                </p>

                                <div className="mt-8 flex flex-wrap gap-3">
                                    {[
                                        'Guest & Stay Management',
                                        'MikroTik Integration',
                                        'Multi-Router Sync',
                                        'Live Sessions',
                                        'Usage Reports',
                                        'Hotel Billing',
                                        '184-Language Portal',
                                        'Staff Permissions',
                                    ].map((item) => (
                                        <span
                                            key={item}
                                            className="rounded-full border border-white/10 bg-slate-950/60 px-4 py-2 text-sm font-bold text-slate-200"
                                        >
                                            {item}
                                        </span>
                                    ))}
                                </div>

                                <div className="mt-9 flex flex-wrap gap-3">
                                    <Link
                                        href={route('hotel.login')}
                                        className="rounded-xl bg-cyan-400 px-6 py-3.5 font-black text-slate-950 transition hover:bg-cyan-300"
                                    >
                                        Hotel Login
                                    </Link>

                                    <a
                                        href="#plans"
                                        className="rounded-xl border border-white/15 bg-white/5 px-6 py-3.5 font-black text-white transition hover:bg-white/10"
                                    >
                                        View Company Plans
                                    </a>
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                                <div className="rounded-3xl border border-white/10 bg-slate-950/70 p-6">
                                    <div className="text-sm font-black uppercase tracking-[0.18em] text-emerald-300">
                                        Guest Experience
                                    </div>
                                    <div className="mt-3 text-2xl font-black">
                                        Simple access for every stay
                                    </div>
                                    <p className="mt-3 leading-7 text-slate-400">
                                        Reception can manage guest Wi-Fi
                                        around the actual Hotel stay instead
                                        of maintaining separate network notes
                                        and manual expiry lists.
                                    </p>
                                </div>

                                <div className="rounded-3xl border border-white/10 bg-slate-950/70 p-6">
                                    <div className="text-sm font-black uppercase tracking-[0.18em] text-cyan-300">
                                        Network Operations
                                    </div>
                                    <div className="mt-3 text-2xl font-black">
                                        MikroTik automation behind the scenes
                                    </div>
                                    <p className="mt-3 leading-7 text-slate-400">
                                        Router synchronization, voucher
                                        provisioning, session telemetry,
                                        retries and reconciliation reduce
                                        repetitive RouterOS administration.
                                    </p>
                                </div>

                                <div className="rounded-3xl border border-white/10 bg-slate-950/70 p-6">
                                    <div className="text-sm font-black uppercase tracking-[0.18em] text-violet-300">
                                        Management
                                    </div>
                                    <div className="mt-3 text-2xl font-black">
                                        Visibility beyond voucher creation
                                    </div>
                                    <p className="mt-3 leading-7 text-slate-400">
                                        Billing, payments, alerts, audit
                                        records, live sessions and usage
                                        reports give Hotel management a
                                        clearer operational picture.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="mt-20">
                            <div className="max-w-3xl">
                                <div className="text-sm font-black uppercase tracking-[0.2em] text-cyan-300">
                                    Complete Feature Set
                                </div>

                                <h3 className="mt-3 text-3xl font-black md:text-4xl">
                                    Everything needed to operate Hotel guest Wi-Fi
                                </h3>

                                <p className="mt-4 leading-7 text-slate-400">
                                    The Hotel Hotspot module is separated
                                    from ordinary Company operations while
                                    still using MikroPanel as the central
                                    management platform.
                                </p>
                            </div>

                            <div className="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                {hotelHotspotFeatures.map(
                                    (feature, index) => (
                                        <div
                                            key={feature.title}
                                            className="group rounded-3xl border border-white/10 bg-white/[0.04] p-6 transition hover:-translate-y-1 hover:border-cyan-400/30 hover:bg-white/[0.06]"
                                        >
                                            <div className="flex items-start gap-4">
                                                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-cyan-400/10 text-sm font-black text-cyan-300">
                                                    {String(
                                                        index + 1,
                                                    ).padStart(
                                                        2,
                                                        '0',
                                                    )}
                                                </div>

                                                <div>
                                                    <h4 className="text-lg font-black">
                                                        {feature.title}
                                                    </h4>

                                                    <p className="mt-2 text-sm leading-7 text-slate-400">
                                                        {feature.text}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>

                        <div className="mt-20 grid gap-8 lg:grid-cols-2">
                            <div className="rounded-[2rem] border border-emerald-400/20 bg-emerald-400/[0.06] p-7 md:p-9">
                                <div className="text-sm font-black uppercase tracking-[0.2em] text-emerald-300">
                                    Benefits
                                </div>

                                <h3 className="mt-3 text-3xl font-black">
                                    Why Hotel teams benefit from MikroPanel
                                </h3>

                                <div className="mt-7 grid gap-3 sm:grid-cols-2">
                                    {hotelHotspotBenefits.map(
                                        (item) => (
                                            <div
                                                key={item}
                                                className="flex gap-3 rounded-2xl bg-slate-950/40 p-4"
                                            >
                                                <span className="mt-0.5 text-emerald-300">
                                                    ✓
                                                </span>

                                                <span className="text-sm font-semibold leading-6 text-slate-200">
                                                    {item}
                                                </span>
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>

                            <div className="rounded-[2rem] border border-white/10 bg-slate-950/60 p-7 md:p-9">
                                <div className="text-sm font-black uppercase tracking-[0.2em] text-cyan-300">
                                    Suitable For
                                </div>

                                <h3 className="mt-3 text-3xl font-black">
                                    From small properties to multi-router Hotels
                                </h3>

                                <p className="mt-4 leading-7 text-slate-400">
                                    The Hotel module can support properties
                                    that need controlled guest Wi-Fi without
                                    forcing reception staff to manage raw
                                    MikroTik configuration directly.
                                </p>

                                <div className="mt-7 space-y-4">
                                    {[
                                        [
                                            'Hotels & Resorts',
                                            'Guest Wi-Fi tied to stays, rooms and checkout periods.',
                                        ],
                                        [
                                            'Hotel Apartments',
                                            'Manage temporary resident internet access with staff-controlled vouchers.',
                                        ],
                                        [
                                            'Hostels & Guest Houses',
                                            'Simplify short-stay Wi-Fi credentials and expiry management.',
                                        ],
                                        [
                                            'Multi-Building Properties',
                                            'Use multiple Hotel MikroTik routers while maintaining centralized voucher operations.',
                                        ],
                                    ].map(([title, text]) => (
                                        <div
                                            key={title}
                                            className="rounded-2xl border border-white/10 p-5"
                                        >
                                            <div className="font-black">
                                                {title}
                                            </div>
                                            <div className="mt-1 text-sm leading-6 text-slate-400">
                                                {text}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        <div className="mt-20">
                            <div className="text-center">
                                <div className="text-sm font-black uppercase tracking-[0.2em] text-cyan-300">
                                    Hotel Workflow
                                </div>

                                <h3 className="mt-3 text-3xl font-black md:text-4xl">
                                    From Hotel setup to guest checkout
                                </h3>
                            </div>

                            <div className="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                                {hotelHotspotWorkflow.map(
                                    (item) => (
                                        <div
                                            key={item.step}
                                            className="rounded-3xl border border-white/10 bg-white/[0.04] p-6"
                                        >
                                            <div className="text-3xl font-black text-cyan-300">
                                                {item.step}
                                            </div>

                                            <h4 className="mt-4 text-xl font-black">
                                                {item.title}
                                            </h4>

                                            <p className="mt-3 text-sm leading-7 text-slate-400">
                                                {item.text}
                                            </p>
                                        </div>
                                    ),
                                )}
                            </div>
                        </div>

                        <div className="mt-20 overflow-hidden rounded-[2rem] border border-cyan-400/20 bg-gradient-to-r from-cyan-400/10 via-slate-950 to-emerald-400/10 p-8 md:p-12">
                            <div className="grid items-center gap-8 lg:grid-cols-[1fr_auto]">
                                <div>
                                    <div className="text-sm font-black uppercase tracking-[0.2em] text-cyan-300">
                                        Hotel Hotspot Platform
                                    </div>

                                    <h3 className="mt-3 text-3xl font-black md:text-4xl">
                                        Give reception an easier Wi-Fi workflow.
                                        Give management better control.
                                    </h3>

                                    <p className="mt-4 max-w-3xl leading-7 text-slate-300">
                                        Guest access, Hotel staff, MikroTik
                                        routers, voucher automation, usage,
                                        reports and commercial operations can
                                        be managed from one dedicated Hotel
                                        Hotspot system.
                                    </p>
                                </div>

                                <div className="flex flex-col gap-3 sm:flex-row lg:flex-col">
                                    <Link
                                        href={route('hotel.login')}
                                        className="rounded-xl bg-cyan-400 px-7 py-3.5 text-center font-black text-slate-950 hover:bg-cyan-300"
                                    >
                                        Hotel Login
                                    </Link>

                                    <a
                                        href="#plans"
                                        className="rounded-xl border border-white/15 px-7 py-3.5 text-center font-black text-white hover:bg-white/10"
                                    >
                                        Explore Plans
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

            {/* NETWORK_COMPLIANCE_PUBLIC_DETAILS_V1 */}
            <section
                id="network-compliance"
                className="border-t border-white/10 bg-slate-950 px-5 py-20 text-white"
            >
                <div className="mx-auto max-w-7xl">
                    <div className="max-w-4xl">
                        <div className="text-sm font-black uppercase tracking-[0.2em] text-cyan-300">
                            Network Compliance & Filtering
                        </div>
                        <h2 className="mt-4 text-3xl font-black md:text-5xl">
                            Logging and Filtering — Separate Monthly Services or One Complete Package
                        </h2>
                        <p className="mt-5 text-lg leading-8 text-slate-300">
                            Available to standalone organizations and to activated MikroPanel MAC, Hotspot and Hotel Hotspot customers. Choose Logging only, Filtering only, or both from the same professional platform.
                        </p>
                    </div>

                    <div className="mt-10 grid gap-5 lg:grid-cols-3">
                        <ComplianceServiceCard
                            title="Compliance Logging"
                            badge="Independent Service"
                            text="Searchable network metadata and client attribution for authorized organization networks, with customer-selected external archive storage."
                            items={[
                                'Public IP + translated source port + exact-time search',
                                'IPFIX NAT mapping and destination metadata',
                                'DHCP / Hotspot identity correlation',
                                'Device or authenticated-user attribution when source data is available',
                                'Mounted NAS / NFS archive',
                                'Amazon S3 and S3-compatible cloud/NAS archive',
                                'SHA-256 archive integrity records',
                                'Retention policy and legal hold controls',
                                'Investigation CSV export and audit trail',
                            ]}
                        />

                        <ComplianceServiceCard
                            title="Network Filtering"
                            badge="Independent Service"
                            text="Control Internet destinations on supported organization networks using domain, IP, server, application and protocol policies."
                            items={[
                                'Website / domain blocking',
                                'IP and CIDR blocking',
                                'Server destination blocking',
                                'Custom third-party application signatures',
                                'Application category filtering',
                                'Protocol / port policies',
                                'Blocklist mode — allow by default',
                                'Allowlist / Default-Deny mode — block by default',
                                'Versioned MikroPanel-owned MikroTik policy deployment',
                            ]}
                        />

                        <ComplianceServiceCard
                            title="Compliance Complete"
                            badge="Logging + Filtering"
                            text="Combine attribution, external archives and filtering in one Compliance workspace while keeping both commercial entitlements independent."
                            items={[
                                'Standalone Compliance organization and login',
                                'Same-panel access for activated MAC / Hotspot Company owners',
                                'Same-panel access for activated Hotel Admins',
                                'Separate Logging and Filtering subscriptions',
                                'MikroTik capability testing and guided setup',
                                'Collector registration with one-time token',
                                'Central Super Admin plans, organizations and subscriptions',
                                'Router-owned policy namespace isolation',
                            ]}
                        />
                    </div>

                    <div className="mt-10">
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <div className="text-sm font-black uppercase tracking-[0.18em] text-violet-300">
                                    Current Service Plans
                                </div>

                                <h3 className="mt-2 text-3xl font-black">
                                    Pricing managed by Super Admin
                                </h3>
                            </div>

                            <Link
                                href={route(
                                    'website.login-center',
                                )}
                                className="rounded-xl border border-white/15 px-5 py-3 font-black text-white hover:bg-white/5"
                            >
                                View All Panel Logins
                            </Link>
                        </div>

                        <div className="mt-6 grid gap-4 lg:grid-cols-3">
                            {compliancePlans.map(
                                (plan) => (
                                    <div
                                        key={plan.id}
                                        className="rounded-2xl border border-white/10 bg-white/[0.04] p-5"
                                    >
                                        <div className="text-xs font-black uppercase tracking-[0.16em] text-violet-300">
                                            {plan.service_type}
                                        </div>

                                        <div className="mt-2 text-xl font-black">
                                            {plan.name}
                                        </div>

                                        <div className="mt-3 font-black text-cyan-300">
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

                                        {plan.description && (
                                            <p className="mt-3 text-sm leading-6 text-slate-400">
                                                {plan.description}
                                            </p>
                                        )}
                                    </div>
                                ),
                            )}

                            {!compliancePlans.length && (
                                <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-5 text-sm text-slate-300 lg:col-span-3">
                                    Compliance plan pricing is controlled by Super Admin. Contact the platform administrator for current availability.
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="mt-10 grid gap-6 lg:grid-cols-2">
                        <div className="rounded-3xl border border-white/10 bg-white/[0.04] p-7">
                            <h3 className="text-2xl font-black">Two Filtering Modes</h3>
                            <div className="mt-5 space-y-4 text-sm leading-6 text-slate-300">
                                <div className="rounded-2xl bg-slate-900 p-5"><strong className="text-white">Blocklist:</strong> Internet is allowed by default; the company selects what to block.</div>
                                <div className="rounded-2xl bg-slate-900 p-5"><strong className="text-white">Allowlist / Default-Deny:</strong> the selected network is denied by default; only explicitly approved destinations are allowed.</div>
                            </div>
                        </div>

                        <div className="rounded-3xl border border-white/10 bg-white/[0.04] p-7">
                            <h3 className="text-2xl font-black">Standalone or Existing Customer</h3>
                            <div className="mt-5 space-y-3 text-sm leading-6 text-slate-300">
                                <p><strong className="text-white">Standalone:</strong> add the organization, network/router, collector and storage target from the Compliance platform.</p>
                                <p><strong className="text-white">MAC / Hotspot Company:</strong> Super Admin can link the service so the Company owner opens Internet Compliance from the same panel.</p>
                                <p><strong className="text-white">Hotel Hotspot:</strong> the Hotel Admin can open the linked Compliance workspace from the same Hotel panel.</p>
                                <p><strong className="text-white">Router support:</strong> MikroTik has automated capability/configuration and filtering deployment. Other registered vendors use guided/agent integration according to their capabilities.</p>
                            </div>
                        </div>
                    </div>

                    <div className="mt-8 rounded-3xl border border-amber-300/20 bg-amber-300/[0.06] p-6">
                        <div className="font-black text-amber-200">Privacy & attribution</div>
                        <p className="mt-2 text-sm leading-6 text-slate-300">
                            The service is designed for authorized organization networks and metadata logging. HTTPS page contents, messages and passwords are not decrypted or captured by default. Reliable public-IP attribution depends on the network providing the relevant NAT mapping, source port and timestamp; upstream ISP CGNAT may require the ISP&apos;s own mapping records.
                        </p>
                    </div>

                    <div className="mt-8 flex flex-wrap gap-3">
                        <Link href={route('compliance.login')} className="rounded-xl bg-cyan-400 px-6 py-3 font-black text-slate-950">Compliance Login</Link>
                        <Link href={route('login')} className="rounded-xl border border-white/15 px-6 py-3 font-black text-white">Company Login</Link>
                        <Link href={route('hotel.login')} className="rounded-xl border border-white/15 px-6 py-3 font-black text-white">Hotel Login</Link>
                        <Link href={route('website.login-center')} className="rounded-xl border border-violet-300/30 bg-violet-400/10 px-6 py-3 font-black text-violet-100">All Panel Logins</Link>
                    </div>
                </div>
            </section>



                {/* PUBLIC_ALL_PANEL_LOGIN_CENTER_V2 */}
                <section
                    id="portal-access"
                    className="border-y border-white/10 bg-gradient-to-br from-slate-950 via-slate-900 to-slate-950 py-20"
                >
                    <div className="mx-auto max-w-7xl px-5">
                        <div className="grid gap-8 lg:grid-cols-[1fr_auto] lg:items-end">
                            <div>
                                <div className="text-sm font-black uppercase tracking-[0.2em] text-cyan-300">
                                    Services & Login Portals
                                </div>

                                <h2 className="mt-3 text-4xl font-black tracking-tight">
                                    Company, Hotel and Compliance — one website, one login directory
                                </h2>

                                <p className="mt-4 max-w-4xl text-lg leading-8 text-slate-400">
                                    View the platform services and open the correct customer or administration login without searching for separate URLs.
                                </p>
                            </div>

                            <Link
                                href={route(
                                    'website.login-center',
                                )}
                                className="rounded-2xl bg-cyan-400 px-7 py-4 text-center font-black text-slate-950"
                            >
                                Open All Panel Logins
                            </Link>
                        </div>

                        <div className="mt-10 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                            {[
                                {
                                    title: 'Company / ISP',
                                    path: '/login',
                                    text: 'MAC clients, Hotspot, zones, staff, billing and reports.',
                                },
                                {
                                    title: 'Super Admin',
                                    path: '/login',
                                    text: 'Platform administration uses the main secure login and role-based redirect.',
                                },
                                {
                                    title: 'Hotel Hotspot',
                                    path: '/hotel/login',
                                    text: 'Dedicated Hotel guest Wi-Fi, vouchers, sessions and operations.',
                                },
                                {
                                    title: 'Network Compliance',
                                    path: '/compliance/login',
                                    text: 'Standalone Compliance customers use the dedicated Compliance login.',
                                },
                            ].map(
                                (item) => (
                                    <div
                                        key={item.title}
                                        className="rounded-3xl border border-white/10 bg-white/[0.04] p-6"
                                    >
                                        <div className="text-xl font-black">
                                            {item.title}
                                        </div>

                                        <code className="mt-3 block text-xs text-cyan-300">
                                            {item.path}
                                        </code>

                                        <p className="mt-3 text-sm leading-6 text-slate-400">
                                            {item.text}
                                        </p>
                                    </div>
                                ),
                            )}
                        </div>

                        <div className="mt-6 rounded-2xl border border-violet-300/20 bg-violet-400/[0.06] p-5 text-sm leading-6 text-slate-300">
                            Existing Company Compliance add-on: <code className="text-violet-200">/reseller/compliance</code>
                            {' · '}
                            Existing Hotel Compliance add-on: <code className="text-violet-200">/hotel/compliance</code>
                        </div>
                    </div>
                </section>

                <section
                    id="plans"
                    className="border-y border-white/10 bg-slate-900/60 py-24"
                >
                    <div className="mx-auto max-w-7xl px-5">
                        <SectionTitle
                            eyebrow="Pricing"
                            title={
                                site.pricing_title ||
                                'Choose your Company package'
                            }
                            text={
                                site.pricing_text ||
                                'Active packages are managed by Super Admin and appear here automatically.'
                            }
                        />

                        {plans.length > 0 ? (
                            <div className="mt-12 grid gap-6 lg:grid-cols-3">
                                {plans.map((plan) => (
                                    <PlanCard
                                        key={plan.id}
                                        plan={plan}
                                        site={site}
                                    />
                                ))}
                            </div>
                        ) : (
                            <div className="mt-12 rounded-3xl border border-white/10 bg-white/5 p-10 text-center text-slate-300">
                                No reseller packages are currently available.
                            </div>
                        )}

                        <p className="mt-8 text-center text-sm text-slate-500">
                            The 7-day package is instant only when Super Admin
                            configures it with a price of 0 QAR. Other packages
                            require account approval.
                        </p>
                    </div>
                </section>

                <section
                    id="workflow"
                    className="py-24"
                >
                    <div className="mx-auto max-w-7xl px-5">
                        <SectionTitle
                            eyebrow="Getting Started"
                            title="From registration to first client"
                            text="A simple onboarding path without manual account creation for the free trial."
                        />

                        <div className="mt-12 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {workflow.map(
                                (item, index) => (
                                    <div
                                        key={item}
                                        className="rounded-2xl border border-white/10 bg-white/[0.04] p-6"
                                    >
                                        <div className="text-sm font-black text-cyan-300">
                                            STEP {String(index + 1).padStart(2, '0')}
                                        </div>

                                        <p className="mt-3 font-bold leading-7">
                                            {item}
                                        </p>
                                    </div>
                                ),
                            )}
                        </div>
                    </div>
                </section>

                <section className="border-y border-white/10 bg-slate-900/60 py-24">
                    <div className="mx-auto max-w-7xl px-5">
                        <SectionTitle
                            eyebrow="Operational Security"
                            title="Permissions, tenant separation and auditable actions"
                            text="Reseller operations stay separated from platform administration and staff access is permission-controlled."
                        />

                        <div className="mt-12 grid gap-5 md:grid-cols-3">
                            <InfoBox
                                title="Reseller Isolation"
                                text="Reseller-owned operational data remains inside its tenant scope."
                            />
                            <InfoBox
                                title="Zone Isolation"
                                text="Client and network operations can be limited to the selected or assigned Network Zone."
                            />
                            <InfoBox
                                title="Financial History"
                                text="Important Manager cash corrections use reversal history instead of silently deleting the original movement."
                            />
                        </div>
                    </div>
                </section>

                <section
                    id="faq"
                    className="py-24"
                >
                    <div className="mx-auto max-w-5xl px-5">
                        <SectionTitle
                            eyebrow="FAQ"
                            title="Common reseller questions"
                            text="What to expect before creating your account."
                        />

                        <div className="mt-10 space-y-4">
                            {faqs.map(
                                ([question, answer]) => (
                                    <details
                                        key={question}
                                        className="group rounded-2xl border border-white/10 bg-white/[0.04] p-6"
                                    >
                                        <summary className="cursor-pointer list-none font-black">
                                            {question}
                                        </summary>

                                        <p className="mt-4 leading-7 text-slate-400">
                                            {answer}
                                        </p>
                                    </details>
                                ),
                            )}
                        </div>
                    </div>
                </section>

                <section className="pb-24">
                    <div className="mx-auto max-w-7xl px-5">
                        <div className="rounded-[2rem] bg-cyan-400 p-10 text-slate-950 md:p-14">
                            <div className="grid items-center gap-8 lg:grid-cols-[1fr_auto]">
                                <div>
                                    <h2 className="text-4xl font-black">
                                        Ready to operate your network professionally?
                                    </h2>

                                    <p className="mt-4 max-w-3xl text-lg font-medium text-slate-800">
                                        Create a reseller account, start the
                                        free trial when available, and manage
                                        clients, zones, Hotspot operations and
                                        accounting from one platform.
                                    </p>
                                </div>

                                <Link
                                    href={route('website.register')}
                                    className="rounded-2xl bg-slate-950 px-8 py-4 text-center font-black text-white"
                                >
                                    Register Now
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>
            </main>

            <footer className="border-t border-white/10 bg-slate-950">
                <div className="mx-auto flex max-w-7xl flex-col gap-5 px-5 py-10 md:flex-row md:items-center md:justify-between">
                    <div>
                        <div className="text-xl font-black">
                            {brand}
                        </div>

                        <div className="mt-1 text-sm text-slate-500">
                            {site.footer_text ||
                                site.website_tagline ||
                                'ISP & MikroTik Operations Platform'}
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-5 text-sm font-semibold text-slate-400">
                        <Link href={route('website.terms')}>
                            Terms
                        </Link>
                        <Link href={route('website.privacy')}>
                            Privacy
                        </Link>
                        <Link
                            href={route(
                                'website.login-center',
                            )}
                        >
                            All Panel Logins
                        </Link>
                    </div>
                </div>
            </footer>
        </div>
    );
}

function PlanCard({
    plan,
    site = {},
}) {
    const features =
        Array.isArray(plan.features)
        && plan.features.length > 0
            ? plan.features
            : [
                plan.is_unlimited
                    ? 'Unlimited client capacity'
                    : `${plan.client_limit} total client capacity`,
                'MAC client operations',
                'Network Zone support',
                'Hotspot operations',
                'Finance & reports',
            ];

    return (
        <div
            className={`relative rounded-[2rem] border p-7 ${
                plan.is_unlimited
                    ? 'border-indigo-400 bg-indigo-400/10 shadow-xl shadow-indigo-500/10'
                    : plan.is_free_trial
                      ? 'border-cyan-400 bg-cyan-400/10 shadow-xl shadow-cyan-500/10'
                      : 'border-white/10 bg-white/[0.04]'
            }`}
        >
            {plan.is_unlimited && (
                <div className="absolute -top-3 left-7 rounded-full bg-indigo-400 px-3 py-1 text-xs font-black uppercase text-slate-950">
                    Unlimited
                </div>
            )}

            {!plan.is_unlimited
                && plan.is_free_trial && (
                    <div className="absolute -top-3 left-7 rounded-full bg-cyan-400 px-3 py-1 text-xs font-black uppercase text-slate-950">
                        7-Day Free Trial
                    </div>
                )}

            <h3 className="text-2xl font-black">
                {plan.name}
            </h3>

            <div className="mt-5 flex items-end gap-2">
                <span className="text-4xl font-black">
                    {plan.is_free_trial
                        ? 'FREE'
                        : plan.is_unlimited
                          && Number(plan.price) <= 0
                          ? 'Call for Price'
                          : `QAR ${money(plan.price)}`}
                </span>
            </div>

            <div className="mt-2 text-sm text-slate-400">
                {plan.validity_days} days ·{' '}
                {plan.is_unlimited
                    ? 'Unlimited clients'
                    : `${plan.client_limit} clients`}
            </div>

            <div className="mt-6 space-y-3">
                {features.map((item) => (
                    <CheckLine
                        key={item}
                        text={item}
                    />
                ))}

                <CheckLine text="Multiple Network Zones" />
                <CheckLine text="Staff & Manager operations" />
            </div>

            {plan.is_unlimited ? (
                <a
                    href={
                        site.contact_url || '#'
                    }
                    className="mt-8 block rounded-xl bg-indigo-400 px-5 py-3 text-center font-black text-slate-950"
                >
                    Contact Super Admin
                </a>
            ) : (
                <Link
                    href={route('website.register', {
                        plan: plan.id,
                    })}
                    className={`mt-8 block rounded-xl px-5 py-3 text-center font-black ${
                        plan.is_free_trial
                            ? 'bg-cyan-400 text-slate-950'
                            : 'bg-white text-slate-950'
                    }`}
                >
                    {plan.is_free_trial
                        ? 'Start Free Trial'
                        : 'Apply for this Package'}
                </Link>
            )}
        </div>
    );
}

function SectionTitle({
    eyebrow,
    title,
    text,
}) {
    return (
        <div className="max-w-3xl">
            <div className="text-sm font-black uppercase tracking-[0.2em] text-cyan-300">
                {eyebrow}
            </div>

            <h2 className="mt-3 text-4xl font-black tracking-tight">
                {title}
            </h2>

            <p className="mt-4 text-lg leading-8 text-slate-400">
                {text}
            </p>
        </div>
    );
}

function HeroStat({ value, label }) {
    return (
        <div className="rounded-2xl border border-white/10 bg-white/[0.04] p-4">
            <div className="font-black text-white">
                {value}
            </div>

            <div className="mt-1 text-xs text-slate-500">
                {label}
            </div>
        </div>
    );
}

function DemoCard({ label, value }) {
    return (
        <div className="rounded-2xl bg-slate-800/70 p-4">
            <div className="text-xs font-semibold text-slate-400">
                {label}
            </div>

            <div className="mt-2 text-xl font-black">
                {value}
            </div>
        </div>
    );
}

function Activity({ title, detail }) {
    return (
        <div className="flex gap-3">
            <span className="mt-1 h-2.5 w-2.5 rounded-full bg-emerald-400" />

            <div>
                <div className="font-bold">
                    {title}
                </div>

                <div className="text-xs text-slate-500">
                    {detail}
                </div>
            </div>
        </div>
    );
}

function CheckLine({ text }) {
    return (
        <div className="flex gap-3 text-sm leading-6 text-slate-300">
            <span className="font-black text-cyan-300">
                ✓
            </span>
            <span>{text}</span>
        </div>
    );
}

function InfoBox({ title, text }) {
    return (
        <div className="rounded-2xl border border-white/10 bg-slate-950/60 p-5">
            <div className="font-black">
                {title}
            </div>

            <p className="mt-2 text-sm leading-6 text-slate-400">
                {text}
            </p>
        </div>
    );
}

function ComplianceServiceCard({ title, badge, text, items }) {
    return (
        <article className="rounded-3xl border border-white/10 bg-white/[0.04] p-7">
            <div className="inline-flex rounded-full bg-cyan-400/10 px-3 py-1 text-xs font-black uppercase tracking-wide text-cyan-300">{badge}</div>
            <h3 className="mt-4 text-2xl font-black">{title}</h3>
            <p className="mt-3 text-sm leading-6 text-slate-400">{text}</p>
            <ul className="mt-5 space-y-2 text-sm text-slate-300">
                {items.map((item) => <li key={item} className="flex gap-2"><span className="text-cyan-300">✓</span><span>{item}</span></li>)}
            </ul>
        </article>
    );
}
