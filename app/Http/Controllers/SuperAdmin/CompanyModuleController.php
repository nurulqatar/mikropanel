<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Services\Reseller\ResellerModuleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompanyModuleController extends Controller
{
    public function index(
        Request $request,
        ResellerModuleService $modules
    ): Response {
        $this->superAdmin(
            $request
        );

        $resellers =
            Reseller::query()
                ->orderBy(
                    'company_name'
                )
                ->get([
                    'id',
                    'code',
                    'company_name',
                    'status',
                ])
                ->map(
                    fn (
                        Reseller $reseller
                    ): array => [
                        'id' =>
                            $reseller->id,

                        'code' =>
                            $reseller->code,

                        'company_name' =>
                            $reseller
                                ->company_name,

                        'status' =>
                            $reseller->status,

                        'modules' =>
                            $modules
                                ->enabledKeysForResellerId(
                                    $reseller->id
                                ),
                    ]
                )
                ->values();

        return Inertia::render(
            'SuperAdmin/CompanyModules/Index',
            [
                'resellers' =>
                    $resellers,

                'moduleDefinitions' =>
                    array_values(
                        $modules
                            ->definitions()
                    ),
            ]
        );
    }

    public function update(
        Request $request,
        Reseller $reseller,
        ResellerModuleService $modules
    ): RedirectResponse {
        $this->superAdmin(
            $request
        );

        $data =
            $request->validate([
                'modules' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'modules.*' => [
                    'required',
                    'string',
                    'distinct',
                    Rule::in(
                        $modules->keys()
                    ),
                ],
            ]);

        $modules->setEnabledKeys(
            $reseller->id,
            $data['modules'],
            $request->user()->id
        );

        return back()->with(
            'success',
            'Company modules updated.'
        );
    }

    private function superAdmin(
        Request $request
    ): void {
        abort_unless(
            $request->user()
            && $request
                ->user()
                ->isSuperAdmin(),
            403
        );
    }
}
