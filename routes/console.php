<?php

use Illuminate\Support\Facades\Schedule;

/*
| Butuh penjadwal Laravel yang berjalan di server (cron tiap menit):
|   * * * * * cd /jalur/ke/rangkul && php artisan schedule:run >> /dev/null 2>&1
|
| Tanpa cron ini: notifikasi surel/WhatsApp tidak pernah terkirim (diantrekan),
| pengingat 24 jam tidak berjalan, dan audit log tidak dipangkas.
*/

// Notifikasi diantrekan supaya halaman tidak menunggu SMTP dan Fonnte. Di
// hosting bersama tidak ada pekerja antrean yang hidup terus, jadi antrean
// dikosongkan tiap menit lalu berhenti.
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=1')
    ->everyMinute()
    ->withoutOverlapping(5);

Schedule::command('rangkul:ingatkan-pesan')->hourly()->withoutOverlapping();

Schedule::command('rangkul:pangkas-audit')->dailyAt('02:30');
