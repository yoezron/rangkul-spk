<?php

namespace Tests\Feature;

use App\Enums\StatusPermintaan;
use App\Enums\StatusPertemuan;
use App\Models\Permintaan;
use App\Models\User;
use App\Notifications\NotifikasiRangkul;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MemalsukanSpk;
use Tests\Concerns\MembuatKasus;
use Tests\TestCase;

/**
 * Laporan ke komite tentang pendampingan yang tanpa aktivitas lebih dari 48 jam.
 */
class KasusMenggantungTest extends TestCase
{
    use MemalsukanSpk, MembuatKasus, RefreshDatabase;

    private User $komite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        Notification::fake();
        $this->komite = $this->anggotaKomite();
    }

    private function kasusDitugaskan(int $jamLalu): Permintaan
    {
        $permintaan = $this->kasusBerlangsung();
        $permintaan->update(['ditugaskan_pada' => now()->subHours($jamLalu)]);

        return $permintaan;
    }

    private function pesanLama(Permintaan $permintaan, int $jamLalu, ?int $pengirim = null): void
    {
        $pesan = $permintaan->pesan()->create(['pengirim_id' => $pengirim ?? $permintaan->user_id, 'isi' => 'Halo.']);
        $pesan->forceFill(['created_at' => now()->subHours($jamLalu)])->save();
    }

    private function jumlahLaporan(): int
    {
        return Notification::sent($this->komite, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'kasus.menggantung')->count();
    }

    public function test_kasus_tanpa_aktivitas_48_jam_dilaporkan_ke_komite_dalam_satu_ringkasan(): void
    {
        $diam = $this->kasusDitugaskan(100);
        $this->pesanLama($diam, 50);
        $diamTanpaPesan = $this->kasusDitugaskan(60);
        $aktif = $this->kasusDitugaskan(100);
        $this->pesanLama($aktif, 5);
        $baruDitugaskan = $this->kasusDitugaskan(10);
        $adaJadwal = $this->kasusDitugaskan(100);
        $adaJadwal->pertemuan()->create(['mulai_pada' => now()->addDays(3), 'durasi_menit' => 60, 'status' => StatusPertemuan::Terjadwal]);
        $selesai = Permintaan::factory()->selesai()->create(['ditugaskan_pada' => now()->subDays(10)]);

        $this->artisan('rangkul:ingatkan-kasus-menggantung')->assertSuccessful();

        Notification::assertSentTo($this->komite, NotifikasiRangkul::class, function (NotifikasiRangkul $notifikasi, array $saluran, User $kepada) use ($diam, $diamTanpaPesan, $aktif, $baruDitugaskan, $adaJadwal, $selesai): bool {
            $teks = $notifikasi->toWhatsapp($kepada);

            return $notifikasi->jenis === 'kasus.menggantung'
                && str_contains($teks, $diam->kode)
                && str_contains($teks, $diamTanpaPesan->kode)
                && ! str_contains($teks, $aktif->kode)
                && ! str_contains($teks, $baruDitugaskan->kode)
                && ! str_contains($teks, $adaJadwal->kode)
                && ! str_contains($teks, $selesai->kode)
                && str_contains($teks, route('kelola.permintaan.index', ['status' => 'menggantung']));
        });
        $this->assertSame(1, $this->jumlahLaporan());
        Notification::assertNotSentTo($diam->konselor, NotifikasiRangkul::class);
    }

    public function test_kasus_yang_sama_tidak_dilaporkan_ulang_sampai_ada_aktivitas_baru(): void
    {
        $permintaan = $this->kasusDitugaskan(100);
        $this->pesanLama($permintaan, 50);

        $this->artisan('rangkul:ingatkan-kasus-menggantung')->assertSuccessful();
        $this->travel(5)->hours();
        $this->artisan('rangkul:ingatkan-kasus-menggantung')->assertSuccessful();
        $this->assertSame(1, $this->jumlahLaporan());

        $this->pesanLama($permintaan, 0, $permintaan->konselor_id);
        $this->travel(47)->hours();
        $this->artisan('rangkul:ingatkan-kasus-menggantung')->assertSuccessful();
        $this->assertSame(1, $this->jumlahLaporan());

        $this->travel(2)->hours();
        $this->artisan('rangkul:ingatkan-kasus-menggantung')->assertSuccessful();
        $this->assertSame(2, $this->jumlahLaporan());
    }

    public function test_komite_dapat_menyaring_kasus_menggantung(): void
    {
        $diam = $this->kasusDitugaskan(100);
        $aktif = $this->kasusDitugaskan(100);
        $this->pesanLama($aktif, 1);

        $this->masukSebagai($this->komite)->get(route('kelola.permintaan.index', ['status' => 'menggantung']))
            ->assertOk()
            ->assertSee($diam->kode)
            ->assertDontSee($aktif->kode);

        $this->get(route('kelola.permintaan.tampil', $diam))->assertSee('Tidak ada aktivitas percakapan maupun pertemuan lebih dari 48 jam.');
    }

    public function test_menunggu_penutupan_juga_dianggap_menggantung(): void
    {
        $permintaan = $this->kasusDitugaskan(100);
        $permintaan->update(['status' => StatusPermintaan::MenungguPenutupan]);

        $this->artisan('rangkul:ingatkan-kasus-menggantung')->assertSuccessful();

        $this->assertSame(1, $this->jumlahLaporan());
    }
}
