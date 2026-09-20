import HotelLayout from '@/Layouts/HotelLayout';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

const today = () =>
    new Date()
        .toISOString()
        .slice(0, 10);

const tomorrow = () => {
    const date =
        new Date();

    date.setDate(
        date.getDate() + 1,
    );

    return date
        .toISOString()
        .slice(0, 10);
};

const inputClass =
    'w-full rounded-xl border-slate-300';

export default function Create({
    hotel = {},
}) {
    const form =
        useForm({
            room_number: '',
            check_in_date:
                today(),
            check_out_date:
                tomorrow(),
            phone: '',
            phone_country: '',
            name: '',
            identity_type:
                'passport',
            identity_number: '',
            nationality: '',
            preferred_locale:
                'en',
            terms_accepted:
                false,
        });

    return (
        <HotelLayout title="Create Voucher">
            <Head title="Create Guest Voucher" />

            <div className="mx-auto max-w-4xl">
                <Link
                    href={route(
                        'hotel.vouchers.index',
                    )}
                    className="font-black text-indigo-600"
                >
                    ← Vouchers
                </Link>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();

                        form.post(
                            route(
                                'hotel.vouchers.store',
                            ),
                        );
                    }}
                    className="mt-5 rounded-2xl border bg-white p-6 shadow-sm"
                >
                    <h1 className="text-2xl font-black">
                        Create Guest Voucher
                    </h1>

                    <p className="mt-1 text-sm text-slate-500">
                        Checkout time:{' '}
                        {
                            hotel.check_out_time
                        }
                    </p>

                    <div className="mt-6 grid gap-5 md:grid-cols-2">
                        <Input
                            label="Room Number"
                            value={
                                form.data
                                    .room_number
                            }
                            onChange={(v) =>
                                form.setData(
                                    'room_number',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Guest Name"
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
                            label="Check-in Date"
                            type="date"
                            value={
                                form.data
                                    .check_in_date
                            }
                            onChange={(v) =>
                                form.setData(
                                    'check_in_date',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="Check-out Date"
                            type="date"
                            value={
                                form.data
                                    .check_out_date
                            }
                            onChange={(v) =>
                                form.setData(
                                    'check_out_date',
                                    v,
                                )
                            }
                        />

                        <Input
                            label="International Phone"
                            placeholder="+974..."
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
                            label="Nationality"
                            value={
                                form.data
                                    .nationality
                            }
                            onChange={(v) =>
                                form.setData(
                                    'nationality',
                                    v,
                                )
                            }
                        />

                        <label>
                            <div className="mb-1 text-sm font-bold">
                                Identification
                            </div>

                            <select
                                value={
                                    form.data
                                        .identity_type
                                }
                                onChange={(e) =>
                                    form.setData(
                                        'identity_type',
                                        e.target.value,
                                    )
                                }
                                className={
                                    inputClass
                                }
                            >
                                <option value="passport">
                                    Passport
                                </option>

                                <option value="qid">
                                    Qatar ID
                                </option>
                            </select>
                        </label>

                        <Input
                            label="ID Number"
                            value={
                                form.data
                                    .identity_number
                            }
                            onChange={(v) =>
                                form.setData(
                                    'identity_number',
                                    v,
                                )
                            }
                        />
                    </div>

                    <label className="mt-5 flex items-start gap-3 rounded-xl bg-slate-50 p-4">
                        <input
                            type="checkbox"
                            checked={
                                form.data
                                    .terms_accepted
                            }
                            onChange={(e) =>
                                form.setData(
                                    'terms_accepted',
                                    e.target.checked,
                                )
                            }
                            className="mt-1 rounded"
                        />

                        <span className="text-sm font-semibold text-slate-600">
                            Guest has accepted the Hotel
                            WiFi Terms & Privacy Notice.
                        </span>
                    </label>

                    {Object.keys(
                        form.errors,
                    ).length > 0 && (
                        <div className="mt-4 rounded-xl bg-red-50 p-4 text-sm font-bold text-red-700">
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
                        }
                        className="mt-5 rounded-xl bg-emerald-600 px-6 py-3 font-black text-white disabled:opacity-50"
                    >
                        Generate Voucher
                    </button>
                </form>
            </div>
        </HotelLayout>
    );
}

function Input({
    label,
    value,
    onChange,
    type = 'text',
    placeholder = '',
}) {
    return (
        <label>
            <div className="mb-1 text-sm font-bold">
                {label}
            </div>

            <input
                type={type}
                value={value}
                placeholder={
                    placeholder
                }
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
