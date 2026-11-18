<?php

namespace App\Services\Spk;

use App\Exceptions\PermintaanSpkGagal;

/**
 * Jawaban 200 dari endpoint data anggota spk.or.id, sudah diperiksa bentuknya.
 */
final readonly class JawabanAnggota
{
    /** Kode alasan penolakan yang dikenal kontrak (bagian 2 dan 4.2). */
    public const ALASAN_DIKENAL = ['belum_terkonfirmasi', 'tidak_aktif', 'wajib_ganti_password', 'tidak_ditemukan'];

    /**
     * @param  int  $id  id akun yang ditanyakan
     * @param  bool  $berhak  true hanya bila spk.or.id menjawab `berhak: true` secara harfiah
     * @param  string  $alasan  kode penolakan; string kosong bila berhak
     * @param  int  $sesiValidSejak  waktu "keluarkan semua sesi" terakhir di spk.or.id (0 = belum pernah)
     * @param  array{nama?: string, email?: string|null, telp?: string|null, gender?: string|null, kampus?: string|null, wilayah?: string|null, peran?: string|null, nomor_anggota?: string|null, foto?: string|null}  $profil  kosong bila tidak berhak
     */
    private function __construct(
        public int $id,
        public bool $berhak,
        public string $alasan,
        public int $sesiValidSejak,
        public array $profil,
    ) {}

    /**
     * Baca badan JSON jawaban untuk akun `$uid`.
     *
     * @param  mixed  $badan  hasil json_decode jawaban
     *
     * @throws PermintaanSpkGagal bila bentuknya tidak sesuai kontrak
     */
    public static function dariBadan(mixed $badan, int $uid): self
    {
        if (! is_array($badan) || ($badan['ok'] ?? null) !== true || ! is_array($badan['anggota'] ?? null)) {
            throw new PermintaanSpkGagal('bentuk');
        }

        $anggota = $badan['anggota'];
        $sesiValidSejak = max(0, (int) ($badan['sesi_valid_sejak'] ?? 0));

        if (($anggota['berhak'] ?? null) !== true) {
            $alasan = $anggota['alasan'] ?? null;

            return new self(
                id: $uid,
                berhak: false,
                alasan: in_array($alasan, self::ALASAN_DIKENAL, true) ? $alasan : 'tidak_dikenal',
                sesiValidSejak: $sesiValidSejak,
                profil: [],
            );
        }

        // Jawaban "berhak" untuk akun lain daripada yang ditanyakan tidak
        // boleh dipakai memasukkan siapa pun.
        if (($anggota['id'] ?? null) !== $uid) {
            throw new PermintaanSpkGagal('bentuk');
        }

        $nama = self::teks($anggota['nama'] ?? null);
        $foto = self::teks($anggota['foto'] ?? null, 2048);

        return new self(
            id: $uid,
            berhak: true,
            alasan: '',
            sesiValidSejak: $sesiValidSejak,
            profil: [
                'nama' => $nama ?? 'Anggota SPK',
                'email' => self::teks($anggota['email'] ?? null),
                'telp' => self::teks($anggota['telp'] ?? null),
                'gender' => self::teks($anggota['gender'] ?? null),
                'kampus' => self::teks($anggota['kampus'] ?? null),
                'wilayah' => self::teks($anggota['wilayah'] ?? null),
                'peran' => self::teks($anggota['peran'] ?? null),
                'nomor_anggota' => self::teks($anggota['nomor_anggota'] ?? null),
                'foto' => $foto !== null && preg_match('#^https?://#i', $foto) === 1 ? $foto : null,
            ],
        );
    }

    /**
     * Rapikan satu nilai teks: bukan teks atau kosong menjadi null, dan
     * dipotong supaya muat di kolomnya.
     */
    private static function teks(mixed $nilai, int $panjangMaks = 255): ?string
    {
        if (! is_string($nilai) && ! is_int($nilai)) {
            return null;
        }

        $nilai = trim((string) $nilai);

        return $nilai === '' ? null : mb_substr($nilai, 0, $panjangMaks);
    }
}
