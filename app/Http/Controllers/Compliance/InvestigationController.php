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
    public function csv(
        Request $request,
        ComplianceEntitlementService $entitlements,
        ComplianceInvestigationService $investigation
    ): StreamedResponse {
        $organization = $request->attributes->get('complianceOrganization');
        $user = $request->attributes->get('complianceUser');
        abort_unless($entitlements->logging($organization), 403);

        $data = $request->validate([
            'public_ip' => ['required', 'ip'],
            'public_port' => ['required', 'integer', 'between:1,65535'],
            'time' => ['required', 'date'],
            'timezone' => ['nullable', 'timezone'],
            'protocol' => ['nullable', 'in:tcp,udp'],
        ]);

        $result = $investigation->search($organization, $data);

        DB::table('compliance_audit_logs')->insert([
            'organization_id' => $organization->id,
            'compliance_user_id' => $user->id,
            'action' => 'investigation.csv_export',
            'subject_type' => 'nat_mapping',
            'subject_id' => null,
            'ip_address' => $request->ip(),
            'metadata' => json_encode([
                'public_ip' => $data['public_ip'],
                'public_port' => (int) $data['public_port'],
                'time' => $data['time'],
                'result_count' => $result['count'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->streamDownload(function () use ($result): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, [
                'Attribution Level', 'Protocol', 'Public IP', 'Public Port',
                'Private IP', 'Private Port', 'MAC', 'Identity Type',
                'Identity Key', 'Display Name', 'Mapping Start', 'Mapping End',
            ]);

            foreach ($result['results'] as $row) {
                $mapping = $row['mapping'];
                $identity = $row['identity'];
                fputcsv($out, [
                    $row['attribution_level'],
                    $mapping->protocol,
                    $mapping->public_ip,
                    $mapping->public_port,
                    $mapping->private_ip,
                    $mapping->private_port,
                    $identity?->mac_address ?? $mapping->mac_address,
                    $identity?->identity_type,
                    $identity?->identity_key,
                    $identity?->display_name,
                    $mapping->started_at,
                    $mapping->ended_at,
                ]);
            }
            fclose($out);
        }, 'compliance-investigation-' . now()->format('Ymd-His') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

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
