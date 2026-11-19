<?php

namespace App\Http\Controllers;

use App\Services\PengaturanRangkul;
use App\Services\Spk\KlienSpk;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Halaman depan publik SPK Rangkul (`/`). Seluruh teksnya diatur dari
 * Pengaturan → Halaman depan & kontak. Tidak memuat data anggota apa pun.
 */
class DepanController extends Controller
{
    public function __invoke(Request $request, PengaturanRangkul $pengaturan, KlienSpk $klien): View
    {
        return view('depan', [
            'isi' => $pengaturan->halamanDepan(),
            'kontak' => $pengaturan->kontak(),
            'alamatMasuk' => $klien->siap() ? $klien->alamatMasuk() : null,
            'pesanTutup' => $pengaturan->layananBuka() ? null : (string) $pengaturan->get('layanan.pesan_tutup'),
            'sudahMasuk' => $request->user() !== null,
        ]);
    }
}
