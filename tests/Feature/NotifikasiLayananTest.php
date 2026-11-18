<?php

namespace Tests\Feature;

use App\Models\Pesan;
use App\Models\User;
use App\Notifications\NotifikasiRangkul;
use App\Notifications\SaluranWhatsapp;
use App\Services\PemberitahuLayanan;
use App\Services\PengaturanRangkul;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MemalsukanSpk;
use Tests\Concerns\MembuatKasus;
use Tests\TestCase;

class NotifikasiLayananTest extends TestCase
{
    use MemalsukanSpk, MembuatKasus, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        config(['services.fonnte.url' => 'https://fonnte.test']);
    }

    private function notifikasi(bool $mendesak = false): NotifikasiRangkul
    {
        return new NotifikasiRangkul(
            jenis: 'uji',
            subjek: 'Permintaan baru RGK-2611-0001',
            judul: 'Ada permintaan layanan baru',
            paragraf: ['Permintaan RGK-2611-0001 menunggu ditinjau komite.'],
            teksTombol: 'Tinjau permintaan',
            tautan: 'https://rangkul.test/kelola/permintaan/1',
            mendesak: $mendesak,
        );
    }

    public function test_surel_notifikasi_rapi_dan_tidak_memuat_isi_cerita(): void
    {
        $permintaan = $this->kasusBerlangsung();
        $penerima = User::factory()->create(['nama' => 'Dewi Komite']);
        Notification::fake();

        app(PemberitahuLayanan::class)->permintaanMasuk($permintaan);
        app(PemberitahuLayanan::class)->konselorDitugaskan($permintaan, false);

        Notification::assertSentTo($permintaan->anggota, NotifikasiRangkul::class, function (NotifikasiRangkul $notifikasi, array $saluran, User $kepada) use ($permintaan): bool {
            $html = $notifikasi->toMail($kepada)->render();
            $whatsapp = $notifikasi->toWhatsapp($kepada);

            return ! str_contains($html, $permintaan->uraian)
                && ! str_contains($whatsapp, $permintaan->uraian)
                && ! str_contains($html, 'Stres dan tekanan pekerjaan')
                && str_contains($html, $permintaan->kode);
        });

        $surel = $this->notifikasi(mendesak: true)->toMail($penerima);
        $this->assertInstanceOf(MailMessage::class, $surel);
        $this->assertSame('[MENDESAK] Permintaan baru RGK-2611-0001 — SPK Rangkul', $surel->subject);

        $html = (string) $surel->render();
        $this->assertStringContainsString('Halo, Dewi.', $html);
        $this->assertStringContainsString('MENDESAK', $html);
        $this->assertStringContainsString('href="https://rangkul.test/kelola/permintaan/1"', $html);
        $this->assertStringContainsString('tidak memuat isi cerita maupun percakapan', $html);
    }

    public function test_saluran_mengikuti_pilihan_penerima(): void
    {
        $keduanya = User::factory()->create(['email' => 'a@kampus.test', 'telp' => '0811', 'notif_surel' => true, 'notif_whatsapp' => true]);
        $surelSaja = User::factory()->create(['email' => 'b@kampus.test', 'telp' => '0812', 'notif_whatsapp' => false]);
        $tanpaSurel = User::factory()->create(['email' => null, 'telp' => '0813']);
        $tidakMau = User::factory()->create(['notif_surel' => false, 'notif_whatsapp' => false]);

        $this->assertSame(['mail', SaluranWhatsapp::class], $this->notifikasi()->via($keduanya));
        $this->assertSame(['mail'], $this->notifikasi()->via($surelSaja));
        $this->assertSame([SaluranWhatsapp::class], $this->notifikasi()->via($tanpaSurel));
        $this->assertSame([], $this->notifikasi()->via($tidakMau));
    }

    public function test_whatsapp_dikirim_lewat_fonnte_ke_nomor_pilihan_anggota(): void
    {
        Mail::fake();
        Http::fake(['fonnte.test/send' => Http::response(['status' => true])]);
        app(PengaturanRangkul::class)->simpan(['whatsapp.aktif' => true, 'whatsapp.token' => 'tokenUji']);
        $anggota = User::factory()->create(['telp' => '081111111111', 'nomor_whatsapp' => '0822 2222 2222', 'notif_surel' => false]);

        $anggota->notify($this->notifikasi());

        Http::assertSent(fn (Request $permintaan): bool => $permintaan->url() === 'https://fonnte.test/send'
            && $permintaan['target'] === '082222222222'
            && str_contains($permintaan['message'], '*Ada permintaan layanan baru*')
            && str_contains($permintaan['message'], 'https://rangkul.test/kelola/permintaan/1'));
    }

    public function test_whatsapp_diam_bila_fonnte_belum_aktif(): void
    {
        Mail::fake();
        Http::fake();
        User::factory()->create(['telp' => '081111111111', 'notif_surel' => false])->notify($this->notifikasi());

        Http::assertNothingSent();
    }

    public function test_superadmin_tanpa_peran_komite_tidak_menerima_notifikasi_kasus(): void
    {
        Notification::fake();
        $superadmin = User::factory()->superadmin()->create();
        $komite = $this->anggotaKomite();
        $permintaan = $this->kasusBerlangsung();

        app(PemberitahuLayanan::class)->penutupanPerluTinjauan($permintaan);

        Notification::assertSentTo($komite, NotifikasiRangkul::class);
        Notification::assertNotSentTo($superadmin, NotifikasiRangkul::class);
    }

    public function test_pengingat_pesan_belum_dibaca_lebih_dari_24_jam(): void
    {
        Notification::fake();
        $komite = $this->anggotaKomite();
        $permintaan = $this->kasusBerlangsung();

        $lama = $permintaan->pesan()->create(['pengirim_id' => $permintaan->user_id, 'isi' => 'Halo, apakah ada orang?']);
        $lama->forceFill(['created_at' => now()->subHours(25)])->save();
        $baru = $permintaan->pesan()->create(['pengirim_id' => $permintaan->user_id, 'isi' => 'Baru saja.']);
        $dariKonselor = $permintaan->pesan()->create(['pengirim_id' => $permintaan->konselor_id, 'isi' => 'Sudah dibaca.', 'dibaca_pada' => now()]);
        $dariKonselor->forceFill(['created_at' => now()->subHours(30)])->save();

        $this->artisan('rangkul:ingatkan-pesan')->assertSuccessful();

        Notification::assertSentTo($permintaan->konselor, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'pesan.belum_dibaca');
        Notification::assertSentTo($komite, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'pesan.belum_dibaca_konselor');
        Notification::assertNotSentTo($permintaan->anggota, NotifikasiRangkul::class);
        $this->assertNotNull($lama->refresh()->diingatkan_pada);
        $this->assertNull($baru->refresh()->diingatkan_pada);

        $this->artisan('rangkul:ingatkan-pesan')->assertSuccessful();
        Notification::assertSentToTimes($permintaan->konselor, NotifikasiRangkul::class, 1);
    }

    public function test_pengingat_untuk_anggota_tidak_melibatkan_komite(): void
    {
        Notification::fake();
        $komite = $this->anggotaKomite();
        $permintaan = $this->kasusBerlangsung();
        $pesan = $permintaan->pesan()->create(['pengirim_id' => $permintaan->konselor_id, 'isi' => 'Bagaimana kabar Anda hari ini?']);
        $pesan->forceFill(['created_at' => now()->subHours(26)])->save();

        $this->artisan('rangkul:ingatkan-pesan')->assertSuccessful();

        Notification::assertSentTo($permintaan->anggota, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'pesan.belum_dibaca');
        Notification::assertNotSentTo($komite, NotifikasiRangkul::class);
        $this->assertSame(1, Pesan::query()->whereNotNull('diingatkan_pada')->count());
    }
}
