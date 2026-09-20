<?php

namespace App\Services\Hotel;

use App\Models\Hotel\HotelRouter;
use App\Models\Hotel\HotelVoucher;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use Throwable;

class HotelMikroTikVoucherService
{
    public function upsert(
        HotelRouter $router,
        HotelVoucher $voucher
    ): array {
        if (
            $voucher->trashed()
            || in_array(
                $voucher->status,
                [
                    'expired',
                    'revoked',
                ],
                true
            )
            || (
                $voucher->expires_at
                && $voucher
                    ->expires_at
                    ->lte(now())
            )
        ) {
            return $this->expire(
                $router,
                $voucher
            );
        }

        try {
            $client =
                $this->client(
                    $router
                );

            $existing =
                $this->findUser(
                    $client,
                    $voucher->username
                );

            /*
             * HOTEL_ROUTEROS_OWNERSHIP_GUARD_V1
             *
             * Never overwrite a manually created
             * RouterOS user that only happens to
             * use the same username.
             */
            if (
                $existing
                && !$this->ownsUser(
                    $existing,
                    $voucher
                )
            ) {
                return $this->collision(
                    'upsert',
                    $router,
                    $voucher
                );
            }

            $fields =
                $this->userFields(
                    $router,
                    $voucher
                );

            if ($existing) {
                $query =
                    new Query(
                        '/ip/hotspot/user/set'
                    );

                $query->equal(
                    '.id',
                    (string)
                    $existing['.id']
                );

                foreach (
                    $fields
                    as $key => $value
                ) {
                    $query->equal(
                        $key,
                        $value
                    );
                }

                $client
                    ->query($query)
                    ->read();

                $routerUserId =
                    (string)
                    $existing['.id'];
            } else {
                $query =
                    new Query(
                        '/ip/hotspot/user/add'
                    );

                foreach (
                    $fields
                    as $key => $value
                ) {
                    $query->equal(
                        $key,
                        $value
                    );
                }

                $result =
                    $client
                        ->query($query)
                        ->read();

                $routerUserId =
                    isset(
                        $result['after']['ret']
                    )
                        ? (string)
                            $result['after']['ret']
                        : null;

                if (!$routerUserId) {
                    $created =
                        $this->findUser(
                            $client,
                            $voucher->username
                        );

                    if (
                        $created
                        && !$this->ownsUser(
                            $created,
                            $voucher
                        )
                    ) {
                        return $this->collision(
                            'upsert',
                            $router,
                            $voucher
                        );
                    }

                    $routerUserId =
                        isset(
                            $created['.id']
                        )
                            ? (string)
                                $created['.id']
                            : null;
                }
            }

            return [
                'success' =>
                    true,

                'operation' =>
                    'upsert',

                'router_user_id' =>
                    $routerUserId,

                'message' =>
                    null,
            ];
        } catch (Throwable $e) {
            return [
                'success' =>
                    false,

                'operation' =>
                    'upsert',

                'router_user_id' =>
                    null,

                'message' =>
                    $this->safeMessage(
                        $e,
                        $router
                    ),
            ];
        }
    }

    public function expire(
        HotelRouter $router,
        HotelVoucher $voucher
    ): array {
        try {
            $client =
                $this->client(
                    $router
                );

            $existing =
                $this->findUser(
                    $client,
                    $voucher->username
                );

            /*
             * A foreign/manual username collision
             * must not result in session, cookie,
             * or user deletion.
             */
            if (
                $existing
                && !$this->ownsUser(
                    $existing,
                    $voucher
                )
            ) {
                return $this->collision(
                    'expire',
                    $router,
                    $voucher
                );
            }

            $routerUserId =
                isset(
                    $existing['.id']
                )
                    ? (string)
                        $existing['.id']
                    : null;

            $this->disconnectActiveSessions(
                $client,
                $voucher->username
            );

            /*
             * HOTEL_HOTSPOT_COOKIE_CLEANUP_V1
             */
            $this->removeCookies(
                $client,
                $voucher->username
            );

            if ($routerUserId) {
                $query =
                    new Query(
                        '/ip/hotspot/user/remove'
                    );

                $query->equal(
                    '.id',
                    $routerUserId
                );

                $client
                    ->query($query)
                    ->read();
            }

            return [
                'success' =>
                    true,

                'operation' =>
                    'expire',

                'router_user_id' =>
                    $routerUserId,

                'message' =>
                    null,
            ];
        } catch (Throwable $e) {
            return [
                'success' =>
                    false,

                'operation' =>
                    'expire',

                'router_user_id' =>
                    null,

                'message' =>
                    $this->safeMessage(
                        $e,
                        $router
                    ),
            ];
        }
    }

    private function userFields(
        HotelRouter $router,
        HotelVoucher $voucher
    ): array {
        $voucher->loadMissing(
            'profile'
        );

        $dataLimitMb =
            (int) (
                $voucher
                    ->profile
                    ?->data_limit_mb
                ?? 0
            );

        return [
            'server' =>
                $router
                    ->hotspot_server_name,

            'name' =>
                $voucher->username,

            'password' =>
                (string)
                $voucher->password,

            'profile' =>
                $router
                    ->hotspot_profile_name,

            'comment' =>
                $this->comment(
                    $voucher
                ),

            'limit-bytes-total' =>
                $dataLimitMb > 0
                    ? (string)
                        (
                            $dataLimitMb
                            * 1024
                            * 1024
                        )
                    : '0',
        ];
    }

    private function comment(
        HotelVoucher $voucher
    ): string {
        $expires =
            $voucher
                ->expires_at
                ?->utc()
                ->format(
                    'Y-m-d H:i:s'
                )
            ?? 'none';

        return mb_substr(
            $this->ownershipPrefix(
                $voucher
            )
            . ' expires='
            . $expires
            . ' UTC',
            0,
            255
        );
    }

    private function ownershipPrefix(
        HotelVoucher $voucher
    ): string {
        return
            'MikroPanel Hotel Voucher #'
            . $voucher->id;
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
            $this->ownershipPrefix(
                $voucher
            )
        );
    }

    private function collision(
        string $operation,
        HotelRouter $router,
        HotelVoucher $voucher
    ): array {
        return [
            'success' =>
                false,

            'operation' =>
                $operation,

            'router_user_id' =>
                null,

            'message' =>
                'Safety collision on router '
                . $router->name
                . ': Hotspot username "'
                . $voucher->username
                . '" already exists but is not owned by MikroPanel Hotel Voucher #'
                . $voucher->id
                . '. No RouterOS user/session/cookie was changed.',
        ];
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

    private function disconnectActiveSessions(
        Client $client,
        string $username
    ): void {
        $query =
            new Query(
                '/ip/hotspot/active/print'
            );

        $query->where(
            'user',
            $username
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
                (string)
                $id
            );

            $client
                ->query($remove)
                ->read();
        }
    }

    private function removeCookies(
        Client $client,
        string $username
    ): void {
        $query =
            new Query(
                '/ip/hotspot/cookie/print'
            );

        $query->where(
            'user',
            $username
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
                    '/ip/hotspot/cookie/remove'
                );

            $remove->equal(
                '.id',
                (string)
                $id
            );

            $client
                ->query($remove)
                ->read();
        }
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
        Throwable $exception,
        HotelRouter $router
    ): string {
        $message =
            trim(
                $exception
                    ->getMessage()
            );

        if ($message === '') {
            return
                'MikroTik voucher synchronization failed.';
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
