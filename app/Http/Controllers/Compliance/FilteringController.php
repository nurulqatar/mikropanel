<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\AppSignature;
use App\Models\Compliance\FilterRule;
use App\Models\Compliance\Network;
use App\Models\Compliance\Router;
use App\Services\Compliance\ComplianceEntitlementService;
use App\Services\Compliance\ComplianceFilterDeploymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class FilteringController extends Controller
{
    public function index(
        Request $request,
        ComplianceEntitlementService $entitlements
    ): Response {
        $organization = $request->attributes->get('complianceOrganization');
        abort_unless($entitlements->filtering($organization), 403);

        return Inertia::render('Compliance/Filtering/Index', [
            'networks' => Network::query()
                ->where('organization_id', $organization->id)
                ->orderBy('name')
                ->get(),
            'routers' => Router::query()
                ->where('organization_id', $organization->id)
                ->where('enabled', true)
                ->orderBy('name')
                ->get(),
            'rules' => FilterRule::query()
                ->where('organization_id', $organization->id)
                ->with('network:id,name,filter_mode')
                ->orderBy('priority')
                ->orderBy('id')
                ->get(),
            'signatures' => AppSignature::query()
                ->where(function ($query) use ($organization): void {
                    $query->whereNull('organization_id')
                        ->orWhere('organization_id', $organization->id);
                })
                ->where('enabled', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $organization = $request->attributes->get('complianceOrganization');

        $data = $request->validate([
            'network_id' => [
                'required',
                'integer',
                Rule::exists('compliance_networks', 'id')->where(
                    fn ($q) => $q->where('organization_id', $organization->id)
                ),
            ],
            'name' => ['required', 'string', 'max:150'],
            'rule_type' => ['required', 'in:domain,ip,cidr,server,app,category,protocol,custom'],
            'action' => ['required', 'in:allow,block'],
            'target_value' => ['required', 'string', 'max:10000'],
            'priority' => ['required', 'integer', 'between:1,10000'],
        ]);

        FilterRule::query()->create([
            'organization_id' => $organization->id,
            ...$data,
            'enabled' => true,
            'deployment_status' => 'draft',
        ]);

        return back()->with('success', 'Filtering rule created.');
    }

    public function storeSignature(Request $request): RedirectResponse
    {
        $organization = $request->attributes->get('complianceOrganization');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'domains_text' => ['nullable', 'string', 'max:20000'],
            'ip_ranges_text' => ['nullable', 'string', 'max:20000'],
            'confidence' => ['required', 'in:high,medium,limited'],
        ]);

        $lines = fn (string $v) => collect(preg_split('/[\r\n,]+/', $v))
            ->map(fn ($x) => trim((string) $x))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $slug = Str::slug($data['name']) ?: 'app-' . Str::lower(Str::random(8));

        AppSignature::query()->create([
            'organization_id' => $organization->id,
            'name' => $data['name'],
            'slug' => $slug,
            'scope_type' => 'organization',
            'category' => $data['category'] ?? null,
            'domains' => $lines($data['domains_text'] ?? ''),
            'ip_ranges' => $lines($data['ip_ranges_text'] ?? ''),
            'ports' => [],
            'protocols' => [],
            'confidence' => $data['confidence'],
            'enabled' => true,
        ]);

        return back()->with('success', 'Application signature created.');
    }

    public function deploy(
        Request $request,
        ComplianceFilterDeploymentService $deployment
    ): RedirectResponse {
        $organization = $request->attributes->get('complianceOrganization');

        $data = $request->validate([
            'network_id' => [
                'required',
                Rule::exists('compliance_networks', 'id')->where(
                    fn ($q) => $q->where('organization_id', $organization->id)
                ),
            ],
            'router_id' => [
                'required',
                Rule::exists('compliance_routers', 'id')->where(
                    fn ($q) => $q->where('organization_id', $organization->id)
                ),
            ],
        ]);

        try {
            $result = $deployment->deploy(
                Network::query()->findOrFail($data['network_id']),
                Router::query()->findOrFail($data['router_id'])
            );

            return back()->with(
                'success',
                'Policy deployed: ' . $result['policy_version']
            );
        } catch (Throwable $e) {
            return back()->with('error', mb_substr($e->getMessage(), 0, 500));
        }
    }
}
