import { Link } from '@inertiajs/react';

const style = (active) =>
    `rounded-xl px-4 py-2 text-sm font-black ${
        active
            ? 'bg-slate-900 text-white'
            : 'bg-white text-slate-700 ring-1 ring-slate-200'
    }`;

export default function HotelCommercialMenu() {
    if (
        !route().current(
            'superadmin.hotel.*',
        )
    ) {
        return null;
    }

    return (
        <div className="mb-5 flex flex-wrap gap-2">
            <Link
                href={route(
                    'superadmin.hotel.dashboard',
                )}
                className={style(
                    route().current(
                        'superadmin.hotel.dashboard',
                    ),
                )}
            >
                Dashboard
            </Link>

            <Link
                href={route(
                    'superadmin.hotel.hotels.index',
                )}
                className={style(
                    route().current(
                        'superadmin.hotel.hotels.*',
                    ),
                )}
            >
                Hotels
            </Link>

            <Link
                href={route(
                    'superadmin.hotel.plans.index',
                )}
                className={style(
                    route().current(
                        'superadmin.hotel.plans.*',
                    ),
                )}
            >
                Plans
            </Link>

            <Link
                href={route(
                    'superadmin.hotel.commercial.index',
                )}
                className={style(
                    route().current(
                        'superadmin.hotel.commercial.*',
                    ),
                )}
            >
                Billing & Operations
            </Link>
        </div>
    );
}
