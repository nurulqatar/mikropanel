<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Services\Compliance\ComplianceEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RetentionController extends Controller
{
    public function index(
        Request $request,
        ComplianceEntitlementService $entitlements
    ): Response {
        $organization = $request->attributes->get('complianceOrganization');
        abort_unless($entitlements->logging($organization), 403, 'Logging subscription required.');

        return Inertia::render('Compliance/Retention/Index', [
            'policies' => DB::table('compliance_retention_policies')
                ->where('organization_id', $organization->id)
                ->latest('id')->get(),
            'holds' => DB::table('compliance_legal_holds')
                ->where('organization_id', $organization->id)
                ->latest('id')->get(),
        ]);
    }

    public function storePolicy(Request $request): RedirectResponse
    {
        $organization = $request->attributes->get('complianceOrganization');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'retention_days' => ['required', 'integer', 'between:1,3650'],
            'automatic_purge' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($organization, $data): void {
            DB::table('compliance_retention_policies')
                ->where('organization_id', $organization->id)
                ->update(['is_active' => false, 'updated_at' => now()]);

            DB::table('compliance_retention_policies')->insert([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'retention_days' => (int) $data['retention_days'],
                'automatic_purge' => (bool) ($data['automatic_purge'] ?? true),
                'legal_hold_override' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Retention policy activated.');
    }

    public function storeHold(Request $request): RedirectResponse
    {
        $organization = $request->attributes->get('complianceOrganization');
        $data = $request->validate([
            'scope_type' => ['required', 'in:organization,case,public_ip,identity'],
            'scope_value' => ['required', 'string', 'max:2000'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        DB::table('compliance_legal_holds')->insert([
            'organization_id' => $organization->id,
            'case_id' => null,
            'scope_type' => $data['scope_type'],
            'scope_value' => $data['scope_value'],
            'starts_at' => now(),
            'ends_at' => $data['ends_at'] ?? null,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with(
            'success',
            'Legal hold activated. Automatic purge is conservatively paused while an active hold exists.'
        );
    }

    public function releaseHold(Request $request, int $hold): RedirectResponse
    {
        $organization = $request->attributes->get('complianceOrganization');
        $updated = DB::table('compliance_legal_holds')
            ->where('id', $hold)
            ->where('organization_id', $organization->id)
            ->update([
                'active' => false,
                'ends_at' => now(),
                'updated_at' => now(),
            ]);

        abort_unless($updated > 0, 404);
        return back()->with('success', 'Legal hold released.');
    }
}
