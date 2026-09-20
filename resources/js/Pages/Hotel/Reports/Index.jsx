import HotelLayout from '@/Layouts/HotelLayout';
import {
    Head,
    Link,
    router,
} from '@inertiajs/react';
import { useState } from 'react';

function humanBytes(value) {
    let size = Number(value || 0);

    if (size < 1024) {
        return `${size} B`;
    }

    const units = [
        'KB',
        'MB',
        'GB',
        'TB',
    ];

    let index = -1;

    do {
        size /= 1024;
        index++;
    } while (
        size >= 1024 &&
        index < units.length - 1
    );

    return `${size.toFixed(2)} ${units[index]}`;
}

export default function Index({
    filters = {},
    stats = {},
    live = [],
    history = [],
    routers = [],
    guests = [],
}) {
    const [from, setFrom] =
        useState(filters.from ?? '');

    const [to, setTo] =
        useState(filters.to ?? '');

    const apply = (event) => {
        event.preventDefault();

        router.get(
            route(
                'hotel.reports.index',
            ),
            {
                from,
                to,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    return (
        <HotelLayout title="Hotel WiFi Reports">
            <Head title="Hotel WiFi Reports" />

            <div className="space-y-6">
                <section className="rounded-3xl bg-slate-950 p-7 text-white">
                    <div className="flex flex-wrap items-end justify-between gap-5">
                        <div>
                            <div className="text-xs font-black uppercase tracking-[0.25em] text-cyan-300">
                                Hotel WiFi Intelligence
                            </div>

                            <h1 className="mt-2 text-3xl font-black">
                                Live Sessions & Usage
                            </h1>
                        </div>

                        <Link
                            href={route(
                                'hotel.reports.csv',
                                {
                                    from,
                                    to,
                                },
                            )}
                            className="rounded-xl bg-cyan-400 px-5 py-3 font-black text-slate-950"
                        >
                            Download CSV
                        </Link>
                    </div>
                </section>

                <form
                    onSubmit={apply}
                    className="flex flex-wrap items-end gap-4 rounded-2xl border bg-white p-5"
                >
                    <DateField
                        label="From"
                        value={from}
                        change={setFrom}
                    />

                    <DateField
                        label="To"
                        value={to}
                        change={setTo}
                    />

                    <button className="rounded-xl bg-slate-900 px-6 py-2.5 font-black text-white">
                        Apply
                    </button>
                </form>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Stat
                        label="Online"
                        value={stats.online_users}
                    />

                    <Stat
                        label="Sessions"
                        value={stats.sessions}
                    />

                    <Stat
                        label="Vouchers"
                        value={stats.unique_vouchers}
                    />

                    <Stat
                        label="Active Vouchers"
                        value={stats.active_vouchers}
                    />

                    <Stat
                        label="Traffic"
                        value={humanBytes(
                            stats.total_bytes,
                        )}
                    />
                </div>

                <Panel title="Connected Guests">
                    <Table
                        headers={[
                            'Guest',
                            'Room',
                            'Voucher',
                            'Router',
                            'IP',
                            'MAC',
                            'Uptime',
                            'Traffic',
                        ]}
                        rows={live}
                        render={(row) => (
                            <tr
                                key={row.id}
                                className="border-t"
                            >
                                <Td>
                                    {row.voucher?.stay
                                        ?.guest?.name ??
                                        'Unknown'}
                                </Td>

                                <Td>
                                    {row.voucher?.stay
                                        ?.room_number ??
                                        '—'}
                                </Td>

                                <Td>{row.username}</Td>

                                <Td>
                                    {row.router?.name ??
                                        '—'}
                                </Td>

                                <Td>
                                    {row.ip_address ??
                                        '—'}
                                </Td>

                                <Td>
                                    {row.mac_address ??
                                        '—'}
                                </Td>

                                <Td>
                                    {row.uptime ??
                                        '—'}
                                </Td>

                                <Td>
                                    {humanBytes(
                                        Number(
                                            row.bytes_in ??
                                                0,
                                        ) +
                                            Number(
                                                row.bytes_out ??
                                                    0,
                                            ),
                                    )}
                                </Td>
                            </tr>
                        )}
                    />
                </Panel>

                <Panel title="Router Usage">
                    <Table
                        headers={[
                            'Router',
                            'Status',
                            'Online',
                            'Sessions',
                            'Traffic',
                        ]}
                        rows={routers}
                        render={(row) => (
                            <tr
                                key={row.id}
                                className="border-t"
                            >
                                <Td>
                                    {row.name}
                                    <div className="text-xs text-slate-400">
                                        {row.host}
                                    </div>
                                </Td>

                                <Td>
                                    {row.enabled
                                        ? row.status
                                        : 'disabled'}
                                </Td>

                                <Td>{row.online}</Td>

                                <Td>
                                    {row.sessions}
                                </Td>

                                <Td>
                                    {humanBytes(
                                        row.total_bytes,
                                    )}
                                </Td>
                            </tr>
                        )}
                    />
                </Panel>

                <Panel title="Guest Usage">
                    <Table
                        headers={[
                            'Guest',
                            'Room',
                            'Sessions',
                            'Traffic',
                        ]}
                        rows={guests}
                        render={(row) => (
                            <tr
                                key={row.guest_id}
                                className="border-t"
                            >
                                <Td>{row.name}</Td>

                                <Td>
                                    {row.room_number ??
                                        '—'}
                                </Td>

                                <Td>
                                    {row.sessions}
                                </Td>

                                <Td>
                                    {humanBytes(
                                        row.total_bytes,
                                    )}
                                </Td>
                            </tr>
                        )}
                    />
                </Panel>

                <Panel title="Session History">
                    <Table
                        headers={[
                            'Guest',
                            'Voucher',
                            'Router',
                            'State',
                            'Traffic',
                            'Started',
                            'Ended',
                        ]}
                        rows={history}
                        render={(row) => (
                            <tr
                                key={row.id}
                                className="border-t"
                            >
                                <Td>
                                    {row.voucher?.stay
                                        ?.guest?.name ??
                                        'Unknown'}
                                </Td>

                                <Td>{row.username}</Td>

                                <Td>
                                    {row.router?.name ??
                                        '—'}
                                </Td>

                                <Td>
                                    {row.is_online
                                        ? 'ONLINE'
                                        : 'ENDED'}
                                </Td>

                                <Td>
                                    {humanBytes(
                                        Number(
                                            row.bytes_in ??
                                                0,
                                        ) +
                                            Number(
                                                row.bytes_out ??
                                                    0,
                                            ),
                                    )}
                                </Td>

                                <Td>
                                    {row.started_at
                                        ? new Date(
                                            row.started_at,
                                        ).toLocaleString()
                                        : '—'}
                                </Td>

                                <Td>
                                    {row.ended_at
                                        ? new Date(
                                            row.ended_at,
                                        ).toLocaleString()
                                        : '—'}
                                </Td>
                            </tr>
                        )}
                    />
                </Panel>
            </div>
        </HotelLayout>
    );
}

function DateField({
    label,
    value,
    change,
}) {
    return (
        <label>
            <span className="block text-xs font-black uppercase text-slate-500">
                {label}
            </span>

            <input
                type="date"
                value={value}
                onChange={(event) =>
                    change(
                        event.target.value,
                    )
                }
                className="mt-1 rounded-xl border-slate-300"
            />
        </label>
    );
}

function Stat({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border bg-white p-5">
            <div className="text-xs font-black uppercase text-slate-400">
                {label}
            </div>

            <div className="mt-2 text-2xl font-black">
                {value ?? 0}
            </div>
        </div>
    );
}

function Panel({
    title,
    children,
}) {
    return (
        <section className="overflow-hidden rounded-2xl border bg-white">
            <div className="border-b px-5 py-4 text-lg font-black">
                {title}
            </div>

            {children}
        </section>
    );
}

function Table({
    headers,
    rows,
    render,
}) {
    return (
        <div className="overflow-x-auto">
            <table className="min-w-full">
                <thead className="bg-slate-50">
                    <tr>
                        {headers.map(
                            (header) => (
                                <th
                                    key={
                                        header
                                    }
                                    className="px-4 py-3 text-left text-xs font-black uppercase text-slate-500"
                                >
                                    {
                                        header
                                    }
                                </th>
                            ),
                        )}
                    </tr>
                </thead>

                <tbody>
                    {rows.length ? (
                        rows.map(render)
                    ) : (
                        <tr>
                            <td
                                colSpan={
                                    headers.length
                                }
                                className="p-8 text-center text-slate-400"
                            >
                                No records.
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}

function Td({
    children,
}) {
    return (
        <td className="whitespace-nowrap px-4 py-4 text-sm">
            {children}
        </td>
    );
}
