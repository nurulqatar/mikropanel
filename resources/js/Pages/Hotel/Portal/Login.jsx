import {
    PortalFrame,
    tx,
} from '@/Hotel/PortalV15Kit';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

export default function Login({
    hotel,
    locale,
    verification = null,
    captive = null,
}) {
    const t = (key) => {
        const map = {
            guestWifiLogin:
                'Welcome Back',
            voucherCode:
                'Voucher Code',
            verifyVoucher:
                'Login to WiFi',
            voucherValid:
                'Registration Successful',
            room:
                'Room',
            validUntil:
                'Valid Until',
            invalidVoucher:
                'Please enter your voucher code.',
            back:
                'Back',
        };

        return tx(
            locale,
            map[key] || key,
        );
    };

    const form =
        useForm({
            voucher_code: '',
        });

    const connectHref =
        verification?.valid
        && captive?.connect_url
            ? `${captive.connect_url}?voucher=${encodeURIComponent(
                  verification.voucher_code,
              )}`
            : null;

    return (
        <PortalFrame
            hotel={hotel}
            locale={locale}
        >
            <Head
                title={t(
                    'guestWifiLogin',
                )}
            />

            <div className="mx-auto flex min-h-screen max-w-md items-center">
                <div className="w-full overflow-hidden rounded-[2rem] border border-white/10 bg-white shadow-2xl">
                    <div className="bg-gradient-to-r from-blue-600 via-indigo-600 to-cyan-500 px-7 py-8 text-center text-white">
                        {hotel.logo_url ? (
                            <img
                                src={
                                    hotel.logo_url
                                }
                                alt={hotel.name}
                                className="mx-auto h-20 max-w-52 object-contain"
                            />
                        ) : (
                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-white/15 text-xl font-black">
                                WiFi
                            </div>
                        )}

                        <h1 className="mt-4 text-2xl font-black">
                            {t(
                                'guestWifiLogin',
                            )}
                        </h1>

                        <p className="mt-1 text-sm text-blue-100">
                            {hotel.name}
                        </p>
                    </div>

                    <div className="p-7">
                        {captive && (
                            <div className="mb-5 grid grid-cols-2 gap-2 text-xs">
                                <div className="rounded-xl bg-slate-50 p-3">
                                    <div className="font-bold text-slate-400">
                                        DEVICE IP
                                    </div>

                                    <div className="mt-1 font-mono font-bold text-slate-700">
                                        {captive.ip
                                            || '-'}
                                    </div>
                                </div>

                                <div className="rounded-xl bg-slate-50 p-3">
                                    <div className="font-bold text-slate-400">
                                        DEVICE MAC
                                    </div>

                                    <div className="mt-1 break-all font-mono font-bold text-slate-700">
                                        {captive.mac
                                            || '-'}
                                    </div>
                                </div>
                            </div>
                        )}

                        <form
                            onSubmit={(event) => {
                                event.preventDefault();

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
                        >
                            <label className="text-sm font-black text-slate-700">
                                {t(
                                    'voucherCode',
                                )}
                            </label>

                            <input
                                dir="ltr"
                                autoFocus
                                maxLength={32}
                                value={
                                    form.data
                                        .voucher_code
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'voucher_code',
                                        e.target.value
                                            .replace(
                                                /[^a-zA-Z0-9]/g,
                                                '',
                                            )
                                            .toUpperCase(),
                                    )
                                }
                                className="mt-2 w-full rounded-2xl border-slate-200 bg-slate-50 px-5 py-4 text-center font-mono text-2xl font-black uppercase tracking-[0.25em] focus:border-blue-500 focus:ring-blue-500"
                                placeholder="XXXXXXXX"
                            />

                            {form.errors
                                .voucher_code && (
                                <div className="mt-2 text-sm font-bold text-red-600">
                                    {
                                        form.errors
                                            .voucher_code
                                    }
                                </div>
                            )}

                            <button
                                type="submit"
                                disabled={
                                    form.processing
                                }
                                className="mt-5 w-full rounded-2xl bg-gradient-to-r from-blue-600 to-cyan-500 px-6 py-4 text-base font-black text-white shadow-lg disabled:opacity-50"
                            >
                                {t(
                                    'verifyVoucher',
                                )}
                            </button>
                        </form>

                        {verification?.valid && (
                            <div className="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                                <div className="text-center font-black text-emerald-700">
                                    ✓{' '}
                                    {t(
                                        'voucherValid',
                                    )}
                                </div>

                                <div className="mt-3 space-y-1 text-center text-sm text-slate-600">
                                    <div>
                                        {
                                            verification.guest_name
                                        }
                                    </div>

                                    <div>
                                        {t(
                                            'room',
                                        )}{' '}
                                        {
                                            verification.room_number
                                        }
                                    </div>

                                    <div>
                                        {t(
                                            'validUntil',
                                        )}{' '}
                                        {
                                            verification.expires_at
                                        }
                                    </div>
                                </div>

                                {connectHref && (
                                    <a
                                        href={
                                            connectHref
                                        }
                                        className="mt-5 block rounded-2xl bg-emerald-600 px-6 py-4 text-center font-black text-white shadow-lg"
                                    >
                                        Connect to WiFi
                                    </a>
                                )}
                            </div>
                        )}

                        {verification
                            && !verification.valid && (
                            <div className="mt-5 rounded-2xl bg-red-50 p-4 text-center font-bold text-red-700">
                                {t(
                                    'invalidVoucher',
                                )}
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
                            className="mt-6 block text-center font-bold text-slate-400"
                        >
                            {t('back')}
                        </Link>
                    </div>
                </div>
            </div>
        </PortalFrame>
    );
}
