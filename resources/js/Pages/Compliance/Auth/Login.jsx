import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

export default function Login() {
    const form = useForm({
        email: '',
        password: '',
    });

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route(
                'compliance.login.store',
            ),
        );
    };

    return (
        <>
            <Head title="Compliance Login" />

            <div className="flex min-h-screen items-center justify-center bg-slate-950 px-5 py-12 text-white">
                <div className="w-full max-w-md rounded-[2rem] border border-white/10 bg-white/[0.05] p-8 shadow-2xl">
                    <div className="text-sm font-black uppercase tracking-[0.2em] text-cyan-300">
                        Network Compliance
                    </div>

                    <h1 className="mt-3 text-3xl font-black">
                        Organization Login
                    </h1>

                    <p className="mt-3 text-sm leading-6 text-slate-400">
                        Standalone Logging,
                        Filtering or Combined
                        Service customers can
                        access their network
                        workspace here.
                    </p>

                    <form
                        onSubmit={submit}
                        className="mt-8 space-y-5"
                    >
                        <Field
                            label="Email"
                            error={
                                form.errors
                                    .email
                            }
                        >
                            <input
                                type="email"
                                value={
                                    form.data
                                        .email
                                }
                                onChange={(
                                    event,
                                ) =>
                                    form.setData(
                                        'email',
                                        event.target
                                            .value,
                                    )
                                }
                                className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none focus:border-cyan-400"
                                required
                            />
                        </Field>

                        <Field
                            label="Password"
                            error={
                                form.errors
                                    .password
                            }
                        >
                            <input
                                type="password"
                                value={
                                    form.data
                                        .password
                                }
                                onChange={(
                                    event,
                                ) =>
                                    form.setData(
                                        'password',
                                        event.target
                                            .value,
                                    )
                                }
                                className="w-full rounded-xl border border-white/10 bg-slate-900 px-4 py-3 text-white outline-none focus:border-cyan-400"
                                required
                            />
                        </Field>

                        <button
                            type="submit"
                            disabled={
                                form.processing
                            }
                            className="w-full rounded-xl bg-cyan-400 px-5 py-3 font-black text-slate-950 disabled:opacity-60"
                        >
                            Sign In
                        </button>
                    </form>

                    <Link
                        href="/"
                        className="mt-6 block text-center text-sm text-slate-400 hover:text-white"
                    >
                        Back to Website
                    </Link>
                </div>
            </div>
        </>
    );
}

function Field({
    label,
    error,
    children,
}) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-bold">
                {label}
            </span>

            {children}

            {error && (
                <span className="mt-2 block text-sm text-rose-300">
                    {error}
                </span>
            )}
        </label>
    );
}
