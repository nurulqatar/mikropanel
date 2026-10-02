<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\Collector;
use App\Models\Compliance\Router;
use App\Services\Compliance\ComplianceEntitlementService;
use App\Services\Compliance\ComplianceMikroTikService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class RouterRuntimeController extends Controller
{
    public function test(
        Request $request,
        Router $router,
        ComplianceMikroTikService $mikrotik
    ): RedirectResponse {
        $organization = $request->attributes->get('complianceOrganization');
        abort_unless($router->organization_id === $organization->id, 404);

        $result = $mikrotik->inspect($router);

        $router->forceFill([
            'connection_status' => $result['success'] ? 'online' : 'failed',
            'wan_interface' => $result['wan_interface'] ?? $router->wan_interface,
            'capabilities' => $result,
            'capability_checked_at' => now(),
            'last_seen_at' => $result['success'] ? now() : $router->last_seen_at,
            'last_error' => $result['success'] ? null : ($result['message'] ?? 'Failed'),
        ])->save();

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['success']
                ? 'Router capability test passed.'
                : ($result['message'] ?? 'Router test failed.')
        );
    }

    public function setWan(Request $request, Router $router): RedirectResponse
    {
        $organization = $request->attributes->get('complianceOrganization');
        abort_unless($router->organization_id === $organization->id, 404);

        $data = $request->validate([
            'wan_interface' => ['required', 'string', 'max:100', 'not_regex:/[\r\n]/'],
        ]);

        $router->forceFill(['wan_interface' => $data['wan_interface']])->save();
        return back()->with('success', 'WAN interface saved.');
    }

    public function configureLogging(
        Request $request,
        Router $router,
        ComplianceEntitlementService $entitlements,
        ComplianceMikroTikService $mikrotik
    ): RedirectResponse {
        $organization = $request->attributes->get('complianceOrganization');
        abort_unless($router->organization_id === $organization->id, 404);
        abort_unless($entitlements->logging($organization), 403);

        $data = $request->validate([
            'collector_id' => [
                'required',
                Rule::exists('compliance_collectors', 'id')->where(
                    fn ($q) => $q->where('organization_id', $organization->id)
                ),
            ],
        ]);

        $collector = Collector::query()->findOrFail($data['collector_id']);

        if ($collector->router_id && $collector->router_id !== $router->id) {
            return back()->with('error', 'Collector belongs to another router.');
        }

        $collector->forceFill(['router_id' => $router->id])->save();

        try {
            $result = $mikrotik->configureLogging($router, $collector);
            return back()->with('success', $result['message']);
        } catch (Throwable $e) {
            $router->forceFill([
                'logging_ready' => false,
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();
            return back()->with('error', mb_substr($e->getMessage(), 0, 500));
        }
    }
}
