import {
    Link,
    usePage,
} from '@inertiajs/react';

const style = (active) =>
    `rounded-xl px-4 py-2 text-sm font-black ${
        active
            ? 'bg-slate-900 text-white'
            : 'bg-white text-slate-700 ring-1 ring-slate-200'
    }`;

export default function HotelNetworkMenu() {
    const { props } = usePage();

    const context =
        props.hotelContext
        ?? props.hotel_context
        ?? props.hotelAuth
        ?? {};

    const user =
        context.user
        ?? props.hotelUser
        ?? null;

    const permissions =
        user?.permissions
        ?? context.permissions
        ?? [];

    const admin =
        user?.role === 'admin'
        || user?.is_admin === true;

    const reports =
        !user
        || admin
        || permissions.includes(
            'reports.view',
        );

    const operations =
        reports
        || permissions.includes(
            'vouchers.issue',
        )
        || permissions.includes(
            'routers.manage',
        );

    return (
        <div className="mb-5 flex flex-wrap gap-2">
            {operations && (
                <Link
                    href={route(
                        'hotel.operations.index',
                    )}
                    className={style(
                        route().current(
                            'hotel.operations.*',
                        ),
                    )}
                >
                    Operations
                </Link>
            )}

            <Link
                href={route(
                    'hotel.vouchers.index',
                )}
                className={style(
                    route().current(
                        'hotel.vouchers.*',
                    ),
                )}
            >
                Vouchers
            </Link>

            <Link
                href={route(
                    'hotel.routers.index',
                )}
                className={style(
                    route().current(
                        'hotel.routers.*',
                    ),
                )}
            >
                MikroTik Routers
            </Link>

            {reports && (
                <Link
                    href={route(
                        'hotel.reports.index',
                    )}
                    className={style(
                        route().current(
                            'hotel.reports.*',
                        ),
                    )}
                >
                    Reports
                </Link>
            )}

            {reports && (
                <Link
                    href={route(
                        'hotel.billing.index',
                    )}
                    className={style(
                        route().current(
                            'hotel.billing.*',
                        ),
                    )}
                >
                    Billing
                </Link>
            )}
        </div>
    );
}
