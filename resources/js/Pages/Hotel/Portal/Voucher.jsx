import {
    PortalFrame,
    tx,
} from '@/Hotel/PortalV15Kit';
import {
    Head,
    Link,
} from '@inertiajs/react';

export default function Voucher({
    hotel,
    locale,
    voucher,
    captive = null,
}) {
    const t = (key) => {
        const map = {
            yourVoucher:
                'Your WiFi Access Is Ready',
            registrationComplete:
                'Registration Successful',
            voucherCode:
                'Voucher Code',
            guest:
                'Guest',
            room:
                'Room',
            validUntil:
                'Valid Until',
            continueLogin:
                'Connect to WiFi',
        };

        return tx(
            locale,
            map[key] || key,
        );
    };

    const connectHref =
        captive?.connect_url
            ? `${captive.connect_url}?voucher=${encodeURIComponent(
                  voucher.username,
              )}`
            : null;

    return (
        <PortalFrame
            hotel={hotel}
            locale={locale}
        >
            <Head
                title={t(
                    'yourVoucher',
                )}
            />

            <div className="mx-auto flex min-h-screen max-w-lg items-center">
                <div className="w-full overflow-hidden rounded-[2rem] bg-white shadow-2xl">
                    <div className="bg-gradient-to-r from-blue-600 via-indigo-600 to-cyan-500 px-7 py-8 text-center text-white">
                        {hotel.logo_url && (
                            <img
                                src={
                                    hotel.logo_url
                                }
                                alt={hotel.name}
                                className="mx-auto h-20 max-w-52 object-contain"
                            />
                        )}

                        <div className="mt-3 text-xs font-black uppercase tracking-[0.22em] text-blue-100">
                            ✓{' '}
                            {t(
                                'registrationComplete',
                            )}
                        </div>

                        <h1 className="mt-2 text-2xl font-black">
                            {t(
                                'yourVoucher',
                            )}
                        </h1>
                    </div>

                    <div className="p-7">
                        <div className="rounded-3xl bg-slate-950 p-7 text-center shadow-xl">
                            <div className="text-xs font-black uppercase tracking-[0.25em] text-slate-400">
                                {t(
                                    'voucherCode',
                                )}
                            </div>

                            <div
                                dir="ltr"
                                className="mt-3 break-all font-mono text-4xl font-black tracking-widest text-white"
                            >
                                {
                                    voucher.username
                                }
                            </div>
                        </div>

                        <div className="mt-6 grid gap-3">
                            <Info
                                label={t(
                                    'guest',
                                )}
                                value={
                                    voucher.guest_name
                                }
                            />

                            <Info
                                label={t(
                                    'room',
                                )}
                                value={
                                    voucher.room_number
                                }
                            />

                            <Info
                                label={t(
                                    'validUntil',
                                )}
                                value={
                                    voucher.expires_at
                                }
                            />

                            {captive && (
                                <>
                                    <Info
                                        label="Device IP"
                                        value={
                                            captive.ip
                                            || '-'
                                        }
                                    />

                                    <Info
                                        label="Device MAC"
                                        value={
                                            captive.mac
                                            || '-'
                                        }
                                    />
                                </>
                            )}
                        </div>

                        {connectHref ? (
                            <a
                                href={
                                    connectHref
                                }
                                className="mt-6 block rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-500 px-6 py-4 text-center text-lg font-black text-white shadow-lg"
                            >
                                Connect to WiFi
                            </a>
                        ) : (
                            <Link
                                href={route(
                                    'hotel.portal.login',
                                    {
                                        hotel:
                                            hotel.slug,

                                        lang:
                                            locale,
                                    },
                                )}
                                className="mt-6 block rounded-2xl bg-gradient-to-r from-blue-600 to-cyan-500 px-6 py-4 text-center font-black text-white shadow-lg"
                            >
                                {t(
                                    'continueLogin',
                                )}
                            </Link>
                        )}
                    </div>
                </div>
            </div>
        </PortalFrame>
    );
}

function Info({
    label,
    value,
}) {
    return (
        <div className="flex items-center justify-between gap-4 rounded-2xl border border-slate-100 bg-slate-50 px-4 py-3">
            <span className="text-sm font-bold text-slate-400">
                {label}
            </span>

            <strong className="text-right text-sm text-slate-800">
                {value}
            </strong>
        </div>
    );
}
