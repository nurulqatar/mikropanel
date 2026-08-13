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

/*
|--------------------------------------------------------------------------
| Automatic Monthly Billing
|--------------------------------------------------------------------------
|
| প্রথম install-এ default FALSE।
| Dry-run verify করার পরে setting enable করা হবে।
|
*/
Schedule::command(
    'billing:generate-monthly'
)
    ->hourlyAt(5)
    ->timezone('Asia/Qatar')
    ->withoutOverlapping(30)
    ->when(
        fn (): bool =>
            Setting::bool(
                'auto_billing_enabled',
                false
            )
    );

/*
|--------------------------------------------------------------------------
| Automatic Expiry Suspension
|--------------------------------------------------------------------------
*/
Schedule::command(
    'notifications:prepare-external'
)
    ->everyFiveMinutes()
    ->timezone('Asia/Qatar')
    ->withoutOverlapping(10);

Schedule::command(
    'notifications:generate-panel'
)
    ->everyFiveMinutes()
    ->timezone('Asia/Qatar')
    ->withoutOverlapping(10);

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

/*
|--------------------------------------------------------------------------
| Consolidated Router Telemetry
|--------------------------------------------------------------------------
|
| Client connection status and monthly Queue usage are
| collected by the routers:queue-health background job.
| This prevents separate DHCP and Queue RouterOS sessions
| every minute.
|
*/

/*
 * Multi-router convergence:
 * retries failed/offline routers and
 * automatically discovers newly added
 * enabled MikroTik routers.
 */
\Illuminate\Support\Facades\Schedule::command(
    'clients:sync-routers'
)
    ->everyMinute()
    ->timezone('Asia/Qatar')
    ->withoutOverlapping(10);

/* MIKROPANEL_ROUTER_BACKGROUND_SYNC_START */

/*
 * Background MikroTik health snapshot dispatcher.
 * The scheduler only queues jobs and never waits for RouterOS.
 */
Schedule::command(
    'routers:queue-health'
)
    ->everyMinute()
    ->timezone('Asia/Qatar')
    ->withoutOverlapping(2);

/* MIKROPANEL_ROUTER_BACKGROUND_SYNC_END */
