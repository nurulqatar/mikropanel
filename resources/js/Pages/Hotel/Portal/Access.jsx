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

export default function Access({
    hotel,
    locale,
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
            <Head title={hotel.name} />

            <div className="w-full max-w-xl rounded-[2rem] bg-white p-8 shadow-2xl">
                {hotel.logo_url && (
                    <img
                        src={hotel.logo_url}
                        alt={hotel.name}
                        className="mx-auto h-20 max-w-48 object-contain"
                    />
                )}

                <h1 className="mt-5 text-center text-3xl font-black">
                    {t('guestWifi')}
                </h1>

                <p className="mt-2 text-center text-slate-500">
                    {t(
                        'chooseContinue',
                    )}
                </p>

                <div className="mt-8 grid gap-4">
                    <Link
                        href={route(
                            'hotel.portal.register',
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
                        className="rounded-2xl p-5 text-center text-lg font-black text-white shadow-lg"
                    >
                        {t(
                            'newGuestRegistration',
                        )}
                    </Link>

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
                        className="rounded-2xl bg-slate-900 p-5 text-center text-lg font-black text-white"
                    >
                        {t(
                            'existingGuestLogin',
                        )}
                    </Link>
                </div>
            </div>
        </div>
    );
}
