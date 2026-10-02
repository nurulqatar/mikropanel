<?php

namespace App\Services\Compliance;

use App\Models\Compliance\AppSignature;
use App\Models\Compliance\FilterRule;
use App\Models\Compliance\Network;
use App\Models\Compliance\Router;
use RouterOS\Client;
use RouterOS\Query;
use RuntimeException;
use Throwable;

class ComplianceFilterDeploymentService
{
    public function __construct(
        private ComplianceMikroTikService $mikrotik
    ) {
    }

    public function deploy(Network $network, Router $router): array
    {
        if ($router->organization_id !== $network->organization_id) {
            throw new RuntimeException('Router/network tenant mismatch.');
        }

        if ($router->vendor !== 'mikrotik') {
            throw new RuntimeException(
                'Automatic filtering deployment currently requires MikroTik.'
            );
        }

        $wan = trim((string) $router->wan_interface);
        if ($wan === '') {
            throw new RuntimeException(
                'WAN interface missing. Test router capability or set WAN manually.'
            );
        }

        $sources = collect($network->local_networks ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        if ($network->filter_mode === 'allowlist' && count($sources) === 0) {
            throw new RuntimeException(
                'Allowlist mode requires at least one local CIDR.'
            );
        }

        $client = $this->mikrotik->routerClient($router);
        $version = mb_substr(
            'MPC-C'
            . $network->organization_id
            . 'N'
            . $network->id
            . 'V'
            . strtoupper(base_convert((string) time(), 10, 36)),
            0,
            40
        );

        $jumpComment = 'MikroPanel Compliance Network #' . $network->id . ' Jump';

        try {
            $rules = FilterRule::query()
                ->where('organization_id', $network->organization_id)
                ->where('network_id', $network->id)
                ->where('enabled', true)
                ->orderBy('priority')
                ->orderBy('id')
                ->get();

            foreach ($rules as $rule) {
                if ($rule->rule_type === 'protocol') {
                    $this->protocolRule($client, $version, $rule);
                    continue;
                }

                $targets = $this->targets($rule);
                if (!$targets) {
                    continue;
                }

                $list = mb_substr($version . '-R' . $rule->id, 0, 63);

                foreach ($targets as $target) {
                    $q = new Query('/ip/firewall/address-list/add');
                    $q->equal('list', $list)
                        ->equal('address', $target)
                        ->equal('comment', 'MikroPanel Compliance Rule #' . $rule->id);
                    $client->query($q)->read();
                }

                $q = new Query('/ip/firewall/filter/add');
                $q->equal('chain', $version)
                    ->equal('dst-address-list', $list)
                    ->equal('action', $rule->action === 'allow' ? 'accept' : 'drop')
                    ->equal('comment', 'MikroPanel Compliance Rule #' . $rule->id);
                $client->query($q)->read();
            }

            if ($network->filter_mode === 'allowlist') {
                foreach ([['udp', '53'], ['tcp', '53'], ['udp', '123']] as [$proto, $port]) {
                    $q = new Query('/ip/firewall/filter/add');
                    $q->equal('chain', $version)
                        ->equal('protocol', $proto)
                        ->equal('dst-port', $port)
                        ->equal('action', 'accept')
                        ->equal('comment', 'MikroPanel Compliance Essential');
                    $client->query($q)->read();
                }

                $q = new Query('/ip/firewall/filter/add');
                $q->equal('chain', $version)
                    ->equal('action', 'drop')
                    ->equal('comment', 'MikroPanel Compliance Default Deny');
                $client->query($q)->read();
            } else {
                $q = new Query('/ip/firewall/filter/add');
                $q->equal('chain', $version)
                    ->equal('action', 'return')
                    ->equal('comment', 'MikroPanel Compliance Return');
                $client->query($q)->read();
            }

            $all = $client->query(
                new Query('/ip/firewall/filter/print')
            )->read();

            $newCount = collect($all)->filter(
                fn ($row) => (string) ($row['chain'] ?? '') === $version
            )->count();

            if ($newCount < 1) {
                throw new RuntimeException('New policy chain verification failed.');
            }

            $existingJumps = collect($all)->filter(
                fn ($row) => (string) ($row['comment'] ?? '') === $jumpComment
            )->values();

            if ($existingJumps->isEmpty()) {
                foreach ($sources ?: [null] as $source) {
                    $q = new Query('/ip/firewall/filter/add');
                    $q->equal('chain', 'forward')
                        ->equal('out-interface', $wan)
                        ->equal('action', 'jump')
                        ->equal('jump-target', $version)
                        ->equal('comment', $jumpComment)
                        ->equal('place-before', '0');

                    if ($source) {
                        $q->equal('src-address', $source);
                    }

                    $client->query($q)->read();
                }
            } else {
                foreach ($existingJumps as $row) {
                    $id = $row['.id'] ?? null;
                    if (!$id) {
                        continue;
                    }

                    $q = new Query('/ip/firewall/filter/set');
                    $q->equal('.id', (string) $id)
                        ->equal('jump-target', $version)
                        ->equal('out-interface', $wan);
                    $client->query($q)->read();
                }
            }

            FilterRule::query()
                ->where('organization_id', $network->organization_id)
                ->where('network_id', $network->id)
                ->where('enabled', true)
                ->update([
                    'deployment_status' => 'applied',
                    'updated_at' => now(),
                ]);

            $router->forceFill([
                'filtering_ready' => true,
                'last_error' => null,
            ])->save();

            return [
                'success' => true,
                'policy_version' => $version,
                'mode' => $network->filter_mode,
            ];
        } catch (Throwable $e) {
            $router->forceFill([
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();

            throw $e;
        }
    }

    private function targets(FilterRule $rule): array
    {
        if ($rule->rule_type === 'app') {
            $signature = AppSignature::query()
                ->where(function ($query) use ($rule): void {
                    $query->whereNull('organization_id')
                        ->orWhere('organization_id', $rule->organization_id);
                })
                ->where('slug', trim($rule->target_value))
                ->where('enabled', true)
                ->first();

            if (!$signature) {
                throw new RuntimeException(
                    'Application signature not found: ' . $rule->target_value
                );
            }

            return collect([
                ...($signature->domains ?? []),
                ...($signature->ip_ranges ?? []),
            ])->map(fn ($x) => trim((string) $x))
                ->filter()
                ->reject(fn ($x) => str_contains($x, '*'))
                ->unique()
                ->values()
                ->all();
        }

        if ($rule->rule_type === 'category') {
            return AppSignature::query()
                ->where(function ($query) use ($rule): void {
                    $query->whereNull('organization_id')
                        ->orWhere('organization_id', $rule->organization_id);
                })
                ->where('category', trim($rule->target_value))
                ->where('enabled', true)
                ->get()
                ->flatMap(fn ($sig) => [
                    ...($sig->domains ?? []),
                    ...($sig->ip_ranges ?? []),
                ])
                ->map(fn ($x) => trim((string) $x))
                ->filter()
                ->reject(fn ($x) => str_contains($x, '*'))
                ->unique()
                ->values()
                ->all();
        }

        return collect(preg_split('/[\r\n,]+/', $rule->target_value))
            ->map(fn ($x) => trim((string) $x))
            ->filter()
            ->reject(fn ($x) => str_contains($x, '*'))
            ->unique()
            ->values()
            ->all();
    }

    private function protocolRule(Client $client, string $chain, FilterRule $rule): void
    {
        $value = strtolower(trim($rule->target_value));

        if (!preg_match('/^(tcp|udp)(?::([0-9]{1,5}))?$/', $value, $m)) {
            throw new RuntimeException('Protocol rule must look like tcp:443 or udp:53.');
        }

        $q = new Query('/ip/firewall/filter/add');
        $q->equal('chain', $chain)
            ->equal('protocol', $m[1])
            ->equal('action', $rule->action === 'allow' ? 'accept' : 'drop')
            ->equal('comment', 'MikroPanel Compliance Rule #' . $rule->id);

        if (!empty($m[2])) {
            $port = (int) $m[2];
            if ($port < 1 || $port > 65535) {
                throw new RuntimeException('Protocol port is invalid.');
            }
            $q->equal('dst-port', (string) $port);
        }

        $client->query($q)->read();
    }
}
