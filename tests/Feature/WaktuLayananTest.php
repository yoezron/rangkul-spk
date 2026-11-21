<?php

namespace Tests\Feature;

use App\Enums\StatusPertemuan;
use App\Models\KehadiranPercakapan;
use App\Models\Permintaan;
use App\Models\User;
use App\Services\PenghitungWaktuLayanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MemalsukanSpk;
use Tests\Concerns\MembuatKasus;
use Tests\TestCase;

/**
 * Waktu layanan konselor per bulan: kehadiran kedua pihak × percakapan aktif
 * (jeda ≤ 30 menit) + pertemuan daring yang sudah berlangsung.
 */
class WaktuLayananTest extends TestCase
{
    use MemalsukanSpk, MembuatKasus, RefreshDatabase;

    private User $konselor;

    private User $anggota;

    private Permintaan $permintaan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-20 20:00:00'));
        $this->konselor = $this->peerKonselor(['nama' => 'Konselor Sari', 'nomor_anggota' => 'SPK-0042', 'kampus' => 'Universitas Beta']);
        $this->anggota = $this->anggotaBiasa();
        $this->permintaan = $this->kasusBerlangsung($this->anggota, $this->konselor);
    }

    private function hadir(User $pengguna, string $sebagai, string $mulai, string $selesai, ?Permintaan $permintaan = null): void
    {
        KehadiranPercakapan::query()->create([
            'permintaan_id' => ($permintaan ?? $this->permintaan)->id,
            'user_id' => $pengguna->id,
            'sebagai' => $sebagai,
            'mulai_pada' => Carbon::parse($mulai),
            'terakhir_pada' => Carbon::parse($selesai),
        ]);
    }

    private function pesan(User $pengirim, string $waktu, ?Permintaan $permintaan = null): void
    {
        $pesan = ($permintaan ?? $this->permintaan)->pesan()->create(['pengirim_id' => $pengirim->id, 'isi' => 'Pesan.']);
        $pesan->forceFill(['created_at' => Carbon::parse($waktu)])->save();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function barisKonselor(string $bulan, ?User $konselor = null): ?array
    {
        $konselor ??= $this->konselor;

        return collect(app(PenghitungWaktuLayanan::class)->rekap(Carbon::parse($bulan.'-01')))
            ->first(fn (array $baris): bool => $baris['konselor']->is($konselor));
    }

    public function test_polling_peserta_mencatat_kehadiran_dan_rentang_baru_setelah_keluar(): void
    {
        $this->masukSebagai($this->konselor)->getJson(route('percakapan.daftar', $this->permintaan))->assertOk();
        $this->travel(10)->seconds();
        $this->getJson(route('percakapan.daftar', $this->permintaan))->assertOk();
        $this->travel(20)->seconds();
        $this->getJson(route('percakapan.daftar', $this->permintaan))->assertOk();

        $kehadiran = KehadiranPercakapan::query()->sole();
        $this->assertSame('konselor', $kehadiran->sebagai);
        $this->assertSame(30, (int) $kehadiran->mulai_pada->diffInSeconds($kehadiran->terakhir_pada));

        // Menutup halaman lima menit, lalu kembali: rentang baru.
        $this->travel(5)->minutes();
        $this->getJson(route('percakapan.daftar', $this->permintaan))->assertOk();
        $this->masukSebagai($this->anggota)->getJson(route('percakapan.daftar', $this->permintaan))->assertOk();

        $this->assertSame(2, KehadiranPercakapan::query()->where('user_id', $this->konselor->id)->count());
        $this->assertSame(1, KehadiranPercakapan::query()->where('user_id', $this->anggota->id)->where('sebagai', 'anggota')->count());
    }

    public function test_komite_yang_memantau_tidak_tercatat_hadir(): void
    {
        $this->masukSebagai($this->anggotaKomite())->getJson(route('percakapan.daftar', $this->permintaan))->assertOk();

        $this->assertSame(0, KehadiranPercakapan::query()->count());
    }

    public function test_jeda_lebih_dari_30_menit_tidak_dihitung(): void
    {
        $this->hadir($this->anggota, 'anggota', '2026-11-10 10:00', '2026-11-10 12:00');
        $this->hadir($this->konselor, 'konselor', '2026-11-10 10:00', '2026-11-10 12:00');
        $this->pesan($this->anggota, '2026-11-10 10:05');
        $this->pesan($this->konselor, '2026-11-10 10:15');
        $this->pesan($this->anggota, '2026-11-10 10:25');
        $this->pesan($this->konselor, '2026-11-10 11:10'); // jeda 45 menit: tidak dihitung
        $this->pesan($this->anggota, '2026-11-10 11:40'); // jeda tepat 30 menit: dihitung

        $baris = $this->barisKonselor('2026-11');

        $this->assertSame(50, $baris['menit_percakapan']);
        $this->assertSame(0, $baris['menit_pertemuan']);
        $this->assertSame(50, $baris['menit_total']);
        $this->assertSame($this->permintaan->kode, $baris['kasus'][0]['permintaan']->kode);
    }

    public function test_hitungan_berhenti_saat_salah_satu_pihak_keluar(): void
    {
        $this->hadir($this->konselor, 'konselor', '2026-11-10 10:00', '2026-11-10 11:00');
        $this->hadir($this->anggota, 'anggota', '2026-11-10 10:00', '2026-11-10 10:12');
        $this->hadir($this->anggota, 'anggota', '2026-11-10 10:20', '2026-11-10 11:00');
        foreach (['10:05', '10:15', '10:25', '10:30'] as $jam) {
            $this->pesan($this->anggota, "2026-11-10 {$jam}");
        }

        // 10:05–10:12 (anggota lalu keluar) + 10:20–10:30 = 17 menit.
        $this->assertSame(17, $this->barisKonselor('2026-11')['menit_total']);
    }

    public function test_pesan_tanpa_kehadiran_konselor_tidak_dihitung(): void
    {
        $this->hadir($this->anggota, 'anggota', '2026-11-10 10:00', '2026-11-10 11:00');
        $this->pesan($this->anggota, '2026-11-10 10:05');
        $this->pesan($this->anggota, '2026-11-10 10:15');

        $this->assertNull($this->barisKonselor('2026-11'));
    }

    public function test_pertemuan_daring_yang_sudah_lewat_dihitung_tanpa_hitung_ganda(): void
    {
        $this->permintaan->pertemuan()->create(['diusulkan_oleh' => $this->konselor->id, 'mulai_pada' => Carbon::parse('2026-11-12 19:00'), 'durasi_menit' => 60, 'status' => StatusPertemuan::Terjadwal]);
        $this->permintaan->pertemuan()->create(['diusulkan_oleh' => $this->konselor->id, 'mulai_pada' => Carbon::parse('2026-11-25 19:00'), 'durasi_menit' => 60, 'status' => StatusPertemuan::Terjadwal]);
        $this->permintaan->pertemuan()->create(['diusulkan_oleh' => $this->konselor->id, 'mulai_pada' => Carbon::parse('2026-11-05 19:00'), 'durasi_menit' => 45, 'status' => StatusPertemuan::Dibatalkan]);

        // Percakapan 18:50–19:10 bertumpuk 10 menit dengan pertemuan.
        $this->hadir($this->anggota, 'anggota', '2026-11-12 18:45', '2026-11-12 19:15');
        $this->hadir($this->konselor, 'konselor', '2026-11-12 18:45', '2026-11-12 19:15');
        $this->pesan($this->anggota, '2026-11-12 18:50');
        $this->pesan($this->konselor, '2026-11-12 19:10');

        $baris = $this->barisKonselor('2026-11');

        $this->assertSame(60, $baris['menit_pertemuan']);
        $this->assertSame(10, $baris['menit_percakapan']);
        $this->assertSame(70, $baris['menit_total']);
    }

    public function test_waktu_dipisah_per_bulan(): void
    {
        $this->hadir($this->anggota, 'anggota', '2026-10-31 23:40', '2026-11-01 00:30');
        $this->hadir($this->konselor, 'konselor', '2026-10-31 23:40', '2026-11-01 00:30');
        $this->pesan($this->anggota, '2026-10-31 23:50');
        $this->pesan($this->konselor, '2026-11-01 00:05');
        $this->pesan($this->anggota, '2026-11-01 00:20');

        $this->assertSame(10, $this->barisKonselor('2026-10')['menit_total']);
        $this->assertSame(20, $this->barisKonselor('2026-11')['menit_total']);
    }

    public function test_waktu_dihitung_untuk_konselor_yang_hadir_saat_itu(): void
    {
        $penggantinya = $this->peerKonselor(['nama' => 'Konselor Tono']);
        $this->hadir($this->anggota, 'anggota', '2026-11-10 10:00', '2026-11-10 12:00');
        $this->hadir($this->konselor, 'konselor', '2026-11-10 10:00', '2026-11-10 10:30');
        $this->hadir($penggantinya, 'konselor', '2026-11-10 11:00', '2026-11-10 12:00');
        foreach (['10:00', '10:20', '10:40', '11:00', '11:15'] as $jam) {
            $this->pesan($this->anggota, "2026-11-10 {$jam}");
        }

        $this->assertSame(30, $this->barisKonselor('2026-11')['menit_total']);
        $this->assertSame(15, $this->barisKonselor('2026-11', $penggantinya)['menit_total']);
    }

    public function test_komite_melihat_statistik_dan_mengunduh_csv_bulanan(): void
    {
        $komite = $this->anggotaKomite();
        $this->hadir($this->anggota, 'anggota', '2026-11-10 10:00', '2026-11-10 11:00');
        $this->hadir($this->konselor, 'konselor', '2026-11-10 10:00', '2026-11-10 11:00');
        $this->pesan($this->anggota, '2026-11-10 10:00');
        $this->pesan($this->konselor, '2026-11-10 10:25');

        $this->masukSebagai($komite)->get(route('kelola.waktu-layanan.index', ['bulan' => '2026-11']))
            ->assertOk()
            ->assertSee('Konselor Sari')
            ->assertSee('0 jam 25 menit');

        $ringkasan = $this->get(route('kelola.waktu-layanan.unduh', ['bulan' => '2026-11']));
        $ringkasan->assertOk()->assertDownload('rangkul-waktu-layanan-2026-11.csv');
        $isi = $ringkasan->streamedContent();
        $this->assertStringContainsString('2026-11,"Konselor Sari",SPK-0042,"Universitas Beta"', $isi);
        $this->assertStringContainsString(',1,25,0,25,"0 jam 25 menit"', $isi);
        $this->assertStringContainsString('TOTAL', $isi);

        $rincian = $this->get(route('kelola.waktu-layanan.unduh', ['bulan' => '2026-11', 'isi' => 'rincian', 'pemisah' => 'titik_koma']))->streamedContent();
        $this->assertStringContainsString($this->permintaan->kode.';25;0;25', $rincian);
        $this->assertStringNotContainsString($this->anggota->nama, $rincian);

        $this->assertDatabaseHas('audit_log', ['aksi' => 'rekap.diunduh', 'sasaran' => 'Waktu layanan konselor 2026-11']);
        $this->get(route('kelola.waktu-layanan.index', ['bulan' => '2026-10']))->assertOk()->assertSee('Belum ada waktu layanan');
    }

    public function test_hanya_pemegang_hak_yang_dapat_membuka(): void
    {
        $this->masukSebagai($this->konselor)->get(route('kelola.waktu-layanan.index'))->assertForbidden();
        $this->masukSebagai($this->anggota)->get(route('kelola.waktu-layanan.unduh'))->assertForbidden();
        $this->masukSebagai(User::factory()->superadmin()->create())->get(route('kelola.waktu-layanan.index'))->assertOk();
    }
}
