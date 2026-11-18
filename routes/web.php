<?php

use App\Http\Controllers\BerandaController;
use App\Http\Controllers\Kelola\AuditController;
use App\Http\Controllers\Kelola\DasborController;
use App\Http\Controllers\Kelola\PengaturanController;
use App\Http\Controllers\Kelola\PenggunaController;
use App\Http\Controllers\Kelola\PeranController;
use App\Http\Controllers\Kelola\RekapController;
use App\Http\Controllers\MasukController;
use App\Http\Controllers\SsoSpkController;
use App\Http\Middleware\PeriksaUlangAnggota;
use Illuminate\Support\Facades\Route;

/*
| Tidak ada rute pendaftaran, login ber-password, maupun reset password:
| anggota hanya masuk lewat token SSO dari spk.or.id.
*/

Route::get('/masuk', [MasukController::class, 'tampil'])->middleware('guest')->name('masuk');
Route::get('/sso/spk', SsoSpkController::class)->name('sso.spk');
Route::post('/keluar', [MasukController::class, 'keluar'])->middleware('auth')->name('keluar');

Route::middleware(['auth', PeriksaUlangAnggota::class])->group(function () {
    Route::get('/', BerandaController::class)->name('beranda');

    /*
    | Dashboard pengelola. Superadmin (Administrator di spk.or.id) memiliki
    | semua hak; pengguna lain hanya hak dari peran yang ditugaskan kepadanya.
    */
    Route::prefix('kelola')->name('kelola.')->middleware('can:dasbor.lihat')->group(function () {
        Route::get('/', DasborController::class)->name('dasbor');

        Route::middleware('can:pengaturan.umum')->group(function () {
            Route::get('pengaturan/layanan', [PengaturanController::class, 'layanan'])->name('pengaturan.layanan');
            Route::put('pengaturan/layanan', [PengaturanController::class, 'simpanLayanan']);
        });

        Route::middleware('can:pengaturan.halaman')->group(function () {
            Route::get('pengaturan/halaman', [PengaturanController::class, 'halaman'])->name('pengaturan.halaman');
            Route::put('pengaturan/halaman', [PengaturanController::class, 'simpanHalaman']);
        });

        Route::middleware('can:pengaturan.surel')->group(function () {
            Route::get('pengaturan/surel', [PengaturanController::class, 'surel'])->name('pengaturan.surel');
            Route::put('pengaturan/surel', [PengaturanController::class, 'simpanSurel']);
            Route::post('pengaturan/surel/uji', [PengaturanController::class, 'ujiSurel'])->name('pengaturan.surel.uji');
        });

        Route::middleware('can:pengaturan.whatsapp')->group(function () {
            Route::get('pengaturan/whatsapp', [PengaturanController::class, 'whatsapp'])->name('pengaturan.whatsapp');
            Route::put('pengaturan/whatsapp', [PengaturanController::class, 'simpanWhatsapp']);
            Route::post('pengaturan/whatsapp/uji', [PengaturanController::class, 'ujiWhatsapp'])->name('pengaturan.whatsapp.uji');
            Route::post('pengaturan/whatsapp/periksa', [PengaturanController::class, 'periksaWhatsapp'])->name('pengaturan.whatsapp.periksa');
        });

        Route::middleware('can:pengguna.lihat')->group(function () {
            Route::get('pengguna', [PenggunaController::class, 'index'])->name('pengguna.index');
            Route::get('pengguna/{pengguna}', [PenggunaController::class, 'tampil'])->whereNumber('pengguna')->name('pengguna.tampil');
        });

        Route::middleware('can:pengguna.tugaskan')->group(function () {
            Route::post('pengguna', [PenggunaController::class, 'tambah'])->name('pengguna.tambah');
            Route::post('pengguna/{pengguna}/peran', [PenggunaController::class, 'tugaskan'])->name('pengguna.tugaskan');
            Route::delete('pengguna/{pengguna}/peran/{peran}', [PenggunaController::class, 'cabut'])->name('pengguna.cabut');
        });

        Route::resource('peran', PeranController::class)->except('show')->middleware('can:peran.kelola');

        Route::get('audit', AuditController::class)->middleware('can:audit.lihat')->name('audit.index');

        Route::middleware('can:rekap.unduh')->group(function () {
            Route::get('rekap', [RekapController::class, 'index'])->name('rekap.index');
            Route::get('rekap/{jenis}', [RekapController::class, 'unduh'])->name('rekap.unduh');
        });
    });
});
