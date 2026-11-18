<?php

use Illuminate\Support\Facades\Schedule;

/*
| Butuh penjadwal Laravel yang berjalan di server (cron tiap menit):
|   * * * * * cd /jalur/ke/rangkul && php artisan schedule:run >> /dev/null 2>&1
*/

Schedule::command('rangkul:pangkas-audit')->dailyAt('02:30');
