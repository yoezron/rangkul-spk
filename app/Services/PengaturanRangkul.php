<?php

namespace App\Services;

use App\Models\Pengaturan;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

/**
 * Pengaturan SPK Rangkul yang diubah dari dashboard pengelola.
 *
 * Hanya kunci yang terdaftar di BAWAAN yang dikenal; kunci yang belum pernah
 * disimpan memakai nilai bawaannya. Nilai disimpan sebagai JSON. Nilai
 * rahasia (sandi SMTP, token Fonnte) disimpan terenkripsi dengan APP_KEY dan tidak pernah
 * dikirim kembali ke formulir.
 */
class PengaturanRangkul
{
    /**
     * @var array<string, bool|int|string>
     */
    public const BAWAAN = [
        'layanan.buka' => true,
        'layanan.pesan_tutup' => 'Layanan SPK Rangkul sedang ditutup sementara. Silakan kembali lagi nanti.',

        'halaman.judul' => 'SPK Rangkul',
        'halaman.subjudul' => 'Layanan bantuan psikologis bagi anggota Serikat Pekerja Kampus.',
        'halaman.isi' => '',

        'kontak.email' => '',
        'kontak.whatsapp' => '',
        'kontak.jam_layanan' => '',
        'kontak.darurat' => 'Bila Anda atau orang di sekitar Anda dalam bahaya, segera hubungi layanan darurat 112.',

        // Bawaan mengikuti relay SMTP Brevo, penyedia surel yang dipakai SPK.
        'surel.aktif' => false,
        'surel.host' => 'smtp-relay.brevo.com',
        'surel.port' => 587,
        'surel.enkripsi' => 'tls',
        'surel.username' => '',
        'surel.password' => '',
        'surel.dari_alamat' => '',
        'surel.dari_nama' => 'SPK Rangkul',

        'whatsapp.aktif' => false,
        'whatsapp.token' => '',
        'whatsapp.kode_negara' => '62',

        'audit.retensi_hari' => 365,
        'audit.catat_ip' => true,
    ];

    /** Kunci yang nilainya disimpan terenkripsi dan tidak pernah ditampilkan. */
    public const RAHASIA = ['surel.password', 'whatsapp.token'];

    /**
     * Nilai yang sudah dimuat dari basis data, per kunci.
     *
     * @var array<string, mixed>|null
     */
    private ?array $tersimpan = null;

    public function get(string $kunci): mixed
    {
        $this->pastikanDikenal($kunci);
        $this->muat();

        return $this->tersimpan[$kunci] ?? self::BAWAAN[$kunci];
    }

