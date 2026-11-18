<?php

namespace App\Http\Controllers;

use App\Exceptions\PermintaanSpkGagal;
use App\Exceptions\TokenSsoDitolak;
use App\Models\User;
use App\Services\Spk\KlienSpk;
use App\Services\Spk\PemeriksaTokenSso;
use App\Services\Spk\SesiRangkul;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Pintu masuk dari spk.or.id: `GET /sso/spk?t=<token>`.
 *
 * Alamat ini tidak pernah menampilkan halaman. Apa pun hasilnya — masuk,
 * ditolak, maupun galat tak terduga — jawabannya selalu pengalihan ke alamat
 * tanpa `?t=`, supaya token tidak tertinggal di bilah alamat, riwayat
 * peramban, atau kepala Referer.
 *
 * Catatan (log) dari sini hanya memuat kode sebab: tidak ada token, nonce,
 * id akun, maupun data pribadi.
 */
class SsoSpkController extends Controller
{
    /** Batas token tertolak per alamat IP per menit. */
    private const BATAS_GAGAL_PER_MENIT = 30;

    public function __construct(
        private readonly PemeriksaTokenSso $pemeriksa,
        private readonly KlienSpk $klien,
        private readonly SesiRangkul $sesi,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        try {
            return $this->proses($request);
        } catch (Throwable $galat) {
            report($galat);

            return $this->tolak('gangguan');
        }
    }

    private function proses(Request $request): RedirectResponse
    {
        $kunciBatas = 'sso-spk-gagal:'.$request->ip();

        if (RateLimiter::tooManyAttempts($kunciBatas, self::BATAS_GAGAL_PER_MENIT)) {
            return $this->tolak('terlalu_sering');
        }

        $token = $request->query('t');

        try {
            $klaim = $this->pemeriksa->periksa(is_string($token) ? $token : '');
        } catch (TokenSsoDitolak $galat) {
            RateLimiter::hit($kunciBatas, 60);
            Log::notice('SSO: token masuk ditolak.', ['sebab' => $galat->sebab]);

            return $this->tolak('tautan_tidak_sah');
        }

        // Token sah berarti orang di depan peramban ini adalah pemilik akun
        // pada token. Sesi anggota lain yang masih terbuka di peramban yang
        // sama harus berakhir, apa pun hasil langkah berikutnya.
        if ($request->user() !== null && $request->user()->spk_id !== $klaim->uid) {
            $this->sesi->putus($request);
        }

        try {
            $jawaban = $this->klien->anggota($klaim->uid);
        } catch (PermintaanSpkGagal) {
            return $this->tolak('gangguan');
        }

        if (! $jawaban->berhak) {
            $this->sesi->putus($request);

            return $this->tolak($jawaban->alasan);
        }

        // Token diterbitkan sebelum "keluarkan semua sesi" terakhir: sesi
        // spk.or.id yang menerbitkannya sudah dinyatakan tidak berlaku.
        if ($jawaban->sesiValidSejak > 0 && $klaim->iat < $jawaban->sesiValidSejak) {
            $this->sesi->putus($request);

            return $this->tolak('sesi_berakhir');
        }

        $this->sesi->mulai($request, User::sinkronkanDariSpk($jawaban), $klaim->iat);

        return redirect()->route('beranda');
    }

    /**
     * Kembali ke halaman masuk dengan kode alasan (lewat flash sesi, bukan alamat).
     */
    private function tolak(string $alasan): RedirectResponse
    {
        return redirect()->route('masuk')->with('alasan', $alasan);
    }
}
