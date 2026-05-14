<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('helpdesk:rollup-analytics')->dailyAt('00:15');
Schedule::command('helpdesk:check-sla')->everyFiveMinutes();
Schedule::command('helpdesk:auto-close-idle --hours=24')->hourly();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
