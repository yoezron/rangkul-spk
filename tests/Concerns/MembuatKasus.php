<?php

namespace Tests\Concerns;

use App\Models\Instrumen;
use App\Models\Peran;
use App\Models\Permintaan;
use App\Models\User;
use App\Services\Asesmen\PenilaiAsesmen;

/**
 * Pemeran dan isian baku untuk menguji alur layanan.
 */
trait MembuatKasus
{
    protected function peranBawaan(string $kunci): Peran
    {
        return Peran::query()->where('kunci', $kunci)->sole();
    }

    protected function anggotaKomite(array $atribut = []): User
    {
        $komite = User::factory()->create(['peran' => 'Pengurus', ...$atribut]);
        $komite->daftarPeran()->attach($this->peranBawaan('komite'));

        return $komite;
    }

    protected function peerKonselor(array $atribut = []): User
    {
        $konselor = User::factory()->create(['peran' => 'Anggota', ...$atribut]);
        $konselor->daftarPeran()->attach($this->peranBawaan('peer_konselor'));

        return $konselor;
    }

    protected function anggotaBiasa(array $atribut = []): User
    {
        return User::factory()->create(['peran' => 'Anggota', 'kampus' => 'Universitas Alfa', ...$atribut]);
    }

    protected function kasusBerlangsung(?User $anggota = null, ?User $konselor = null): Permintaan
    {
        return Permintaan::factory()
            ->for($anggota ?? $this->anggotaBiasa(), 'anggota')
            ->berlangsung($konselor ?? $this->peerKonselor())
            ->create();
    }

    /**
     * Jawaban untuk semua instrumen yang dipakai; `$pilih` menerima (instrumen, butir, pilihan) dan mengembalikan nilai.
     *
     * @return array<int, array<string, int|float>>
     */
    protected function jawabanAsesmen(?callable $pilih = null): array
    {
        $jawaban = [];

        foreach (Instrumen::query()->dipakai()->get() as $instrumen) {
            foreach ($instrumen->butir as $butir) {
                $pilihan = PenilaiAsesmen::pilihanButir($instrumen->salinan(), $butir);
                $jawaban[$instrumen->id][$butir['kunci']] = $pilih ? $pilih($instrumen, $butir, $pilihan) : end($pilihan)['nilai'];
            }
        }

        return $jawaban;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function isianLangkah(array $timpa = []): array
    {
        return array_replace([
            1 => [
                'persetujuan_layanan' => '1',
                'persetujuan_data' => '1',
                'keselamatan' => ['pikiran_menyakiti_diri' => 'tidak', 'terancam' => 'tidak', 'butuh_segera' => 'tidak'],
            ],
            2 => ['topik' => ['stres_kerja', 'burnout'], 'topik_lainnya' => ''],
            3 => ['uraian' => 'Setiap malam saya memeriksa tugas mahasiswa sampai larut dan merasa tidak sanggup lagi.', 'harapan' => 'Ingin didengarkan.'],
            4 => ['jawaban' => $this->jawabanAsesmen(fn ($instrumen, $butir, $pilihan) => $pilihan[0]['nilai'] === 100 ? 25 : 0)],
            5 => [
                'bentuk_layanan' => 'chat',
                'waktu_luang' => 'Malam hari',
                'hindari_kampus_sama' => '1',
                'gender_konselor' => 'perempuan',
                'notif_surel' => '1',
                'notif_whatsapp' => '0',
                'nomor_whatsapp' => '',
            ],
        ], $timpa);
    }

    /**
     * Isi seluruh langkah formulir sebagai pengguna yang sedang masuk lalu kirim.
     */
    protected function ajukanPermintaan(array $timpa = []): Permintaan
    {
        $this->post(route('permintaan.mulai'));
        $permintaan = Permintaan::query()->latest('id')->firstOrFail();

        foreach ($this->isianLangkah($timpa) as $langkah => $isian) {
            $this->put(route('permintaan.simpan', [$permintaan, $langkah]), $isian)->assertSessionHasNoErrors();
        }

        $this->post(route('permintaan.kirim', $permintaan))->assertRedirectToRoute('permintaan.tampil', $permintaan);

        return $permintaan->refresh();
    }
}
