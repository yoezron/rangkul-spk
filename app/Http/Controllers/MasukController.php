<?php

namespace App\Http\Controllers;

use App\Services\PengaturanRangkul;
use App\Services\Spk\KlienSpk;
use App\Services\Spk\SesiRangkul;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Halaman masuk dan keluar. Tidak ada formulir password maupun pendaftaran:
 * satu-satunya jalan masuk adalah akun spk.or.id.
 */
class MasukController extends Controller
{
    /**
     * Penjelasan bagi yang ditolak, per kode `alasan`.
     *
     * Empat yang pertama adalah kode dari kontrak spk.or.id; sisanya kode
     * internal Rangkul. Rincian teknis tidak pernah ditampilkan.
     *
     * @var array<string, string>
     */
    private const PENJELASAN = [
        'belum_terkonfirmasi' => 'Akun Anda masih berstatus Calon Anggota. SPK Rangkul hanya terbuka bagi anggota yang keanggotaannya sudah dikonfirmasi pengurus.',
        'tidak_aktif' => 'Akun SPK Anda sedang tidak aktif, sehingga belum dapat memakai SPK Rangkul. Silakan hubungi pengurus untuk mengaktifkannya kembali.',
        'wajib_ganti_password' => 'Akun Anda diwajibkan mengganti password di spk.or.id lebih dulu. Setelah password diganti, silakan masuk kembali ke SPK Rangkul.',
        'tidak_ditemukan' => 'Akun Anda tidak ditemukan di spk.or.id. Silakan masuk ulang di spk.or.id, lalu coba lagi.',
        'tautan_tidak_sah' => 'Tautan masuk tidak berlaku atau sudah kedaluwarsa. Tautan hanya berlaku dua menit dan sekali pakai — silakan masuk lagi lewat tombol di bawah.',
        'sesi_berakhir' => 'Sesi Anda sudah berakhir. Silakan masuk kembali.',
        'gangguan' => 'Kami belum dapat memeriksa keanggotaan Anda karena spk.or.id sedang tidak dapat dihubungi. Silakan coba lagi beberapa saat lagi.',
        'terlalu_sering' => 'Terlalu banyak percobaan masuk yang gagal. Silakan tunggu satu menit, lalu coba lagi.',
    ];

    private const PENJELASAN_UMUM = 'Anda belum dapat masuk ke SPK Rangkul saat ini. Silakan coba lagi, atau hubungi pengurus bila terus terjadi.';

    /**
     * Halaman masuk sekaligus halaman depan; isinya diatur dari dashboard.
     */
    public function tampil(Request $request, KlienSpk $klien, PengaturanRangkul $pengaturan): View
    {
        $alasan = $request->session()->get('alasan');

        return view('masuk', [
            'penjelasan' => is_string($alasan) ? (self::PENJELASAN[$alasan] ?? self::PENJELASAN_UMUM) : null,
            'sudahKeluar' => $request->session()->get('keluar') === true,
            'alamatMasuk' => $klien->siap() ? $klien->alamatMasuk() : null,
            'halaman' => $pengaturan->halaman(),
            'kontak' => $pengaturan->kontak(),
            'pesanTutup' => $pengaturan->layananBuka() ? null : (string) $pengaturan->get('layanan.pesan_tutup'),
        ]);
    }

    /**
     * Keluar dari Rangkul. Sesi di spk.or.id tidak ikut berakhir.
     */
    public function keluar(Request $request, SesiRangkul $sesi): RedirectResponse
    {
        $sesi->putus($request);

        return redirect()->route('masuk')->with('keluar', true);
    }
}
