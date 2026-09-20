<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\Network;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NetworkController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $organization =
            $request->attributes->get(
                'complianceOrganization'
            );

        return Inertia::render(
            'Compliance/Networks/Index',
            [
                'networks' =>
                    Network::query()
                        ->where(
                            'organization_id',
                            $organization->id
                        )
                        ->withCount(
                            'routers'
                        )
                        ->latest('id')
                        ->get(),
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
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'site_code' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'filter_mode' => [
                    'required',
                    'in:blocklist,allowlist',
                ],

                'local_networks_text' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],
            ]);

        $localNetworks =
            collect(
                preg_split(
                    '/[\r\n,]+/',
                    (string) (
                        $data[
                            'local_networks_text'
                        ] ?? ''
                    )
                )
            )
                ->map(
                    fn ($item) =>
                        trim(
                            (string) $item
                        )
                )
                ->filter()
                ->values()
                ->all();

        Network::query()
            ->create([
                'organization_id' =>
                    $organization->id,

                'name' =>
                    $data['name'],

                'site_code' =>
                    $data[
                        'site_code'
                    ] ?? null,

                'local_networks' =>
                    $localNetworks,

                'filter_mode' =>
                    $data[
                        'filter_mode'
                    ],

                'enabled' =>
                    true,
            ]);

        return back()->with(
            'success',
            'Network created.'
        );
    }
}
