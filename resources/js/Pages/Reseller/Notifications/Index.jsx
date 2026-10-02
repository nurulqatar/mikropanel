import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

export default function Index({
    notifications = [],
    unread = 0,
}) {
    return (
        <AppLayout title="Notifications">
            <Head title="Notifications" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <Link
                            href={route(
                                'reseller.dashboard',
                            )}
                            className="font-semibold text-cyan-700"
                        >
                            ← Dashboard
                        </Link>

                        <h1 className="mt-2 text-3xl font-black">
                            Notifications
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
                                        'reseller.notifications.read-all',
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
                                <div className="flex justify-between gap-3">
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
                                                        'reseller.notifications.read',
                                                        item.id,
                                                    ),
                                                )
                                            }
                                            className="self-start rounded-lg bg-slate-700 px-3 py-2 text-sm font-bold text-white"
                                        >
                                            Read
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
        </AppLayout>
    );
}
