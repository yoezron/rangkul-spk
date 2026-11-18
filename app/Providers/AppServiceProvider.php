<?php

namespace App\Providers;

use App\Enums\Hak;
use App\Models\User;
use App\Services\PengaturanRangkul;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Satu gate per hak akses, dipakai lewat middleware `can:<hak>` dan @can.
        foreach (Hak::cases() as $hak) {
            Gate::define($hak->value, fn (User $pengguna): bool => $pengguna->punyaHak($hak));
        }

        // Pengaturan SMTP dari dashboard dipasang saat pengirim surel pertama
        // kali dibutuhkan, bukan di setiap permintaan.
        $this->app->afterResolving('mail.manager', function (): void {
            try {
                $this->app->make(PengaturanRangkul::class)->terapkanSurel();
            } catch (QueryException) {
                // Tabel pengaturan belum dimigrasi: pakai konfigurasi .env.
            }
        });
    }
}
