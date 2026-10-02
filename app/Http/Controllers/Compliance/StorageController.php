<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\StorageTarget;
use App\Services\Compliance\ComplianceArchiveService;
use App\Services\Compliance\ComplianceEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class StorageController extends Controller
{
    public function index(
        Request $request,
        ComplianceEntitlementService $entitlements
    ): Response {
        $organization = $request->attributes->get('complianceOrganization');
        abort_unless($entitlements->logging($organization), 403, 'Logging subscription required.');

        return Inertia::render('Compliance/Storage/Index', [
            'targets' => StorageTarget::query()
                ->where('organization_id', $organization->id)
                ->latest('id')
                ->get(),
            'objects' => DB::table('compliance_storage_objects')
                ->where('organization_id', $organization->id)
                ->latest('stored_at')
                ->limit(50)
                ->get(),
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
            'driver' => ['required', 'in:local,nfs,s3,s3_compatible'],
            'path' => ['nullable', 'string', 'max:1200'],
            'endpoint' => ['nullable', 'url', 'max:1200'],
            'bucket' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:100'],
            'access_key' => ['nullable', 'string', 'max:500'],
            'secret_key' => ['nullable', 'string', 'max:1000'],
            'path_style' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (in_array($data['driver'], ['local', 'nfs'], true)) {
            $path = trim((string) ($data['path'] ?? ''));
            if ($path === '' || !str_starts_with($path, '/')) {
                return back()->withErrors([
                    'path' => 'Use an absolute mounted path such as /mnt/compliance-archive.',
                ]);
            }
            $config = ['path' => $path];
        } else {
            $config = [
                'endpoint' => trim((string) ($data['endpoint'] ?? '')),
                'bucket' => trim((string) ($data['bucket'] ?? '')),
                'region' => trim((string) ($data['region'] ?? 'us-east-1')),
                'access_key' => (string) ($data['access_key'] ?? ''),
                'secret_key' => (string) ($data['secret_key'] ?? ''),
                'path_style' => (bool) ($data['path_style'] ?? true),
            ];

            if (
                $config['bucket'] === ''
                || $config['access_key'] === ''
                || $config['secret_key'] === ''
            ) {
                return back()->withErrors([
                    'bucket' => 'Bucket, access key and secret key are required.',
                ]);
            }
        }

        DB::transaction(function () use ($organization, $data, $config): void {
            $isDefault = (bool) ($data['is_default'] ?? false);
            if ($isDefault) {
                StorageTarget::query()
                    ->where('organization_id', $organization->id)
                    ->update(['is_default' => false]);
            }

            StorageTarget::query()->create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'driver' => $data['driver'],
                'config_encrypted' => Crypt::encryptString(
                    json_encode($config, JSON_THROW_ON_ERROR)
                ),
                'health_status' => 'pending',
                'enabled' => true,
                'is_default' => $isDefault,
            ]);
        });

        return back()->with('success', 'External archive target created.');
    }

    public function test(
        Request $request,
        StorageTarget $storageTarget,
        ComplianceArchiveService $archive
    ): RedirectResponse {
        $organization = $request->attributes->get('complianceOrganization');
        abort_unless($storageTarget->organization_id === $organization->id, 404);
        $result = $archive->test($storageTarget);

        return back()->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    public function destroy(
        Request $request,
        StorageTarget $storageTarget
    ): RedirectResponse {
        $organization = $request->attributes->get('complianceOrganization');
        abort_unless($storageTarget->organization_id === $organization->id, 404);

        if (
            DB::table('compliance_storage_objects')
                ->where('storage_target_id', $storageTarget->id)
                ->exists()
        ) {
            return back()->with(
                'error',
                'This storage target has archive history. Keep it for evidence history.'
            );
        }

        $storageTarget->delete();
        return back()->with('success', 'Storage target removed.');
    }
}
