<?php

namespace App\Services\Compliance;

use App\Models\Compliance\Collector;
use App\Models\Compliance\StorageTarget;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class ComplianceArchiveService
{
    public function __construct(private ComplianceS3Service $s3)
    {
    }

    public function archive(Collector $collector, array $payload): ?array
    {
        $target = StorageTarget::query()
            ->where('organization_id', $collector->organization_id)
            ->where('enabled', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if (!$target) {
            return null;
        }

        $json = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
        $compressed = gzencode($json, 6);
        if ($compressed === false) {
            throw new RuntimeException('Unable to compress compliance archive.');
        }

        $sha256 = hash('sha256', $compressed);
        $batchUuid = (string) ($payload['batch_uuid'] ?? bin2hex(random_bytes(16)));
        $objectKey =
            'organization-' . $collector->organization_id
            . '/' . now('UTC')->format('Y/m/d/H')
            . '/' . $collector->uuid
            . '/' . $batchUuid . '.json.gz';
        $config = $this->config($target);
        $archiveRef = $this->put($target, $config, $objectKey, $compressed);

        $objectId = DB::table('compliance_storage_objects')->insertGetId([
            'organization_id' => $collector->organization_id,
            'storage_target_id' => $target->id,
            'collector_id' => $collector->id,
            'object_key' => $objectKey,
            'archive_ref' => $archiveRef,
            'sha256' => $sha256,
            'size_bytes' => strlen($compressed),
            'event_count' => count($payload['events'] ?? []),
            'stored_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $target->forceFill([
            'health_status' => 'healthy',
            'last_health_check_at' => now(),
            'last_error' => null,
        ])->save();

        return [
            'storage_object_id' => $objectId,
            'archive_ref' => $archiveRef,
            'object_key' => $objectKey,
            'sha256' => $sha256,
            'size_bytes' => strlen($compressed),
        ];
    }

    public function test(StorageTarget $target): array
    {
        $key = 'mikropanel-health/' . bin2hex(random_bytes(12)) . '.json.gz';

        try {
            $body = gzencode(json_encode([
                'service' => 'MikroPanel Compliance',
                'health' => 'ok',
                'time' => now('UTC')->toIso8601String(),
            ], JSON_THROW_ON_ERROR), 6);

            if ($body === false) {
                throw new RuntimeException('Health payload compression failed.');
            }

            $config = $this->config($target);
            $ref = $this->put($target, $config, $key, $body);
            $this->delete($target, $config, $key);

            $target->forceFill([
                'health_status' => 'healthy',
                'last_health_check_at' => now(),
                'last_error' => null,
            ])->save();

            return ['success' => true, 'message' => 'Storage write/delete test passed.', 'reference' => $ref];
        } catch (Throwable $e) {
            $target->forceFill([
                'health_status' => 'failed',
                'last_health_check_at' => now(),
                'last_error' => mb_substr($e->getMessage(), 0, 2000),
            ])->save();

            return ['success' => false, 'message' => mb_substr($e->getMessage(), 0, 500)];
        }
    }

    public function deleteTrackedObject(object $object, StorageTarget $target): void
    {
        $this->delete($target, $this->config($target), (string) $object->object_key);
    }

    private function put(StorageTarget $target, array $config, string $key, string $body): string
    {
        return match ($target->driver) {
            'local', 'nfs' => $this->putLocal($config, $key, $body),
            's3', 's3_compatible' => $this->s3->put($config, $key, $body),
            default => throw new RuntimeException('Unsupported storage driver.'),
        };
    }

    private function delete(StorageTarget $target, array $config, string $key): void
    {
        match ($target->driver) {
            'local', 'nfs' => $this->deleteLocal($config, $key),
            's3', 's3_compatible' => $this->s3->delete($config, $key),
            default => throw new RuntimeException('Unsupported storage driver.'),
        };
    }

    private function putLocal(array $config, string $key, string $body): string
    {
        $path = $this->localPath($config, $key);
        $directory = dirname($path);

        if (
            !is_dir($directory)
            && !mkdir($directory, 0750, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException('Unable to create archive directory.');
        }

        if (file_put_contents($path, $body, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write archive object.');
        }

        return $path;
    }

    private function deleteLocal(array $config, string $key): void
    {
        $path = $this->localPath($config, $key);
        if (is_file($path) && !unlink($path)) {
            throw new RuntimeException('Unable to delete archive object.');
        }
    }

    private function localPath(array $config, string $key): string
    {
        $root = rtrim((string) ($config['path'] ?? ''), '/');
        if ($root === '' || !str_starts_with($root, '/')) {
            throw new RuntimeException(
                'NAS/local archive path must be an absolute mounted path.'
            );
        }
        return $root . '/' . ltrim($key, '/');
    }

    private function config(StorageTarget $target): array
    {
        if (!$target->config_encrypted) {
            return [];
        }

        $data = json_decode(
            Crypt::decryptString($target->config_encrypted),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        return is_array($data) ? $data : [];
    }
}
