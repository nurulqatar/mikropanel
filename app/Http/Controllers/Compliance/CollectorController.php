<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\Collector;
use App\Models\Compliance\Router;
use App\Services\Compliance\ComplianceEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CollectorController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = $request->attributes->get('complianceOrganization');

        return Inertia::render('Compliance/Collectors/Index', [
            'collectors' => Collector::query()
                ->where('organization_id', $organization->id)
                ->with('router:id,name')
                ->latest('id')
                ->get(),
            'routers' => Router::query()
                ->where('organization_id', $organization->id)
                ->where('enabled', true)
                ->orderBy('name')
                ->get(['id', 'name', 'vendor']),
            'newCollector' => $request->session()->pull('compliance_new_collector'),
            'panelUrl' => url('/'),
        ]);
    }

    public function store(
        Request $request,
        ComplianceEntitlementService $entitlements
    ): RedirectResponse {
        $organization = $request->attributes->get('complianceOrganization');
        abort_unless($entitlements->logging($organization), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'router_id' => [
                'nullable',
                'integer',
                Rule::exists('compliance_routers', 'id')->where(
                    fn ($query) => $query->where('organization_id', $organization->id)
                ),
            ],
            'listen_ip' => ['required', 'ip'],
            'ipfix_port' => ['required', 'integer', 'between:1,65535'],
        ]);

        $token = Str::random(80);

        $collector = Collector::query()->create([
            'organization_id' => $organization->id,
            'network_id' => null,
            'router_id' => $data['router_id'] ?? null,
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'token_hash' => hash('sha256', $token),
            'token_prefix' => substr($token, 0, 12),
            'listen_ip' => $data['listen_ip'],
            'ipfix_port' => $data['ipfix_port'],
            'status' => 'active',
            'registered_at' => now(),
        ]);

        $request->session()->put('compliance_new_collector', [
            'id' => $collector->id,
            'uuid' => $collector->uuid,
            'token' => $token,
            'ipfix_port' => $collector->ipfix_port,
        ]);

        return back()->with(
            'success',
            'Collector created. Copy the one-time install command now.'
        );
    }
}
