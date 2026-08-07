import { Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Edit({ range, routers }) {

    const { data, setData, put, processing, errors } = useForm({

        router_id: range.router_id ?? '',
        name: range.name ?? '',
        interface: range.interface ?? '',
        network: range.network ?? '',
        gateway: range.gateway ?? '',
        dns_server: range.dns_server ?? '',
        start_ip: range.start_ip ?? '',
        end_ip: range.end_ip ?? '',
        enabled: range.enabled ?? true,

    });


    const submit = (e) => {

        e.preventDefault();

        put(
            route(
                'ip-ranges.update',
                range.id
            )
        );

    };


    return (

        <AuthenticatedLayout>

            <div className="p-6">


                <h1 className="text-2xl font-bold mb-6">
                    Edit IP Range
                </h1>


                <form
                    onSubmit={submit}
                    className="bg-white shadow rounded p-6 space-y-4"
                >


                    <div>

                        <label>
                            Router
                        </label>


                        <select

                            className="w-full border rounded p-2"

                            value={data.router_id}

                            onChange={
                                e =>
                                    setData(
                                        'router_id',
                                        e.target.value
                                    )
                            }

                        >

                            <option value="">
                                Select Router
                            </option>


                            {routers.map(router => (

                                <option
                                    key={router.id}
                                    value={router.id}
                                >
                                    {router.name}
                                </option>

                            ))}


                        </select>

                    </div>



                    <div>

                        <label>
                            Range Name
                        </label>


                        <input

                            className="w-full border rounded p-2"

                            value={data.name}

                            onChange={
                                e =>
                                    setData(
                                        'name',
                                        e.target.value
                                    )
                            }

                        />

                    </div>



                    <div>

                        <label>
                            Interface
                        </label>


                        <input

                            className="w-full border rounded p-2"

                            value={data.interface}

                            onChange={
                                e =>
                                    setData(
                                        'interface',
                                        e.target.value
                                    )
                            }

                        />

                    </div>



                    <div>

                        <label>
                            Network
                        </label>


                        <input

                            className="w-full border rounded p-2"

                            value={data.network}

                            onChange={
                                e =>
                                    setData(
                                        'network',
                                        e.target.value
                                    )
                            }

                        />

                    </div>



                    <div>

                        <label>
                            Gateway
                        </label>


                        <input

                            className="w-full border rounded p-2"

                            value={data.gateway}

                            onChange={
                                e =>
                                    setData(
                                        'gateway',
                                        e.target.value
                                    )
                            }

                        />

                    </div>



                    <div>

                        <label>
                            DNS Server
                        </label>


                        <input

                            className="w-full border rounded p-2"

                            value={data.dns_server}

                            onChange={
                                e =>
                                    setData(
                                        'dns_server',
                                        e.target.value
                                    )
                            }

                        />

                    </div>



                    <div className="grid grid-cols-2 gap-4">


                        <div>

                            <label>
                                Start IP
                            </label>


                            <input

                                className="w-full border rounded p-2"

                                value={data.start_ip}

                                onChange={
                                    e =>
                                        setData(
                                            'start_ip',
                                            e.target.value
                                        )
                                }

                            />

                        </div>


                        <div>

                            <label>
                                End IP
                            </label>


                            <input

                                className="w-full border rounded p-2"

                                value={data.end_ip}

                                onChange={
                                    e =>
                                        setData(
                                            'end_ip',
                                            e.target.value
                                        )
                                }

                            />

                        </div>


                    </div>



                    <label className="flex gap-2">

                        <input

                            type="checkbox"

                            checked={data.enabled}

                            onChange={
                                e =>
                                    setData(
                                        'enabled',
                                        e.target.checked
                                    )
                            }

                        />

                        Enabled

                    </label>



                    <div className="flex gap-3">


                        <button

                            disabled={processing}

                            className="bg-blue-600 text-white px-5 py-2 rounded"

                        >

                            Update

                        </button>



                        <Link

                            href={route('ip-ranges.index')}

                            className="border px-5 py-2 rounded"

                        >

                            Cancel

                        </Link>


                    </div>


                </form>


            </div>


        </AuthenticatedLayout>

    );
}
