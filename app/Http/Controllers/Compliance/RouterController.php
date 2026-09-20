<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\Network;
use App\Models\Compliance\Router;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RouterController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $organization =
            $request->attributes->get(
                'complianceOrganization'
            );

        return Inertia::render(
            'Compliance/Routers/Index',
            [
                'routers' =>
                    Router::query()
                        ->where(
                            'organization_id',
                            $organization->id
                        )
                        ->with('network')
                        ->latest('id')
                        ->get(),

                'networks' =>
                    Network::query()
                        ->where(
                            'organization_id',
                            $organization->id
                        )
                        ->where(
                            'enabled',
                            true
                        )
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                        ]),

                'supportedVendors' => [
                    [
                        'value' =>
                            'mikrotik',

                        'label' =>
                            'MikroTik',

                        'mode' =>
                            'Full automation target',
                    ],
                    [
                        'value' =>
                            'openwrt',

                        'label' =>
                            'OpenWrt',

                        'mode' =>
                            'Agent/API',
                    ],
                    [
                        'value' =>
                            'pfsense',

                        'label' =>
                            'pfSense',

                        'mode' =>
                            'Agent/API',
                    ],
                    [
                        'value' =>
                            'opnsense',

                        'label' =>
                            'OPNsense',

                        'mode' =>
                            'Agent/API',
                    ],
                    [
                        'value' =>
                            'linux',

                        'label' =>
                            'Linux Gateway',

                        'mode' =>
                            'Collector/Agent',
                    ],
                    [
                        'value' =>
                            'other',

                        'label' =>
                            'Other Router',

                        'mode' =>
                            'Guided Collector/Gateway',
                    ],
                ],
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $organization =
            $request->attributes->get(
                'complianceOrganization'
            );

        $data =
            $request->validate([
                'network_id' => [
                    'nullable',
                    'integer',

                    Rule::exists(
                        'compliance_networks',
                        'id'
                    )->where(
                        fn ($query) =>
                            $query->where(
                                'organization_id',
                                $organization->id
                            )
                    ),
                ],

                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'vendor' => [
                    'required',
                    'in:mikrotik,openwrt,pfsense,opnsense,linux,other',
                ],

                'host' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'management_port' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:65535',
                ],

                'management_protocol' => [
                    'required',
                    'in:api,https,ssh,agent,syslog,manual',
                ],

                'api_username' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'password' => [
                    'nullable',
                    'string',
                    'max:4096',
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],
            ]);

        Router::query()
            ->create([
                'organization_id' =>
                    $organization->id,

                'network_id' =>
                    $data[
                        'network_id'
                    ] ?? null,

                'name' =>
                    $data['name'],

                'vendor' =>
                    $data['vendor'],

                'host' =>
                    $data['host'],

                'management_port' =>
                    $data[
                        'management_port'
                    ] ?? null,

                'management_protocol' =>
                    $data[
                        'management_protocol'
                    ],

                'api_username' =>
                    $data[
                        'api_username'
                    ] ?? null,

                'credential_encrypted' =>
                    !empty(
                        $data['password']
                    )
                        ? Crypt::encryptString(
                            $data['password']
                        )
                        : null,

                'connection_status' =>
                    'pending',

                'enabled' =>
                    true,

                'notes' =>
                    $data['notes']
                    ?? null,
            ]);

        return back()->with(
            'success',
            'Router registered. Capability detection will run after the deployment engine is enabled.'
        );
    }
}
