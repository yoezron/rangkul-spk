<?php

use App\Http\Controllers\Anggota\PermintaanController as PermintaanAnggotaController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\DepanController;
use App\Http\Controllers\Kasus\CatatanSesiController;
use App\Http\Controllers\Kasus\KirimanInstrumenController;
use App\Http\Controllers\Kasus\PenutupanController;
use App\Http\Controllers\Kasus\PercakapanController;
use App\Http\Controllers\Kasus\PertemuanController;
use App\Http\Controllers\Kelola\AuditController;
use App\Http\Controllers\Kelola\DasborController;
use App\Http\Controllers\Kelola\InstrumenController;
use App\Http\Controllers\Kelola\PengaturanController;
use App\Http\Controllers\Kelola\PenggunaController;
use App\Http\Controllers\Kelola\PeranController;
use App\Http\Controllers\Kelola\PermintaanController as PermintaanKelolaController;
use App\Http\Controllers\Kelola\RekapController;
use App\Http\Controllers\Kelola\WaktuLayananController;
use App\Http\Controllers\Konseling\KasusController;
use App\Http\Controllers\Konseling\StatistikController;
use App\Http\Controllers\MasukController;
use App\Http\Controllers\SsoSpkController;
use App\Http\Middleware\PeriksaUlangAnggota;
use Illuminate\Support\Facades\Route;

/*
| Tidak ada rute pendaftaran, login ber-password, maupun reset password:
| anggota hanya masuk lewat token SSO dari spk.or.id.
*/

Route::get('/', DepanController::class)->name('depan');
Route::get('/masuk', [MasukController::class, 'tampil'])->middleware('guest')->name('masuk');
Route::get('/sso/spk', SsoSpkController::class)->name('sso.spk');
Route::post('/keluar', [MasukController::class, 'keluar'])->middleware('auth')->name('keluar');

