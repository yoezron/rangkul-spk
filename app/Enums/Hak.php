<?php

namespace App\Enums;

/**
 * Daftar hak akses SPK Rangkul.
 *
 * Hak akses ditetapkan di kode karena tiap hak berpasangan dengan pemeriksaan
 * di rute atau kontroler. Yang diatur dari dashboard adalah peran: nama peran
 * dan hak mana saja yang dimilikinya.
 */
enum Hak: string
{
    case DasborLihat = 'dasbor.lihat';
    case PengaturanUmum = 'pengaturan.umum';
    case PengaturanHalaman = 'pengaturan.halaman';
    case PengaturanSurel = 'pengaturan.surel';
    case PengaturanWhatsapp = 'pengaturan.whatsapp';
    case PenggunaLihat = 'pengguna.lihat';
    case PenggunaTugaskan = 'pengguna.tugaskan';
    case PeranKelola = 'peran.kelola';
    case AuditLihat = 'audit.lihat';
    case RekapUnduh = 'rekap.unduh';
    case PermintaanKelola = 'permintaan.kelola';
    case PermintaanRekap = 'permintaan.rekap';
    case AsesmenKelola = 'asesmen.kelola';
    case KonselingTangani = 'konseling.tangani';

    public function label(): string
    {
        return match ($this) {
            self::DasborLihat => 'Membuka dashboard pengelola',
            self::PengaturanUmum => 'Membuka/menutup layanan dan mengatur audit log',
            self::PengaturanHalaman => 'Mengatur halaman depan dan kontak',
            self::PengaturanSurel => 'Mengatur pengiriman surel (SMTP Brevo)',
            self::PengaturanWhatsapp => 'Mengatur notifikasi WhatsApp (Fonnte)',
            self::PenggunaLihat => 'Melihat daftar pengguna',
            self::PenggunaTugaskan => 'Menugaskan dan mencabut peran pengguna',
            self::PeranKelola => 'Membuat dan mengubah peran beserta hak aksesnya',
            self::AuditLihat => 'Melihat audit log',
            self::RekapUnduh => 'Mengunduh rekap data',
            self::PermintaanKelola => 'Membaca semua permintaan layanan, menetapkan status, menugaskan konselor, dan menyetujui pertemuan serta penutupan',
            self::PermintaanRekap => 'Mengunduh rekap lengkap permintaan layanan dan percakapan',
            self::AsesmenKelola => 'Menyusun instrumen dan butir asesmen dasar',
            self::KonselingTangani => 'Menjadi peer konselor: menangani permintaan yang ditugaskan kepadanya',
        };
    }

    public function kelompok(): string
    {
        return match ($this) {
            self::DasborLihat => 'Dashboard',
            self::PengaturanUmum, self::PengaturanHalaman, self::PengaturanSurel, self::PengaturanWhatsapp => 'Pengaturan',
            self::PenggunaLihat, self::PenggunaTugaskan, self::PeranKelola => 'Pengguna dan peran',
            self::AuditLihat, self::RekapUnduh => 'Catatan dan rekap',
            self::PermintaanKelola, self::PermintaanRekap, self::AsesmenKelola => 'Komite layanan',
            self::KonselingTangani => 'Layanan',
        };
    }

    /**
     * Hak yang hanya bermakna di dalam dashboard pengelola.
     */
    public function butuhDasbor(): bool
    {
        return ! in_array($this, [self::DasborLihat, self::KonselingTangani], true);
    }

    /**
     * @return list<string>
     */
    public static function semuaNilai(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, list<self>>
     */
    public static function perKelompok(): array
    {
        $kelompok = [];

        foreach (self::cases() as $hak) {
            $kelompok[$hak->kelompok()][] = $hak;
        }

        return $kelompok;
    }
}
