import {
    directionForLocale,
} from '@/Hotel/portalCatalog';
import {
    createTranslator,
} from '@/Hotel/portalI18n';
import {
    Head,
    Link,
} from '@inertiajs/react';

export default function Voucher({
    hotel,
    locale,
    voucher,
}) {
    const t =
        createTranslator(
            locale,
            hotel.portal_translations,
        );

    return (
        <div
            dir={directionForLocale(
                locale,
            )}
            className="flex min-h-screen items-center justify-center bg-slate-950 p-5"
        >
            <Head
                title={t(
                    'yourVoucher',
                )}
            />

            <div className="w-full max-w-lg rounded-[2rem] bg-white p-8 text-center shadow-2xl">
                {hotel.logo_url && (
                    <img
                        src={hotel.logo_url}
                        alt={hotel.name}
                        className="mx-auto h-20 max-w-52 object-contain"
                    />
                )}

                <div className="mt-5 text-sm font-black uppercase tracking-widest text-emerald-600">
                    {t(
                        'registrationComplete',
                    )}
                </div>

                <h1 className="mt-2 text-3xl font-black">
                    {t(
                        'yourVoucher',
                    )}
                </h1>

                <div className="mt-7 rounded-2xl bg-slate-950 p-7">
                    <div className="text-xs font-black uppercase tracking-[0.25em] text-slate-400">
                        {t(
                            'voucherCode',
                        )}
                    </div>

                    <div
                        dir="ltr"
                        className="mt-3 font-mono text-4xl font-black tracking-widest text-white"
                    >
                        {voucher.username}
                    </div>
                </div>

                <div className="mt-6 space-y-2 text-sm text-slate-600">
                    <div>
                        {t('guest')}:{' '}
                        <strong>
                            {
                                voucher.guest_name
                            }
                        </strong>
                    </div>

                    <div>
                        {t('room')}:{' '}
                        <strong>
                            {
                                voucher.room_number
                            }
                        </strong>
                    </div>

                    <div>
                        {t(
                            'validUntil',
                        )}:{' '}
                        <strong>
                            {
                                voucher.expires_at
                            }
                        </strong>
                    </div>
                </div>

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
                    style={{
                        backgroundColor:
                            hotel.primary_color,
                    }}
                    className="mt-7 block rounded-xl px-6 py-3 font-black text-white"
                >
                    {t(
                        'continueLogin',
                    )}
                </Link>
            </div>
        </div>
    );
}
