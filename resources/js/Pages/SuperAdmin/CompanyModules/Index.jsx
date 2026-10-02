import SuperAdminLayout from '@/Layouts/SuperAdminLayout';
import {
    Head,
    useForm,
} from '@inertiajs/react';

export default function Index({
    resellers = [],
    moduleDefinitions = [],
}) {
    return (
        <SuperAdminLayout>
            <Head title="Company Modules" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-black text-slate-900">
                        Company Modules
                    </h1>

                    <p className="mt-2 max-w-3xl text-sm text-slate-500">
                        Enable only the service modules purchased by each company.
                        Disabled module data is preserved but hidden from the company.
                    </p>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Th>Company</Th>

                                    {moduleDefinitions.map(
                                        (module) => (
                                            <Th
                                                key={
                                                    module.key
                                                }
                                            >
                                                {
                                                    module.name
                                                }
                                            </Th>
                                        ),
                                    )}

                                    <Th>Action</Th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {resellers.map(
                                    (reseller) => (
                                        <CompanyRow
                                            key={
                                                reseller.id
                                            }
                                            reseller={
                                                reseller
                                            }
                                            modules={
                                                moduleDefinitions
                                            }
                                        />
                                    ),
                                )}

                                {resellers.length ===
                                    0 && (
                                    <tr>
                                        <td
                                            colSpan={
                                                moduleDefinitions.length
                                                + 2
                                            }
                                            className="p-10 text-center text-slate-500"
                                        >
                                            No companies found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    {moduleDefinitions.map(
                        (module) => (
                            <div
                                key={
                                    module.key
                                }
                                className="rounded-2xl border border-slate-200 bg-white p-5"
                            >
                                <div className="font-black text-slate-900">
                                    {module.name}
                                </div>

                                <div className="mt-1 text-sm leading-6 text-slate-500">
                                    {
                                        module.description
                                    }
                                </div>
                            </div>
                        ),
                    )}
                </div>
            </div>
        </SuperAdminLayout>
    );
}

function CompanyRow({
    reseller,
    modules,
}) {
    const form = useForm({
        modules:
            reseller.modules ?? [],
    });

    const toggle = (
        key,
        checked,
    ) => {
        const current =
            form.data.modules ?? [];

        form.setData(
            'modules',
            checked
                ? Array.from(
                    new Set([
                        ...current,
                        key,
                    ]),
                )
                : current.filter(
                    (item) =>
                        item !== key,
                ),
        );
    };

    const submit = () => {
        form.put(
            route(
                'superadmin.company-modules.update',
                reseller.id,
            ),
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <tr>
            <Td>
                <div className="font-bold text-slate-900">
                    {reseller.company_name}
                </div>

                <div className="mt-1 text-xs text-slate-500">
                    {reseller.code}
                    {' · '}
                    {reseller.status}
                </div>
            </Td>

            {modules.map(
                (module) => {
                    const checked =
                        form.data.modules.includes(
                            module.key,
                        );

                    return (
                        <Td
                            key={
                                module.key
                            }
                        >
                            <label className="inline-flex cursor-pointer items-center gap-3">
                                <input
                                    type="checkbox"
                                    checked={
                                        checked
                                    }
                                    onChange={(
                                        event,
                                    ) =>
                                        toggle(
                                            module.key,
                                            event
                                                .target
                                                .checked,
                                        )
                                    }
                                    className="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />

                                <span
                                    className={
                                        checked
                                            ? 'font-bold text-emerald-700'
                                            : 'font-semibold text-slate-400'
                                    }
                                >
                                    {checked
                                        ? 'Enabled'
                                        : 'Disabled'}
                                </span>
                            </label>
                        </Td>
                    );
                },
            )}

            <Td>
                <button
                    type="button"
                    onClick={submit}
                    disabled={
                        form.processing
                        || form.data.modules
                            .length === 0
                    }
                    className="rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Save Modules
                </button>

                {form.data.modules
                    .length === 0 && (
                    <div className="mt-1 text-xs font-semibold text-red-600">
                        Select at least one module.
                    </div>
                )}
            </Td>
        </tr>
    );
}

function Th({
    children,
}) {
    return (
        <th className="whitespace-nowrap px-5 py-3 text-left text-xs font-black uppercase tracking-wide text-slate-500">
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
