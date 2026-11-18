<?php

namespace App\Services\Spk;

use App\Exceptions\PermintaanSpkGagal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Klien server-ke-server untuk spk.or.id.
 *
 * Satu-satunya data anggota yang masuk ke Rangkul lewat sini (kontrak bagian
 * 4.2): `GET {SPK_URL}rangkul/anggota?uid=…` dengan tanda tangan HMAC di
 * kepala permintaan. Rangkul tidak pernah membaca basis data spk.or.id.
 */
class KlienSpk
{
    /**
     * Rahasia bersama sudah diisi dan alamat situs induk layak dipakai.
     *
     * Di produksi alamatnya wajib https: jawaban endpoint tidak bertanda
     * tangan, jadi keasliannya bertumpu pada TLS.
     */
    public function siap(): bool
    {
        $alamat = $this->alamat();

        if (strlen((string) config('services.spk.sso_secret')) < 32 || $alamat === '/') {
            return false;
        }

        return ! app()->isProduction() || str_starts_with(strtolower($alamat), 'https://');
    }

    /**
     * Alamat di spk.or.id; alamat dasarnya selalu berakhiran garis miring.
     */
    public function alamat(string $jalur = ''): string
    {
        return rtrim((string) config('services.spk.url'), '/').'/'.ltrim($jalur, '/');
    }

    /**
     * Tautan tombol "Masuk dengan akun SPK" (kontrak bagian 3).
     */
    public function alamatMasuk(): string
    {
        return $this->alamat('auth').'?lanjut=rangkul';
    }

    /**
     * Tanyakan kepada spk.or.id siapa pemilik id akun ini dan apakah ia berhak.
     *
     * Catatan (log) kegagalan sengaja hanya memuat kode sebab — tanpa id akun
     * dan tanpa pesan galat jaringan (yang memuat alamat lengkap berikut uid),
     * supaya log Rangkul tidak menjadi daftar "siapa membuka layanan psikologis".
     *
     * @throws PermintaanSpkGagal
     */
    public function anggota(int $uid): JawabanAnggota
    {
        if (! $this->siap()) {
            throw $this->gagal('belum_diatur_di_rangkul', ditolak: true);
        }

        $waktu = now()->timestamp;
        $tanda = hash_hmac('sha256', "anggota\n{$uid}\n{$waktu}", (string) config('services.spk.sso_secret'));

        try {
            $jawaban = Http::connectTimeout(3)
                ->timeout(6)
                ->withoutRedirecting()
                ->acceptJson()
                ->withHeaders([
                    'X-Rangkul-Waktu' => (string) $waktu,
                    'X-Rangkul-Tanda' => $tanda,
                ])
                ->get($this->alamat('rangkul/anggota'), ['uid' => $uid]);
        } catch (ConnectionException) {
            throw $this->gagal('jaringan');
        }

        if ($jawaban->status() === 401 || $jawaban->status() === 403) {
            throw $this->gagal('tanda_tangan', ditolak: true);
        }

        if ($jawaban->status() === 503 && $jawaban->json('galat') === 'belum_diatur') {
            throw $this->gagal('belum_diatur', ditolak: true);
        }

        if ($jawaban->status() !== 200) {
            throw $this->gagal('http_'.$jawaban->status());
        }

        try {
            return JawabanAnggota::dariBadan($jawaban->json(), $uid);
        } catch (PermintaanSpkGagal $galat) {
            throw $this->gagal($galat->sebab);
        }
    }

    /**
     * Catat kegagalan untuk pengelola lalu kembalikan pengecualiannya.
     */
    private function gagal(string $sebab, bool $ditolak = false): PermintaanSpkGagal
    {
        Log::warning('SPK: permintaan data anggota gagal.', ['sebab' => $sebab]);

        return new PermintaanSpkGagal($sebab, $ditolak);
    }
}
