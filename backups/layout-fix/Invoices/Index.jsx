import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ invoices }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold">
                    Invoices
                </h2>
            }
        >
            <Head title="Invoices" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">

                    <div className="mb-5 flex items-center justify-between">

                        <h3 className="text-lg font-bold">
                            Invoice List
                        </h3>

                        <Link
                            href={route('invoices.create')}
                            className="rounded bg-blue-600 px-4 py-2 text-white"
                        >
                            + New Invoice
                        </Link>

                    </div>

                    <div className="overflow-hidden rounded bg-white shadow">

                        <table className="min-w-full">

                            <thead className="bg-gray-100">

                                <tr>

                                    <th className="px-4 py-3 text-left">
                                        Invoice
                                    </th>

                                    <th className="px-4 py-3 text-left">
                                        Client
                                    </th>

                                    <th className="px-4 py-3 text-left">
                                        Amount
                                    </th>

                                    <th className="px-4 py-3 text-left">
                                        Due
                                    </th>

                                    <th className="px-4 py-3 text-left">
                                        Status
                                    </th>

                                    <th className="px-4 py-3 text-left">
                                        Actions
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                {invoices.length === 0 ? (

                                    <tr>

                                        <td
                                            colSpan="6"
                                            className="py-6 text-center text-gray-500"
                                        >
                                            No invoices found.
                                        </td>

                                    </tr>

                                ) : (

                                    invoices.map(invoice => (

                                        <tr
                                            key={invoice.id}
                                            className="border-t"
                                        >

                                            <td className="px-4 py-3">
                                                {invoice.invoice_no}
                                            </td>

                                            <td className="px-4 py-3">
                                                {invoice.client?.name}
                                            </td>

                                            <td className="px-4 py-3">
                                                ৳ {invoice.amount}
                                            </td>

                                            <td className="px-4 py-3">
                                                {invoice.due_date}
                                            </td>

                                            <td className="px-4 py-3">

                                                {invoice.status === 'paid' && (
                                                    <span className="text-green-600 font-bold">
                                                        Paid
                                                    </span>
                                                )}

                                                {invoice.status === 'partial' && (
                                                    <span className="text-yellow-600 font-bold">
                                                        Partial
                                                    </span>
                                                )}

                                                {invoice.status === 'unpaid' && (
                                                    <span className="text-red-600 font-bold">
                                                        Unpaid
                                                    </span>
                                                )}

                                            </td>

                                            <td className="px-4 py-3">

                                                <Link
                                                    href={route(
                                                        'invoices.edit',
                                                        invoice.id
                                                    )}
                                                    className="rounded bg-yellow-500 px-3 py-1 text-white"
                                                >
                                                    Edit
                                                </Link>

                                            </td>

                                        </tr>

                                    ))

                                )}

                            </tbody>

                        </table>

                    </div>

                </div>
            </div>

        </AuthenticatedLayout>
    );
}
