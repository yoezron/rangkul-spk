<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Pengaturan;
use App\Models\Peran;
use App\Models\User;
use App\Services\PengaturanRangkul;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MemalsukanSpk;
use Tests\TestCase;

class KelolaWhatsappTest extends TestCase
{
    use MemalsukanSpk, RefreshDatabase;

    private const TOKEN = 'tokenPerangkatFonnte123';

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        config(['services.fonnte.url' => 'https://fonnte.test']);
        $this->superadmin = User::factory()->superadmin()->create(['telp' => '081234567890']);
    }

    private function aktifkan(): void
    {
        app(PengaturanRangkul::class)->simpan(['whatsapp.aktif' => true, 'whatsapp.token' => self::TOKEN]);
    }

    /**
     * @return array<string, string>
     */
    private function formulir(array $timpa = []): array
    {
        return $timpa + [
            'whatsapp_aktif' => '1',
            'whatsapp_token' => self::TOKEN,
            'whatsapp_kode_negara' => '62',
        ];
    }

    public function test_token_tersimpan_terenkripsi_dan_tidak_masuk_audit_maupun_formulir(): void
    {
        $this->masukSebagai($this->superadmin)
            ->put(route('kelola.pengaturan.whatsapp'), $this->formulir())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Pengaturan disimpan.');

        $this->assertStringNotContainsString(self::TOKEN, (string) Pengaturan::query()->findOrFail('whatsapp.token')->nilai);
        $this->assertSame(self::TOKEN, app(PengaturanRangkul::class)->get('whatsapp.token'));

        $catatan = AuditLog::query()->sole();
        $this->assertSame('whatsapp', $catatan->sasaran);
        $this->assertContains('whatsapp.token', $catatan->rincian['kunci']);
        $this->assertStringNotContainsString(self::TOKEN, json_encode($catatan->rincian));

        $this->get(route('kelola.pengaturan.whatsapp'))
            ->assertOk()
            ->assertSee('Token tersimpan')
            ->assertDontSee(self::TOKEN);
        $this->get(route('kelola.dasbor'))->assertSee('Notifikasi WhatsApp');
    }

    public function test_token_kosong_mempertahankan_token_lama_dan_bisa_dihapus(): void
    {
        $this->masukSebagai($this->superadmin)->put(route('kelola.pengaturan.whatsapp'), $this->formulir());

        $this->put(route('kelola.pengaturan.whatsapp'), $this->formulir(['whatsapp_token' => '', 'whatsapp_kode_negara' => '0']))
            ->assertSessionHasNoErrors();
        $this->assertSame(self::TOKEN, app(PengaturanRangkul::class)->get('whatsapp.token'));

        $this->put(route('kelola.pengaturan.whatsapp'), $this->formulir(['whatsapp_aktif' => '0', 'whatsapp_token' => '', 'whatsapp_hapus_token' => '1']))
            ->assertSessionHasNoErrors();
        $this->assertSame('', app(PengaturanRangkul::class)->get('whatsapp.token'));
    }

    public function test_tidak_dapat_diaktifkan_tanpa_token(): void
    {
        $this->masukSebagai($this->superadmin)
            ->put(route('kelola.pengaturan.whatsapp'), $this->formulir(['whatsapp_token' => '']))
            ->assertSessionHasErrors('whatsapp_token');

        $this->assertFalse(app(PengaturanRangkul::class)->get('whatsapp.aktif'));
    }

    public function test_kode_negara_dan_token_divalidasi(): void
    {
        $this->masukSebagai($this->superadmin)
            ->put(route('kelola.pengaturan.whatsapp'), $this->formulir(['whatsapp_kode_negara' => '+62', 'whatsapp_token' => 'ada spasi']))
            ->assertSessionHasErrors(['whatsapp_kode_negara', 'whatsapp_token']);
    }

    public function test_pesan_uji_dikirim_ke_fonnte_dan_nomornya_disamarkan_di_audit(): void
    {
        $this->aktifkan();
        Http::fake(['fonnte.test/send' => Http::response(['detail' => 'success! message in queue', 'status' => true, 'target' => ['6281234567890']])]);

        $this->masukSebagai($this->superadmin)
            ->post(route('kelola.pengaturan.whatsapp.uji'), ['nomor_uji' => '0812-3456-7890'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Http::assertSent(fn (Request $permintaan): bool => $permintaan->method() === 'POST'
            && $permintaan->url() === 'https://fonnte.test/send'
            && $permintaan->header('Authorization') === [self::TOKEN]
            && $permintaan['target'] === '081234567890'
            && $permintaan['countryCode'] === '62'
            && str_contains($permintaan['message'], 'SPK Rangkul'));

        $catatan = AuditLog::query()->where('aksi', 'whatsapp.uji_dikirim')->sole();
        $this->assertSame('0812•••••890', $catatan->sasaran);
        $this->assertStringNotContainsString('34567', json_encode($catatan->toArray()));
    }

    public function test_penolakan_fonnte_ditampilkan_kepada_pengelola(): void
    {
        $this->aktifkan();
        Http::fake(['fonnte.test/send' => Http::response(['Status' => false, 'reason' => 'token invalid'])]);

        $this->masukSebagai($this->superadmin)
            ->post(route('kelola.pengaturan.whatsapp.uji'), ['nomor_uji' => '081234567890'])
            ->assertSessionHasErrors(['whatsapp' => 'Pesan uji gagal dikirim: token invalid']);

        $this->assertDatabaseMissing('audit_log', ['aksi' => 'whatsapp.uji_dikirim']);
    }

    public function test_fonnte_yang_tidak_terjangkau(): void
    {
        $this->aktifkan();
        Http::fake(['fonnte.test/*' => Http::failedConnection()]);

        $this->masukSebagai($this->superadmin)
            ->post(route('kelola.pengaturan.whatsapp.uji'), ['nomor_uji' => '081234567890'])
            ->assertSessionHasErrors(['whatsapp' => 'Pesan uji gagal dikirim: Fonnte tidak dapat dihubungi']);
    }

    public function test_pesan_uji_ditolak_selama_belum_diaktifkan(): void
    {
        Http::fake();

        $this->masukSebagai($this->superadmin)
            ->post(route('kelola.pengaturan.whatsapp.uji'), ['nomor_uji' => '081234567890'])
            ->assertSessionHasErrors('whatsapp');

        Http::assertNothingSent();
    }

    public function test_nomor_uji_yang_tidak_sah_ditolak(): void
    {
        $this->aktifkan();
        Http::fake();

        $this->masukSebagai($this->superadmin)
            ->post(route('kelola.pengaturan.whatsapp.uji'), ['nomor_uji' => '0812; DROP'])
            ->assertSessionHasErrors('nomor_uji');

        Http::assertNothingSent();
    }

    public function test_status_perangkat_ditampilkan(): void
    {
        $this->aktifkan();
        Http::fake(['fonnte.test/device' => Http::response([
            'device' => '6281200001111', 'device_status' => 'disconnect', 'expired' => '2026-12-31',
            'name' => 'Rangkul', 'package' => 'Regular', 'quota' => '9876', 'status' => true,
        ])]);

        $this->masukSebagai($this->superadmin)
            ->from(route('kelola.pengaturan.whatsapp'))
            ->post(route('kelola.pengaturan.whatsapp.periksa'))
            ->assertRedirectToRoute('kelola.pengaturan.whatsapp');

        Http::assertSent(fn (Request $permintaan): bool => $permintaan->url() === 'https://fonnte.test/device'
            && $permintaan->header('Authorization') === [self::TOKEN]);

        $this->get(route('kelola.pengaturan.whatsapp'))
            ->assertOk()
            ->assertSee('Terputus')
            ->assertSee('9876')
            ->assertSee('pindai ulang kode QR');
    }

    public function test_administrator_rangkul_bawaan_tidak_dapat_mengatur_whatsapp(): void
    {
        $pengelola = User::factory()->create();
        $pengelola->daftarPeran()->attach(Peran::query()->where('kunci', 'administrator')->sole());

        $this->masukSebagai($pengelola)->get(route('kelola.pengaturan.whatsapp'))->assertForbidden();
        $this->post(route('kelola.pengaturan.whatsapp.uji'), ['nomor_uji' => '081234567890'])->assertForbidden();
        $this->get(route('kelola.dasbor'))->assertDontSee('WhatsApp (Fonnte)');
    }
}
