import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

const inputClass =
    'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20';

const money = (value) =>
    Number(value || 0).toLocaleString(
        undefined,
        {
            maximumFractionDigits: 2,
        },
    );

export default function Register({
    plans = [],
    selectedPlan = null,
}) {
    const form = useForm({
        plan_id:
            selectedPlan?.id ?? '',
        company_name: '',
        owner_name: '',
        email: '',
        phone: '',
        address: '',
        password: '',
        password_confirmation: '',
        terms: false,
    });

    const current =
        plans.find(
            (plan) =>
                Number(plan.id)
                === Number(
                    form.data.plan_id,
                ),
        )
        ?? selectedPlan;

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route(
                'website.register.store',
            ),
        );
    };

    return (
        <div className="min-h-screen bg-slate-950 px-5 py-10 text-white">
            <Head title="Reseller Registration" />

            <div className="mx-auto max-w-6xl">
                <div className="mb-8 flex items-center justify-between">
                    <Link
                        href={route('website.home')}
                        className="text-2xl font-black"
                    >
                        <span className="text-cyan-400">
                            Mikro
                        </span>
                        Panel
                    </Link>

                    <Link
                        href={route('login')}
                        className="text-sm font-bold text-slate-300"
                    >
                        Already registered? Login
                    </Link>
                </div>

                <div className="grid gap-7 lg:grid-cols-[.8fr_1.2fr]">
                    <aside className="rounded-[2rem] border border-white/10 bg-white/[0.05] p-7">
                        <div className="text-sm font-black uppercase tracking-[0.2em] text-cyan-300">
                            Selected Package
                        </div>

                        {current ? (
                            <>
                                <h1 className="mt-4 text-3xl font-black">
                                    {current.name}
                                </h1>

                                <div className="mt-5 text-4xl font-black">
                                    {current.is_free_trial
                                        ? 'FREE'
                                        : current.is_unlimited
                                          && Number(current.price) <= 0
                                          ? 'Call for Price'
                                          : `QAR ${money(current.price)}`}
                                </div>

                                <div className="mt-2 text-slate-400">
                                    {current.validity_days} days
                                    {' · '}
                                    {current.client_limit} clients
                                </div>

                                {current.is_free_trial ? (
                                    <div className="mt-6 rounded-2xl bg-emerald-400/10 p-4 text-sm leading-6 text-emerald-200">
                                        This is the automatic
                                        7-day free trial.
                                        Your account will be
                                        activated immediately.
                                    </div>
                                ) : (
                                    <div className="mt-6 rounded-2xl bg-amber-400/10 p-4 text-sm leading-6 text-amber-200">
                                        This package requires
                                        Super Admin approval.
                                        You can check the
                                        application status after
                                        submission.
                                    </div>
                                )}
                            </>
                        ) : (
                            <p className="mt-5 text-slate-400">
                                No active package is available.
                            </p>
                        )}

                        <div className="mt-8 border-t border-white/10 pt-6">
                            <div className="font-bold">
                                Included platform capabilities
                            </div>

                            <div className="mt-4 space-y-3 text-sm text-slate-300">
                                <Line text="MAC client management" />
                                <Line text="Network Zones" />
                                <Line text="MikroTik API synchronization" />
                                <Line text="Hotspot & vouchers" />
                                <Line text="Invoices & payments" />
                                <Line text="Manager cash accounting" />
                                <Line text="Reports & staff permissions" />
                            </div>
                        </div>
                    </aside>

                    <section className="rounded-[2rem] bg-white p-7 text-slate-900 shadow-2xl md:p-9">
                        <div>
                            <h2 className="text-3xl font-black">
                                Create Reseller Account
                            </h2>

                            <p className="mt-2 text-slate-500">
                                Enter your company and owner information.
                            </p>
                        </div>

                        <form
                            onSubmit={submit}
                            className="mt-8 space-y-5"
                        >
                            <Field
                                label="Package"
                                error={form.errors.plan_id}
                            >
                                <select
                                    value={form.data.plan_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'plan_id',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                    required
                                >
                                    <option value="">
                                        Select package
                                    </option>

                                    {plans.map((plan) => (
                                        <option
                                            key={plan.id}
                                            value={plan.id}
                                        >
                                            {plan.name}
                                            {' — '}
                                            {Number(plan.price) <= 0
                                                ? 'FREE'
                                                : `QAR ${money(plan.price)}`}
                                            {' — '}
                                            {plan.validity_days} days
                                        </option>
                                    ))}
                                </select>
                            </Field>

                            <div className="grid gap-5 md:grid-cols-2">
                                <Field
                                    label="Company / Business Name"
                                    error={form.errors.company_name}
                                >
                                    <input
                                        value={form.data.company_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'company_name',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                        required
                                    />
                                </Field>

                                <Field
                                    label="Owner Name"
                                    error={form.errors.owner_name}
                                >
                                    <input
                                        value={form.data.owner_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'owner_name',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                        required
                                    />
                                </Field>

                                <Field
                                    label="Login Email"
                                    error={form.errors.email}
                                >
                                    <input
                                        type="email"
                                        value={form.data.email}
                                        onChange={(event) =>
                                            form.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                        required
                                    />
                                </Field>

                                <Field
                                    label="Phone / WhatsApp"
                                    error={form.errors.phone}
                                >
                                    <input
                                        value={form.data.phone}
                                        onChange={(event) =>
                                            form.setData(
                                                'phone',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                        required
                                    />
                                </Field>

                                <Field
                                    label="Password"
                                    error={form.errors.password}
                                >
                                    <input
                                        type="password"
                                        value={form.data.password}
                                        onChange={(event) =>
                                            form.setData(
                                                'password',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                        required
                                    />
                                </Field>

                                <Field label="Confirm Password">
                                    <input
                                        type="password"
                                        value={form.data.password_confirmation}
                                        onChange={(event) =>
                                            form.setData(
                                                'password_confirmation',
                                                event.target.value,
                                            )
                                        }
                                        className={inputClass}
                                        required
                                    />
                                </Field>
                            </div>

                            <Field
                                label="Address / Service Area"
                                error={form.errors.address}
                            >
                                <textarea
                                    rows="3"
                                    value={form.data.address}
                                    onChange={(event) =>
                                        form.setData(
                                            'address',
                                            event.target.value,
                                        )
                                    }
                                    className={inputClass}
                                />
                            </Field>

                            <label className="flex items-start gap-3 rounded-xl bg-slate-50 p-4">
                                <input
                                    type="checkbox"
                                    checked={form.data.terms}
                                    onChange={(event) =>
                                        form.setData(
                                            'terms',
                                            event.target.checked,
                                        )
                                    }
                                    className="mt-1 rounded border-slate-300"
                                />

                                <span className="text-sm leading-6 text-slate-600">
                                    I agree to the{' '}
                                    <Link
                                        href={route('website.terms')}
                                        className="font-bold text-cyan-700"
                                    >
                                        Terms
                                    </Link>
                                    {' '}and{' '}
                                    <Link
                                        href={route('website.privacy')}
                                        className="font-bold text-cyan-700"
                                    >
                                        Privacy Policy
                                    </Link>
                                    .
                                </span>
                            </label>

                            {form.errors.terms && (
                                <div className="text-sm text-red-600">
                                    {form.errors.terms}
                                </div>
                            )}

                            <button
                                type="submit"
                                disabled={
                                    form.processing
                                    || !current
                                }
                                className="w-full rounded-xl bg-slate-950 px-5 py-4 font-black text-white disabled:opacity-50"
                            >
                                {form.processing
                                    ? 'Submitting...'
                                    : current?.is_free_trial
                                      ? 'Start 7-Day Free Trial'
                                      : 'Submit Registration'}
                            </button>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    );
}

function Field({
    label,
    error,
    children,
}) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-bold text-slate-700">
                {label}
            </span>

            {children}

            {error && (
                <span className="mt-1 block text-sm text-red-600">
                    {error}
                </span>
            )}
        </label>
    );
}

function Line({ text }) {
    return (
        <div className="flex gap-2">
            <span className="text-cyan-300">
                ✓
            </span>
            <span>{text}</span>
        </div>
    );
}
