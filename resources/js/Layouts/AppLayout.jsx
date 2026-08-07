import Sidebar from '@/Components/Layout/Sidebar';
import { usePage } from '@inertiajs/react';

export default function AppLayout({
    title = 'Dashboard',
    children,
}) {
    const panelSettings =
        usePage().props.panelSettings ?? {};

    const panelName =
        panelSettings.panel_name ??
        'MikroPanel';

    return (
        <div className="flex min-h-screen bg-gray-100">
            <Sidebar />

            <div className="flex min-w-0 flex-1 flex-col">
                <header className="border-b bg-white shadow-sm">
                    <div className="flex min-h-16 items-center justify-between gap-4 px-5 py-3 md:px-8">
                        <h1 className="truncate text-xl font-bold text-slate-800 md:text-2xl">
                            {title}
                        </h1>

                        <div className="truncate font-semibold text-slate-600">
                            {panelName}
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
