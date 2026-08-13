<?php

namespace App\Services;

use App\Models\Router;
use RouterOS\Client as RouterClient;
use RouterOS\Query;

class QueueUsageService
{
    public function readForRouter(
        Router $router
    ): array {
        $api =
            new RouterClient([
                'host' =>
                    $router->host,

                'user' =>
                    $router->username,

                'pass' =>
                    $router->password,

                'port' =>
                    (int) (
                        $router->api_port
                        ?? 8728
                    ),

                'ssl' =>
                    (bool)
                    $router->use_ssl,

                'timeout' => 10,
            ]);

        $rows =
            $api->query(
                (new Query(
                    '/queue/simple/print'
                ))
                    ->equal(
                        '.proplist',
                        implode(',', [
                            '.id',
                            'name',
                            'bytes',
                            'disabled',
                            'invalid',
                        ])
                    )
            )
                ->read();

        return $this->normalizeRows(
            $rows
        );
    }

    public function normalizeRows(
        array $rows
    ): array {
        $result = [];

        foreach ($rows as $row) {
            $name =
                $row['name']
                ?? null;

            if (!$name) {
                continue;
            }

            [
                $upload,
                $download,
            ] =
                $this->parseBytesPair(
                    $row['bytes']
                    ?? '0/0'
                );

            $result[$name] = [
                'queue_id' =>
                    $row['.id']
                    ?? null,

                'upload_bytes' =>
                    $upload,

                'download_bytes' =>
                    $download,

                'disabled' =>
                    (
                        $row['disabled']
                        ?? 'false'
                    ) === 'true',

                'invalid' =>
                    (
                        $row['invalid']
                        ?? 'false'
                    ) === 'true',
            ];
        }

        return $result;
    }

    private function parseBytesPair(
        mixed $value
    ): array {
        $parts =
            explode(
                '/',
                (string) $value,
                2
            );

        $upload =
            isset($parts[0])
                ? max(
                    0,
                    (int) $parts[0]
                )
                : 0;

        $download =
            isset($parts[1])
                ? max(
                    0,
                    (int) $parts[1]
                )
                : 0;

        return [
            $upload,
            $download,
        ];
    }
}
