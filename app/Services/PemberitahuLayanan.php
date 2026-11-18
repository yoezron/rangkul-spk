<?php

namespace App\Services;

use App\Enums\Hak;
use App\Models\Permintaan;
use App\Models\Pertemuan;
use App\Models\User;
use App\Notifications\NotifikasiRangkul;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Semua notifikasi layanan di satu tempat: siapa menerima apa, dengan kata-kata apa.
 *
 * Tidak satu pun pesan di sini memuat isi cerita, topik, skor, atau isi
 * percakapan (lihat NotifikasiRangkul).
 */
class PemberitahuLayanan
{
    /**
     * Anggota tim komite yang menerima notifikasi kasus.
     *
     * @return Collection<int, User>
     */
    public function komite(?Permintaan $kecuali = null): Collection
    {
        return User::query()
            ->pemegangPeranDenganHak(Hak::PermintaanKelola)
            ->when($kecuali !== null, fn ($kueri) => $kueri->whereKeyNot($kecuali->user_id))
            ->get();
    }

    public function permintaanMasuk(Permintaan $permintaan): void
    {
        $this->kirim($this->komite($permintaan), new NotifikasiRangkul(
            jenis: 'permintaan.masuk',
            subjek: ($permintaan->darurat ? 'Permintaan DARURAT ' : 'Permintaan baru ').$permintaan->kode,
            judul: $permintaan->darurat ? 'Permintaan layanan ditandai DARURAT' : 'Ada permintaan layanan baru',
            paragraf: array_values(array_filter([
                "Permintaan {$permintaan->kode} baru saja diajukan anggota dan menunggu ditinjau komite.",
                $permintaan->darurat
                    ? 'Jawaban keselamatan atau asesmen anggota menunjukkan kemungkinan risiko. Mohon segera dihubungi dan ditugaskan konselor hari ini.'
                    : null,
                'Silakan baca isiannya dan tugaskan peer konselor pendamping.',
            ])),
            teksTombol: 'Tinjau permintaan',
            tautan: route('kelola.permintaan.tampil', $permintaan),
            mendesak: $permintaan->darurat,
        ));

        $this->kirim($permintaan->anggota, new NotifikasiRangkul(
            jenis: 'permintaan.diterima',
            subjek: "Permintaan {$permintaan->kode} diterima",
            judul: 'Permintaan Anda sudah kami terima',
            paragraf: [
                "Terima kasih telah menghubungi SPK Rangkul. Permintaan Anda tercatat dengan kode {$permintaan->kode}.",
                'Tim komite akan membacanya dan menugaskan peer konselor yang akan mendampingi Anda. Kami akan memberi kabar begitu konselor ditetapkan.',
            ],
            teksTombol: 'Lihat permintaan',
            tautan: route('permintaan.tampil', $permintaan),
        ));
    }

    public function konselorDitugaskan(Permintaan $permintaan, bool $pengganti): void
    {
        $this->kirim($permintaan->konselor, new NotifikasiRangkul(
            jenis: 'penugasan.konselor',
            subjek: "Anda ditugaskan mendampingi {$permintaan->kode}",
            judul: 'Anda ditugaskan sebagai konselor pendamping',
            paragraf: [
                "Komite menugaskan Anda sebagai PIC untuk permintaan {$permintaan->kode}.",
                'Silakan baca isian dan hasil asesmen dasarnya, lalu sapa anggota lewat percakapan di SPK Rangkul.',
            ],
            teksTombol: 'Buka kasus',
            tautan: route('konseling.tampil', $permintaan),
        ));

        $this->kirim($permintaan->anggota, new NotifikasiRangkul(
            jenis: 'penugasan.anggota',
            subjek: 'Konselor pendamping Anda sudah ditetapkan',
            judul: $pengganti ? 'Konselor pendamping Anda diganti' : 'Konselor pendamping Anda sudah ditetapkan',
            paragraf: [
                "Untuk permintaan {$permintaan->kode}, Anda kini didampingi {$permintaan->konselor->nama}.",
                'Anda dapat mulai bercerita lewat percakapan di SPK Rangkul. Konselor juga dapat mengusulkan pertemuan daring bila diperlukan.',
            ],
            teksTombol: 'Buka percakapan',
            tautan: route('permintaan.tampil', $permintaan),
        ));
    }

    public function permintaanDitolak(Permintaan $permintaan): void
    {
        $this->kirim($permintaan->anggota, new NotifikasiRangkul(
            jenis: 'permintaan.ditolak',
            subjek: "Kabar tentang permintaan {$permintaan->kode}",
            judul: 'Kabar dari tim komite tentang permintaan Anda',
            paragraf: [
                "Tim komite sudah meninjau permintaan {$permintaan->kode} dan menuliskan penjelasan serta saran untuk Anda.",
            ],
            teksTombol: 'Baca penjelasan',
            tautan: route('permintaan.tampil', $permintaan),
        ));
    }

