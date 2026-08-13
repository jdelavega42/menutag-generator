<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| WS-3 — scheduled maintenance (spec §5.1, §7.4; contract 05)
|--------------------------------------------------------------------------
| Guest retention hourly: the 24 h window (product.guests.retention_hours)
| is aligned with the signed-URL expiry, so an hourly sweep never deletes
| anything still reachable. Stuck-record recovery every five minutes: the
| threshold (product.engine.stuck_after_minutes = 15) stays far above the
| job timeout (120 s), so only genuinely dead runs are swept and the UI
| never polls a hung "processing" for long. withoutOverlapping() guards
| against a slow sweep (many files) piling onto the next tick.
*/
Schedule::command('menutag:prune-guests')->hourly()->withoutOverlapping();
Schedule::command('menutag:recover-stuck')->everyFiveMinutes()->withoutOverlapping();
