<?php

namespace Tests\Feature;

use App\Enums\StatusPermintaan;
use App\Models\Permintaan;
use App\Models\Pesan;
use App\Models\User;
use App\Notifications\NotifikasiRangkul;
use App\Notifications\SaluranWhatsapp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MemalsukanSpk;
use Tests\Concerns\MembuatKasus;
use Tests\TestCase;

/**
 * WhatsApp untuk percakapan: pesan pertama tiap pihak dan pesan yang lebih
 * dari 2 jam belum dibuka/dibalas, tanpa notifikasi untuk setiap pesan saat
 * percakapan sedang berjalan.
 */
class NotifikasiPercakapanTest extends TestCase
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
        $this->konselor = $this->peerKonselor(['email' => 'sari@kampus.test', 'telp' => '081100000001']);
        $this->anggota = $this->anggotaBiasa(['email' => 'rina@kampus.test', 'telp' => '081100000002']);
        $this->permintaan = $this->kasusBerlangsung($this->anggota, $this->konselor);
    }

    private function kirimPesan(User $pengirim, string $isi): void
    {
        $this->masukSebagai($pengirim)->postJson(route('percakapan.kirim', $this->permintaan), ['isi' => $isi])->assertOk();
    }

    private function bukaPercakapan(User $pengguna): void
    {
        $this->masukSebagai($pengguna)->getJson(route('percakapan.daftar', $this->permintaan))->assertOk();
    }

    private function jumlahNotifikasi(User $penerima, string $jenis): int
    {
        return Notification::sent($penerima, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === $jenis)->count();
    }

    public function test_pesan_pertama_anggota_dikabarkan_ke_konselor_lewat_whatsapp_saja(): void
    {
        $this->kirimPesan($this->anggota, 'Halo, saya butuh teman bicara soal beban mengajar.');
        $this->kirimPesan($this->anggota, 'Apakah Ibu ada waktu?');

        Notification::assertSentTo($this->konselor, NotifikasiRangkul::class, function (NotifikasiRangkul $notifikasi, array $saluran, User $kepada): bool {
            $whatsapp = $notifikasi->toWhatsapp($kepada);

            return $notifikasi->jenis === 'pesan.pertama'
                && $saluran === [SaluranWhatsapp::class]
                && str_contains($whatsapp, $this->permintaan->kode)
                && str_contains($whatsapp, route('konseling.tampil', $this->permintaan).'#percakapan')
                && ! str_contains($whatsapp, 'beban mengajar');
        });
        $this->assertSame(1, $this->jumlahNotifikasi($this->konselor, 'pesan.pertama'));
        Notification::assertNotSentTo($this->anggota, NotifikasiRangkul::class);
    }

    public function test_pesan_pertama_konselor_dikabarkan_ke_anggota(): void
    {
        $this->kirimPesan($this->konselor, 'Halo Rina, saya Sari yang akan mendampingi.');
        $this->kirimPesan($this->konselor, 'Silakan bercerita kapan saja.');

        Notification::assertSentTo($this->anggota, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi, array $saluran, User $kepada): bool => $notifikasi->jenis === 'pesan.pertama'
            && $saluran === [SaluranWhatsapp::class]
            && str_contains($notifikasi->toWhatsapp($kepada), route('permintaan.tampil', $this->permintaan).'#percakapan'));
        $this->assertSame(1, $this->jumlahNotifikasi($this->anggota, 'pesan.pertama'));
        Notification::assertNotSentTo($this->konselor, NotifikasiRangkul::class);
    }

    public function test_percakapan_yang_sedang_berjalan_tidak_menghasilkan_whatsapp_tiap_pesan(): void
    {
        foreach (range(1, 5) as $giliran) {
            $this->kirimPesan($this->anggota, "Pesan anggota ke-{$giliran}");
            $this->travel(3)->minutes();
            $this->bukaPercakapan($this->konselor);
            $this->kirimPesan($this->konselor, "Balasan konselor ke-{$giliran}");
            $this->travel(3)->minutes();
            $this->bukaPercakapan($this->anggota);
        }

        $this->travel(3)->hours();
        $this->artisan('rangkul:ingatkan-pesan-tertunda')->assertSuccessful();

        $this->assertSame(1, $this->jumlahNotifikasi($this->konselor, 'pesan.pertama'));
        $this->assertSame(1, $this->jumlahNotifikasi($this->anggota, 'pesan.pertama'));
        Notification::assertSentToTimes($this->konselor, NotifikasiRangkul::class, 1);
        Notification::assertSentToTimes($this->anggota, NotifikasiRangkul::class, 1);
    }

    public function test_pesan_anggota_yang_lebih_dari_dua_jam_belum_dibuka_diingatkan_sekali_per_rangkaian(): void
    {
        $this->kirimPesan($this->anggota, 'Halo?');

        $this->travel(110)->minutes();
        $this->artisan('rangkul:ingatkan-pesan-tertunda')->assertSuccessful();
        $this->assertSame(0, $this->jumlahNotifikasi($this->konselor, 'pesan.tertunda'));

        $this->travel(15)->minutes();
        $this->artisan('rangkul:ingatkan-pesan-tertunda')->assertSuccessful();
        Notification::assertSentTo($this->konselor, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi, array $saluran): bool => $notifikasi->jenis === 'pesan.tertunda'
            && $saluran === [SaluranWhatsapp::class]);
        $this->assertSame(1, $this->jumlahNotifikasi($this->konselor, 'pesan.tertunda'));

        // Pesan susulan sebelum konselor membuka percakapan: tetap satu pengingat.
        $this->kirimPesan($this->anggota, 'Saya masih menunggu.');
        $this->travel(3)->hours();
        $this->artisan('rangkul:ingatkan-pesan-tertunda')->assertSuccessful();
        $this->assertSame(1, $this->jumlahNotifikasi($this->konselor, 'pesan.tertunda'));

        // Setelah dibuka, rangkaian baru yang kembali menunggu boleh diingatkan lagi.
        $this->bukaPercakapan($this->konselor);
        $this->kirimPesan($this->anggota, 'Terima kasih, satu hal lagi.');
        $this->travel(3)->hours();
        $this->artisan('rangkul:ingatkan-pesan-tertunda')->assertSuccessful();
        $this->assertSame(2, $this->jumlahNotifikasi($this->konselor, 'pesan.tertunda'));
        Notification::assertNotSentTo($this->anggota, NotifikasiRangkul::class);
    }

    public function test_pesan_konselor_yang_lebih_dari_dua_jam_belum_dibuka_diingatkan_ke_anggota(): void
    {
        $this->kirimPesan($this->konselor, 'Bagaimana kabar Anda hari ini?');
        $this->travel(3)->hours();

        $this->artisan('rangkul:ingatkan-pesan-tertunda')->assertSuccessful();
        $this->artisan('rangkul:ingatkan-pesan-tertunda')->assertSuccessful();

        Notification::assertSentTo($this->anggota, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi, array $saluran, User $kepada): bool => $notifikasi->jenis === 'pesan.tertunda'
            && str_contains($notifikasi->toWhatsapp($kepada), 'Konselor pendamping Anda mengirim pesan')
            && str_contains($notifikasi->toWhatsapp($kepada), route('permintaan.tampil', $this->permintaan)));
        $this->assertSame(1, $this->jumlahNotifikasi($this->anggota, 'pesan.tertunda'));
        $this->assertSame(0, $this->jumlahNotifikasi($this->konselor, 'pesan.tertunda'));
    }

    public function test_pesan_yang_sudah_dibaca_atau_dibalas_tidak_diingatkan(): void
    {
        $terbaca = $this->permintaan->pesan()->create(['pengirim_id' => $this->anggota->id, 'isi' => 'Sudah dibaca.', 'dibaca_pada' => now()]);
        $terbaca->forceFill(['created_at' => now()->subHours(5)])->save();

        $dibalas = $this->permintaan->pesan()->create(['pengirim_id' => $this->anggota->id, 'isi' => 'Belum ditandai terbaca.']);
        $dibalas->forceFill(['created_at' => now()->subHours(4)])->save();
        $balasan = $this->permintaan->pesan()->create(['pengirim_id' => $this->konselor->id, 'isi' => 'Ini balasan saya.', 'dibaca_pada' => now()]);
        $balasan->forceFill(['created_at' => now()->subHours(3)])->save();

        $this->artisan('rangkul:ingatkan-pesan-tertunda')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertSame(0, Pesan::query()->whereNotNull('diingatkan_whatsapp_pada')->count());
    }

    public function test_kasus_yang_sudah_selesai_tidak_diingatkan(): void
    {
        $pesan = $this->permintaan->pesan()->create(['pengirim_id' => $this->anggota->id, 'isi' => 'Terima kasih banyak.']);
        $pesan->forceFill(['created_at' => now()->subHours(3)])->save();
        $this->permintaan->forceFill(['status' => StatusPermintaan::Selesai])->save();

        $this->artisan('rangkul:ingatkan-pesan-tertunda')->assertSuccessful();

        Notification::assertNothingSent();
    }
}
