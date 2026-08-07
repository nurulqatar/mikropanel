<?php

namespace App\Services;

use App\Models\Client;
use App\Models\IpRange;
use Illuminate\Support\Facades\Log;

class IpAllocatorService
{
    public function allocate(
        IpRange $range
    ): ?string {
        $start = ip2long(
            $range->start_ip
        );

        $end = ip2long(
            $range->end_ip
        );

        if (
            $start === false
            || $end === false
            || $start > $end
        ) {
            Log::error(
                'Invalid IP Range boundaries.',
                [
                    'ip_range_id' =>
                        $range->id,
                ]
            );

            return null;
        }

        $gateway = ip2long(
            (string) $range->gateway
        );

        [
            $network,
            $broadcast,
        ] = $this->networkBounds(
            (string) $range->network
        );

        for (
            $ip = $start;
            $ip <= $end;
            $ip++
        ) {
            /*
             * Never allocate gateway,
             * network address or broadcast.
             */
            if (
                $ip === $gateway
                || $ip === $network
                || $ip === $broadcast
            ) {
                continue;
            }

            /*
             * Range must remain inside
             * the configured CIDR.
             */
            if (
                $network !== null
                && $broadcast !== null
                && (
                    $ip < $network
                    || $ip > $broadcast
                )
            ) {
                continue;
            }

            $address = long2ip($ip);

            $exists = Client::query()
                ->where(
                    'ip_address',
                    $address
                )
                ->exists();

            if (!$exists) {
                return $address;
            }
        }

        Log::warning(
            'No free IP available in range.',
            [
                'ip_range_id' =>
                    $range->id,

                'range_name' =>
                    $range->name,
            ]
        );

        return null;
    }

    private function networkBounds(
        string $cidr
    ): array {
        if (
            !str_contains(
                $cidr,
                '/'
            )
        ) {
            return [
                null,
                null,
            ];
        }

        [
            $address,
            $prefix,
        ] = explode(
            '/',
            $cidr,
            2
        );

        $addressLong =
            ip2long($address);

        $prefix = filter_var(
            $prefix,
            FILTER_VALIDATE_INT
        );

        if (
            $addressLong === false
            || $prefix === false
            || $prefix < 0
            || $prefix > 32
        ) {
            return [
                null,
                null,
            ];
        }

        $mask = $prefix === 0
            ? 0
            : (
                0xFFFFFFFF
                << (32 - $prefix)
            ) & 0xFFFFFFFF;

        $network =
            $addressLong & $mask;

        $broadcast =
            $network
            | (
                (~$mask)
                & 0xFFFFFFFF
            );

        return [
            $network,
            $broadcast,
        ];
    }
}
