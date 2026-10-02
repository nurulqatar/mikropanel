import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import {
    Head,
    useForm,
} from '@inertiajs/react';

const inputClass =
    'w-full rounded-xl border-slate-300';

export default function Edit({
    hotel,
    admin,
    plans = [],
    subscriptions = [],
}) {
    const form =
        useForm({
            name:
                hotel.name ?? '',

            status:
                hotel.status
                ?? 'active',

            owner_name:
                hotel.owner_name
                ?? '',

            email:
                hotel.email ?? '',

            phone:
                hotel.phone ?? '',

            whatsapp:
                hotel.whatsapp
                ?? '',

            address:
                hotel.address
                ?? '',

            timezone:
                hotel.timezone
                ?? 'Asia/Qatar',

            currency:
                hotel.currency
                ?? 'QAR',

            check_out_time:
                String(
                    hotel.check_out_time
                    ?? '12:00',
                ).slice(0, 5),
        });

    const subscription =
        useForm({
            plan_id:
                hotel
                    .active_subscription
                    ?.hotel_plan_id
                ?? plans[0]?.id
                ?? '',
        });

    return (
        <SuperAdminLayout title="Hotel Hotspot · Manage Hotel">
            <Head title={`Manage ${hotel.name}`} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black">
                        {hotel.name}
                    </h1>

                    <p className="text-slate-500">
                        {hotel.code}
                    </p>
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();

                        form.put(
                            route(
                                'superadmin.hotel.hotels.update',
                                hotel.id,
                            ),
                            {
                                preserveScroll:
                                    true,
                            },
                        );
                    }}
                    className="rounded-2xl border bg-white p-6 shadow-sm"
                >
                    <div className="grid gap-5 md:grid-cols-2">
                        <Input
                            label="Hotel Name"
                            value={
                                form.data.name
                            }
                            onChange={(v) =>
                                form.setData(
                                    'name',
                                    v,
                                )
                            }
                        />

                        <label>
                            <div className="mb-1 text-sm font-bold">
                                Status
                            </div>

                            <select
                                value={
                                    form.data
                                        .status
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'status',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            >
                                <option value="active">
                                    Active
                                </option>

                                <option value="suspended">
                                    Suspended
                                </option>
                            </select>
                        </label>

                        <Input
                            label="Owner"
                            value={
                                form.data
                                    .owner_name
                            }
                            onChange={(v) =>
                                form.setData(
                                    'owner_name',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Email"
                            value={
                                form.data.email
                            }
                            onChange={(v) =>
                                form.setData(
                                    'email',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Phone"
                            value={
                                form.data.phone
                            }
                            onChange={(v) =>
                                form.setData(
                                    'phone',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="WhatsApp"
                            value={
                                form.data
                                    .whatsapp
                            }
                            onChange={(v) =>
                                form.setData(
                                    'whatsapp',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Timezone"
                            value={
                                form.data
                                    .timezone
                            }
                            onChange={(v) =>
                                form.setData(
                                    'timezone',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Currency"
                            value={
                                form.data
                                    .currency
                            }
                            onChange={(v) =>
                                form.setData(
                                    'currency',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Checkout Time"
                            type="time"
                            value={
                                form.data
                                    .check_out_time
                            }
                            onChange={(v) =>
                                form.setData(
                                    'check_out_time',
                                    v,
                                )
                            }
                        />
                    </div>

                    <textarea
                        rows="3"
                        value={
                            form.data.address
                        }
                        onChange={(e) =>
                            form.setData(
                                'address',
                                e.target.value,
                            )
                        }
                        className={`${inputClass} mt-5`}
                    />

                    <button
                        type="submit"
                        className="mt-5 rounded-xl bg-indigo-600 px-5 py-2.5 font-black text-white"
                    >
                        Save Hotel
                    </button>
                </form>

                <div className="grid gap-6 lg:grid-cols-2">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();

                            if (
                                !confirm(
                                    'Start this new Hotel subscription now?',
                                )
                            ) {
                                return;
                            }

                            subscription.post(
                                route(
                                    'superadmin.hotel.hotels.subscription',
                                    hotel.id,
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
                            Subscription
                        </h2>

                        <div className="mt-4 rounded-xl bg-slate-50 p-4">
                            <strong>
                                {hotel
                                    .active_subscription
                                    ?.plan
                                    ?.name
                                    ?? 'No active plan'}
                            </strong>
                        </div>

                        <select
                            value={
                                subscription
                                    .data.plan_id
                            }
                            onChange={(e) =>
                                subscription.setData(
                                    'plan_id',
                                    e.target.value,
                                )
                            }
                            className={`${inputClass} mt-4`}
                        >
                            {plans.map(
                                (plan) => (
                                    <option
                                        key={
                                            plan.id
                                        }
                                        value={
                                            plan.id
                                        }
                                    >
                                        {
                                            plan.name
                                        }
                                    </option>
                                ),
                            )}
                        </select>

                        <button
                            type="submit"
                            className="mt-4 rounded-xl bg-emerald-600 px-5 py-2.5 font-black text-white"
                        >
                            Start New Subscription
                        </button>
                    </form>

                    <div className="rounded-2xl border bg-white p-6 shadow-sm">
                        <h2 className="text-xl font-black">
                            Hotel Admin
                        </h2>

                        <div className="mt-4">
                            <div>
                                {admin?.name
                                    ?? '-'}
                            </div>

                            <div className="text-slate-500">
                                {admin?.email
                                    ?? '-'}
                            </div>

                            <div className="mt-4 text-sm">
                                Login URL:{' '}
                                <strong>
                                    /hotel/login
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-2xl border bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                <Th>Plan</Th>
                                <Th>Status</Th>
                                <Th>Start</Th>
                                <Th>Expiry</Th>
                                <Th>Price</Th>
                            </tr>
                        </thead>

                        <tbody className="divide-y">
                            {subscriptions.map(
                                (item) => (
                                    <tr
                                        key={
                                            item.id
                                        }
                                    >
                                        <Td>
                                            {
                                                item.plan
                                                    ?.name
                                                ?? '-'
                                            }
                                        </Td>

                                        <Td>
                                            {
                                                item.status
                                            }
                                        </Td>

                                        <Td>
                                            {new Date(
                                                item.starts_at,
                                            ).toLocaleString()}
                                        </Td>

                                        <Td>
                                            {new Date(
                                                item.expires_at,
                                            ).toLocaleString()}
                                        </Td>

                                        <Td>
                                            QAR{' '}
                                            {
                                                item.price
                                            }
                                        </Td>
                                    </tr>
                                ),
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </SuperAdminLayout>
    );
}

function Input({
    label,
    value,
    onChange,
    type = 'text',
}) {
    return (
        <label>
            <div className="mb-1 text-sm font-bold">
                {label}
            </div>

            <input
                type={type}
                value={value}
                onChange={(e) =>
                    onChange(
                        e.target.value,
                    )
                }
                className={
                    inputClass
                }
            />
        </label>
    );
}

function Th({ children }) {
    return (
        <th className="px-4 py-3 text-left text-xs font-black uppercase text-slate-500">
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
