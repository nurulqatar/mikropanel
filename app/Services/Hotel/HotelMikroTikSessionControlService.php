<?php

namespace App\Services\Hotel;

use App\Models\Hotel\HotelHotspotSession;
use App\Models\Hotel\HotelRouter;
use App\Models\Hotel\HotelVoucher;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class HotelMikroTikSessionControlService
{
    public function disconnect(
        HotelVoucher $voucher
    ): array {
        $result = [
            'routers' =>
                0,

            'disconnected' =>
                0,

            'failures' =>
                0,

            'errors' =>
                [],
        ];

        $routers =
            HotelRouter::query()
                ->where(
                    'hotel_id',
                    $voucher->hotel_id
                )
                ->where(
                    'enabled',
                    true
                )
                ->orderBy('id')
                ->get();

        foreach ($routers as $router) {
            $result['routers']++;

            try {
                $client =
                    $this->client(
                        $router
                    );

                $user =
                    $this->findUser(
                        $client,
                        $voucher->username
                    );

                /*
                 * Never kick a session belonging
                 * to a foreign/manual username.
                 */
                if (
                    $user
                    && !$this->ownsUser(
                        $user,
                        $voucher
                    )
                ) {
                    $result[
                        'failures'
                    ]++;

                    $result[
                        'errors'
                    ][] = [
                        'router_id' =>
                            $router->id,

                        'message' =>
                            'Safety collision: username is not owned by this Hotel voucher.',
                    ];

                    continue;
                }

                $query =
                    new Query(
                        '/ip/hotspot/active/print'
                    );

                $query->where(
                    'user',
                    $voucher->username
                );

                $rows =
                    $client
                        ->query($query)
                        ->read();

                foreach ($rows as $row) {
                    $id =
                        $row['.id']
                        ?? null;

                    if (!$id) {
                        continue;
                    }

                    $remove =
                        new Query(
                            '/ip/hotspot/active/remove'
                        );

                    $remove->equal(
                        '.id',
                        $id
                    );

                    $client
                        ->query($remove)
                        ->read();

                    $result[
                        'disconnected'
                    ]++;
                }

                HotelHotspotSession::query()
                    ->where(
                        'hotel_router_id',
                        $router->id
                    )
                    ->where(
                        'hotel_voucher_id',
                        $voucher->id
                    )
                    ->where(
                        'is_online',
                        true
                    )
                    ->update([
                        'is_online' =>
                            false,

                        'ended_at' =>
                            now(),

                        'last_seen_at' =>
                            now(),
                    ]);
            } catch (Throwable $e) {
                $result[
                    'failures'
                ]++;

                $result[
                    'errors'
                ][] = [
                    'router_id' =>
                        $router->id,

                    'message' =>
                        $this->safeMessage(
                            $e,
                            $router
                        ),
                ];
            }
        }

        return $result;
    }

    private function findUser(
        Client $client,
        string $username
    ): ?array {
        $query =
            new Query(
                '/ip/hotspot/user/print'
            );

        $query->where(
            'name',
            $username
        );

        $rows =
            $client
                ->query($query)
                ->read();

        return isset($rows[0])
            && is_array($rows[0])
                ? $rows[0]
                : null;
    }

    private function ownsUser(
        array $user,
        HotelVoucher $voucher
    ): bool {
        $comment =
            trim(
                (string) (
                    $user['comment']
                    ?? ''
                )
            );

        return str_starts_with(
            $comment,
            'MikroPanel Hotel Voucher #'
            . $voucher->id
        );
    }

    private function client(
        HotelRouter $router
    ): Client {
        return new Client(
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
            ])
        );
    }

    private function safeMessage(
        Throwable $e,
        HotelRouter $router
    ): string {
        $message =
            trim(
                $e->getMessage()
            );

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
            $message !== ''
                ? $message
                : 'Disconnect failed.',
            0,
            500
        );
    }
}
