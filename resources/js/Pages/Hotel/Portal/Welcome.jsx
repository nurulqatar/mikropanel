import {
    BrandHeader,
    LanguagePicker,
    PortalFrame,
    tx,
} from '@/Hotel/PortalV15Kit';
import {
    Head,
    Link,
} from '@inertiajs/react';
import {
    useState,
} from 'react';

export default function Welcome({
    hotel,
    locale,
}) {
    const [
        selected,
        setSelected,
    ] = useState(
        locale,
    );

    return (
        <PortalFrame
            hotel={
                hotel
            }
            locale={
                selected
            }
        >
            <Head
                title={
                    hotel.name
                }
            />

            <div className="overflow-hidden rounded-[2rem] bg-white shadow-2xl">
                <BrandHeader
                    hotel={
                        hotel
                    }
                    title={
                        hotel.portal_title
                        || tx(
                            selected,
                            'Welcome to',
                        )
                    }
                    subtitle={
                        hotel.portal_subtitle
                        || tx(
                            selected,
                            'Secure high-speed internet for our valued hotel guests.',
                        )
                    }
                />

                <div className="p-6">
                    <div className="mb-4 inline-flex rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-black text-emerald-700">
                        {tx(
                            selected,
                            '● Guest WiFi Available',
                        )}
                    </div>

                    <h2 className="text-xl font-black text-slate-900">
                        {tx(
                            selected,
                            'Connect in seconds',
                        )}
                    </h2>

                    <p className="mt-2 text-sm leading-6 text-slate-500">
                        {tx(
                            selected,
                            'Choose your language above and continue to secure hotel WiFi access.',
                        )}
                    </p>

                    <div className="mt-5">
                        <LanguagePicker
                            locale={
                                selected
                            }
                            onSelect={
                                setSelected
                            }
                        />
                    </div>

                    <Link
                        href={route(
                            'hotel.portal.access',
                            {
                                hotel:
                                    hotel.slug,

                                lang:
                                    selected,
                            },
                        )}
                        className="mt-5 block rounded-2xl bg-gradient-to-r from-blue-600 to-cyan-500 px-6 py-4 text-center text-lg font-black text-white shadow-lg shadow-blue-500/20"
                    >
                        {tx(
                            selected,
                            'Get WiFi Access',
                        )}
                    </Link>

                    <div className="mt-5 text-center text-xs leading-5 text-slate-400">
                        {tx(
                            selected,
                            '🔒 Your connection and registration information is protected and used only for guest WiFi access.',
                        )}
                    </div>
                </div>
            </div>
        </PortalFrame>
    );
}
