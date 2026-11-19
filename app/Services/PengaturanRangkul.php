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
     * @var array<string, bool|int|string|list<array<string, string>>>
     */
    public const BAWAAN = [
        'layanan.buka' => true,
        'layanan.pesan_tutup' => 'Layanan SPK Rangkul sedang ditutup sementara. Silakan kembali lagi nanti.',

        // Halaman depan publik (/) dan kartu masuk (/masuk).
        'halaman.judul' => 'SPK Rangkul',
        'halaman.label' => 'Layanan bantuan psikologis',
        'halaman.tajuk' => 'Ruang aman untuk bercerita, didampingi sesama pekerja kampus.',
        'halaman.subjudul' => 'Layanan bantuan psikologis bagi anggota Serikat Pekerja Kampus.',
        'halaman.teks_tombol' => 'Masuk dengan akun SPK',
        'halaman.judul_tentang' => 'Tentang SPK Rangkul',
        'halaman.isi' => '',
        'halaman.tampil_keunggulan' => true,
        'halaman.keunggulan' => [
            ['judul' => 'Rahasia', 'teks' => 'Cerita, jawaban asesmen, dan percakapan disimpan terenkripsi dan hanya dibaca tim yang mendampingi Anda.'],
            ['judul' => 'Sesama pekerja kampus', 'teks' => 'Didampingi peer konselor SPK yang memahami dunia kerja kampus dan sudah dilatih untuk mendengarkan.'],
            ['judul' => 'Cukup akun SPK', 'teks' => 'Tidak perlu mendaftar atau membuat password baru — masuk memakai akun spk.or.id Anda.'],
            ['judul' => 'Dirujuk bila perlu', 'teks' => 'Bila dibutuhkan, konselor menyarankan bantuan psikolog profesional di luar tim Rangkul.'],
        ],
        'halaman.tampil_langkah' => true,
        'halaman.langkah' => [
            ['judul' => 'Masuk dengan akun SPK', 'teks' => 'Gunakan akun spk.or.id Anda; anggota terkonfirmasi dapat langsung mengajukan permintaan.'],
            ['judul' => 'Ceritakan keadaan Anda', 'teks' => 'Isi formulir singkat dan asesmen dasar sekitar 10–15 menit. Isian tersimpan di setiap langkah.'],
            ['judul' => 'Komite menugaskan konselor', 'teks' => 'Tim komite membaca permintaan Anda dan menugaskan peer konselor pendamping, biasanya dalam 1–2 hari kerja.'],
            ['judul' => 'Mulai didampingi', 'teks' => 'Bercerita lewat percakapan tertulis, lalu pertemuan daring bila Anda dan konselor sepakat.'],
        ],
        'halaman.tampil_faq' => true,
        'halaman.faq' => [
            ['pertanyaan' => 'Siapa yang dapat memakai SPK Rangkul?', 'jawaban' => 'Anggota Serikat Pekerja Kampus yang keanggotaannya sudah dikonfirmasi pengurus dan akunnya aktif di spk.or.id.'],
            ['pertanyaan' => 'Apakah ini layanan psikolog?', 'jawaban' => 'Bukan. SPK Rangkul adalah pendampingan oleh peer konselor — sesama anggota yang dilatih mendengarkan dan mendampingi. Bila diperlukan, konselor akan menyarankan rujukan ke psikolog profesional.'],
            ['pertanyaan' => 'Siapa yang dapat membaca cerita saya?', 'jawaban' => 'Tim komite Rangkul, peer konselor yang ditugaskan kepada Anda, dan superadmin pengelola sistem. Isian disimpan terenkripsi dan tidak dikirim ke spk.or.id.'],
            ['pertanyaan' => 'Berapa lama sampai saya didampingi?', 'jawaban' => 'Komite biasanya menugaskan konselor dalam 1–2 hari kerja. Permintaan yang menunjukkan risiko keselamatan ditangani lebih dulu.'],
            ['pertanyaan' => 'Bagaimana bila saya dalam keadaan darurat?', 'jawaban' => 'Peer konselor bukan layanan gawat darurat. Bila Anda atau orang di sekitar Anda dalam bahaya, segera hubungi layanan darurat 112 atau orang yang Anda percaya.'],
        ],
        'halaman.indeks' => false,

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
     * @return array<string, bool|int|string|list<array<string, string>>>
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
     * Seluruh isi halaman depan publik. Bagian yang dimatikan dikembalikan
     * sebagai daftar kosong supaya tidak ditampilkan.
     *
     * @return array{label: string, tajuk: string, subjudul: string, teks_tombol: string, judul_tentang: string, isi: string, keunggulan: list<array{judul: string, teks: string}>, langkah: list<array{judul: string, teks: string}>, faq: list<array{pertanyaan: string, jawaban: string}>, indeks: bool}
     */
    public function halamanDepan(): array
    {
        return [
            'label' => (string) $this->get('halaman.label'),
            'tajuk' => (string) $this->get('halaman.tajuk'),
            'subjudul' => (string) $this->get('halaman.subjudul'),
            'teks_tombol' => (string) $this->get('halaman.teks_tombol') ?: 'Masuk dengan akun SPK',
            'judul_tentang' => (string) $this->get('halaman.judul_tentang') ?: 'Tentang SPK Rangkul',
            'isi' => (string) $this->get('halaman.isi'),
            'keunggulan' => $this->get('halaman.tampil_keunggulan') ? $this->get('halaman.keunggulan') : [],
            'langkah' => $this->get('halaman.tampil_langkah') ? $this->get('halaman.langkah') : [],
            'faq' => $this->get('halaman.tampil_faq') ? $this->get('halaman.faq') : [],
            'indeks' => (bool) $this->get('halaman.indeks'),
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

    /**
     * @return bool|int|string|list<array<string, string>>
     */
    private function sesuaikanJenis(string $kunci, mixed $nilai): bool|int|string|array
    {
        return match (true) {
            is_bool(self::BAWAAN[$kunci]) => (bool) $nilai,
            is_int(self::BAWAAN[$kunci]) => (int) $nilai,
            is_array(self::BAWAAN[$kunci]) => $this->daftarRapi($kunci, $nilai),
            default => trim((string) ($nilai ?? '')),
        };
    }

    /**
     * Daftar baris berisi teks dengan kolom yang sama seperti bawaannya;
     * baris yang semua kolomnya kosong dibuang.
     *
     * @return list<array<string, string>>
     */
    private function daftarRapi(string $kunci, mixed $nilai): array
    {
        $kolom = array_keys(self::BAWAAN[$kunci][0] ?? []);
        $hasil = [];

        foreach (is_array($nilai) ? $nilai : [] as $baris) {
            if (! is_array($baris)) {
                continue;
            }

            $rapi = [];

            foreach ($kolom as $nama) {
                $rapi[$nama] = trim(is_scalar($baris[$nama] ?? null) ? (string) $baris[$nama] : '');
            }

            if (implode('', $rapi) !== '') {
                $hasil[] = $rapi;
            }
        }

        return $hasil;
    }

    private function pastikanDikenal(string $kunci): void
    {
        if (! array_key_exists($kunci, self::BAWAAN)) {
            throw new InvalidArgumentException("Pengaturan tidak dikenal: {$kunci}");
        }
    }
}
