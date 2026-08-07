import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Index({ payments }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold text-gray-800">
                    Payments
                </h2>
            }
        >
            <Head title="Payments" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">

                    <div className="mb-4 flex justify-between">
                        <h3 className="text-lg font-semibold">
                            Payment History
                        </h3>

                        <Link
                            href={route('payments.create')}
                            className="rounded bg-green-600 px-4 py-2 text-white"
                        >
                            Receive Payment
                        </Link>
                    </div>

                    <div className="overflow-hidden rounded bg-white shadow">
                        <table className="min-w-full">
                            <thead className="bg-gray-100">
                                <tr>
                                    <th className="px-4 py-3 text-left">Client</th>
                                    <th className="px-4 py-3 text-left">Invoice</th>
                                    <th className="px-4 py-3 text-left">Amount</th>
                                    <th className="px-4 py-3 text-left">Date</th>
                                    <th className="px-4 py-3 text-left">Method</th>
                                </tr>
                            </thead>

                            <tbody>
                                {payments.map(payment => (
                                    <tr key={payment.id} className="border-t">
                                        <td className="px-4 py-3">
                                            {payment.client?.name}
                                        </td>

                                        <td className="px-4 py-3">
                                            {payment.invoice?.invoice_no}
                                        </td>

                                        <td className="px-4 py-3">
                                            ৳ {payment.amount}
                                        </td>

                                        <td className="px-4 py-3">
                                            {payment.payment_date}
                                        </td>

                                        <td className="px-4 py-3">
                                            {payment.payment_method}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>

                        </table>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
