import {
    BrandHeader,
    CountryPicker,
    PortalFrame,
    TextField,
    termsText,
    tx,
} from '@/Hotel/PortalV15Kit';
import {
    Head,
    Link,
    useForm,
} from '@inertiajs/react';
import {
    useState,
} from 'react';

export default function Register({
    hotel,
    locale,
}) {
    const [
        firstName,
        setFirstName,
    ] = useState('');

    const [
        lastName,
        setLastName,
    ] = useState('');

    const [
        country,
        setCountry,
    ] = useState(
        null,
    );

    const [
        mobileCountry,
        setMobileCountry,
    ] = useState(
        null,
    );

    const [
        mobile,
        setMobile,
    ] = useState('');

    const form =
        useForm({
            room_number: '',
            check_in_date: '',
            check_out_date: '',
            phone_country: '',
            phone: '',
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

    const today =
        new Date()
            .toISOString()
            .slice(
                0,
                10,
            );

    const submit = (
        event,
    ) => {
        event.preventDefault();

        const name =
            `${firstName.trim()} ${lastName.trim()}`
                .trim();

        const digits =
            mobile.replace(
                /\D/g,
                '',
            );

        const phone =
            mobileCountry?.dial
                ? `${mobileCountry.dial}${digits}`
                : digits;

        form.transform(
            (
                data,
            ) => ({
                ...data,

                name,

                nationality:
                    country?.code
                    || '',

                phone_country:
                    mobileCountry?.code
                    || '',

                phone,

                identity_type:
                    'passport',

                preferred_locale:
                    locale,
            }),
        );

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
    };

    return (
        <PortalFrame
            hotel={
                hotel
            }
            locale={
                locale
            }
        >
            <Head
                title={tx(
                    locale,
                    'Guest Registration',
                )}
            />

            <div className="overflow-hidden rounded-[2rem] bg-white shadow-2xl">
                <BrandHeader
                    hotel={
                        hotel
                    }
                    title={tx(
                        locale,
                        'Guest Registration',
                    )}
                    subtitle={tx(
                        locale,
                        'Please enter details exactly as shown on Passport / QID.',
                    )}
                />

                <form
                    onSubmit={
                        submit
                    }
                    className="space-y-5 p-6"
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            label={tx(
                                locale,
                                'First Name',
                            )}
                            value={
                                firstName
                            }
                            onChange={
                                setFirstName
                            }
                            autoComplete="given-name"
                            placeholder={tx(
                                locale,
                                'As Passport / QID',
                            )}
                        />

                        <TextField
                            label={tx(
                                locale,
                                'Last Name',
                            )}
                            value={
                                lastName
                            }
                            onChange={
                                setLastName
                            }
                            autoComplete="family-name"
                            placeholder={tx(
                                locale,
                                'As Passport / QID',
                            )}
                        />
                    </div>

                    <div>
                        <div className="mb-2 text-sm font-black text-slate-700">
                            {tx(
                                locale,
                                'Country',
                            )}
                        </div>

                        <CountryPicker
                            locale={
                                locale
                            }
                            value={
                                country?.code
                            }
                            onSelect={
                                setCountry
                            }
                        />
                    </div>

                    <TextField
                        label={tx(
                            locale,
                            'Passport Number',
                        )}
                        value={
                            form.data
                                .identity_number
                        }
                        onChange={(value) =>
                            form.setData(
                                'identity_number',
                                value
                                    .toUpperCase(),
                            )
                        }
                        placeholder={tx(
                            locale,
                            'Passport number',
                        )}
                    />

                    <TextField
                        label={tx(
                            locale,
                            'Room Number',
                        )}
                        value={
                            form.data
                                .room_number
                        }
                        onChange={(value) =>
                            form.setData(
                                'room_number',
                                value,
                            )
                        }
                        placeholder={tx(
                            locale,
                            'Example: 504',
                        )}
                    />

                    <div className="grid gap-4 sm:grid-cols-2">
                        <TextField
                            label={tx(
                                locale,
                                'Check-in Date',
                            )}
                            type="date"
                            min={
                                today
                            }
                            value={
                                form.data
                                    .check_in_date
                            }
                            onChange={(value) => {
                                form.setData(
                                    'check_in_date',
                                    value,
                                );

                                if (
                                    form.data
                                        .check_out_date
                                    && form.data
                                        .check_out_date
                                        < value
                                ) {
                                    form.setData(
                                        'check_out_date',
                                        '',
                                    );
                                }
                            }}
                        />

                        <TextField
                            label={tx(
                                locale,
                                'Checkout Date',
                            )}
                            type="date"
                            min={
                                form.data
                                    .check_in_date
                                || today
                            }
                            value={
                                form.data
                                    .check_out_date
                            }
                            onChange={(value) =>
                                form.setData(
                                    'check_out_date',
                                    value,
                                )
                            }
                        />
                    </div>

                    <div>
                        <div className="mb-2 text-sm font-black text-slate-700">
                            {tx(
                                locale,
                                'Mobile Country Prefix',
                            )}
                        </div>

                        <CountryPicker
                            locale={
                                locale
                            }
                            value={
                                mobileCountry?.code
                            }
                            onSelect={
                                setMobileCountry
                            }
                            dialMode
                        />
                    </div>

                    <label className="block">
                        <span className="mb-2 block text-sm font-black text-slate-700">
                            {tx(
                                locale,
                                'Mobile Number',
                            )}
                        </span>

                        <div className="flex overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm focus-within:border-blue-500">
                            <div
                                dir="ltr"
                                className="flex min-w-20 items-center justify-center border-e border-slate-200 bg-slate-50 px-3 font-black text-blue-700"
                            >
                                {
                                    mobileCountry?.dial
                                    || '+'
                                }
                            </div>

                            <input
                                dir="ltr"
                                inputMode="tel"
                                value={
                                    mobile
                                }
                                onChange={(e) =>
                                    setMobile(
                                        e.target.value
                                            .replace(
                                                /[^0-9\s()-]/g,
                                                '',
                                            ),
                                    )
                                }
                                placeholder={tx(
                                    locale,
                                    'Mobile number',
                                )}
                                className="min-w-0 flex-1 border-0 px-4 py-3.5 focus:ring-0"
                            />
                        </div>
                    </label>

                    {(hotel.terms_text
                        || hotel.privacy_text) && (
                        <div className="max-h-40 overflow-y-auto rounded-2xl bg-slate-50 p-4 text-xs leading-5 text-slate-500">
                            {hotel.terms_text && (
                                <p className="whitespace-pre-line">
                                    {
                                        hotel.terms_text
                                    }
                                </p>
                            )}

                            {hotel.privacy_text && (
                                <p className="mt-3 whitespace-pre-line">
                                    {
                                        hotel.privacy_text
                                    }
                                </p>
                            )}
                        </div>
                    )}

                    <label className="flex items-start gap-3 rounded-2xl border border-slate-100 bg-slate-50 p-4">
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
                            className="mt-1 rounded border-slate-300"
                        />

                        <span className="text-sm font-semibold text-slate-600">
                            {termsText(
                                locale,
                            )}
                        </span>
                    </label>

                    {Object.keys(
                        form.errors,
                    ).length > 0 && (
                        <div className="rounded-2xl bg-red-50 p-4 text-sm font-bold text-red-700">
                            {Object.values(
                                form.errors,
                            ).map(
                                (
                                    message,
                                    index,
                                ) => (
                                    <div
                                        key={
                                            index
                                        }
                                    >
                                        {
                                            message
                                        }
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
                        className="w-full rounded-2xl bg-gradient-to-r from-blue-600 to-cyan-500 px-6 py-4 text-lg font-black text-white shadow-lg disabled:opacity-50"
                    >
                        {tx(
                            locale,
                            'Create WiFi Access',
                        )}
                    </button>

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
                        className="block text-center text-sm font-black text-slate-400"
                    >
                        ←{' '}
                        {tx(
                            locale,
                            'Back',
                        )}
                    </Link>
                </form>
            </div>
        </PortalFrame>
    );
}
