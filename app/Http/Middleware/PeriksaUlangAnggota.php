<?php

namespace App\Http\Middleware;

use App\Exceptions\PermintaanSpkGagal;
use App\Models\User;
use App\Services\Spk\KlienSpk;
use App\Services\Spk\SesiRangkul;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memeriksa ulang keanggotaan ke spk.or.id selama sesi berjalan.
 *
 * spk.or.id tidak punya cara memutus sesi di Rangkul. Karena itu paling lama
 * tiap 15 menit Rangkul sendiri yang bertanya, dan memutus sesi bila:
 *
 *  - `berhak` sudah false (dinonaktifkan, wajib ganti password, dsb.), atau
 *  - sesi ini dimulai sebelum `sesi_valid_sejak` ("keluarkan semua sesi").
 *
 * BILA spk.or.id TIDAK DAPAT DIHUBUNGI — keputusan yang disengaja:
 *
 *  1. Gangguan sementara (jaringan putus, waktu habis, galat 5xx, jawaban
 *     rusak): sesi DIPERTAHANKAN, tetapi hanya sampai 30 menit sejak
 *     pemeriksaan terakhir yang berhasil. Sesudah itu sesi diputus.
 *     Alasannya: orang yang sedang menulis cerita berat tidak sepatutnya
 *     terlempar keluar hanya karena situs induk tersendat sebentar; dan selama
 *     spk.or.id mati, pengurus pun tidak sedang bisa menonaktifkan siapa pun
 *     di sana. Namun jendelanya harus pendek dan pasti — endpoint ini
 *     satu-satunya jalan pencabutan akses, jadi sesi yang tidak bisa
 *     dipastikan haknya tidak boleh hidup tanpa batas. Selama masa tenggang
 *     percobaan diulang paling sering sekali per menit supaya tiap halaman
 *     tidak ikut menunggu batas waktu jaringan.
 *
 *  2. Penolakan tegas (401 tanda tangan, 503 `belum_diatur`): sesi DIPUTUS
 *     SEKETIKA, tanpa masa tenggang. Itu bukan gangguan: rahasia bersama
 *     sudah diganti atau integrasi sengaja dimatikan di spk.or.id (cara resmi
 *     mematikan Rangkul menurut panduan), dan keduanya harus langsung berlaku.
 */
class PeriksaUlangAnggota
{
    /** Selang terlama antara dua pemeriksaan yang berhasil. */
    public const SELANG_DETIK = 15 * 60;

    /** Umur terlama pemeriksaan terakhir yang berhasil selagi spk.or.id terganggu. */
    public const TENGGANG_DETIK = 30 * 60;

    /** Jeda antar percobaan ulang selama masa tenggang. */
    public const JEDA_COBA_DETIK = 60;

    public function __construct(
        private readonly KlienSpk $klien,
        private readonly SesiRangkul $sesi,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $anggota = $request->user();
        $mulai = $request->session()->get(SesiRangkul::KUNCI_MULAI);
        $diperiksa = $request->session()->get(SesiRangkul::KUNCI_DIPERIKSA);

        // Sesi yang tidak lahir dari pintu SSO tidak punya dasar untuk dipercaya.
        if (! $anggota instanceof User || ! is_int($mulai) || ! is_int($diperiksa)) {
            return $this->putus($request, 'sesi_berakhir');
        }

        $kini = now()->timestamp;
        $umur = $kini - $diperiksa;

        if ($umur >= 0 && $umur < self::SELANG_DETIK) {
            return $next($request);
        }

        $dalamTenggang = $umur >= 0 && $umur <= self::TENGGANG_DETIK;
        $dicoba = $request->session()->get(SesiRangkul::KUNCI_DICOBA);

        if ($dalamTenggang && is_int($dicoba) && $kini - $dicoba >= 0 && $kini - $dicoba < self::JEDA_COBA_DETIK) {
            return $next($request);
        }

        try {
            $jawaban = $this->klien->anggota($anggota->spk_id);
        } catch (PermintaanSpkGagal $galat) {
            if ($galat->ditolak || ! $dalamTenggang) {
                return $this->putus($request, 'gangguan');
            }

            $request->session()->put(SesiRangkul::KUNCI_DICOBA, $kini);

            return $next($request);
        }

        if (! $jawaban->berhak) {
            return $this->putus($request, $jawaban->alasan);
        }

        if ($jawaban->sesiValidSejak > 0 && $mulai < $jawaban->sesiValidSejak) {
            return $this->putus($request, 'sesi_berakhir');
        }

        $anggota->perbaruiDariSpk($jawaban);

        $request->session()->put(SesiRangkul::KUNCI_DIPERIKSA, $kini);
        $request->session()->forget(SesiRangkul::KUNCI_DICOBA);

        return $next($request);
    }

    /**
     * Putuskan sesi dan arahkan ke halaman masuk dengan kode alasannya.
     */
    private function putus(Request $request, string $alasan): Response
    {
        $this->sesi->putus($request);

        if ($request->expectsJson()) {
            return response()->json(['pesan' => 'Sesi berakhir. Silakan masuk kembali.'], 401);
        }

        return redirect()->route('masuk')->with('alasan', $alasan);
    }
}
