<?php

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command(
    'inspire',
    function (): void {
        $this->comment(
            Inspiring::quote()
        );
    }
)->purpose(
    'Display an inspiring quote'
);

Schedule::command(
    'clients:suspend-expired'
)
    ->everyMinute()
    ->withoutOverlapping(10)
    ->when(
        fn (): bool =>
            Setting::bool(
                'auto_suspend_enabled',
                true
            )
    );

Schedule::command(
    'clients:sync-connection'
)
    ->everyMinute()
    ->withoutOverlapping(5)
    ->when(
        fn (): bool =>
            Setting::bool(
                'connection_sync_enabled',
                true
            )
    );

Schedule::command(
    'clients:sync-usage'
)
    ->everyMinute()
    ->withoutOverlapping(10)
    ->when(
        fn (): bool =>
            Setting::bool(
                'usage_sync_enabled',
                true
            )
    );
