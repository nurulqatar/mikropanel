import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ invoices }) {

    const { data, setData, post, processing, errors } = useForm({
        invoice_id: '',
        client_id: '',
        amount: '',
        payment_date: new Date().toISOString().slice(0, 10),
        payment_method: 'Cash',
        transaction_id: '',
        notes: '',
    });

    function invoiceChanged(id) {

        const invoice = invoices.find(i => i.id == id);

        setData({
            ...data,
            invoice_id: id,
            client_id: invoice ? invoice.client_id : '',
            amount: invoice ? invoice.due_amount : '',
        });
    }

    function submit(e) {
        e.preventDefault();
        post(route('payments.store'));
    }

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold">
                    Receive Payment
                </h2>
            }
        >
            <Head title="Receive Payment" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl">

                    <form
                        onSubmit={submit}
                        className="rounded bg-white p-6 shadow space-y-5"
                    >

                        <div>
                            <label className="block mb-1">
                                Invoice
                            </label>

                            <select
                                className="w-full rounded border"
                                value={data.invoice_id}
                                onChange={e =>
                                    invoiceChanged(e.target.value)
                                }
                            >
                                <option value="">
                                    Select Invoice
                                </option>

                                {invoices.map(invoice => (
                                    <option
                                        key={invoice.id}
                                        value={invoice.id}
                                    >
                                        {invoice.invoice_no} —
                                        {' '}
                                        {invoice.client?.name}
                                        {' '}
                                        (Due:
                                        {' '}
                                        {invoice.due_amount}
                                        )
                                    </option>
                                ))}

                            </select>

                            {errors.invoice_id &&
                                <div className="text-red-600 text-sm">
                                    {errors.invoice_id}
                                </div>
                            }

                        </div>

                        <div>
                            <label className="block mb-1">
                                Amount
                            </label>

                            <input
                                type="number"
                                className="w-full rounded border"
                                value={data.amount}
                                onChange={e =>
                                    setData('amount', e.target.value)
                                }
                            />
                        </div>

                        <div>
                            <label className="block mb-1">
                                Payment Date
                            </label>

                            <input
                                type="date"
                                className="w-full rounded border"
                                value={data.payment_date}
                                onChange={e =>
                                    setData('payment_date', e.target.value)
                                }
                            />
                        </div>

                        <div>
                            <label className="block mb-1">
                                Payment Method
                            </label>

                            <select
                                className="w-full rounded border"
                                value={data.payment_method}
                                onChange={e =>
                                    setData('payment_method', e.target.value)
                                }
                            >
                                <option>Cash</option>
                                <option>bKash</option>
                                <option>Nagad</option>
                                <option>Bank</option>
                            </select>
                        </div>

                        <div>
                            <label className="block mb-1">
                                Transaction ID
                            </label>

                            <input
                                type="text"
                                className="w-full rounded border"
                                value={data.transaction_id}
                                onChange={e =>
                                    setData('transaction_id', e.target.value)
                                }
                            />
                        </div>

                        <div>
                            <label className="block mb-1">
                                Notes
                            </label>

                            <textarea
                                className="w-full rounded border"
                                value={data.notes}
                                onChange={e =>
                                    setData('notes', e.target.value)
                                }
                            />
                        </div>

                        <button
                            disabled={processing}
                            className="rounded bg-green-600 px-5 py-2 text-white"
                        >
                            Save Payment
                        </button>

                    </form>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
