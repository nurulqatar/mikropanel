<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Services\Compliance\ComplianceEntitlementService;
use App\Services\Compliance\ComplianceInvestigationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InvestigationController extends Controller
{
    public function index(
        Request $request,
        ComplianceEntitlementService $entitlements,
        ComplianceInvestigationService $investigation
    ): Response {
        $organization = $request->attributes->get('complianceOrganization');
        abort_unless($entitlements->logging($organization), 403);

        $search = null;

        if ($request->filled('public_ip')) {
            $data = $request->validate([
                'public_ip' => ['required', 'ip'],
                'public_port' => ['required', 'integer', 'between:1,65535'],
                'time' => ['required', 'date'],
                'timezone' => ['nullable', 'timezone'],
                'protocol' => ['nullable', 'in:tcp,udp'],
            ]);

            $search = $investigation->search($organization, $data);
        }

        return Inertia::render('Compliance/Investigation/Index', [
            'search' => $search,
            'timezone' => $organization->timezone ?? 'Asia/Qatar',
        ]);
    }
}
