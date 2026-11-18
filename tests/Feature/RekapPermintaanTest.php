<?php

namespace Tests\Feature;

use App\Models\Permintaan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MemalsukanSpk;
use Tests\Concerns\MembuatKasus;
use Tests\TestCase;

class RekapPermintaanTest extends TestCase
{
    use MemalsukanSpk, MembuatKasus, RefreshDatabase;

    /**
     * @return list<array<string, string>>
     */
    private function bacaCsv(string $isi): array
    {
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, substr($isi, 3));
        rewind($handle);
        $kepala = fgetcsv($handle, null, ',', '"', '');
        $baris = [];

        while (($satu = fgetcsv($handle, null, ',', '"', '')) !== false) {
            $baris[] = array_combine($kepala, $satu);
        }

        return $baris;
    }

    public function test_komite_mengunduh_rekap_permintaan_lengkap(): void
    {
        $this->siapkanSpk();
        $komite = $this->anggotaKomite();
        $permintaan = $this->kasusBerlangsung($this->anggotaBiasa(['nama' => 'Rina Rekap']));
        $permintaan->jawabanAsesmen()->create([
            'nama_instrumen' => 'GAD-7 — gejala kecemasan',
            'isian' => ['instrumen' => [], 'jawaban' => [], 'skor' => [['subskala' => '', 'nama' => 'Skor total', 'nilai' => 11, 'label' => 'Sedang', 'saran' => '']], 'kritis' => []],
            'kritis' => false,
        ]);
        $permintaan->catatanSesi()->create(['penulis_id' => $permintaan->konselor_id, 'rangkuman' => 'Sesi pertama berjalan baik.', 'rujukan' => true, 'rujukan_ke' => 'Biro psikologi kampus']);
        $permintaan->pesan()->create(['pengirim_id' => $permintaan->user_id, 'isi' => 'Terima kasih sudah mendengarkan.']);
        Permintaan::factory()->draf()->create();

        $jawaban = $this->masukSebagai($komite)->get(route('kelola.rekap.unduh', 'permintaan'));
        $jawaban->assertOk();
        $baris = $this->bacaCsv($jawaban->streamedContent());

        $this->assertCount(1, $baris);
        $this->assertSame($permintaan->kode, $baris[0]['kode']);
        $this->assertSame('Rina Rekap', $baris[0]['nama']);
        $this->assertSame($permintaan->uraian, $baris[0]['uraian']);
        $this->assertSame('GAD-7 — gejala kecemasan: 11 (Sedang)', $baris[0]['asesmen']);
        $this->assertStringContainsString('Sesi pertama berjalan baik.', $baris[0]['catatan_sesi']);
        $this->assertSame('Biro psikologi kampus', $baris[0]['dirujuk_ke']);
        $this->assertSame('1', $baris[0]['jumlah_pesan']);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'rekap.diunduh', 'sasaran' => 'Permintaan layanan (lengkap)']);

        $percakapan = $this->bacaCsv($this->get(route('kelola.rekap.unduh', 'percakapan'))->streamedContent());
        $this->assertSame('Terima kasih sudah mendengarkan.', $percakapan[0]['isi']);
        $this->assertSame('anggota', $percakapan[0]['sebagai']);
    }

    public function test_hak_rekap_dipisah_per_jenis(): void
    {
        $this->siapkanSpk();
        $komite = $this->anggotaKomite();
        $administrator = User::factory()->create();
        $administrator->daftarPeran()->attach($this->peranBawaan('administrator'));

        $this->masukSebagai($komite);
        $this->get(route('kelola.rekap.index'))->assertOk()->assertSee('Permintaan layanan (lengkap)')->assertDontSee('Audit log');
        $this->get(route('kelola.rekap.unduh', 'audit'))->assertForbidden();

        $this->masukSebagai($administrator)->get(route('kelola.rekap.index'))->assertForbidden();
        $this->get(route('kelola.rekap.unduh', 'permintaan'))->assertForbidden();

        $this->masukSebagai(User::factory()->superadmin()->create())->get(route('kelola.rekap.index'))
            ->assertOk()->assertSee('Permintaan layanan (lengkap)')->assertSee('Audit log');
    }
}
