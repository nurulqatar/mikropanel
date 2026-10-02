<?php

namespace App\Console\Commands;

use App\Models\Compliance\Organization;
use App\Models\Compliance\StorageTarget;
use App\Services\Compliance\ComplianceArchiveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ComplianceMaintain extends Command
{
    protected $signature = 'compliance:maintain';
    protected $description = 'Apply Compliance retention policies while respecting legal holds.';

    public function handle(ComplianceArchiveService $archive): int
    {
        $organizations = 0;
        $purged = 0;
        $held = 0;
        $errors = 0;

        Organization::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById(25, function ($items) use (
                $archive,
                &$organizations,
                &$purged,
                &$held,
                &$errors
            ): void {
                foreach ($items as $organization) {
                    $organizations++;
                    $policy = DB::table('compliance_retention_policies')
                        ->where('organization_id', $organization->id)
                        ->where('is_active', true)
                        ->latest('id')
                        ->first();

                    if (!$policy || !$policy->automatic_purge) {
                        continue;
                    }

                    $hasHold = DB::table('compliance_legal_holds')
                        ->where('organization_id', $organization->id)
                        ->where('active', true)
                        ->where('starts_at', '<=', now())
                        ->where(function ($query): void {
                            $query->whereNull('ends_at')
                                ->orWhere('ends_at', '>=', now());
                        })
                        ->exists();

                    if ($hasHold) {
                        $held++;
                        continue;
                    }

                    $cutoff = now()->subDays(max(1, (int) $policy->retention_days));

                    try {
                        $purged += DB::table('compliance_browse_indexes')
                            ->where('organization_id', $organization->id)
                            ->where('observed_at', '<', $cutoff)
                            ->delete();

                        $purged += DB::table('compliance_nat_mappings')
                            ->where('organization_id', $organization->id)
                            ->whereNotNull('ended_at')
                            ->where('ended_at', '<', $cutoff)
                            ->delete();

                        $purged += DB::table('compliance_identity_bindings')
                            ->where('organization_id', $organization->id)
                            ->whereNotNull('ended_at')
                            ->where('ended_at', '<', $cutoff)
                            ->delete();

                        $objects = DB::table('compliance_storage_objects')
                            ->where('organization_id', $organization->id)
                            ->where('stored_at', '<', $cutoff)
                            ->orderBy('id')
                            ->limit(500)
                            ->get();

                        foreach ($objects as $object) {
                            $target = StorageTarget::query()->find($object->storage_target_id);
                            if (!$target) {
                                continue;
                            }
                            $archive->deleteTrackedObject($object, $target);
                            DB::table('compliance_storage_objects')
                                ->where('id', $object->id)
                                ->delete();
                            $purged++;
                        }

                        DB::table('compliance_collector_batches')
                            ->where('organization_id', $organization->id)
                            ->where('received_at', '<', $cutoff)
                            ->delete();
                    } catch (Throwable $e) {
                        $errors++;
                        DB::table('compliance_alerts')->insert([
                            'organization_id' => $organization->id,
                            'severity' => 'high',
                            'type' => 'retention_failure',
                            'title' => 'Compliance retention failed',
                            'message' => mb_substr($e->getMessage(), 0, 2000),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            });

        $this->line(
            "ORGANIZATIONS={$organizations} PURGED={$purged} HELD={$held} ERRORS={$errors}"
        );

        return self::SUCCESS;
    }
}
