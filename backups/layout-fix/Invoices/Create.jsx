import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ clients }) {

    const { data, setData, post, processing, errors } = useForm({
        client_id: '',
        billing_month: '',
        amount: '',
        discount: 0,
        issue_date: '',
        due_date: '',
        notes: '',
    });

    function submit(e) {
        e.preventDefault();
        post(route('invoices.store'));
    }

    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold">Create Invoice</h2>}
        >
            <Head title="Create Invoice" />

            <div className="py-8">
                <div className="mx-auto max-w-3xl rounded bg-white p-6 shadow">

                    <form onSubmit={submit} className="space-y-4">

                        <div>
                            <label className="block mb-1">Client</label>

                            <select
                                className="w-full rounded border"
                                value={data.client_id}
                                onChange={e =>
                                    setData('client_id', e.target.value)
                                }
                            >
                                <option value="">Select Client</option>

                                {clients.map(client => (
                                    <option
                                        key={client.id}
                                        value={client.id}
                                    >
                                        {client.client_code} - {client.name}
                                    </option>
                                ))}

                            </select>

                            {errors.client_id && (
                                <div className="text-red-500 text-sm">
                                    {errors.client_id}
                                </div>
                            )}
                        </div>

                        <div>
                            <label className="block mb-1">
                                Billing Month
                            </label>

                            <input
                                type="date"
                                className="w-full rounded border"
                                value={data.billing_month}
                                onChange={e =>
                                    setData('billing_month', e.target.value)
                                }
                            />
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
                                Discount
                            </label>

                            <input
                                type="number"
                                className="w-full rounded border"
                                value={data.discount}
                                onChange={e =>
                                    setData('discount', e.target.value)
                                }
                            />
                        </div>

                        <div>
                            <label className="block mb-1">
                                Issue Date
                            </label>

                            <input
                                type="date"
                                className="w-full rounded border"
                                value={data.issue_date}
                                onChange={e =>
                                    setData('issue_date', e.target.value)
                                }
                            />
                        </div>

                        <div>
                            <label className="block mb-1">
                                Due Date
                            </label>

                            <input
                                type="date"
                                className="w-full rounded border"
                                value={data.due_date}
                                onChange={e =>
                                    setData('due_date', e.target.value)
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
                            type="submit"
                            disabled={processing}
                            className="rounded bg-blue-600 px-5 py-2 text-white"
                        >
                            Save Invoice
                        </button>

                    </form>

                </div>
            </div>

        </AuthenticatedLayout>
    );
}
