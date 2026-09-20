import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';

const inputClass =
    'w-full rounded-xl border-slate-300';

export default function Register({
    hotel,
    locale,
}) {
    const form =
        useForm({
            room_number: '',
            check_in_date: '',
            check_out_date: '',
            phone: '',
            phone_country: '',
            name: '',
            identity_type:
                'passport',
            identity_number: '',
            nationality: '',
            preferred_locale:
                locale,
            terms_accepted:
                false,
        });

    return (
        <div className="min-h-screen bg-slate-100 p-4 md:p-8">
            <Head title="Guest WiFi Registration" />

            <div className="mx-auto max-w-3xl rounded-[2rem] bg-white p-6 shadow-xl md:p-8">
                <div className="text-center">
                    {hotel.logo_url && (
                        <img
                            src={
                                hotel.logo_url
                            }
                            alt={
                                hotel.name
                            }
                            className="mx-auto h-20 max-w-52 object-contain"
                        />
                    )}

                    <h1 className="mt-4 text-2xl font-black">
                        Guest WiFi Registration
                    </h1>

                    <p className="mt-1 text-slate-500">
                        {hotel.name}
                    </p>
                </div>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();

                        form.post(
                            route(
                                'hotel.portal.register.store',
                                {
                                    hotel:
                                        hotel.slug,

                                    lang:
                                        locale,
                                },
                            ),
                        );
                    }}
                    className="mt-8"
                >
                    <div className="grid gap-5 md:grid-cols-2">
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
                            label="Hotel Check-in Date"
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
                            label="Hotel Check-out Date"
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
                            label="Mobile Number"
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
                            <div className="mb-1 text-sm font-black">
                                Identification Type
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
                            label="Identification Number"
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

                    {(hotel.terms_text
                        || hotel.privacy_text) && (
                        <div className="mt-6 max-h-44 overflow-y-auto rounded-xl bg-slate-50 p-4 text-sm text-slate-600">
                            {hotel.terms_text && (
                                <>
                                    <strong>
                                        Terms & Conditions
                                    </strong>

                                    <p className="mt-2 whitespace-pre-line">
                                        {
                                            hotel.terms_text
                                        }
                                    </p>
                                </>
                            )}

                            {hotel.privacy_text && (
                                <>
                                    <strong className="mt-4 block">
                                        Privacy Notice
                                    </strong>

                                    <p className="mt-2 whitespace-pre-line">
                                        {
                                            hotel.privacy_text
                                        }
                                    </p>
                                </>
                            )}
                        </div>
                    )}

                    <label className="mt-5 flex items-start gap-3">
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

                        <span className="text-sm font-semibold">
                            I accept the Hotel WiFi
                            Terms & Privacy Notice.
                        </span>
                    </label>

                    {Object.keys(
                        form.errors,
                    ).length > 0 && (
                        <div className="mt-5 rounded-xl bg-red-50 p-4 text-sm font-bold text-red-700">
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
                        style={{
                            backgroundColor:
                                hotel.primary_color,
                        }}
                        className="mt-6 w-full rounded-xl px-6 py-3 font-black text-white disabled:opacity-50"
                    >
                        Register & Get Voucher
                    </button>
                </form>

                <Link
                    href={route(
                        'hotel.portal.access',
                        {
                            hotel:
                                hotel.slug,
                            lang:
                                locale,
                        },
                    )}
                    className="mt-5 block text-center font-bold text-slate-500"
                >
                    Back
                </Link>
            </div>
        </div>
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
            <div className="mb-1 text-sm font-black">
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
