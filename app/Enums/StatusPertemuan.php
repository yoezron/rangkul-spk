<?php

namespace App\Enums;

/**
 * Usulan pertemuan daring.
 *
 * diusulkan (menunggu anggota) → menunggu_komite (hanya pertemuan pertama per
 * kasus) → terjadwal. Tautan Zoom/Meet hanya terlihat anggota saat terjadwal.
 */
enum StatusPertemuan: string
{
    case Diusulkan = 'diusulkan';
    case MenungguKomite = 'menunggu_komite';
    case Terjadwal = 'terjadwal';
    case Ditolak = 'ditolak';
    case Dibatalkan = 'dibatalkan';

    public function label(): string
    {
        return match ($this) {
            self::Diusulkan => 'Menunggu persetujuan anggota',
            self::MenungguKomite => 'Menunggu persetujuan komite',
            self::Terjadwal => 'Terjadwal',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function aktif(): bool
    {
        return in_array($this, [self::Diusulkan, self::MenungguKomite, self::Terjadwal], true);
    }
}
