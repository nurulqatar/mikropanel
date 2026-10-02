<?php

namespace App\Services\Compliance;

use App\Models\Compliance\IdentityBinding;
use App\Models\Compliance\NatMapping;
use App\Models\Compliance\Organization;
use Carbon\CarbonImmutable;

class ComplianceInvestigationService
{
    public function search(Organization $organization, array $data): array
    {
        $time = CarbonImmutable::parse(
            $data['time'],
            $data['timezone'] ?? $organization->timezone ?? 'Asia/Qatar'
        )->utc();

        $query = NatMapping::query()
            ->where('organization_id', $organization->id)
            ->where('public_ip', $data['public_ip'])
            ->where('public_port', (int) $data['public_port'])
            ->where('started_at', '<=', $time->addSeconds(30))
            ->where(function ($query) use ($time): void {
                $query->whereNull('ended_at')
                    ->orWhere('ended_at', '>=', $time->subSeconds(30));
            });

        if (!empty($data['protocol'])) {
            $query->where('protocol', strtolower($data['protocol']));
        }

        $mappings = $query
            ->orderByRaw(
                'ABS(TIMESTAMPDIFF(SECOND, started_at, ?)) ASC',
                [$time->toDateTimeString()]
            )
            ->limit(25)
            ->get();

        $results = [];

        foreach ($mappings as $mapping) {
            $identityQuery = IdentityBinding::query()
                ->where('organization_id', $organization->id)
                ->where('started_at', '<=', $time)
                ->where(function ($query) use ($time): void {
                    $query->whereNull('ended_at')
                        ->orWhere('ended_at', '>=', $time);
                });

            if ($mapping->router_id) {
                $identityQuery->where('router_id', $mapping->router_id);
            }

            $mac = strtoupper(trim((string) $mapping->mac_address));

            $identityQuery->where(function ($query) use ($mapping, $mac): void {
                if ($mac !== '') {
                    $query->where('mac_address', $mac)
                        ->orWhere('private_ip', $mapping->private_ip);
                } else {
                    $query->where('private_ip', $mapping->private_ip);
                }
            });

            $identity = $identityQuery
                ->orderByRaw(
                    "FIELD(identity_type, 'hotspot', 'radius', 'employee', 'guest', 'device')"
                )
                ->orderByDesc('started_at')
                ->first();

            $results[] = [
                'mapping' => $mapping,
                'identity' => $identity,
                'attribution_level' => $identity
                    ? (
                        in_array(
                            $identity->identity_type,
                            ['hotspot', 'radius', 'employee', 'guest'],
                            true
                        )
                            ? 'USER_ATTRIBUTION'
                            : 'DEVICE_ATTRIBUTION'
                    )
                    : ($mapping->mac_address ? 'DEVICE_MAC_ONLY' : 'NAT_MAPPING_ONLY'),
            ];
        }

        return [
            'requested_time_utc' => $time->toIso8601String(),
            'count' => count($results),
            'results' => $results,
        ];
    }
}
