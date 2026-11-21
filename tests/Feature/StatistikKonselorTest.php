<?php

namespace Tests\Feature;

use App\Enums\StatusPertemuan;
use App\Models\KehadiranPercakapan;
use App\Models\Permintaan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MemalsukanSpk;
use Tests\Concerns\MembuatKasus;
use Tests\TestCase;

/**
 * Peer konselor melihat statistik layanannya sendiri: waktu layanan per bulan
 * dan riwayat kasus yang pernah didampingi.
 */
class StatistikKonselorTest extends TestCase
{
    use MemalsukanSpk, MembuatKasus, RefreshDatabase;

    private User $konselor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-11-20 20:00:00'));
        $this->konselor = $this->peerKonselor();
    }

    /**
     * Percakapan bersama selama `$menit` menit (dua pesan, kedua pihak hadir).
     */
    private function layani(Permintaan $permintaan, User $konselor, string $mulai, int $menit): void
    {
        $awal = Carbon::parse($mulai);

        foreach ([[$permintaan->anggota, 'anggota'], [$konselor, 'konselor']] as [$pengguna, $sebagai]) {
            KehadiranPercakapan::query()->create([
                'permintaan_id' => $permintaan->id,
                'user_id' => $pengguna->id,
                'sebagai' => $sebagai,
                'mulai_pada' => $awal,
                'terakhir_pada' => $awal->copy()->addMinutes($menit),
            ]);
        }

        foreach ([$awal, $awal->copy()->addMinutes($menit)] as $waktu) {
            $permintaan->pesan()->create(['pengirim_id' => $permintaan->user_id, 'isi' => 'Halo.'])->forceFill(['created_at' => $waktu])->save();
        }
    }

    public function test_konselor_melihat_waktu_layanan_per_bulan_dan_riwayat_kasusnya_sendiri(): void
    {
        $berjalan = $this->kasusBerlangsung(null, $this->konselor);
        $selesai = Permintaan::factory()->selesai($this->konselor, now()->subMonth())->create();
        $this->layani($berjalan, $this->konselor, '2026-11-10 10:00', 25);
        $this->layani($selesai, $this->konselor, '2026-10-05 10:00', 20);
        $berjalan->pertemuan()->create(['diusulkan_oleh' => $this->konselor->id, 'mulai_pada' => Carbon::parse('2026-11-12 19:00'), 'durasi_menit' => 60, 'status' => StatusPertemuan::Terjadwal]);
        $berjalan->pesan()->create(['pengirim_id' => $this->konselor->id, 'isi' => 'Balasan saya.']);

        $konselorLain = $this->peerKonselor();
        $kasusLain = $this->kasusBerlangsung(null, $konselorLain);
        $this->layani($kasusLain, $konselorLain, '2026-11-11 10:00', 15);

        $respons = $this->masukSebagai($this->konselor)->get(route('konseling.statistik'))->assertOk();

        $respons->assertViewHas('bulanIni', fn (array $bulan): bool => $bulan['menit_total'] === 85
            && $bulan['menit_percakapan'] === 25
            && $bulan['menit_pertemuan'] === 60
            && $bulan['kasus'] === 1);
        $respons->assertViewHas('menitSepanjangWaktu', 105);
        $respons->assertViewHas('perBulan', fn ($perBulan): bool => $perBulan->count() === 12
            && $perBulan->firstWhere(fn ($bulan) => $bulan['bulan']->format('Y-m') === '2026-10')['menit_total'] === 20);
        $respons->assertViewHas('riwayat', fn ($riwayat): bool => $riwayat->pluck('id')->sort()->values()->all() === collect([$berjalan->id, $selesai->id])->sort()->values()->all()
            && $riwayat->first()->is($berjalan)
            && $riwayat->firstWhere('id', $berjalan->id)->pesan_saya_count === 1
            && $riwayat->firstWhere('id', $berjalan->id)->pertemuan_terlaksana_count === 1);

        $respons->assertSee($berjalan->kode)
            ->assertSee($selesai->kode)
            ->assertDontSee($kasusLain->kode)
            ->assertSee('1 jam 25 menit')
            ->assertSee(route('konseling.tampil', $berjalan), false);
    }

    public function test_kasus_yang_dialihkan_tetap_tercatat_dengan_waktunya(): void
    {
        $kasus = $this->kasusBerlangsung(null, $this->konselor);
        $this->layani($kasus, $this->konselor, '2026-11-03 09:00', 30);
        $kasus->update(['konselor_id' => $this->peerKonselor()->id]);

        $this->masukSebagai($this->konselor)->get(route('konseling.statistik'))
            ->assertOk()
            ->assertSee($kasus->kode)
            ->assertSee('Dialihkan')
            ->assertDontSee(route('konseling.tampil', $kasus), false)
            ->assertViewHas('menitSepanjangWaktu', 30);
    }

    public function test_menu_dan_halaman_hanya_untuk_peer_konselor(): void
    {
        $this->masukSebagai($this->konselor)->get(route('konseling.index'))->assertSee('Statistik layanan saya');

        $this->masukSebagai($this->anggotaBiasa())->get(route('konseling.statistik'))->assertForbidden();
        $this->masukSebagai($this->anggotaKomite())->get(route('konseling.statistik'))->assertForbidden();
    }

    public function test_konselor_baru_melihat_halaman_kosong(): void
    {
        $this->masukSebagai($this->konselor)->get(route('konseling.statistik'))
            ->assertOk()
            ->assertSee('Belum ada waktu layanan yang tercatat.')
            ->assertSee('Belum ada kasus yang pernah Anda dampingi.');
    }
}