    /**
     * Simpan beberapa pengaturan sekaligus.
     *
     * @param  array<string, mixed>  $nilaiBaru
     * @return list<string> kunci yang nilainya benar-benar berubah
     */
    public function simpan(array $nilaiBaru): array
    {
        $berubah = [];

        foreach ($nilaiBaru as $kunci => $nilai) {
            $this->pastikanDikenal($kunci);
            $nilai = $this->sesuaikanJenis($kunci, $nilai);

            if ($this->get($kunci) === $nilai) {
                continue;
            }

            $json = json_encode($nilai, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            Pengaturan::query()->updateOrCreate(
                ['kunci' => $kunci],
                ['nilai' => in_array($kunci, self::RAHASIA, true) ? Crypt::encryptString($json) : $json],
            );

            $this->tersimpan[$kunci] = $nilai;
            $berubah[] = $kunci;
        }

        return $berubah;
    }

    /**
     * Seluruh nilai untuk mengisi formulir; nilai rahasia dikosongkan.
     *
     * @return array<string, bool|int|string>
     */
    public function untukFormulir(): array
    {
        $nilai = [];

        foreach (array_keys(self::BAWAAN) as $kunci) {
            $nilai[$kunci] = in_array($kunci, self::RAHASIA, true) ? '' : $this->get($kunci);
        }

        return $nilai;
    }

    public function adaRahasia(string $kunci): bool
    {
        return $this->get($kunci) !== '';
    }

    public function layananBuka(): bool
    {
        return (bool) $this->get('layanan.buka');
    }

    /**
     * @return array{judul: string, subjudul: string, isi: string}
     */
    public function halaman(): array
    {
        return [
            'judul' => (string) $this->get('halaman.judul'),
            'subjudul' => (string) $this->get('halaman.subjudul'),
            'isi' => (string) $this->get('halaman.isi'),
        ];
    }

    /**
     * Kontak yang ditampilkan kepada anggota, termasuk tautan WhatsApp siap pakai.
     *
     * @return array{email: string, whatsapp: string, whatsapp_tautan: string|null, jam_layanan: string, darurat: string}
     */
    public function kontak(): array
    {
        $whatsapp = (string) $this->get('kontak.whatsapp');
        $digit = preg_replace('/\D+/', '', $whatsapp) ?? '';

        if (str_starts_with($digit, '0')) {
            $digit = '62'.substr($digit, 1);
        }

        return [
            'email' => (string) $this->get('kontak.email'),
            'whatsapp' => $whatsapp,
            'whatsapp_tautan' => $digit !== '' ? 'https://wa.me/'.$digit : null,
            'jam_layanan' => (string) $this->get('kontak.jam_layanan'),
            'darurat' => (string) $this->get('kontak.darurat'),
        ];
    }

    /**
     * SMTP sudah diaktifkan dan cukup lengkap untuk dipakai.
     */
    public function surelSiap(): bool
    {
        return $this->get('surel.aktif') === true
            && $this->get('surel.host') !== ''
            && $this->get('surel.dari_alamat') !== '';
    }

    /**
     * Pasang pengaturan SMTP dari dashboard ke konfigurasi surel Laravel.
     *
     * Selama SMTP belum diaktifkan di dashboard, konfigurasi dari .env tetap
     * berlaku (di lokal: surel hanya ditulis ke log).
     */
    public function terapkanSurel(): void
    {
        if (! $this->surelSiap()) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.scheme' => $this->get('surel.enkripsi') === 'ssl' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.host' => $this->get('surel.host'),
            'mail.mailers.smtp.port' => $this->get('surel.port'),
            'mail.mailers.smtp.username' => $this->get('surel.username') ?: null,
            'mail.mailers.smtp.password' => $this->get('surel.password') ?: null,
            'mail.mailers.smtp.timeout' => 10,
            'mail.from.address' => $this->get('surel.dari_alamat'),
            'mail.from.name' => $this->get('surel.dari_nama') ?: 'SPK Rangkul',
        ]);
    }

    private function muat(): void
    {
        if ($this->tersimpan !== null) {
            return;
        }

        $this->tersimpan = [];

        foreach (Pengaturan::query()->get() as $baris) {
            if (! array_key_exists($baris->kunci, self::BAWAAN)) {
                continue;
            }

            try {
                $json = in_array($baris->kunci, self::RAHASIA, true)
                    ? Crypt::decryptString((string) $baris->nilai)
                    : (string) $baris->nilai;
            } catch (DecryptException) {
                // APP_KEY sudah berganti: nilai lama tidak bisa dibaca dan
                // dianggap belum diisi, sehingga pengelola mengisinya ulang.
                continue;
            }

            $this->tersimpan[$baris->kunci] = $this->sesuaikanJenis($baris->kunci, json_decode($json, true));
        }
    }

    private function sesuaikanJenis(string $kunci, mixed $nilai): bool|int|string
    {
        return match (true) {
            is_bool(self::BAWAAN[$kunci]) => (bool) $nilai,
            is_int(self::BAWAAN[$kunci]) => (int) $nilai,
            default => trim((string) ($nilai ?? '')),
        };
    }

    private function pastikanDikenal(string $kunci): void
    {
        if (! array_key_exists($kunci, self::BAWAAN)) {
            throw new InvalidArgumentException("Pengaturan tidak dikenal: {$kunci}");
        }
    }
}
