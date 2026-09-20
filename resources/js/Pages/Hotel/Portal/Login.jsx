import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

export default function Login({
    hotel,
    locale,
    verification = null,
}) {
    const form =
        useForm({
            voucher_code: '',
        });

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-950 p-5">
            <Head title="Guest WiFi Login" />

            <div className="w-full max-w-md rounded-[2rem] bg-white p-8 shadow-2xl">
                {hotel.logo_url && (
                    <img
                        src={
                            hotel.logo_url
                        }
                        alt={
                            hotel.name
                        }
                        className="mx-auto h-20 max-w-48 object-contain"
                    />
                )}

                <h1 className="mt-5 text-center text-3xl font-black">
                    Guest WiFi Login
                </h1>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();

                        form.post(
                            route(
                                'hotel.portal.login.verify',
                                {
                                    hotel:
                                        hotel.slug,
                                    lang:
                                        locale,
                                },
                            ),
                        );
                    }}
                    className="mt-7"
                >
                    <label className="text-sm font-black">
                        Voucher Code
                    </label>

                    <input
                        value={
                            form.data
                                .voucher_code
                        }
                        onChange={(e) =>
                            form.setData(
                                'voucher_code',
                                e.target.value
                                    .toUpperCase(),
                            )
                        }
                        className="mt-2 w-full rounded-xl border-slate-300 text-center font-mono text-xl font-black uppercase tracking-widest"
                        placeholder="XXXXXXXX"
                    />

                    <button
                        type="submit"
                        style={{
                            backgroundColor:
                                hotel.primary_color,
                        }}
                        className="mt-5 w-full rounded-xl px-6 py-3 font-black text-white"
                    >
                        Verify Voucher
                    </button>
                </form>

                {verification?.valid && (
                    <div className="mt-5 rounded-xl bg-emerald-50 p-4 text-center text-emerald-700">
                        <div className="font-black">
                            Voucher Valid
                        </div>

                        <div className="mt-1 text-sm">
                            {
                                verification.guest_name
                            }
                            {' · Room '}
                            {
                                verification.room_number
                            }
                        </div>

                        <div className="mt-1 text-xs">
                            Valid until{' '}
                            {
                                verification.expires_at
                            }
                        </div>
                    </div>
                )}

                {verification
                    && !verification.valid && (
                    <div className="mt-5 rounded-xl bg-red-50 p-4 text-center font-bold text-red-700">
                        Invalid or expired voucher.
                    </div>
                )}

                <Link
                    href={route(
                        'hotel.portal.access',
                        {
                            hotel:
                                hotel.slug,
                            lang:
                                locale,
                        },
                    )}
                    className="mt-6 block text-center font-bold text-slate-500"
                >
                    Back
                </Link>
            </div>
        </div>
    );
}
