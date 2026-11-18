<?php

namespace App\Services\Spk;

use App\Exceptions\TokenSsoDitolak;
use Illuminate\Support\Facades\DB;
use SensitiveParameter;

/**
 * Pemeriksa token masuk sekali klik dari spk.or.id.
 *
 * Bentuk token (lihat PANDUAN_DEPLOY_RANGKUL_SSO.md bagian 4.1 di spk.or.id):
 *
 *   base64url(payload) . "." . base64url( HMAC-SHA256("sso." . base64url(payload), rahasia) )
 *
 * Urutan pemeriksaan mengikuti kontrak: tanda tangan lebih dulu, baru isi
 * token dibaca. Tidak satu pun bagian token yang dipercaya sebelum tanda
 * tangannya cocok.
 */
class PemeriksaTokenSso
{
    /** Kelonggaran selisih jam antara server spk.or.id dan Rangkul, dalam detik. */
    public const KELONGGARAN_DETIK = 60;

    /**
     * Masa berlaku terpanjang yang masih diterima. Penerbit sah hanya memberi
     * 120 detik; batas ini mencegah token "berumur panjang" dan membatasi
     * berapa lama sebuah nonce harus disimpan.
     */
    public const MASA_BERLAKU_MAKS_DETIK = 600;

    /**
     * Periksa token dan tandai nonce-nya sebagai terpakai.
     *
     * @throws TokenSsoDitolak
     */
    public function periksa(#[SensitiveParameter] string $token): KlaimSso
    {
        $rahasia = (string) config('services.spk.sso_secret');

        if (strlen($rahasia) < 32) {
            throw new TokenSsoDitolak('belum_diatur');
        }

        if (strlen($token) > 1024 || preg_match('/^([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/D', $token, $bagian) !== 1) {
            throw new TokenSsoDitolak('bentuk');
        }

        [, $muatan, $tanda] = $bagian;

        $tandaSeharusnya = self::base64Url(hash_hmac('sha256', 'sso.'.$muatan, $rahasia, true));

        if (! hash_equals($tandaSeharusnya, $tanda)) {
            throw new TokenSsoDitolak('tanda_tangan');
        }

        $klaim = json_decode((string) base64_decode(strtr($muatan, '-_', '+/'), true), true);

        if (! is_array($klaim)) {
            throw new TokenSsoDitolak('bentuk');
        }

        if (($klaim['v'] ?? null) !== 1 || ($klaim['aud'] ?? null) !== 'rangkul') {
            throw new TokenSsoDitolak('peruntukan');
        }

        $uid = $klaim['uid'] ?? null;
        $iat = $klaim['iat'] ?? null;
        $exp = $klaim['exp'] ?? null;
        $nonce = $klaim['nonce'] ?? null;

        if (! is_int($uid) || $uid < 1 || ! is_int($iat) || ! is_int($exp)
            || ! is_string($nonce) || preg_match('/^[0-9a-f]{16,64}$/D', $nonce) !== 1) {
            throw new TokenSsoDitolak('bentuk');
        }

        $kini = now()->timestamp;

        if ($kini > $exp + self::KELONGGARAN_DETIK) {
            throw new TokenSsoDitolak('kedaluwarsa');
        }

        if ($iat > $kini + self::KELONGGARAN_DETIK || $exp > $kini + self::KELONGGARAN_DETIK + self::MASA_BERLAKU_MAKS_DETIK) {
            throw new TokenSsoDitolak('waktu');
        }

        $this->pakaiNonce($nonce, $exp + self::KELONGGARAN_DETIK, $kini);

        return new KlaimSso($uid, $iat, $exp);
    }

    /**
     * Catat nonce sebagai terpakai; tolak bila sudah pernah tercatat.
     *
     * Penyisipan ke kolom berkunci utama bersifat atomik, sehingga dua
     * permintaan serentak dengan token yang sama tidak mungkin sama-sama lolos.
     *
     * @throws TokenSsoDitolak
     */
    private function pakaiNonce(#[SensitiveParameter] string $nonce, int $simpanSampai, int $kini): void
    {
        DB::table('sso_nonce')->where('kedaluwarsa_pada', '<', $kini)->delete();

        $tersisip = DB::table('sso_nonce')->insertOrIgnore([
            'sidik' => hash('sha256', $nonce),
            'kedaluwarsa_pada' => $simpanSampai,
        ]);

        if ($tersisip !== 1) {
            throw new TokenSsoDitolak('nonce_terpakai');
        }
    }

    /**
     * Base64url tanpa `=`, sama dengan rangkul_b64() di spk.or.id.
     */
    public static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
