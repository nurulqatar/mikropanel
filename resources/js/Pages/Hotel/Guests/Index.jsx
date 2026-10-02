import HotelLayout from '@/Layouts/HotelLayout';
import {
    Head,
} from '@inertiajs/react';

const formatDate = (value) => {
    if (!value) {
        return '-';
    }

    return new Date(
        value,
    ).toLocaleString();
};

export default function Index({
    stays = [],
}) {
    return (
        <HotelLayout title="Guests">
            <Head title="Hotel Guests" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black">
                        Hotel Guests
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Guest stays and voucher status.
                    </p>
                </div>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Guest</Th>
                                <Th>Room</Th>
                                <Th>Phone</Th>
                                <Th>Nationality</Th>
                                <Th>Check In</Th>
                                <Th>Check Out</Th>
                                <Th>Voucher</Th>
                                <Th>Status</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {stays.map(
                                (stay) => (
                                    <tr
                                        key={
                                            stay.id
                                        }
                                    >
                                        <Td>
                                            {
                                                stay.guest
                                                    ?.name
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                stay.room_number
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                stay.guest
                                                    ?.phone
                                                ?? '-'
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                stay.guest
                                                    ?.nationality
                                                ?? '-'
                                            }
                                        </Td>

                                        <Td>
                                            {formatDate(
                                                stay.check_in_at,
                                            )}
                                        </Td>

                                        <Td>
                                            {formatDate(
                                                stay.check_out_at,
                                            )}
                                        </Td>

                                        <Td>
                                            {
                                                stay.voucher
                                                    ?.username
                                                ?? '-'
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                stay.status
                                            }
                                        </Td>
                                    </tr>
                                ),
                            )}

                            {stays.length ===
                                0 && (
                                <tr>
                                    <td
                                        colSpan="8"
                                        className="p-10 text-center text-slate-400"
                                    >
                                        No guest registrations yet.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </HotelLayout>
    );
}

function Th({ children }) {
    return (
        <th className="whitespace-nowrap px-4 py-3 text-left text-xs font-black uppercase text-slate-500">
            {children}
        </th>
    );
}

function Td({ children }) {
    return (
        <td className="whitespace-nowrap px-4 py-4 text-sm">
            {children}
        </td>
    );
}
