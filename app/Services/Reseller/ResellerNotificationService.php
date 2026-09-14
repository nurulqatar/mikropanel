<?php

namespace App\Services\Reseller;

use App\Models\Reseller;
use App\Models\ResellerNotification;

class ResellerNotificationService
{
    public function unique(
        Reseller $reseller,
        string $type,
        string $level,
        string $title,
        string $message,
        string $dedupeKey,
        array $data = []
    ): ResellerNotification {
        return ResellerNotification::query()
            ->firstOrCreate(
                [
                    'dedupe_key' =>
                        $dedupeKey,
                ],
                [
                    'reseller_id' =>
                        $reseller->id,

                    'type' =>
                        $type,

                    'level' =>
                        $level,

                    'title' =>
                        $title,

                    'message' =>
                        $message,

                    'data' =>
                        $data,
                ]
            );
    }
}
