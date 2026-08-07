<?php

namespace App\Console\Commands;

use App\Models\NotificationDeliveryLog;
use App\Services\ExternalNotificationDeliveryService;
use Illuminate\Console\Command;

class NotificationDeliveryStatus extends Command
{
    protected $signature =
        'notifications:delivery-status';

    protected $description =
        'Show external notification delivery readiness without exposing secrets';

    public function handle(
        ExternalNotificationDeliveryService $delivery
    ): int {
        $channels =
            $delivery->channelStatus();

        $this->table(
            [
                'Channel',
                'Enabled',
                'Configured',
            ],
            collect($channels)
                ->map(
                    fn (
                        array $state,
                        string $channel
                    ): array => [
                        strtoupper(
                            $channel
                        ),

                        $state['enabled']
                            ? 'YES'
                            : 'NO',

                        $state['configured']
                            ? 'YES'
                            : 'NO',
                    ]
                )
                ->values()
                ->all()
        );

        $this->newLine();

        $counts =
            NotificationDeliveryLog::query()
                ->selectRaw(
                    'channel, status, COUNT(*) AS total'
                )
                ->groupBy(
                    'channel',
                    'status'
                )
                ->orderBy(
                    'channel'
                )
                ->orderBy(
                    'status'
                )
                ->get();

        if ($counts->isEmpty()) {
            $this->info(
                'No external delivery records yet.'
            );

            return self::SUCCESS;
        }

        $this->table(
            [
                'Channel',
                'Status',
                'Count',
            ],
            $counts
                ->map(
                    fn ($row): array => [
                        strtoupper(
                            $row->channel
                        ),

                        $row->status,

                        (int) $row->total,
                    ]
                )
                ->all()
        );

        return self::SUCCESS;
    }
}
