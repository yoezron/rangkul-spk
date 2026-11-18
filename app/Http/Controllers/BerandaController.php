<?php

namespace App\Http\Controllers;

use App\Enums\StatusPermintaan;
use App\Models\Pesan;
use App\Services\PengaturanRangkul;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BerandaController extends Controller
{
    public function __invoke(Request $request, PengaturanRangkul $pengaturan): View
    {
        $pengguna = $request->user();
        $terbuka = $pengguna->permintaan()
            ->whereNotIn('status', [StatusPermintaan::Selesai->value, StatusPermintaan::Ditolak->value, StatusPermintaan::Dibatalkan->value])
            ->latest('id')
            ->first();

        return view('beranda', [
            'anggota' => $pengguna,
            'pesanTutup' => $pengaturan->layananBuka() ? null : (string) $pengaturan->get('layanan.pesan_tutup'),
            'kontak' => $pengaturan->kontak(),
            'permintaanTerbuka' => $terbuka,
            'pesanBelumDibaca' => $terbuka === null ? 0 : Pesan::query()
                ->where('permintaan_id', $terbuka->id)
                ->where('jenis', 'pesan')
                ->where('pengirim_id', '!=', $pengguna->id)
                ->whereNull('dibaca_pada')
                ->count(),
            'kasusAktif' => $pengguna->kasusDampingan()
                ->whereIn('status', [StatusPermintaan::Berlangsung->value, StatusPermintaan::MenungguPenutupan->value])
                ->count(),
        ]);
    }
}
