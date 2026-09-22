import {
    BrandHeader,
    PortalFrame,
    tx,
} from '@/Hotel/PortalV15Kit';
import {
    Head,
    Link,
} from '@inertiajs/react';

export default function Access({
    hotel,
    locale,
}) {
    return (
        <PortalFrame
            hotel={
                hotel
            }
            locale={
                locale
            }
        >
            <Head
                title={tx(
                    locale,
                    'Guest WiFi Access',
                )}
            />

            <div className="overflow-hidden rounded-[2rem] bg-white shadow-2xl">
                <BrandHeader
                    hotel={
                        hotel
                    }
                    title={tx(
                        locale,
                        'Guest WiFi Access',
                    )}
                    subtitle={tx(
                        locale,
                        'Choose one option to continue.',
                    )}
                />

                <div className="space-y-4 p-6">
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
                        className="block rounded-2xl border-2 border-blue-100 bg-blue-50 p-5 transition hover:border-blue-300"
                    >
                        <div className="text-lg font-black text-blue-900">
                            {tx(
                                locale,
                                'I Already Registered — Login Now',
                            )}
                        </div>

                        <div className="mt-1 text-sm font-semibold text-blue-600">
                            {tx(
                                locale,
                                'Use your existing voucher code',
                            )}
                        </div>
                    </Link>

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
                        className="block rounded-2xl border-2 border-emerald-100 bg-emerald-50 p-5 transition hover:border-emerald-300"
                    >
                        <div className="text-lg font-black text-emerald-900">
                            {tx(
                                locale,
                                'New Guest — Register Now',
                            )}
                        </div>

                        <div className="mt-1 text-sm font-semibold text-emerald-600">
                            {tx(
                                locale,
                                'Create your hotel WiFi access',
                            )}
                        </div>
                    </Link>

                    <Link
                        href={route(
                            'hotel.portal.welcome',
                            {
                                hotel:
                                    hotel.slug,

                                lang:
                                    locale,
                            },
                        )}
                        className="block pt-2 text-center text-sm font-black text-slate-400"
                    >
                        ←{' '}
                        {tx(
                            locale,
                            'Back',
                        )}
                    </Link>
                </div>
            </div>
        </PortalFrame>
    );
}
