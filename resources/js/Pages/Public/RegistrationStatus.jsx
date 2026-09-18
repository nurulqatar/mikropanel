import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

export default function RegistrationStatus({
    registration,
}) {
    const approved =
        registration.status === 'approved';

    const pending =
        registration.status === 'pending';

    const rejected =
        registration.status === 'rejected';

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-950 px-5 py-12 text-white">
            <Head title="Registration Status" />

            <div className="w-full max-w-2xl rounded-[2rem] border border-white/10 bg-white/[0.05] p-8 text-center shadow-2xl md:p-12">
                <div
                    className={`mx-auto flex h-20 w-20 items-center justify-center rounded-full text-3xl ${
                        approved
                            ? 'bg-emerald-400/15 text-emerald-300'
                            : pending
                              ? 'bg-amber-400/15 text-amber-300'
                              : 'bg-red-400/15 text-red-300'
                    }`}
                >
                    {approved
                        ? '✓'
                        : pending
                          ? '…'
                          : '×'}
                </div>

                <h1 className="mt-6 text-4xl font-black">
                    {approved
                        ? registration.auto_approved
                            ? 'Free Trial Activated'
                            : 'Registration Approved'
                        : pending
                          ? 'Application Submitted'
                          : 'Application Not Approved'}
                </h1>

                <p className="mt-4 leading-7 text-slate-300">
                    {approved
                        ? 'Your reseller account is active. You can now log in to the panel.'
                        : pending
                          ? 'Your reseller account has been created but remains inactive until Super Admin approves the selected package.'
                          : registration.review_notes
                            || 'Your registration was rejected by Super Admin.'}
                </p>

                <div className="mt-8 rounded-2xl bg-slate-900 p-6 text-left">
                    <Row
                        label="Company"
                        value={registration.company_name}
                    />
                    <Row
                        label="Owner"
                        value={registration.owner_name}
                    />
                    <Row
                        label="Package"
                        value={registration.plan_name}
                    />
                    <Row
                        label="Validity"
                        value={`${registration.validity_days} days`}
                    />
                    <Row
                        label="Client Capacity"
                        value={registration.client_limit}
                    />
                    <Row
                        label="Status"
                        value={registration.status.toUpperCase()}
                    />
                </div>

                <div className="mt-8 flex flex-wrap justify-center gap-3">
                    {approved && (
                        <Link
                            href={route('login')}
                            className="rounded-xl bg-cyan-400 px-6 py-3 font-black text-slate-950"
                        >
                            Login to Panel
                        </Link>
                    )}

                    {pending && (
                        <button
                            onClick={() =>
                                router.reload()
                            }
                            className="rounded-xl bg-amber-400 px-6 py-3 font-black text-slate-950"
                        >
                            Refresh Status
                        </button>
                    )}

                    <Link
                        href={route('website.home')}
                        className="rounded-xl border border-white/15 px-6 py-3 font-bold"
                    >
                        Back to Website
                    </Link>
                </div>
            </div>
        </div>
    );
}

function Row({
    label,
    value,
}) {
    return (
        <div className="flex justify-between gap-5 border-b border-white/10 py-3 last:border-0">
            <span className="text-slate-500">
                {label}
            </span>

            <span className="text-right font-bold">
                {value ?? '—'}
            </span>
        </div>
    );
}
