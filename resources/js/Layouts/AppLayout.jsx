import Sidebar from '@/Components/Layout/Sidebar';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

export default function AppLayout({
    title = 'Dashboard',
    children,
}) {
    const page = usePage();

    const panelSettings =
        page.props.panelSettings ?? {};

    const panelNotifications =
        page.props.panelNotifications ?? {
            unread_count: 0,
            items: [],
        };

    const panelName =
        panelSettings.panel_name ??
        'MikroPanel';

    const unreadCount = Number(
        panelNotifications.unread_count ?? 0,
    );

    const notifications =
        panelNotifications.items ?? [];

    const [notificationsOpen, setNotificationsOpen] =
        useState(false);

    const markRead = (item) => {
        if (
            item.read
            && item.action_url
        ) {
            router.visit(
                item.action_url,
            );

            return;
        }

        router.post(
            `/notifications/${item.id}/read`,
            {},
            {
                preserveScroll: true,
                preserveState: true,

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
                preserveState: true,
            },
        );
    };

    return (
        <div className="flex min-h-screen bg-gray-100">
            <Sidebar />

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="border-b bg-white shadow-sm">
                    <div className="flex min-h-16 items-center justify-between gap-4 px-5 py-3 md:px-8">
                        <h1 className="truncate text-xl font-bold text-slate-800 md:text-2xl">
                            {title}
                        </h1>

                        <div className="flex items-center gap-3">
                            <div className="relative">
                                <button
                                    type="button"
                                    onClick={() =>
                                        setNotificationsOpen(
                                            (value) =>
                                                !value,
                                        )
                                    }
                                    className="relative flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900"
                                    aria-label="Notifications"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="1.8"
                                        className="h-5 w-5"
                                        aria-hidden="true"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            d="M14.857 17.082A23.848 23.848 0 0 0 18 16.5c-1.035-1.153-1.714-2.65-1.714-4.286V9.857a4.286 4.286 0 1 0-8.572 0v2.357C7.714 13.85 7.035 15.347 6 16.5a23.848 23.848 0 0 0 3.143.582m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a2.857 2.857 0 0 1-5.714 0"
                                        />
                                    </svg>

                                    {unreadCount > 0 && (
                                        <span className="absolute -right-1 -top-1 flex min-h-5 min-w-5 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-bold text-white">
                                            {unreadCount > 99
                                                ? '99+'
                                                : unreadCount}
                                        </span>
                                    )}
                                </button>

                                {notificationsOpen && (
                                    <div className="absolute right-0 z-50 mt-3 w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl">
                                        <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                                            <div>
                                                <div className="font-bold text-slate-800">
                                                    Notifications
                                                </div>

                                                <div className="text-xs text-slate-500">
                                                    {unreadCount}{' '}
                                                    unread
                                                </div>
                                            </div>

                                            <div className="flex items-center gap-3">
                                                <a
                                                    href="/notifications"
                                                    className="text-xs font-semibold text-slate-600 hover:text-slate-900"
                                                >
                                                    View all
                                                </a>

                                                {unreadCount >
                                                    0 && (
                                                    <button
                                                        type="button"
                                                        onClick={
                                                            markAllRead
                                                        }
                                                        className="text-xs font-semibold text-sky-700 hover:text-sky-900"
                                                    >
                                                        Mark all read
                                                    </button>
                                                )}
                                            </div>
                                        </div>

                                        <div className="max-h-96 overflow-y-auto">
                                            {notifications.length ===
                                            0 ? (
                                                <div className="px-5 py-10 text-center text-sm text-slate-500">
                                                    No notifications
                                                    yet.
                                                </div>
                                            ) : (
                                                notifications.map(
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
                                                            className={`block w-full border-b border-slate-100 px-4 py-3 text-left transition last:border-b-0 hover:bg-slate-50 ${
                                                                item.read
                                                                    ? 'bg-white'
                                                                    : 'bg-sky-50/70'
                                                            }`}
                                                        >
                                                            <div className="flex items-start gap-3">
                                                                <span
                                                                    className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${
                                                                        item.read
                                                                            ? 'bg-slate-300'
                                                                            : 'bg-sky-600'
                                                                    }`}
                                                                />

                                                                <span className="min-w-0 flex-1">
                                                                    <span className="block text-sm font-semibold text-slate-800">
                                                                        {
                                                                            item.title
                                                                        }
                                                                    </span>

                                                                    <span className="mt-1 block text-xs leading-5 text-slate-600">
                                                                        {
                                                                            item.message
                                                                        }
                                                                    </span>

                                                                    <span className="mt-1.5 block text-[11px] text-slate-400">
                                                                        {
                                                                            item.created_at
                                                                        }
                                                                    </span>
                                                                </span>
                                                            </div>
                                                        </button>
                                                    ),
                                                )
                                            )}
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="hidden max-w-64 truncate font-semibold text-slate-600 sm:block">
                                {panelName}
                            </div>
                        </div>
                    </div>
                </header>

                <main className="min-w-0 flex-1 p-4 md:p-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
