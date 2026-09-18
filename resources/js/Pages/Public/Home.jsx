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

export default function Home({
    brand = 'MikroPanel',
    plans = [],
}) {
    return (
        <div className="min-h-screen bg-slate-950 text-white">
            <Head
                title={`${brand} — ISP & MikroTik Reseller Platform`}
            >
                <meta
                    name="description"
                    content="Professional MikroTik client management, Hotspot, billing, accounting, reseller operations and Network Zone platform."
                />
            </Head>

            <header className="sticky top-0 z-50 border-b border-white/10 bg-slate-950/90 backdrop-blur">
                <div className="mx-auto flex max-w-7xl items-center justify-between px-5 py-4">
                    <Link
                        href={route('website.home')}
                        className="text-2xl font-black tracking-tight"
                    >
                        <span className="text-cyan-400">
                            Mikro
                        </span>
                        Panel
                    </Link>

                    <nav className="hidden items-center gap-7 text-sm font-semibold text-slate-300 lg:flex">
                        <a href="#features">
                            Features
                        </a>
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
                                MikroTik ISP Operations · Reseller SaaS
                            </div>

                            <h1 className="max-w-4xl text-5xl font-black leading-[1.05] tracking-tight sm:text-6xl">
                                Run your network,
                                clients and cash flow
                                from one professional
                                control panel.
                            </h1>

                            <p className="mt-7 max-w-3xl text-lg leading-8 text-slate-300">
                                {brand} combines MAC client management,
                                Network Zones, MikroTik synchronization,
                                Hotspot vouchers, billing, staff access,
                                Manager cash accounting and reseller
                                operations in one platform.
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

                <section
                    id="plans"
                    className="border-y border-white/10 bg-slate-900/60 py-24"
                >
                    <div className="mx-auto max-w-7xl px-5">
                        <SectionTitle
                            eyebrow="Pricing"
                            title="Choose your reseller package"
                            text="Packages are managed by Super Admin. Active packages appear here automatically."
                        />

                        {plans.length > 0 ? (
                            <div className="mt-12 grid gap-6 lg:grid-cols-3">
                                {plans.map((plan) => (
                                    <PlanCard
                                        key={plan.id}
                                        plan={plan}
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
                            MikroTik ISP & Reseller Operations Platform
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-5 text-sm font-semibold text-slate-400">
                        <Link href={route('website.terms')}>
                            Terms
                        </Link>
                        <Link href={route('website.privacy')}>
                            Privacy
                        </Link>
                        <Link href={route('login')}>
                            Panel Login
                        </Link>
                    </div>
                </div>
            </footer>
        </div>
    );
}

function PlanCard({ plan }) {
    const features =
        Array.isArray(plan.features)
        && plan.features.length > 0
            ? plan.features
            : [
                `${plan.client_limit} total client capacity`,
                'MAC client operations',
                'Network Zone support',
                'Hotspot operations',
                'Finance & reports',
            ];

    return (
        <div
            className={`relative rounded-[2rem] border p-7 ${
                plan.is_free_trial
                    ? 'border-cyan-400 bg-cyan-400/10 shadow-xl shadow-cyan-500/10'
                    : 'border-white/10 bg-white/[0.04]'
            }`}
        >
            {plan.is_free_trial && (
                <div className="absolute -top-3 left-7 rounded-full bg-cyan-400 px-3 py-1 text-xs font-black uppercase text-slate-950">
                    7-Day Free Trial
                </div>
            )}

            <h3 className="text-2xl font-black">
                {plan.name}
            </h3>

            <div className="mt-5 flex items-end gap-2">
                <span className="text-4xl font-black">
                    {Number(plan.price) <= 0
                        ? 'FREE'
                        : `QAR ${money(plan.price)}`}
                </span>
            </div>

            <div className="mt-2 text-sm text-slate-400">
                {plan.validity_days} days ·{' '}
                {plan.client_limit} clients
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
