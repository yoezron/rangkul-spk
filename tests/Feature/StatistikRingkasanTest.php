<?php

namespace Tests\Feature;

use App\Enums\StatusPermintaan;
use App\Models\Instrumen;
use App\Models\Permintaan;
use App\Models\User;
use App\Services\StatistikLayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MemalsukanSpk;
use Tests\Concerns\MembuatKasus;
use Tests\TestCase;

class StatistikRingkasanTest extends TestCase
{
    use MemalsukanSpk, MembuatKasus, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        $this->travelTo(now()->setDate(2026, 11, 15)->setTime(10, 0));
    }

    private function statistik(): StatistikLayanan
    {
        return app(StatistikLayanan::class);
    }

    public function test_tren_bulanan_menghitung_masuk_dan_selesai_per_bulan(): void
    {
        Permintaan::factory()->count(2)->create(['diajukan_pada' => now()->setDate(2026, 11, 3)]);
        Permintaan::factory()->create([
            'diajukan_pada' => now()->setDate(2026, 9, 10),
            'status' => StatusPermintaan::Selesai,
            'selesai_pada' => now()->setDate(2026, 10, 20),
        ]);
        Permintaan::factory()->create(['diajukan_pada' => now()->setDate(2025, 10, 1)]);
        Permintaan::factory()->draf()->create();

        $tren = collect($this->statistik()->trenBulanan())->keyBy('bulan');

        $this->assertCount(12, $tren);
        $this->assertSame('Desember 2025', $tren->keys()->first());
        $this->assertSame(2, $tren['November 2026']['masuk']);
        $this->assertSame(1, $tren['September 2026']['masuk']);
        $this->assertSame(1, $tren['Oktober 2026']['selesai']);
        $this->assertSame(3, $tren->sum('masuk'));
    }

    public function test_alur_terbuka_dan_beban_konselor(): void
    {
        $sari = $this->peerKonselor(['nama' => 'Sari']);
        $budi = $this->peerKonselor(['nama' => 'Budi']);
        $this->kasusBerlangsung(konselor: $sari);
        $this->kasusBerlangsung(konselor: $sari);
        $this->kasusBerlangsung(konselor: $budi);
        Permintaan::factory()->create();
        Permintaan::factory()->create(['status' => StatusPermintaan::Selesai, 'konselor_id' => $budi->id]);

        $alur = collect($this->statistik()->alurTerbuka())->pluck('nilai', 'label');
        $this->assertSame(1, $alur['Baru masuk']);
        $this->assertSame(3, $alur['Pendampingan berlangsung']);

        $this->assertSame([['label' => 'Sari', 'nilai' => 2], ['label' => 'Budi', 'nilai' => 1]], $this->statistik()->bebanKonselor());
    }

    public function test_topik_dihitung_dari_isian_terenkripsi_dan_diurutkan(): void
    {
        Permintaan::factory()->create(['topik' => ['burnout', 'stres_kerja']]);
        Permintaan::factory()->create(['topik' => ['burnout']]);
        Permintaan::factory()->create(['topik' => ['keluarga'], 'diajukan_pada' => now()->subYears(2)]);

        $topik = $this->statistik()->topik();

        $this->assertSame(2, $topik['total']);
        $this->assertSame(['label' => 'Beban kerja, burnout, dan kelelahan', 'nilai' => 2], $topik['baris'][0]);
        $this->assertSame(1, collect($topik['baris'])->firstWhere('label', 'Stres dan tekanan pekerjaan')['nilai']);
        $this->assertSame(0, collect($topik['baris'])->firstWhere('label', 'Persoalan keluarga atau pasangan')['nilai']);
    }

    public function test_tingkat_gejala_mengikuti_urutan_rentang_instrumen(): void
    {
        $phq = Instrumen::query()->where('kunci', 'phq9')->sole();

        foreach (['Sedang', 'Sedang', 'Minimal', 'Berat'] as $label) {
            Permintaan::factory()->create()->jawabanAsesmen()->create([
                'instrumen_id' => $phq->id,
                'nama_instrumen' => $phq->nama,
                'isian' => ['instrumen' => ['kunci' => 'phq9'], 'jawaban' => [], 'skor' => [['subskala' => '', 'nama' => 'Skor total', 'nilai' => 0, 'label' => $label, 'saran' => '']], 'kritis' => []],
                'kritis' => false,
            ]);
        }

        $gejala = collect($this->statistik()->tingkatGejala())->keyBy('judul');
        $phqHasil = $gejala['PHQ-9 — gejala depresi'];

        $this->assertSame(4, $phqHasil['total']);
        $this->assertSame(['Minimal', 'Ringan', 'Sedang', 'Cukup berat', 'Berat'], array_column($phqHasil['bagian'], 'label'));
        $this->assertSame([1, 0, 2, 0, 1], array_column($phqHasil['bagian'], 'nilai'));
        $this->assertSame(0, $gejala['GAD-7 — gejala kecemasan']['total']);
        $this->assertArrayNotHasKey('SRQ-20 — masalah mental emosional', $gejala->all());
    }

    public function test_median_waktu_tunggu_penugasan(): void
    {
        foreach ([2, 6, 30] as $jam) {
            Permintaan::factory()->create(['diajukan_pada' => now()->subDays(3), 'ditugaskan_pada' => now()->subDays(3)->addHours($jam)]);
        }
        Permintaan::factory()->create(['diajukan_pada' => now()->subDays(200), 'ditugaskan_pada' => now()->subDays(100)]);

        $this->assertSame(6.0, $this->statistik()->medianJamTunggu());
    }

    public function test_komite_melihat_grafik_layanan_sedangkan_pengelola_lain_tidak(): void
    {
        Permintaan::factory()->create(['topik' => ['perundungan']]);

        $this->masukSebagai($this->anggotaKomite())->get(route('kelola.dasbor'))
            ->assertOk()
            ->assertSee('Permintaan per bulan')
            ->assertSee('Perundungan, diskriminasi, atau pelecehan')
            ->assertSee('Tingkat gejala saat pengajuan')
            ->assertSee('Median waktu tunggu');

        $administrator = User::factory()->create();
        $administrator->daftarPeran()->attach($this->peranBawaan('administrator'));

        $this->masukSebagai($administrator)->get(route('kelola.dasbor'))
            ->assertOk()
            ->assertDontSee('Permintaan per bulan')
            ->assertDontSee('Perundungan, diskriminasi, atau pelecehan')
            ->assertSee('Anggota per wilayah');
    }

    public function test_ringkasan_tetap_tampil_tanpa_data(): void
    {
        $this->masukSebagai(User::factory()->superadmin()->create())->get(route('kelola.dasbor'))
            ->assertOk()
            ->assertSee('Belum ada permintaan dalam 12 bulan terakhir.')
            ->assertSee('Tidak ada kasus terbuka.');
    }
}
