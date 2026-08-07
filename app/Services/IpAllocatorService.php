<?php

namespace App\Services;

use App\Models\Client;
use App\Models\IpRange;
use Illuminate\Support\Facades\Log;

class IpAllocatorService
{
    public function allocate(IpRange $range): ?string
    {
        $start = ip2long($range->start_ip);
        $end   = ip2long($range->end_ip);

        for ($ip = $start; $ip <= $end; $ip++) {

            $address = long2ip($ip);

            $exists = Client::where('ip_address', $address)
                ->exists();

            if (!$exists) {
                return $address;
            }
        }

        Log::warning(
            "No free IP available in range: {$range->name}"
        );

        return null;
    }
}
