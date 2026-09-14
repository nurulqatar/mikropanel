<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResellerAuditController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $this->access(
            $request
        );

        $query =
            ResellerAuditLog::query()
                ->with([
                    'reseller:id,code,company_name',
                    'user:id,name,email',
                ]);

        if (
            $request->filled(
                'reseller_id'
            )
        ) {
            $query->where(
                'reseller_id',
                $request->integer(
                    'reseller_id'
                )
            );
        }

        if (
            $request->filled(
                'action'
            )
        ) {
            $query->where(
                'action',
                'like',
                '%'
                . $request->input(
                    'action'
                )
                . '%'
            );
        }

        return Inertia::render(
            'SuperAdmin/Audit/Index',
            [
                'logs' =>
                    $query
                        ->latest('id')
                        ->limit(500)
                        ->get(),

                'resellers' =>
                    Reseller::query()
                        ->orderBy(
                            'company_name'
                        )
                        ->get([
                            'id',
                            'code',
                            'company_name',
                        ]),

                'filters' => [
                    'reseller_id' =>
                        $request->input(
                            'reseller_id'
                        ),

                    'action' =>
                        $request->input(
                            'action'
                        ),
                ],
            ]
        );
    }

    private function access(
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