    /**
     * Pengingat pesan yang belum dibaca lebih dari 24 jam.
     */
    public function pesanBelumDibaca(Permintaan $permintaan, User $penerima): void
    {
        $untukKonselor = $penerima->id === $permintaan->konselor_id;

        $this->kirim($penerima, new NotifikasiRangkul(
            jenis: 'pesan.belum_dibaca',
            subjek: 'Ada pesan yang belum Anda baca',
            judul: 'Ada pesan yang menunggu Anda',
            paragraf: [
                $untukKonselor
                    ? "Anggota yang Anda dampingi ({$permintaan->kode}) mengirim pesan lebih dari 24 jam lalu dan belum dibaca."
                    : "Konselor pendamping Anda mengirim pesan untuk permintaan {$permintaan->kode} lebih dari 24 jam lalu dan belum dibaca.",
            ],
            teksTombol: 'Baca pesan',
            tautan: $untukKonselor ? route('konseling.tampil', $permintaan) : route('permintaan.tampil', $permintaan),
        ));

        if ($untukKonselor) {
            $this->kirim($this->komite($permintaan), new NotifikasiRangkul(
                jenis: 'pesan.belum_dibaca_konselor',
                subjek: "Pesan anggota {$permintaan->kode} belum dibaca konselor",
                judul: 'Pesan anggota belum dibaca konselor',
                paragraf: [
                    "Pesan anggota untuk permintaan {$permintaan->kode} belum dibaca konselor pendampingnya lebih dari 24 jam.",
                    'Mohon dipantau; bila perlu hubungi konselor atau tugaskan pendamping lain.',
                ],
                teksTombol: 'Pantau kasus',
                tautan: route('kelola.permintaan.tampil', $permintaan),
            ));
        }
    }

    public function pertemuanDiusulkan(Pertemuan $pertemuan): void
    {
        $permintaan = $pertemuan->permintaan;

        $this->kirim($permintaan->anggota, new NotifikasiRangkul(
            jenis: 'pertemuan.diusulkan',
            subjek: 'Usulan pertemuan daring',
            judul: 'Konselor mengusulkan pertemuan daring',
            paragraf: [
                "Konselor pendamping Anda mengusulkan pertemuan daring pada {$pertemuan->waktu()}.",
                'Silakan setujui atau tolak usulan ini di SPK Rangkul.',
            ],
            teksTombol: 'Tanggapi usulan',
            tautan: route('permintaan.tampil', $permintaan),
        ));
    }

    public function pertemuanPerluKomite(Pertemuan $pertemuan): void
    {
        $permintaan = $pertemuan->permintaan;

        $this->kirim($this->komite($permintaan), new NotifikasiRangkul(
            jenis: 'pertemuan.perlu_komite',
            subjek: "Pertemuan daring pertama {$permintaan->kode} menunggu persetujuan",
            judul: 'Pertemuan daring pertama menunggu persetujuan komite',
            paragraf: [
                "Konselor dan anggota pada permintaan {$permintaan->kode} sepakat bertemu daring pada {$pertemuan->waktu()}.",
                'Sebagai pertemuan daring pertama kasus ini, pertemuan perlu disetujui komite sebelum tautannya dibagikan.',
            ],
            teksTombol: 'Tinjau pertemuan',
            tautan: route('kelola.permintaan.tampil', $permintaan),
        ));
    }

    public function pertemuanTerjadwal(Pertemuan $pertemuan): void
    {
        $permintaan = $pertemuan->permintaan;
        $paragraf = ["Pertemuan daring untuk permintaan {$permintaan->kode} terjadwal pada {$pertemuan->waktu()}."];

        $this->kirim($permintaan->anggota, new NotifikasiRangkul(
            jenis: 'pertemuan.terjadwal',
            subjek: 'Pertemuan daring terjadwal',
            judul: 'Pertemuan daring Anda sudah terjadwal',
            paragraf: [...$paragraf, 'Tautan pertemuan tersedia di SPK Rangkul; cukup klik tombolnya saat waktunya tiba.'],
            teksTombol: 'Lihat jadwal',
            tautan: route('permintaan.tampil', $permintaan),
        ));

        $this->kirim($permintaan->konselor, new NotifikasiRangkul(
            jenis: 'pertemuan.terjadwal',
            subjek: "Pertemuan {$permintaan->kode} terjadwal",
            judul: 'Pertemuan daring sudah terjadwal',
            paragraf: [...$paragraf, 'Pastikan tautan Zoom/Google Meet sudah diisi di halaman kasus.'],
            teksTombol: 'Buka kasus',
            tautan: route('konseling.tampil', $permintaan),
        ));
    }

