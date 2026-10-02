import {
    Head,
    Link,
} from '@inertiajs/react';

export default function Legal({
    brand = 'MikroPanel',
    site = {},
    type,
    content = '',
}) {
    const privacy =
        type === 'privacy';

    return (
        <div className="min-h-screen bg-slate-950 px-5 py-10 text-white">
            <Head
                title={
                    privacy
                        ? 'Privacy Policy'
                        : 'Terms & Conditions'
                }
            />

            <div className="mx-auto max-w-4xl">
                <Link
                    href={route('website.home')}
                    className="flex items-center gap-3 text-2xl font-black"
                >
                    {site.logo_url && (
                        <img
                            src={site.logo_url}
                            alt={
                                site.website_name
                                || brand
                            }
                            className="h-11 max-w-48 object-contain"
                        />
                    )}

                    <span>
                        {site.website_name
                            || brand}
                    </span>
                </Link>

                <article className="mt-10 rounded-[2rem] bg-white p-8 text-slate-800 md:p-12">
                    <h1 className="text-4xl font-black">
                        {privacy
                            ? 'Privacy Policy'
                            : 'Terms & Conditions'}
                    </h1>

                    <p className="mt-3 text-slate-500">
                        {site.website_name
                            || brand}
                    </p>

                    <div className="mt-8 whitespace-pre-line leading-8 text-slate-700">
                        {content ||
                            (
                                privacy
                                    ? 'Privacy policy has not been configured yet.'
                                    : 'Terms and conditions have not been configured yet.'
                            )}
                    </div>

                    {(site.company_email
                        || site.company_phone
                        || site.company_address) && (
                        <div className="mt-10 rounded-2xl bg-slate-50 p-6">
                            <h2 className="font-black">
                                Contact
                            </h2>

                            {site.company_phone && (
                                <div className="mt-2">
                                    Phone:{' '}
                                    {
                                        site.company_phone
                                    }
                                </div>
                            )}

                            {site.company_email && (
                                <div>
                                    Email:{' '}
                                    {
                                        site.company_email
                                    }
                                </div>
                            )}

                            {site.company_address && (
                                <div>
                                    Address:{' '}
                                    {
                                        site.company_address
                                    }
                                </div>
                            )}
                        </div>
                    )}
                </article>
            </div>
        </div>
    );
}
