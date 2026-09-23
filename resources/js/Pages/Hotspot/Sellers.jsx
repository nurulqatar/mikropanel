import AppLayout from '@/Layouts/AppLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

const inputClass =
    'w-full rounded-lg border border-slate-300 px-3 py-2.5 focus:border-cyan-500 focus:ring-cyan-500';

const money = (value) =>
    Number(value ?? 0).toFixed(2);

const dateTime = (value) =>
    value
        ? new Date(value).toLocaleString()
        : '-';

export default function Sellers({
    summary = {},
    sellers = [],
    batches = [],
    collections = [],
    sales = [],
}) {
    const sellerForm = useForm({
        code: '',
        name: '',
        phone: '',
        notes: '',
    });

    const batchForm = useForm({
        batch_id: '',
        seller_id: '',
    });

    const voucherForm = useForm({
        voucher_code: '',
        seller_id: '',
    });

    const collectionForm = useForm({
        seller_id: '',
        amount: '',
        payment_method: 'Cash',
        reference: '',
        notes: '',
    });

    const activeSellers =
        sellers.filter(
            (seller) => seller.active,
        );

    const selectedSeller =
        sellers.find(
            (seller) =>
                String(seller.id)
                === String(
                    collectionForm.data
                        .seller_id,
                ),
        );

    return (
        <AppLayout title="Hotspot Sellers">
            <Head title="Hotspot Sellers" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-3xl font-black">
                            Sellers & Collections
                        </h1>

                        <p className="mt-1 text-slate-500">
                            Assign printed vouchers to sellers,
                            track automatic paid sales, and
                            collect seller cash.
                        </p>
                    </div>

                    <div className="flex gap-2">
                        <Link
                            href={route(
                                'hotspot.vouchers.index',
                            )}
                            className="rounded-xl bg-slate-700 px-4 py-2.5 font-bold text-white"
                        >
                            Vouchers
                        </Link>

                        <Link
                            href={route(
                                'hotspot.batches.index',
                            )}
                            className="rounded-xl bg-violet-600 px-4 py-2.5 font-bold text-white"
                        >
                            Batches
                        </Link>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-3 xl:grid-cols-6">
                    <Stat
                        label="Sellers"
                        value={
                            summary.sellers ?? 0
                        }
                    />
                    <Stat
                        label="Active"
                        value={
                            summary.active_sellers
                            ?? 0
                        }
                    />
                    <Stat
                        label="Sold Vouchers"
                        value={
                            summary.sold_vouchers
                            ?? 0
                        }
                    />
                    <Stat
                        label="Sales"
                        value={`QAR ${money(
                            summary.sales_amount,
                        )}`}
                    />
                    <Stat
                        label="Collected"
                        value={`QAR ${money(
                            summary.collected_amount,
                        )}`}
                    />
                    <Stat
                        label="Seller Outstanding"
                        value={`QAR ${money(
                            summary.outstanding_amount,
                        )}`}
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            sellerForm.post(
                                route(
                                    'hotspot.sellers.store',
                                ),
                                {
                                    preserveScroll:
                                        true,
                                    onSuccess: () =>
                                        sellerForm.reset(),
                                },
                            );
                        }}
                        className="rounded-2xl border bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-xl font-black">
                            Add Seller
                        </h2>

                        <div className="mt-5 grid gap-4 md:grid-cols-2">
                            <Field label="Seller Name">
                                <input
                                    className={
                                        inputClass
                                    }
                                    value={
                                        sellerForm
                                            .data.name
                                    }
                                    onChange={(e) =>
                                        sellerForm
                                            .setData(
                                                'name',
                                                e.target
                                                    .value,
                                            )
                                    }
                                    required
                                />
                            </Field>

                            <Field label="Seller Code (Optional)">
                                <input
                                    className={
                                        inputClass
                                    }
                                    value={
                                        sellerForm
                                            .data.code
                                    }
                                    onChange={(e) =>
                                        sellerForm
                                            .setData(
                                                'code',
                                                e.target
                                                    .value,
                                            )
                                    }
                                    placeholder="Auto if empty"
                                />
                            </Field>

                            <Field label="Phone">
                                <input
                                    className={
                                        inputClass
                                    }
                                    value={
                                        sellerForm
                                            .data.phone
                                    }
                                    onChange={(e) =>
                                        sellerForm
                                            .setData(
                                                'phone',
                                                e.target
                                                    .value,
                                            )
                                    }
                                />
                            </Field>

                            <Field label="Notes">
                                <input
                                    className={
                                        inputClass
                                    }
                                    value={
                                        sellerForm
                                            .data.notes
                                    }
                                    onChange={(e) =>
                                        sellerForm
                                            .setData(
                                                'notes',
                                                e.target
                                                    .value,
                                            )
                                    }
                                />
                            </Field>
                        </div>

                        <button
                            className="mt-5 rounded-xl bg-cyan-600 px-5 py-3 font-bold text-white disabled:opacity-50"
                            disabled={
                                sellerForm.processing
                            }
                        >
                            Add Seller
                        </button>
                    </form>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            batchForm.post(
                                route(
                                    'hotspot.sellers.assign-batch',
                                ),
                                {
                                    preserveScroll:
                                        true,
                                },
                            );
                        }}
                        className="rounded-2xl border bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-xl font-black">
                            Assign Voucher Batch
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Assign the printed batch before
                            giving it to a seller.
                        </p>

                        <div className="mt-5 grid gap-4 md:grid-cols-2">
                            <Field label="Batch">
                                <select
                                    className={
                                        inputClass
                                    }
                                    value={
                                        batchForm.data
                                            .batch_id
                                    }
                                    onChange={(e) =>
                                        batchForm
                                            .setData(
                                                'batch_id',
                                                e.target
                                                    .value,
                                            )
                                    }
                                    required
                                >
                                    <option value="">
                                        Select batch
                                    </option>

                                    {batches.map(
                                        (batch) => (
                                            <option
                                                key={
                                                    batch.id
                                                }
                                                value={
                                                    batch.id
                                                }
                                            >
                                                {
                                                    batch.batch_code
                                                }
                                                {' · '}
                                                {
                                                    batch.plan
                                                }
                                                {' · '}
                                                {
                                                    batch.vouchers_count
                                                }
                                                {' vouchers'}
                                                {batch
                                                    .seller
                                                    ? ` · ${batch.seller.name}`
                                                    : ''}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>

                            <Field label="Seller">
                                <select
                                    className={
                                        inputClass
                                    }
                                    value={
                                        batchForm.data
                                            .seller_id
                                    }
                                    onChange={(e) =>
                                        batchForm
                                            .setData(
                                                'seller_id',
                                                e.target
                                                    .value,
                                            )
                                    }
                                    required
                                >
                                    <option value="">
                                        Select seller
                                    </option>

                                    {activeSellers.map(
                                        (seller) => (
                                            <option
                                                key={
                                                    seller.id
                                                }
                                                value={
                                                    seller.id
                                                }
                                            >
                                                {
                                                    seller.code
                                                }
                                                {' · '}
                                                {
                                                    seller.name
                                                }
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>
                        </div>

                        <button
                            className="mt-5 rounded-xl bg-violet-600 px-5 py-3 font-bold text-white"
                        >
                            Assign Batch
                        </button>
                    </form>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            voucherForm.post(
                                route(
                                    'hotspot.sellers.assign-voucher',
                                ),
                                {
                                    preserveScroll:
                                        true,
                                },
                            );
                        }}
                        className="rounded-2xl border bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-xl font-black">
                            Assign Individual Voucher
                        </h2>

                        <div className="mt-5 grid gap-4 md:grid-cols-2">
                            <Field label="6-Digit Voucher">
                                <input
                                    className={
                                        inputClass
                                    }
                                    value={
                                        voucherForm
                                            .data
                                            .voucher_code
                                    }
                                    onChange={(e) =>
                                        voucherForm
                                            .setData(
                                                'voucher_code',
                                                e.target
                                                    .value
                                                    .replace(
                                                        /\D/g,
                                                        '',
                                                    )
                                                    .slice(
                                                        0,
                                                        6,
                                                    ),
                                            )
                                    }
                                    maxLength={6}
                                    required
                                />
                            </Field>

                            <Field label="Seller">
                                <select
                                    className={
                                        inputClass
                                    }
                                    value={
                                        voucherForm
                                            .data
                                            .seller_id
                                    }
                                    onChange={(e) =>
                                        voucherForm
                                            .setData(
                                                'seller_id',
                                                e.target
                                                    .value,
                                            )
                                    }
                                    required
                                >
                                    <option value="">
                                        Select seller
                                    </option>

                                    {activeSellers.map(
                                        (seller) => (
                                            <option
                                                key={
                                                    seller.id
                                                }
                                                value={
                                                    seller.id
                                                }
                                            >
                                                {
                                                    seller.code
                                                }
                                                {' · '}
                                                {
                                                    seller.name
                                                }
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>
                        </div>

                        <button
                            className="mt-5 rounded-xl bg-slate-800 px-5 py-3 font-bold text-white"
                        >
                            Assign Voucher
                        </button>
                    </form>

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();

                            if (
                                !collectionForm
                                    .data.seller_id
                            ) {
                                return;
                            }

                            collectionForm.post(
                                route(
                                    'hotspot.sellers.collections.store',
                                    collectionForm
                                        .data
                                        .seller_id,
                                ),
                                {
                                    preserveScroll:
                                        true,
                                    onSuccess: () =>
                                        collectionForm
                                            .setData({
                                                seller_id:
                                                    '',
                                                amount:
                                                    '',
                                                payment_method:
                                                    'Cash',
                                                reference:
                                                    '',
                                                notes:
                                                    '',
                                            }),
                                },
                            );
                        }}
                        className="rounded-2xl border bg-white p-6 shadow-sm"
                    >
                        <h2 className="text-xl font-black">
                            Collect Money from Seller
                        </h2>

                        <div className="mt-5 grid gap-4 md:grid-cols-2">
                            <Field label="Seller">
                                <select
                                    className={
                                        inputClass
                                    }
                                    value={
                                        collectionForm
                                            .data
                                            .seller_id
                                    }
                                    onChange={(e) =>
                                        collectionForm
                                            .setData(
                                                'seller_id',
                                                e.target
                                                    .value,
                                            )
                                    }
                                    required
                                >
                                    <option value="">
                                        Select seller
                                    </option>

                                    {sellers.map(
                                        (seller) => (
                                            <option
                                                key={
                                                    seller.id
                                                }
                                                value={
                                                    seller.id
                                                }
                                            >
                                                {
                                                    seller.code
                                                }
                                                {' · '}
                                                {
                                                    seller.name
                                                }
                                                {' · Due QAR '}
                                                {money(
                                                    seller
                                                        .outstanding_amount,
                                                )}
                                            </option>
                                        ),
                                    )}
                                </select>
                            </Field>

                            <Field label="Amount">
                                <input
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    max={
                                        selectedSeller
                                            ?.outstanding_amount
                                    }
                                    className={
                                        inputClass
                                    }
                                    value={
                                        collectionForm
                                            .data.amount
                                    }
                                    onChange={(e) =>
                                        collectionForm
                                            .setData(
                                                'amount',
                                                e.target
                                                    .value,
                                            )
                                    }
                                    required
                                />
                            </Field>

                            <Field label="Method">
                                <select
                                    className={
                                        inputClass
                                    }
                                    value={
                                        collectionForm
                                            .data
                                            .payment_method
                                    }
                                    onChange={(e) =>
                                        collectionForm
                                            .setData(
                                                'payment_method',
                                                e.target
                                                    .value,
                                            )
                                    }
                                >
                                    <option>
                                        Cash
                                    </option>
                                    <option>
                                        Bank Transfer
                                    </option>
                                    <option>
                                        Mobile Transfer
                                    </option>
                                    <option>
                                        Other
                                    </option>
                                </select>
                            </Field>

                            <Field label="Reference">
                                <input
                                    className={
                                        inputClass
                                    }
                                    value={
                                        collectionForm
                                            .data.reference
                                    }
                                    onChange={(e) =>
                                        collectionForm
                                            .setData(
                                                'reference',
                                                e.target
                                                    .value,
                                            )
                                    }
                                />
                            </Field>
                        </div>

                        {selectedSeller && (
                            <div className="mt-4 rounded-xl bg-amber-50 p-4 text-sm font-bold text-amber-800">
                                Current seller outstanding:
                                {' QAR '}
                                {money(
                                    selectedSeller
                                        .outstanding_amount,
                                )}
                            </div>
                        )}

                        <Field label="Notes">
                            <textarea
                                className={`${inputClass} mt-4`}
                                rows={2}
                                value={
                                    collectionForm
                                        .data.notes
                                }
                                onChange={(e) =>
                                    collectionForm
                                        .setData(
                                            'notes',
                                            e.target
                                                .value,
                                        )
                                }
                            />
                        </Field>

                        <button
                            className="mt-5 rounded-xl bg-emerald-600 px-5 py-3 font-bold text-white"
                        >
                            Record Collection
                        </button>
                    </form>
                </div>

                <section className="overflow-hidden rounded-2xl border bg-white shadow-sm">
                    <div className="border-b px-5 py-4">
                        <h2 className="text-xl font-black">
                            Seller Balances
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Seller</Th>
                                    <Th>Assigned</Th>
                                    <Th>Unsold</Th>
                                    <Th>Sold</Th>
                                    <Th>Sales</Th>
                                    <Th>Collected</Th>
                                    <Th>Outstanding</Th>
                                    <Th>Status</Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y">
                                {sellers.map(
                                    (seller) => (
                                        <tr
                                            key={
                                                seller.id
                                            }
                                        >
                                            <Td>
                                                <div className="font-bold">
                                                    {
                                                        seller.name
                                                    }
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    {
                                                        seller.code
                                                    }
                                                    {seller.phone
                                                        ? ` · ${seller.phone}`
                                                        : ''}
                                                </div>
                                            </Td>

                                            <Td>
                                                {
                                                    seller.assigned_count
                                                }
                                            </Td>
                                            <Td>
                                                {
                                                    seller.unsold_count
                                                }
                                            </Td>
                                            <Td>
                                                {
                                                    seller.sold_count
                                                }
                                            </Td>
                                            <Td>
                                                QAR{' '}
                                                {money(
                                                    seller.sales_amount,
                                                )}
                                            </Td>
                                            <Td>
                                                QAR{' '}
                                                {money(
                                                    seller.collected_amount,
                                                )}
                                            </Td>
                                            <Td>
                                                <span className="font-black text-amber-700">
                                                    QAR{' '}
                                                    {money(
                                                        seller.outstanding_amount,
                                                    )}
                                                </span>
                                            </Td>
                                            <Td>
                                                {seller.active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </Td>
                                        </tr>
                                    ),
                                )}

                                {sellers.length
                                    === 0 && (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="p-8 text-center text-slate-500"
                                        >
                                            No sellers created yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-2">
                    <section className="overflow-hidden rounded-2xl border bg-white shadow-sm">
                        <div className="border-b px-5 py-4">
                            <h2 className="text-xl font-black">
                                Recent Seller Sales
                            </h2>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <Th>Seller</Th>
                                        <Th>Voucher</Th>
                                        <Th>Amount</Th>
                                        <Th>Started</Th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y">
                                    {sales.map(
                                        (sale) => (
                                            <tr
                                                key={
                                                    sale.id
                                                }
                                            >
                                                <Td>
                                                    {
                                                        sale.seller_name
                                                    }
                                                    <div className="text-xs text-slate-500">
                                                        {
                                                            sale.seller_code
                                                        }
                                                    </div>
                                                </Td>
                                                <Td>
                                                    {
                                                        sale.voucher_code
                                                    }
                                                </Td>
                                                <Td>
                                                    QAR{' '}
                                                    {money(
                                                        sale.amount,
                                                    )}
                                                </Td>
                                                <Td>
                                                    {dateTime(
                                                        sale.service_from,
                                                    )}
                                                </Td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-2xl border bg-white shadow-sm">
                        <div className="border-b px-5 py-4">
                            <h2 className="text-xl font-black">
                                Collection History
                            </h2>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <Th>Date</Th>
                                        <Th>Seller</Th>
                                        <Th>Amount</Th>
                                        <Th>Method</Th>
                                        <Th>Reference</Th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y">
                                    {collections.map(
                                        (row) => (
                                            <tr
                                                key={
                                                    row.id
                                                }
                                            >
                                                <Td>
                                                    {dateTime(
                                                        row.collected_at,
                                                    )}
                                                </Td>
                                                <Td>
                                                    {
                                                        row.seller
                                                            ?.name
                                                    }
                                                </Td>
                                                <Td>
                                                    QAR{' '}
                                                    {money(
                                                        row.amount,
                                                    )}
                                                </Td>
                                                <Td>
                                                    {
                                                        row.payment_method
                                                    }
                                                </Td>
                                                <Td>
                                                    {
                                                        row.reference
                                                        || '-'
                                                    }
                                                </Td>
                                            </tr>
                                        ),
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}

function Stat({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border bg-white p-5 shadow-sm">
            <div className="text-xs font-black uppercase tracking-wide text-slate-500">
                {label}
            </div>
            <div className="mt-2 text-2xl font-black">
                {value}
            </div>
        </div>
    );
}

function Field({
    label,
    children,
}) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-bold text-slate-700">
                {label}
            </span>
            {children}
        </label>
    );
}

function Th({
    children,
}) {
    return (
        <th className="whitespace-nowrap px-5 py-3 text-left text-xs font-black uppercase text-slate-500">
            {children}
        </th>
    );
}

function Td({
    children,
}) {
    return (
        <td className="whitespace-nowrap px-5 py-4 text-sm">
            {children}
        </td>
    );
}