    public function pertemuanBerubah(Pertemuan $pertemuan, User $penerima, string $kalimat): void
    {
        $permintaan = $pertemuan->permintaan;
        $untukKonselor = $penerima->id === $permintaan->konselor_id;

        $this->kirim($penerima, new NotifikasiRangkul(
            jenis: 'pertemuan.berubah',
            subjek: 'Pembaruan jadwal pertemuan',
            judul: 'Ada pembaruan jadwal pertemuan',
            paragraf: [$kalimat],
            teksTombol: 'Lihat jadwal',
            tautan: $untukKonselor ? route('konseling.tampil', $permintaan) : route('permintaan.tampil', $permintaan),
        ));
    }

    public function penutupanDiusulkan(Permintaan $permintaan): void
    {
        $this->kirim($permintaan->anggota, new NotifikasiRangkul(
            jenis: 'penutupan.diusulkan',
            subjek: 'Usulan menutup proses konsultasi',
            judul: 'Konselor mengusulkan menutup proses konsultasi',
            paragraf: [
                "Konselor pendamping Anda mengusulkan agar proses konsultasi untuk permintaan {$permintaan->kode} ditutup.",
                'Penutupan hanya berjalan bila Anda setuju. Silakan baca alasannya dan beri tanggapan di SPK Rangkul.',
            ],
            teksTombol: 'Tanggapi usulan',
            tautan: route('permintaan.tampil', $permintaan),
        ));
    }

    public function penutupanPerluTinjauan(Permintaan $permintaan): void
    {
        $this->kirim($this->komite($permintaan), new NotifikasiRangkul(
            jenis: 'penutupan.perlu_tinjauan',
            subjek: "Penutupan {$permintaan->kode} menunggu persetujuan",
            judul: 'Penutupan proses konsultasi menunggu persetujuan komite',
            paragraf: [
                "Konselor mengusulkan penutupan permintaan {$permintaan->kode} dan anggota sudah menyetujuinya.",
                'Mohon tinjau catatan pendampingan, lalu setujui atau kembalikan penutupan ini.',
            ],
            teksTombol: 'Tinjau penutupan',
            tautan: route('kelola.permintaan.tampil', $permintaan),
        ));
    }

    public function penutupanDiputuskan(Permintaan $permintaan, bool $disetujui): void
    {
        $this->kirim($permintaan->anggota, new NotifikasiRangkul(
            jenis: 'penutupan.diputuskan',
            subjek: $disetujui ? 'Proses konsultasi ditutup' : 'Proses konsultasi dilanjutkan',
            judul: $disetujui ? 'Proses konsultasi Anda telah ditutup' : 'Proses konsultasi Anda dilanjutkan',
            paragraf: [
                $disetujui
                    ? "Komite telah menyetujui penutupan permintaan {$permintaan->kode}. Terima kasih telah mempercayakan cerita Anda kepada SPK Rangkul. Bila suatu saat Anda membutuhkan dukungan lagi, Anda selalu dapat mengajukan permintaan baru."
                    : "Komite meminta pendampingan untuk permintaan {$permintaan->kode} dilanjutkan. Konselor Anda akan tetap mendampingi.",
            ],
            teksTombol: 'Buka SPK Rangkul',
            tautan: route('permintaan.tampil', $permintaan),
        ));

        $this->kirim($permintaan->konselor, new NotifikasiRangkul(
            jenis: 'penutupan.diputuskan',
            subjek: ($disetujui ? 'Penutupan disetujui: ' : 'Penutupan dikembalikan: ').$permintaan->kode,
            judul: $disetujui ? 'Komite menyetujui penutupan kasus' : 'Komite mengembalikan usulan penutupan',
            paragraf: [
                $disetujui
                    ? "Permintaan {$permintaan->kode} kini berstatus selesai. Terima kasih atas pendampingan Anda."
                    : "Komite meminta pendampingan permintaan {$permintaan->kode} dilanjutkan. Baca catatan tinjauannya di halaman kasus.",
            ],
            teksTombol: 'Buka kasus',
            tautan: route('konseling.tampil', $permintaan),
        ));
    }

    public function penutupanDitolakAnggota(Permintaan $permintaan): void
    {
        $this->kirim($permintaan->konselor, new NotifikasiRangkul(
            jenis: 'penutupan.ditolak_anggota',
            subjek: "Anggota belum setuju menutup {$permintaan->kode}",
            judul: 'Anggota belum setuju menutup proses konsultasi',
            paragraf: ["Anggota pada permintaan {$permintaan->kode} memilih melanjutkan pendampingan."],
            teksTombol: 'Buka kasus',
            tautan: route('konseling.tampil', $permintaan),
        ));
    }

    /**
     * @param  User|Collection<int, User>|null  $penerima
     */
    private function kirim(User|Collection|null $penerima, NotifikasiRangkul $notifikasi): void
    {
        if ($penerima === null || ($penerima instanceof Collection && $penerima->isEmpty())) {
            return;
        }

        Notification::send($penerima, $notifikasi);
    }
}