Route::middleware(['auth', PeriksaUlangAnggota::class])->group(function () {
    Route::get('/beranda', BerandaController::class)->name('beranda');

    /*
    | Anggota: pengajuan dan permintaannya sendiri (diperiksa PermintaanPolicy).
    */
    Route::controller(PermintaanAnggotaController::class)->group(function () {
        Route::get('permintaan', 'index')->name('permintaan.index');
        Route::post('permintaan', 'mulai')->name('permintaan.mulai');
        Route::get('permintaan/{permintaan}', 'tampil')->name('permintaan.tampil');
        Route::get('permintaan/{permintaan}/langkah/{langkah}', 'langkah')->whereNumber('langkah')->name('permintaan.langkah');
        Route::put('permintaan/{permintaan}/langkah/{langkah}', 'simpanLangkah')->whereNumber('langkah')->name('permintaan.simpan');
        Route::post('permintaan/{permintaan}/kirim', 'kirim')->name('permintaan.kirim');
        Route::post('permintaan/{permintaan}/batalkan', 'batalkan')->name('permintaan.batalkan');
    });

    /*
    | Tindakan pada satu kasus, dipakai anggota, konselor, dan komite.
    | Siapa boleh apa ditentukan PermintaanPolicy di tiap kontroler.
    */
    Route::prefix('kasus/{permintaan}')->group(function () {
        Route::get('pesan', [PercakapanController::class, 'daftar'])->name('percakapan.daftar');
        Route::post('pesan', [PercakapanController::class, 'kirim'])->middleware('throttle:30,1')->name('percakapan.kirim');
        Route::post('pertemuan', [PertemuanController::class, 'usul'])->name('pertemuan.usul');
        Route::post('pertemuan/{pertemuan}/tanggapi', [PertemuanController::class, 'tanggapi'])->name('pertemuan.tanggapi');
        Route::post('pertemuan/{pertemuan}/putuskan', [PertemuanController::class, 'putuskan'])->name('pertemuan.putuskan');
        Route::put('pertemuan/{pertemuan}/tautan', [PertemuanController::class, 'tautan'])->name('pertemuan.tautan');
        Route::post('pertemuan/{pertemuan}/batal', [PertemuanController::class, 'batal'])->name('pertemuan.batal');
        Route::post('penutupan', [PenutupanController::class, 'usul'])->name('penutupan.usul');
        Route::post('penutupan/tanggapi', [PenutupanController::class, 'tanggapi'])->name('penutupan.tanggapi');
        Route::post('penutupan/putuskan', [PenutupanController::class, 'putuskan'])->name('penutupan.putuskan');
        Route::post('penutupan/tutup', [PenutupanController::class, 'tutup'])->name('penutupan.tutup');
        Route::post('buka-kembali', [PenutupanController::class, 'bukaKembali'])->name('penutupan.buka-kembali');
        Route::post('instrumen', [KirimanInstrumenController::class, 'kirim'])->name('instrumen-kasus.kirim');
        Route::get('instrumen/{kiriman}', [KirimanInstrumenController::class, 'isi'])->name('instrumen-kasus.isi');
        Route::post('instrumen/{kiriman}', [KirimanInstrumenController::class, 'simpan'])->name('instrumen-kasus.simpan');
        Route::post('instrumen/{kiriman}/batal', [KirimanInstrumenController::class, 'batal'])->name('instrumen-kasus.batal');
        Route::post('catatan', [CatatanSesiController::class, 'simpan'])->name('catatan.simpan');
    });

    /*
    | Peer konselor: kasus yang ditugaskan kepadanya.
    */
    Route::middleware('can:konseling.tangani')->group(function () {
        Route::get('konseling', [KasusController::class, 'index'])->name('konseling.index');
        Route::get('konseling/statistik', StatistikController::class)->name('konseling.statistik');
        Route::get('konseling/{permintaan}', [KasusController::class, 'tampil'])->name('konseling.tampil');
    });

    /*
    | Dashboard pengelola. Superadmin (Administrator di spk.or.id) memiliki
    | semua hak; pengguna lain hanya hak dari peran yang ditugaskan kepadanya.
    */
    Route::prefix('kelola')->name('kelola.')->middleware('can:dasbor.lihat')->group(function () {
        Route::get('/', DasborController::class)->name('dasbor');

        Route::middleware('can:permintaan.kelola')->controller(PermintaanKelolaController::class)->group(function () {
            Route::get('permintaan', 'index')->name('permintaan.index');
            Route::get('permintaan/{permintaan}', 'tampil')->name('permintaan.tampil');
            Route::post('permintaan/{permintaan}/tinjau', 'tinjau')->name('permintaan.tinjau');
            Route::post('permintaan/{permintaan}/tugaskan', 'tugaskan')->name('permintaan.tugaskan');
            Route::post('permintaan/{permintaan}/tolak', 'tolak')->name('permintaan.tolak');
            Route::post('permintaan/{permintaan}/darurat', 'darurat')->name('permintaan.darurat');
            Route::post('permintaan/{permintaan}/catatan', 'catatan')->name('permintaan.catatan');
        });

        Route::middleware('can:asesmen.kelola')->group(function () {
            Route::resource('instrumen', InstrumenController::class)->except('show')->parameters(['instrumen' => 'instrumen']);
            Route::post('instrumen/{instrumen}/aktif', [InstrumenController::class, 'aktif'])->name('instrumen.aktif');
        });

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
            Route::get('pengguna/cari-spk', [PenggunaController::class, 'cariSpk'])->middleware('throttle:30,1')->name('pengguna.cari-spk');
            Route::post('pengguna', [PenggunaController::class, 'tambah'])->name('pengguna.tambah');
            Route::post('pengguna/{pengguna}/peran', [PenggunaController::class, 'tugaskan'])->name('pengguna.tugaskan');
            Route::delete('pengguna/{pengguna}/peran/{peran}', [PenggunaController::class, 'cabut'])->name('pengguna.cabut');
        });

        Route::resource('peran', PeranController::class)->except('show')->middleware('can:peran.kelola');

        Route::get('audit', AuditController::class)->middleware('can:audit.lihat')->name('audit.index');

        // Hak per jenis rekap diperiksa di RekapController.
        Route::get('rekap', [RekapController::class, 'index'])->name('rekap.index');
        Route::get('rekap/{jenis}', [RekapController::class, 'unduh'])->name('rekap.unduh');

        Route::middleware('can:waktu_layanan.rekap')->group(function () {
            Route::get('waktu-layanan', [WaktuLayananController::class, 'index'])->name('waktu-layanan.index');
            Route::get('waktu-layanan/unduh', [WaktuLayananController::class, 'unduh'])->name('waktu-layanan.unduh');
        });
    });
});
