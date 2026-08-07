import AppLayout from '@/Layouts/AppLayout';
import { Link, router } from '@inertiajs/react';

export default function Index({
    notifications,
}) {
    const items =
        notifications?.data ?? [];

    const markRead = (item) => {
        if (item.read) {
            if (item.action_url) {
                router.visit(
                    item.action_url,
                );
            }

            return;
        }

        router.post(
            `/notifications/${item.id}/read`,
            {},
            {
                preserveScroll: true,

                onSuccess: () => {
                    if (
                        item.action_url
                    ) {
                        router.visit(
                            item.action_url,
                        );
                    }
                },
            },
        );
    };

    const markAllRead = () => {
        router.post(
            '/notifications/read-all',
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const clearRead = () => {
        if (
            !window.confirm(
                'Clear all read notifications?'
            )
        ) {
            return;
        }

        router.delete(
            '/notifications/clear-read',
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppLayout title="Notifications">
            <div className="mx-auto max-w-5xl space-y-5">
                <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-lg font-bold text-slate-800">
                            Notification History
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Payment, due and expiry
                            alerts from the panel.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={
                                markAllRead
                            }
                            className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Mark All Read
                        </button>

                        <button
                            type="button"
                            onClick={
                                clearRead
                            }
                            className="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-50"
                        >
                            Clear Read
                        </button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {items.length === 0 ? (
                        <div className="px-6 py-16 text-center text-sm text-slate-500">
                            No notifications yet.
                        </div>
                    ) : (
                        items.map(
                            (item) => (
                                <button
                                    key={
                                        item.id
                                    }
                                    type="button"
                                    onClick={() =>
                                        markRead(
                                            item,
                                        )
                                    }
                                    className={`block w-full border-b border-slate-100 px-5 py-4 text-left transition last:border-b-0 hover:bg-slate-50 ${
                                        item.read
                                            ? 'bg-white'
                                            : 'bg-sky-50/60'
                                    }`}
                                >
                                    <div className="flex gap-4">
                                        <span
                                            className={`mt-2 h-2.5 w-2.5 shrink-0 rounded-full ${
                                                item.read
                                                    ? 'bg-slate-300'
                                                    : 'bg-sky-600'
                                            }`}
                                        />

                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                                <div className="font-semibold text-slate-800">
                                                    {
                                                        item.title
                                                    }
                                                </div>

                                                <div className="shrink-0 text-xs text-slate-400">
                                                    {
                                                        item.created_at
                                                    }
                                                </div>
                                            </div>

                                            <div className="mt-1 text-sm leading-6 text-slate-600">
                                                {
                                                    item.message
                                                }
                                            </div>

                                            {item.action_url && (
                                                <div className="mt-2 text-xs font-semibold text-sky-700">
                                                    Open client →
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </button>
                            ),
                        )
                    )}
                </div>

                {(notifications?.prev_page_url ||
                    notifications?.next_page_url) && (
                    <div className="flex items-center justify-between">
                        <div>
                            {notifications?.prev_page_url && (
                                <Link
                                    href={
                                        notifications.prev_page_url
                                    }
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                                >
                                    Previous
                                </Link>
                            )}
                        </div>

                        <div className="text-sm text-slate-500">
                            Page{' '}
                            {notifications?.current_page ??
                                1}{' '}
                            of{' '}
                            {notifications?.last_page ??
                                1}
                        </div>

                        <div>
                            {notifications?.next_page_url && (
                                <Link
                                    href={
                                        notifications.next_page_url
                                    }
                                    className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
                                >
                                    Next
                                </Link>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
