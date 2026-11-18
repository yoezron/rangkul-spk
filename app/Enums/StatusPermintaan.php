<?php

namespace App\Enums;

/**
 * Perjalanan satu permintaan layanan.
 *
 * draf → diajukan → ditinjau → berlangsung → menunggu_penutupan → selesai
 * Di luar jalur utama: ditolak (oleh komite) dan dibatalkan (oleh anggota).
 */
enum StatusPermintaan: string
{
    case Draf = 'draf';
    case Diajukan = 'diajukan';
    case Ditinjau = 'ditinjau';
    case Berlangsung = 'berlangsung';
    case MenungguPenutupan = 'menunggu_penutupan';
    case Selesai = 'selesai';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Draf => 'Draf',
            self::Diajukan => 'Baru masuk',
            self::Ditinjau => 'Sedang ditinjau komite',
            self::Berlangsung => 'Pendampingan berlangsung',
            self::MenungguPenutupan => 'Menunggu persetujuan penutupan',
            self::Selesai => 'Selesai',
            self::Ditolak => 'Tidak dapat dilayani',
            self::Dibatalkan => 'Dibatalkan anggota',
        };
    }

    /**
     * Masih memerlukan tindak lanjut (belum selesai, ditolak, atau dibatalkan).
     */
    public function terbuka(): bool
    {
        return ! in_array($this, [self::Selesai, self::Ditolak, self::Dibatalkan], true);
    }

    /**
     * Anggota dan konselor dapat bercakap.
     */
    public function bolehBercakap(): bool
    {
        return in_array($this, [self::Berlangsung, self::MenungguPenutupan], true);
    }

    /**
     * Belum ditangani konselor, sehingga anggota masih boleh membatalkan.
     */
    public function belumDitangani(): bool
    {
        return in_array($this, [self::Draf, self::Diajukan, self::Ditinjau], true);
    }

    /**
     * Status yang ditampilkan di daftar komite (draf disembunyikan).
     *
     * @return list<self>
     */
    public static function terlihatKomite(): array
    {
        return array_values(array_filter(self::cases(), fn (self $status): bool => $status !== self::Draf));
    }
}
