<?php

namespace App\Services\Spk;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Memulai dan memutus sesi Rangkul, beserta penanda waktunya.
 */
class SesiRangkul
{
    /** Detik Unix (jam spk.or.id) saat token masuk sesi ini diterbitkan. */
    public const KUNCI_MULAI = 'sso.mulai';

    /** Detik Unix (jam Rangkul) pemeriksaan keanggotaan terakhir yang berhasil. */
    public const KUNCI_DIPERIKSA = 'sso.diperiksa_pada';

    /** Detik Unix (jam Rangkul) percobaan terakhir yang gagal karena gangguan. */
    public const KUNCI_DICOBA = 'sso.dicoba_pada';

    /**
     * Masukkan anggota dengan sesi yang benar-benar baru.
     *
     * Isi sesi lama dibuang dan id sesinya diganti, supaya sesi yang dibuka
     * sebelum login (atau milik anggota lain di peramban yang sama) tidak
     * terbawa. Tanpa "ingat saya": sesi berakhir bersama masa hidup sesinya.
     *
     * @param  int  $mulai  `iat` token masuk — dibandingkan dengan `sesi_valid_sejak`
     */
    public function mulai(Request $request, User $anggota, int $mulai): void
    {
        $request->session()->invalidate();

        Auth::guard('web')->login($anggota);

        $request->session()->regenerateToken();
        $request->session()->put([
            self::KUNCI_MULAI => $mulai,
            self::KUNCI_DIPERIKSA => now()->timestamp,
        ]);
    }

    /**
     * Akhiri sesi Rangkul di peramban ini dan buang seluruh isinya.
     */
    public function putus(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
