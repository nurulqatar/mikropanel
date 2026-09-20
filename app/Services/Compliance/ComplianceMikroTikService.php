<?php

namespace App\Services\Compliance;

use App\Models\Compliance\Collector;
use App\Models\Compliance\Router;
use Illuminate\Support\Facades\Crypt;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use RuntimeException;
use Throwable;

class ComplianceMikroTikService
{
    public function inspect(Router $router): array
    {
        try {
            $client = $this->client($router);

            $identityRows = $client->query(
                new Query('/system/identity/print')
            )->read();

            $resourceRows = $client->query(
                new Query('/system/resource/print')
            )->read();

            $routes = $client->query(
                new Query('/ip/route/print')
            )->read();

            $trafficFlow = $client->query(
                new Query('/ip/traffic-flow/print')
            )->read();

            $targets = $client->query(
                new Query('/ip/traffic-flow/target/print')
            )->read();

            $identity = $identityRows[0] ?? [];
            $resource = $resourceRows[0] ?? [];

            return [
                'success' => true,
                'identity' => $identity['name'] ?? null,
                'version' => $resource['version'] ?? null,
                'architecture' => $resource['architecture-name'] ?? null,
                'wan_interface' => $this->detectWanInterface($routes),
                'traffic_flow_supported' => true,
                'traffic_flow_enabled' =>
                    (string) ($trafficFlow[0]['enabled'] ?? 'false') === 'true',
                'traffic_flow_targets' => count($targets),
                'firewall_filter_supported' => true,
                'checked_at' => now()->toIso8601String(),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => $this->safeMessage($e, $router),
                'checked_at' => now()->toIso8601String(),
            ];
        }
    }

    public function configureLogging(Router $router, Collector $collector): array
    {
        if ($router->vendor !== 'mikrotik') {
            throw new RuntimeException(
                'Automatic logging configuration currently requires MikroTik.'
            );
        }

        if (!$collector->listen_ip) {
            throw new RuntimeException('Collector listen IP is required.');
        }

        $client = $this->client($router);

        $flow = new Query('/ip/traffic-flow/set');
        foreach ([
            'enabled' => 'yes',
            'interfaces' => 'all',
            'cache-entries' => '4k',
            'active-flow-timeout' => '1m',
            'inactive-flow-timeout' => '15s',
            'packet-sampling' => 'no',
        ] as $key => $value) {
            $flow->equal($key, $value);
        }
        $client->query($flow)->read();

        $ipfix = new Query('/ip/traffic-flow/ipfix/set');
        foreach ([
            'bytes',
            'src-address',
            'dst-address',
            'src-port',
            'dst-port',
            'protocol',
            'src-mac-address',
            'first-forwarded',
            'last-forwarded',
            'nat-events',
            'nat-src-address',
            'nat-src-port',
            'nat-dst-address',
            'nat-dst-port',
        ] as $field) {
            $ipfix->equal($field, 'yes');
        }
        $client->query($ipfix)->read();

        $targetExists = false;
        $targets = $client->query(
            new Query('/ip/traffic-flow/target/print')
        )->read();

        foreach ($targets as $target) {
            if (
                (string) ($target['dst-address'] ?? '') === (string) $collector->listen_ip
                && (int) ($target['port'] ?? 2055) === (int) $collector->ipfix_port
            ) {
                $targetExists = true;
                break;
            }
        }

        if (!$targetExists) {
            $add = new Query('/ip/traffic-flow/target/add');
            $add->equal('dst-address', (string) $collector->listen_ip)
                ->equal('port', (string) $collector->ipfix_port)
                ->equal('version', 'ipfix');
            $client->query($add)->read();
        }

        $dnsAction = 'mpc-cmp-dns-' . $collector->id;
        $actions = $client->query(
            new Query('/system/logging/action/print')
        )->read();

        $actionExists = collect($actions)->contains(
            fn ($row) => (string) ($row['name'] ?? '') === $dnsAction
        );

        if (!$actionExists) {
            $addAction = new Query('/system/logging/action/add');
            $addAction->equal('name', $dnsAction)
                ->equal('target', 'remote')
                ->equal('remote', (string) $collector->listen_ip)
                ->equal('remote-port', '5514');
            $client->query($addAction)->read();
        }

        $loggingRows = $client->query(
            new Query('/system/logging/print')
        )->read();

        $dnsRuleExists = collect($loggingRows)->contains(
            fn ($row) =>
                (string) ($row['action'] ?? '') === $dnsAction
                && str_contains((string) ($row['topics'] ?? ''), 'dns')
        );

        if (!$dnsRuleExists) {
            $dnsRule = new Query('/system/logging/add');
            $dnsRule->equal('topics', 'dns')
                ->equal('action', $dnsAction);
            $client->query($dnsRule)->read();
        }

        $capabilities = is_array($router->capabilities)
            ? $router->capabilities
            : [];

        $capabilities['compliance_logging'] = [
            'collector_id' => $collector->id,
            'collector_ip' => $collector->listen_ip,
            'ipfix_port' => $collector->ipfix_port,
            'dns_syslog_port' => 5514,
            'configured_at' => now()->toIso8601String(),
        ];

        $router->forceFill([
            'logging_ready' => true,
            'capabilities' => $capabilities,
            'last_error' => null,
        ])->save();

        return [
            'success' => true,
            'message' => 'MikroTik IPFIX NAT export and DNS syslog configured.',
        ];
    }

