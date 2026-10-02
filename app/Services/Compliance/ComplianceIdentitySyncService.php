<?php

namespace App\Services\Compliance;

use App\Models\Compliance\IdentityBinding;
use App\Models\Compliance\Router;
use Throwable;

class ComplianceIdentitySyncService
{
    public function __construct(
        private ComplianceMikroTikService $mikrotik
    ) {
    }

    public function sync(Router $router): array
    {
        $started = now();
        $summary = ['dhcp' => 0, 'hotspot' => 0];

        foreach ($this->mikrotik->dhcpLeases($router) as $lease) {
            $status = strtolower((string) ($lease['status'] ?? ''));
            if ($status !== '' && $status !== 'bound') {
                continue;
            }

            $ip = trim((string) ($lease['address'] ?? ''));
            $mac = strtoupper(trim((string) ($lease['mac-address'] ?? '')));
            if ($ip === '' || $mac === '') {
                continue;
            }

            $binding = IdentityBinding::query()
                ->where('organization_id', $router->organization_id)
                ->where('router_id', $router->id)
                ->where('identity_type', 'device')
                ->where('mac_address', $mac)
                ->whereNull('ended_at')
                ->first();

            if (!$binding) {
                $binding = new IdentityBinding([
                    'organization_id' => $router->organization_id,
                    'router_id' => $router->id,
                    'identity_type' => 'device',
                    'started_at' => now(),
                    'source' => 'mikrotik_dhcp',
                ]);
            }

            $binding->forceFill([
                'identity_key' => $mac,
                'display_name' => trim((string) ($lease['host-name'] ?? '')) ?: null,
                'mac_address' => $mac,
                'private_ip' => $ip,
                'last_seen_at' => now(),
                'ended_at' => null,
            ])->save();

            $summary['dhcp']++;
        }

        try {
            $active = $this->mikrotik->hotspotActive($router);
        } catch (Throwable) {
            $active = [];
        }

        foreach ($active as $row) {
            $user = trim((string) ($row['user'] ?? ''));
            $ip = trim((string) ($row['address'] ?? ''));
            $mac = strtoupper(trim((string) ($row['mac-address'] ?? '')));

            if ($user === '' || $ip === '') {
                continue;
            }

            $binding = IdentityBinding::query()
                ->where('organization_id', $router->organization_id)
                ->where('router_id', $router->id)
                ->where('identity_type', 'hotspot')
                ->where('identity_key', $user)
                ->whereNull('ended_at')
                ->first();

            if (!$binding) {
                $binding = new IdentityBinding([
                    'organization_id' => $router->organization_id,
                    'router_id' => $router->id,
                    'identity_type' => 'hotspot',
                    'identity_key' => $user,
                    'started_at' => now(),
                    'source' => 'mikrotik_hotspot',
                ]);
            }

            $binding->forceFill([
                'display_name' => $user,
                'mac_address' => $mac !== '' ? $mac : null,
                'private_ip' => $ip,
                'last_seen_at' => now(),
                'ended_at' => null,
            ])->save();

            $summary['hotspot']++;
        }

        IdentityBinding::query()
            ->where('organization_id', $router->organization_id)
            ->where('router_id', $router->id)
            ->whereIn('source', ['mikrotik_dhcp', 'mikrotik_hotspot'])
            ->whereNull('ended_at')
            ->where(function ($query) use ($started): void {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $started);
            })
            ->update([
                'ended_at' => now(),
                'updated_at' => now(),
            ]);

        return $summary;
    }
}
