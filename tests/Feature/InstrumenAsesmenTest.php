<?php

namespace Tests\Feature;

use App\Models\Instrumen;
use App\Models\User;
use App\Services\Asesmen\PenilaiAsesmen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MemalsukanSpk;
use Tests\Concerns\MembuatKasus;
use Tests\TestCase;

class InstrumenAsesmenTest extends TestCase
{
    use MemalsukanSpk, MembuatKasus, RefreshDatabase;

    private User $komite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        $this->komite = $this->anggotaKomite();
    }

    /**
     * @return array<string, mixed>
     */
    private function formulir(array $timpa = []): array
    {
        return array_replace([
            'nama' => 'Kesejahteraan kerja',
            'instruksi' => 'Seberapa sering dalam sebulan terakhir…',
            'sumber' => 'Disusun komite Rangkul',
            'metode' => 'jumlah',
            'pengali' => 1,
            'urutan' => 60,
            'aktif' => '1',
            'pilihan' => [['label' => 'Tidak pernah', 'nilai' => 0], ['label' => 'Kadang', 'nilai' => 1], ['label' => 'Sering', 'nilai' => 2], ['label' => '', 'nilai' => '']],
            'subskala' => [['kunci' => 'b', 'nama' => 'Beban'], ['kunci' => 'd', 'nama' => 'Dukungan']],
            'rentang' => [
                ['subskala' => 'b', 'min' => 0, 'max' => 2, 'label' => 'Ringan', 'saran' => ''],
                ['subskala' => 'b', 'min' => 3, 'max' => 4, 'label' => 'Berat', 'saran' => 'Bahas beban kerja'],
                ['subskala' => 'd', 'min' => 0, 'max' => 4, 'label' => 'Ada', 'saran' => ''],
                ['subskala' => '', 'min' => '', 'max' => '', 'label' => '', 'saran' => ''],
            ],
            'butir' => [
                ['kunci' => '', 'teks' => 'Saya membawa pekerjaan ke rumah', 'subskala' => 'b', 'terbalik' => '0', 'kritis_min' => '', 'pilihan_khusus' => ''],
                ['kunci' => '', 'teks' => 'Saya tidak sanggup lagi bekerja', 'subskala' => 'b', 'terbalik' => '0', 'kritis_min' => '2', 'pilihan_khusus' => ''],
                ['kunci' => '', 'teks' => 'Rekan kerja mendukung saya', 'subskala' => 'd', 'terbalik' => '1', 'kritis_min' => '', 'pilihan_khusus' => 'Tidak=0; Ya=4'],
                ['kunci' => '', 'teks' => '', 'subskala' => '', 'terbalik' => '0', 'kritis_min' => '', 'pilihan_khusus' => ''],
            ],
        ], $timpa);
    }

    public function test_komite_menyusun_instrumen_dengan_subskala_dan_butir_kritis(): void
    {
        $this->masukSebagai($this->komite)
            ->post(route('kelola.instrumen.store'), $this->formulir())
            ->assertSessionHasNoErrors();

        $instrumen = Instrumen::query()->where('nama', 'Kesejahteraan kerja')->sole();
        $this->assertSame('kesejahteraan_kerja', $instrumen->kunci);
        $this->assertCount(3, $instrumen->pilihan);
        $this->assertSame(['b' => 'Beban', 'd' => 'Dukungan'], $instrumen->subskala);
        $this->assertCount(3, $instrumen->butir);
        $this->assertSame([['label' => 'Tidak', 'nilai' => 0], ['label' => 'Ya', 'nilai' => 4]], $instrumen->butir[2]['pilihan']);
        $this->assertTrue($instrumen->aktif);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'instrumen.dibuat']);

        [$satu, $dua, $tiga] = array_column($instrumen->butir, 'kunci');
        $hasil = (new PenilaiAsesmen)->nilai($instrumen->salinan(), [$satu => 1, $dua => 2, $tiga => 4]);

        $this->assertSame(['Beban', 3.0, 'Berat'], [$hasil['skor'][0]['nama'], $hasil['skor'][0]['nilai'], $hasil['skor'][0]['label']]);
        $this->assertSame(['Dukungan', 0.0], [$hasil['skor'][1]['nama'], $hasil['skor'][1]['nilai']]);
        $this->assertCount(1, $hasil['kritis']);
    }

    public function test_mengubah_instrumen_mempertahankan_kunci_butir_lama(): void
    {
        $instrumen = Instrumen::query()->where('kunci', 'gad7')->sole();
        $kunciLama = array_column($instrumen->butir, 'kunci');

        $butir = array_map(fn (array $satu) => [...$satu, 'teks' => $satu['teks'].' (direvisi)', 'pilihan_khusus' => '', 'terbalik' => '0', 'kritis_min' => ''], $instrumen->butir);
        $pilihan = $instrumen->pilihan;

        $this->masukSebagai($this->komite)->put(route('kelola.instrumen.update', $instrumen), $this->formulir([
            'nama' => $instrumen->nama, 'subskala' => [], 'rentang' => $instrumen->rentang, 'pilihan' => $pilihan, 'butir' => $butir,
        ]))->assertSessionHasNoErrors();

        $instrumen->refresh();
        $this->assertSame($kunciLama, array_column($instrumen->butir, 'kunci'));
        $this->assertStringEndsWith('(direvisi)', $instrumen->butir[0]['teks']);
    }

    public function test_isian_instrumen_yang_tidak_sah_ditolak(): void
    {
        $this->masukSebagai($this->komite);

        $this->post(route('kelola.instrumen.store'), $this->formulir(['pilihan' => [['label' => 'Satu-satunya', 'nilai' => 0]]]))->assertSessionHasErrors('pilihan');
        $this->post(route('kelola.instrumen.store'), $this->formulir(['butir' => [['teks' => 'Butir', 'subskala' => 'x']]]))->assertSessionHasErrors('butir');
        $this->post(route('kelola.instrumen.store'), $this->formulir(['rentang' => [['subskala' => '', 'min' => 5, 'max' => 1, 'label' => 'Terbalik']]]))->assertSessionHasErrors('rentang');

        $this->assertDatabaseMissing('instrumen', ['nama' => 'Kesejahteraan kerja']);
    }

    public function test_instrumen_yang_sudah_dijawab_tidak_dapat_dihapus(): void
    {
        $phq = Instrumen::query()->where('kunci', 'phq9')->sole();
        $this->kasusBerlangsung()->jawabanAsesmen()->create(['instrumen_id' => $phq->id, 'nama_instrumen' => $phq->nama, 'isian' => [], 'kritis' => false]);
        $srq = Instrumen::query()->where('kunci', 'srq20')->sole();

        $this->masukSebagai($this->komite);
        $this->delete(route('kelola.instrumen.destroy', $phq))->assertSessionHasErrors('instrumen');
        $this->assertModelExists($phq);

        $this->delete(route('kelola.instrumen.destroy', $srq))->assertRedirectToRoute('kelola.instrumen.index');
        $this->assertModelMissing($srq);
    }

    public function test_komite_mengaktifkan_instrumen_untuk_formulir(): void
    {
        $dass = Instrumen::query()->where('kunci', 'dass21')->sole();

        $this->masukSebagai($this->komite)->post(route('kelola.instrumen.aktif', $dass), ['aktif' => '1']);

        $this->assertTrue($dass->refresh()->aktif);
        $this->assertContains('dass21', Instrumen::query()->dipakai()->pluck('kunci')->all());
    }

    public function test_skor_dass21_per_subskala_dikali_dua(): void
    {
        $dass = Instrumen::query()->where('kunci', 'dass21')->sole();
        $jawaban = collect($dass->butir)->mapWithKeys(fn (array $butir) => [$butir['kunci'] => $butir['subskala'] === 'd' ? 2 : 0])->all();

        $skor = collect((new PenilaiAsesmen)->nilai($dass->salinan(), $jawaban)['skor'])->keyBy('subskala');

        $this->assertSame(28.0, $skor['d']['nilai']);
        $this->assertSame('Sangat berat', $skor['d']['label']);
        $this->assertSame(0.0, $skor['a']['nilai']);
        $this->assertSame('Normal', $skor['s']['label']);
    }

    public function test_pembangun_asesmen_hanya_untuk_pemegang_hak(): void
    {
        $this->masukSebagai($this->peerKonselor())->get(route('kelola.instrumen.index'))->assertForbidden();
        $this->masukSebagai($this->komite)->get(route('kelola.instrumen.index'))->assertOk()->assertSee('PHQ-9');
        $this->get(route('kelola.instrumen.edit', Instrumen::query()->where('kunci', 'cbi_kerja')->sole()))->assertOk()->assertSee('Sangat tinggi=100');
    }
}
