<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class PanelNotificationService
{
    private ?array $activeUserIds = null;

    public function sendToActiveUsers(
        string $fingerprint,
        string $kind,
        string $title,
        string $message,
        array $extra = [],
        bool $dryRun = false
    ): int {
        $deliveries = 0;

        foreach (
            $this->activeUserIds()
            as $userId
        ) {
            $id = $this->notificationId(
                $userId,
                $fingerprint
            );

            $exists = DB::table(
                'notifications'
            )
                ->where('id', $id)
                ->exists();

            if ($exists) {
                continue;
            }

            $deliveries++;

            if ($dryRun) {
                continue;
            }

            $now = now();

            DB::table('notifications')
                ->insertOrIgnore([
                    'id' => $id,

                    'type' =>
                        'panel.' . $kind,

                    'notifiable_type' =>
                        User::class,

                    'notifiable_id' =>
                        $userId,

                    'data' => json_encode(
                        [
                            'fingerprint' =>
                                $fingerprint,

                            'kind' => $kind,
                            'title' => $title,
                            'message' => $message,

                            ...$extra,
                        ],
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    ),

                    'read_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        return $deliveries;
    }

    private function activeUserIds(): array
    {
        if ($this->activeUserIds !== null) {
            return $this->activeUserIds;
        }

        return $this->activeUserIds =
            User::query()
                ->where(
                    'is_active',
                    true
                )
                ->orderBy('id')
                ->pluck('id')
                ->map(
                    fn ($id): int =>
                        (int) $id
                )
                ->all();
    }

    private function notificationId(
        int $userId,
        string $fingerprint
    ): string {
        /*
         * Deterministic ID makes every
         * user + event pair idempotent.
         */
        $hash = hash(
            'sha256',
            $userId
            . '|'
            . $fingerprint
        );

        return substr($hash, 0, 8)
            . '-'
            . substr($hash, 8, 4)
            . '-'
            . substr($hash, 12, 4)
            . '-'
            . substr($hash, 16, 4)
            . '-'
            . substr($hash, 20, 12);
    }
}