    public function routerClient(Router $router): Client
    {
        return $this->client($router);
    }

    public function dhcpLeases(Router $router): array
    {
        return $this->client($router)
            ->query(new Query('/ip/dhcp-server/lease/print'))
            ->read();
    }

    public function hotspotActive(Router $router): array
    {
        return $this->client($router)
            ->query(new Query('/ip/hotspot/active/print'))
            ->read();
    }

    private function client(Router $router): Client
    {
        if ($router->vendor !== 'mikrotik') {
            throw new RuntimeException('Router vendor is not MikroTik.');
        }

        if (!$router->credential_encrypted) {
            throw new RuntimeException('Router password is not configured.');
        }

        $password = Crypt::decryptString($router->credential_encrypted);
        $ssl = $router->management_protocol === 'https';

        $config = new Config([
            'host' => $router->host,
            'user' => (string) $router->api_username,
            'pass' => $password,
            'port' => (int) (
                $router->management_port ?: ($ssl ? 8729 : 8728)
            ),
            'ssl' => $ssl,
            'timeout' => 6,
            'socket_timeout' => 6,
            'attempts' => 1,
            'delay' => 0,
        ]);

        return new Client($config);
    }

    private function detectWanInterface(array $routes): ?string
    {
        foreach ($routes as $route) {
            if ((string) ($route['dst-address'] ?? '') !== '0.0.0.0/0') {
                continue;
            }

            if (
                isset($route['active'])
                && (string) $route['active'] === 'false'
            ) {
                continue;
            }

            $immediate = (string) ($route['immediate-gw'] ?? '');
            if (str_contains($immediate, '%')) {
                return trim(substr($immediate, strrpos($immediate, '%') + 1));
            }

            $gateway = trim((string) ($route['gateway'] ?? ''));
            if ($gateway !== '' && !filter_var($gateway, FILTER_VALIDATE_IP)) {
                return $gateway;
            }
        }

        return null;
    }

    private function safeMessage(Throwable $e, Router $router): string
    {
        $message = trim($e->getMessage());

        try {
            if ($router->credential_encrypted) {
                $password = Crypt::decryptString($router->credential_encrypted);
                if ($password !== '') {
                    $message = str_replace($password, '[hidden]', $message);
                }
            }
        } catch (Throwable) {
        }

        return mb_substr($message !== '' ? $message : 'MikroTik API error.', 0, 500);
    }
}
