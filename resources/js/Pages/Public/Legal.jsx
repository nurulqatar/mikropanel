import {
    Head,
    Link,
} from '@inertiajs/react';

export default function Legal({
    brand = 'MikroPanel',
    type,
}) {
    const privacy =
        type === 'privacy';

    return (
        <div className="min-h-screen bg-slate-950 px-5 py-10 text-white">
            <Head
                title={
                    privacy
                        ? 'Privacy Policy'
                        : 'Terms of Service'
                }
            />

            <div className="mx-auto max-w-4xl">
                <Link
                    href={route('website.home')}
                    className="text-2xl font-black"
                >
                    <span className="text-cyan-400">
                        Mikro
                    </span>
                    Panel
                </Link>

                <article className="mt-10 rounded-[2rem] bg-white p-8 text-slate-800 md:p-12">
                    <h1 className="text-4xl font-black">
                        {privacy
                            ? 'Privacy Policy'
                            : 'Terms of Service'}
                    </h1>

                    <p className="mt-3 text-slate-500">
                        {brand} reseller platform
                    </p>

                    {privacy ? (
                        <div className="mt-8 space-y-7 leading-7">
                            <Section
                                title="Information We Collect"
                                text="Registration information may include company name, owner name, email, phone, service address, selected package, IP address and basic browser information."
                            />
                            <Section
                                title="Why We Use It"
                                text="Information is used to create and secure reseller accounts, review paid package applications, operate subscriptions, support customers and maintain platform audit history."
                            />
                            <Section
                                title="Account & Network Data"
                                text="Operational data entered into the reseller panel is used to provide the services and access controls available within the platform."
                            />
                            <Section
                                title="Security"
                                text="Passwords are handled through the application's secure password hashing system and are not stored as readable registration-request text."
                            />
                            <Section
                                title="Retention"
                                text="Registration and account history may be retained for operational, accounting, security and audit purposes."
                            />
                        </div>
                    ) : (
                        <div className="mt-8 space-y-7 leading-7">
                            <Section
                                title="Account Use"
                                text="Reseller accounts must be used for lawful network and customer-management operations. Account owners are responsible for staff access and credentials."
                            />
                            <Section
                                title="Free Trial"
                                text="The automatic free trial applies only to an active seven-day package configured at zero price. Abuse, duplicate registrations or misuse may result in suspension."
                            />
                            <Section
                                title="Paid Packages"
                                text="Non-free packages require Super Admin approval before reseller access becomes active. Package limits, validity and price follow the selected plan."
                            />
                            <Section
                                title="Subscriptions"
                                text="Access may become restricted when a subscription expires, is suspended or is otherwise no longer usable under platform rules."
                            />
                            <Section
                                title="Operational Responsibility"
                                text="Resellers remain responsible for their own customer relationships, local network deployment, billing practices and lawful use of connected MikroTik infrastructure."
                            />
                        </div>
                    )}
                </article>
            </div>
        </div>
    );
}

function Section({
    title,
    text,
}) {
    return (
        <section>
            <h2 className="text-xl font-black">
                {title}
            </h2>

            <p className="mt-2 text-slate-600">
                {text}
            </p>
        </section>
    );
}
