import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import {
    Head,
    router,
} from '@inertiajs/react';

export default function Index({
    notifications = [],
    unread = 0,
}) {
    return (
        <SuperAdminLayout title="Reseller Notifications">
            <Head title="Reseller Notifications" />

            <NotificationList
                title="Reseller Notifications"
                notifications={
                    notifications
                }
                unread={unread}
                readRoute="superadmin.notifications.read"
                readAllRoute="superadmin.notifications.read-all"
            />
        </SuperAdminLayout>
    );
}

function NotificationList({
    title,
    notifications,
    unread,
    readRoute,
    readAllRoute,
}) {
    return (
        <div className="space-y-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-3xl font-black">
                        {title}
                    </h1>

                    <p className="text-slate-500">
                        Unread: {unread}
                    </p>
                </div>

                {unread > 0 && (
                    <button
                        onClick={() =>
                            router.post(
                                route(
                                    readAllRoute,
                                ),
                            )
                        }
                        className="rounded-xl bg-cyan-600 px-5 py-3 font-bold text-white"
                    >
                        Mark All Read
                    </button>
                )}
            </div>

            <div className="space-y-3">
                {notifications.map(
                    (item) => (
                        <div
                            key={item.id}
                            className={`rounded-xl border p-5 ${
                                item.read_at
                                    ? 'bg-white'
                                    : 'border-cyan-200 bg-cyan-50'
                            }`}
                        >
                            <div className="flex flex-wrap justify-between gap-3">
                                <div>
                                    <div className="font-bold">
                                        {
                                            item.title
                                        }
                                    </div>

                                    <div className="mt-1 text-sm text-slate-600">
                                        {
                                            item.message
                                        }
                                    </div>

                                    <div className="mt-2 text-xs text-slate-400">
                                        {item.reseller
                                            ?.company_name ||
                                            '-'}
                                        {' · '}
                                        {
                                            item.created_at
                                        }
                                    </div>
                                </div>

                                {!item.read_at && (
                                    <button
                                        onClick={() =>
                                            router.post(
                                                route(
                                                    readRoute,
                                                    item.id,
                                                ),
                                            )
                                        }
                                        className="self-start rounded-lg bg-slate-700 px-3 py-2 text-sm font-bold text-white"
                                    >
                                        Mark Read
                                    </button>
                                )}
                            </div>
                        </div>
                    ),
                )}

                {!notifications.length && (
                    <div className="rounded-xl border bg-white p-10 text-center text-slate-400">
                        No notification.
                    </div>
                )}
            </div>
        </div>
    );
}
