import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import {
    Head,
    useForm,
} from '@inertiajs/react';

const inputClass =
    'w-full rounded-xl border-slate-300';

export default function Create({
    plans = [],
}) {
    const form =
        useForm({
            name: '',
            owner_name: '',
            email: '',
            phone: '',
            whatsapp: '',
            address: '',
            timezone: 'Asia/Qatar',
            currency: 'QAR',
            check_out_time: '12:00',

            plan_id:
                plans[0]?.id
                ?? '',

            admin_name: '',
            admin_email: '',
            admin_password: '',
            admin_password_confirmation:
                '',
        });

    const submit = (event) => {
        event.preventDefault();

        form.post(
            route(
                'superadmin.hotel.hotels.store',
            ),
        );
    };

    return (
        <SuperAdminLayout title="Hotel Hotspot · Add Hotel">
            <Head title="Add Hotel" />

            <form
                onSubmit={submit}
                className="mx-auto max-w-5xl space-y-6"
            >
                <Section title="Hotel">
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

                        <Input
                            label="Owner / Contact"
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
                            type="email"
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

                        <textarea
                            rows="3"
                            placeholder="Address"
                            value={
                                form.data
                                    .address
                            }
                            onChange={(e) =>
                                form.setData(
                                    'address',
                                    e.target.value,
                                )
                            }
                            className={`${inputClass} md:col-span-2`}
                        />
                    </div>
                </Section>

                <Section title="Subscription">
                    <select
                        value={
                            form.data.plan_id
                        }
                        onChange={(e) =>
                            form.setData(
                                'plan_id',
                                e.target.value,
                            )
                        }
                        className={
                            inputClass
                        }
                    >
                        <option value="">
                            Select Hotel Plan
                        </option>

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
                                    {plan.name}
                                    {' — '}
                                    QAR{' '}
                                    {plan.price}
                                </option>
                            ),
                        )}
                    </select>
                </Section>

                <Section title="Hotel Administrator">
                    <div className="grid gap-5 md:grid-cols-2">
                        <Input
                            label="Admin Name"
                            value={
                                form.data
                                    .admin_name
                            }
                            onChange={(v) =>
                                form.setData(
                                    'admin_name',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Admin Email"
                            type="email"
                            value={
                                form.data
                                    .admin_email
                            }
                            onChange={(v) =>
                                form.setData(
                                    'admin_email',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Password"
                            type="password"
                            value={
                                form.data
                                    .admin_password
                            }
                            onChange={(v) =>
                                form.setData(
                                    'admin_password',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Confirm Password"
                            type="password"
                            value={
                                form.data
                                    .admin_password_confirmation
                            }
                            onChange={(v) =>
                                form.setData(
                                    'admin_password_confirmation',
                                    v,
                                )
                            }
                        />
                    </div>
                </Section>

                {Object.keys(
                    form.errors,
                ).length > 0 && (
                    <div className="rounded-xl bg-red-50 p-4 font-bold text-red-700">
                        {Object.values(
                            form.errors,
                        ).map(
                            (
                                error,
                                index,
                            ) => (
                                <div
                                    key={
                                        index
                                    }
                                >
                                    {error}
                                </div>
                            ),
                        )}
                    </div>
                )}

                <button
                    type="submit"
                    disabled={
                        form.processing
                        || plans.length
                            === 0
                    }
                    className="rounded-xl bg-emerald-600 px-7 py-3 font-black text-white disabled:opacity-50"
                >
                    Create Hotel
                </button>
            </form>
        </SuperAdminLayout>
    );
}

function Section({
    title,
    children,
}) {
    return (
        <section className="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 className="mb-5 text-xl font-black">
                {title}
            </h2>

            {children}
        </section>
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
