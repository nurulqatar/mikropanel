<?php

namespace App\Services\Hotel;

use App\Models\Hotel\HotelRouter;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class HotelMikroTikService
{
    public function test(
        HotelRouter $router
    ): array {
        try {
            $client =
                $this->client(
                    $router
                );

            /*
             * Read-only RouterOS requests.
             */
            $identityRows =
                $client
                    ->query(
                        new Query(
                            '/system/identity/print'
                        )
                    )
                    ->read();

            $resourceRows =
                $client
                    ->query(
                        new Query(
                            '/system/resource/print'
                        )
                    )
                    ->read();

            $identity =
                $identityRows[0]
                ?? [];

            $resource =
                $resourceRows[0]
                ?? [];

            return [
                'success' =>
                    true,

                'identity' =>
                    $identity['name']
                    ?? null,

                'version' =>
                    $resource['version']
                    ?? null,

                'architecture' =>
                    $resource[
                        'architecture-name'
                    ]
                    ?? null,

                'uptime' =>
                    $resource['uptime']
                    ?? null,

                'cpu_load' =>
                    $resource['cpu-load']
                    ?? null,

                'free_memory' =>
                    $resource[
                        'free-memory'
                    ]
                    ?? null,
            ];
        } catch (Throwable $e) {
            return [
                'success' =>
                    false,

                'message' =>
                    $this->safeMessage(
                        $e,
                        $router
                    ),
            ];
        }
    }

    private function client(
        HotelRouter $router
    ): Client {
        $config =
            new Config([
                'host' =>
                    $router->host,

                'user' =>
                    $router->username,

                'pass' =>
                    $router->password,

                'port' =>
                    $router->api_port,

                'ssl' =>
                    (bool)
                    $router->use_ssl,

                'timeout' =>
                    5,

                'socket_timeout' =>
                    5,

                'attempts' =>
                    1,

                'delay' =>
                    0,
            ]);

        return new Client(
            $config
        );
    }

    private function safeMessage(
        Throwable $exception,
        HotelRouter $router
    ): string {
        $message =
            trim(
                $exception
                    ->getMessage()
            );

        if ($message === '') {
            return 'Unable to connect to MikroTik API.';
        }

        $password =
            (string)
            $router->password;

        if ($password !== '') {
            $message =
                str_replace(
                    $password,
                    '[hidden]',
                    $message
                );
        }

        return mb_substr(
            $message,
            0,
            500
        );
    }
}
