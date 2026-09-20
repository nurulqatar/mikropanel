<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\BrowseIndex;
use App\Models\Compliance\Collector;
use App\Models\Compliance\IdentityBinding;
use App\Models\Compliance\NatMapping;
use App\Services\Compliance\ComplianceArchiveService;
use App\Services\Compliance\ComplianceEntitlementService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class CollectorApiController extends Controller
{
    public function heartbeat(
        Request $request,
        ComplianceEntitlementService $entitlements
    ): JsonResponse {
        /** @var Collector $collector */
        $collector = $request->attributes->get('complianceCollector');
        $data = $request->validate([
            'version' => ['nullable', 'string', 'max:100'],
            'capabilities' => ['nullable', 'array'],
        ]);

        $collector->forceFill([
            'version' => $data['version'] ?? $collector->version,
            'capabilities' => $data['capabilities'] ?? $collector->capabilities,
            'last_ip_address' => $request->ip(),
            'last_seen_at' => now(),
            'registered_at' => $collector->registered_at ?? now(),
            'status' => 'active',
        ])->save();

        return response()->json([
            'ok' => true,
            'server_time' => now('UTC')->toIso8601String(),
            'logging' => $entitlements->logging($collector->organization_id),
            'filtering' => $entitlements->filtering($collector->organization_id),
        ]);
    }

    public function batch(
        Request $request,
        ComplianceEntitlementService $entitlements,
        ComplianceArchiveService $archive
    ): JsonResponse {
        /** @var Collector $collector */
        $collector = $request->attributes->get('complianceCollector');

        if (!$entitlements->logging($collector->organization_id)) {
            return response()->json(['message' => 'Logging service is not active.'], 403);
        }

        $payload = $request->validate([
            'batch_uuid' => ['required', 'uuid'],
            'events' => ['required', 'array', 'min:1', 'max:1000'],
        ]);

        if (
            DB::table('compliance_collector_batches')
                ->where('collector_id', $collector->id)
                ->where('batch_uuid', $payload['batch_uuid'])
                ->exists()
        ) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        /* Only these metadata keys are accepted; unknown/body/payload keys are discarded. */
        $events = collect($payload['events'])
            ->filter(fn ($event) => is_array($event))
            ->map(fn ($event) => $this->normalize($event))
            ->filter()
            ->values();

        if ($events->isEmpty()) {
            return response()->json(['message' => 'No valid metadata events.'], 422);
        }

        $normalizedPayload = [
            'batch_uuid' => $payload['batch_uuid'],
            'collector_uuid' => $collector->uuid,
            'received_at' => now('UTC')->toIso8601String(),
            'events' => $events->all(),
        ];

        try {
            $archiveResult = $archive->archive($collector, $normalizedPayload);
        } catch (Throwable $e) {
            return response()->json([
                'message' => 'External archive write failed; batch was not accepted: '
                    . mb_substr($e->getMessage(), 0, 300),
            ], 503);
        }

        $archiveRef = $archiveResult['archive_ref'] ?? null;
        $archiveSha = $archiveResult['sha256'] ?? null;
        $counts = ['identity' => 0, 'nat' => 0, 'browse' => 0];

        DB::transaction(function () use (
            $collector,
            $events,
            $payload,
            $archiveRef,
            $archiveSha,
            &$counts
        ): void {
            foreach ($events as $event) {
                if ($event['type'] === 'identity') {
                    $this->identity($collector, $event);
                    $counts['identity']++;
                } elseif ($event['type'] === 'nat') {
                    $this->nat($collector, $event, $archiveRef, $archiveSha);
                    $counts['nat']++;
                } else {
                    $this->browse($collector, $event, $archiveRef, $archiveSha);
                    $counts['browse']++;
                }
            }

            DB::table('compliance_collector_batches')->insert([
                'organization_id' => $collector->organization_id,
                'collector_id' => $collector->id,
                'batch_uuid' => $payload['batch_uuid'],
                'event_count' => array_sum($counts),
                'sha256' => hash('sha256', json_encode($events->all())),
                'archive_ref' => $archiveRef,
                'archive_sha256' => $archiveSha,
                'received_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $collector->forceFill(['last_seen_at' => now(), 'status' => 'active'])->save();
        });

        return response()->json([
            'ok' => true,
            'counts' => $counts,
            'archived' => $archiveRef !== null,
            'archive_sha256' => $archiveSha,
        ]);
    }

    private function normalize(array $event): ?array
    {
        $type = strtolower(trim((string) ($event['type'] ?? '')));
        $allowed = match ($type) {
            'identity' => [
                'identity_type', 'identity_key', 'display_name', 'mac_address',
                'private_ip', 'started_at', 'ended_at', 'source',
            ],
            'nat' => [
                'protocol', 'public_ip', 'public_port', 'private_ip', 'private_port',
                'destination_ip', 'destination_port', 'mac_address', 'identity_key',
                'started_at', 'ended_at',
            ],
            'browse' => [
                'event_type', 'observed_at', 'source_ip', 'source_port', 'source_mac',
                'identity_key', 'destination_ip', 'destination_port', 'public_ip',
                'public_port', 'domain', 'protocol', 'bytes_up', 'bytes_down', 'blocked',
            ],
            default => null,
        };

        if ($allowed === null) {
            return null;
        }

        $normalized = ['type' => $type];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $event)) {
                continue;
            }
            $value = $event[$key];
            if (is_string($value)) {
                $value = mb_substr(trim($value), 0, 2000);
            }
            $normalized[$key] = $value;
        }
        return $normalized;
    }

    private function identity(Collector $collector, array $event): void
    {
        $privateIp = $this->ip($event['private_ip'] ?? null);
        $mac = $this->mac($event['mac_address'] ?? null);
        if (!$privateIp && !$mac) {
            return;
        }

        IdentityBinding::query()->create([
            'organization_id' => $collector->organization_id,
            'router_id' => $collector->router_id,
            'identity_type' => mb_substr((string) ($event['identity_type'] ?? 'device'), 0, 30),
            'identity_key' => $event['identity_key'] ?? null,
            'display_name' => $event['display_name'] ?? null,
            'mac_address' => $mac,
            'private_ip' => $privateIp,
            'started_at' => $this->time($event['started_at'] ?? null),
            'ended_at' => !empty($event['ended_at']) ? $this->time($event['ended_at']) : null,
            'last_seen_at' => now(),
            'source' => mb_substr((string) ($event['source'] ?? 'collector'), 0, 30),
        ]);
    }

    private function nat(
        Collector $collector,
        array $event,
        ?string $archiveRef,
        ?string $archiveSha
    ): void {
        $publicIp = $this->ip($event['public_ip'] ?? null);
        $privateIp = $this->ip($event['private_ip'] ?? null);
        $publicPort = $this->port($event['public_port'] ?? null);
        if (!$publicIp || !$privateIp || !$publicPort) {
            return;
        }

        NatMapping::query()->create([
            'organization_id' => $collector->organization_id,
            'router_id' => $collector->router_id,
            'collector_id' => $collector->id,
            'protocol' => mb_substr(strtolower((string) ($event['protocol'] ?? 'unknown')), 0, 10),
            'public_ip' => $publicIp,
            'public_port' => $publicPort,
            'private_ip' => $privateIp,
            'private_port' => $this->port($event['private_port'] ?? null),
            'destination_ip' => $this->ip($event['destination_ip'] ?? null),
            'destination_port' => $this->port($event['destination_port'] ?? null),
            'mac_address' => $this->mac($event['mac_address'] ?? null),
            'identity_key' => $event['identity_key'] ?? null,
            'started_at' => $this->time($event['started_at'] ?? null),
            'ended_at' => !empty($event['ended_at']) ? $this->time($event['ended_at']) : null,
            'archive_ref' => $archiveRef,
            'integrity_hash' => $archiveSha,
        ]);
    }

    private function browse(
        Collector $collector,
        array $event,
        ?string $archiveRef,
        ?string $archiveSha
    ): void {
        $domain = strtolower(trim((string) ($event['domain'] ?? '')));

        BrowseIndex::query()->create([
            'organization_id' => $collector->organization_id,
            'router_id' => $collector->router_id,
            'collector_id' => $collector->id,
            'event_type' => mb_substr((string) ($event['event_type'] ?? 'flow'), 0, 20),
            'observed_at' => $this->time($event['observed_at'] ?? null),
            'source_ip' => $this->ip($event['source_ip'] ?? null),
            'source_port' => $this->port($event['source_port'] ?? null),
            'source_mac' => $this->mac($event['source_mac'] ?? null),
            'identity_key' => $event['identity_key'] ?? null,
            'destination_ip' => $this->ip($event['destination_ip'] ?? null),
            'destination_port' => $this->port($event['destination_port'] ?? null),
            'public_ip' => $this->ip($event['public_ip'] ?? null),
            'public_port' => $this->port($event['public_port'] ?? null),
            'domain' => $domain !== '' ? mb_substr($domain, 0, 255) : null,
            'protocol' => mb_substr(strtolower((string) ($event['protocol'] ?? '')), 0, 20),
            'bytes_up' => max(0, (int) ($event['bytes_up'] ?? 0)),
            'bytes_down' => max(0, (int) ($event['bytes_down'] ?? 0)),
            'blocked' => (bool) ($event['blocked'] ?? false),
            'archive_ref' => $archiveRef,
            'integrity_hash' => $archiveSha,
        ]);
    }

    private function time(mixed $value): CarbonImmutable
    {
        return $value
            ? CarbonImmutable::parse((string) $value)->utc()
            : CarbonImmutable::now('UTC');
    }

    private function ip(mixed $value): ?string
    {
        $value = trim((string) $value);
        return filter_var($value, FILTER_VALIDATE_IP) ? $value : null;
    }

    private function port(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $port = (int) $value;
        return $port >= 1 && $port <= 65535 ? $port : null;
    }

    private function mac(mixed $value): ?string
    {
        $mac = strtoupper(trim((string) $value));
        return preg_match('/^[0-9A-F]{2}(?::[0-9A-F]{2}){5}$/', $mac) ? $mac : null;
    }
}
