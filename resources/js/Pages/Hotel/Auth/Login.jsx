import {
    Head,
    useForm,
} from '@inertiajs/react';

export default function Login() {
    const form =
        useForm({
            email: '',
            password: '',
            remember: false,
        });

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route(
                'hotel.login.store',
            ),
        );
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 p-5">
            <Head title="Hotel Hotspot Login" />

            <div className="w-full max-w-md rounded-[2rem] bg-white p-8 shadow-2xl">
                <div className="text-center">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-emerald-600 text-xl font-black text-white">
                        HT
                    </div>

                    <h1 className="mt-5 text-3xl font-black">
                        Hotel Hotspot
                    </h1>

                    <p className="mt-2 text-slate-500">
                        Hotel Admin & Receptionist Login
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="mt-8 space-y-5"
                >
                    <label className="block">
                        <div className="mb-1 text-sm font-bold">
                            Email
                        </div>

                        <input
                            type="email"
                            value={
                                form.data.email
                            }
                            onChange={(e) =>
                                form.setData(
                                    'email',
                                    e.target.value,
                                )
                            }
                            className="w-full rounded-xl border-slate-300"
                            autoFocus
                        />

                        {form.errors.email && (
                            <div className="mt-1 text-sm font-bold text-red-600">
                                {
                                    form.errors.email
                                }
                            </div>
                        )}
                    </label>

                    <label className="block">
                        <div className="mb-1 text-sm font-bold">
                            Password
                        </div>

                        <input
                            type="password"
                            value={
                                form.data.password
                            }
                            onChange={(e) =>
                                form.setData(
                                    'password',
                                    e.target.value,
                                )
                            }
                            className="w-full rounded-xl border-slate-300"
                        />
                    </label>

                    <label className="flex items-center gap-2 text-sm font-semibold text-slate-600">
                        <input
                            type="checkbox"
                            checked={
                                form.data
                                    .remember
                            }
                            onChange={(e) =>
                                form.setData(
                                    'remember',
                                    e.target.checked,
                                )
                            }
                            className="rounded"
                        />

                        Remember me
                    </label>

                    <button
                        type="submit"
                        disabled={
                            form.processing
                        }
                        className="w-full rounded-xl bg-emerald-600 px-5 py-3 font-black text-white disabled:opacity-50"
                    >
                        Login to Hotel Panel
                    </button>
                </form>
            </div>
        </div>
    );
}
