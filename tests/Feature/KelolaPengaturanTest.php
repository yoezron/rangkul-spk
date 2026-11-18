<?php

namespace Tests\Feature;

use App\Mail\SurelUji;
use App\Models\AuditLog;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\PengaturanRangkul;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\Concerns\MemalsukanSpk;
use Tests\TestCase;

class KelolaPengaturanTest extends TestCase
{
    use MemalsukanSpk, RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        $this->superadmin = User::factory()->superadmin()->create(['email' => 'admin@spk.test']);
    }

    /**
     * @return array<string, string|int>
     */
    private function formulirLayanan(array $timpa = []): array
    {
        return $timpa + [
            'layanan_buka' => '1',
            'layanan_pesan_tutup' => 'Layanan ditutup.',
            'audit_retensi_hari' => 365,
            'audit_catat_ip' => '1',
        ];
    }

    /**
     * @return array<string, string|int>
     */
    private function formulirSurel(array $timpa = []): array
    {
        return $timpa + [
            'surel_aktif' => '1',
            'surel_host' => 'smtp-relay.brevo.com',
            'surel_port' => 587,
            'surel_enkripsi' => 'tls',
            'surel_username' => '9a1b2c001@smtp-brevo.com',
            'surel_password' => 'xsmtpsib-rahasia-uji',
            'surel_dari_alamat' => 'rangkul@spk.or.id',
            'surel_dari_nama' => 'SPK Rangkul',
        ];
    }

    public function test_menutup_layanan_tampil_bagi_anggota_dan_tercatat(): void
    {
        $this->masukSebagai($this->superadmin)
            ->put(route('kelola.pengaturan.layanan'), $this->formulirLayanan([
                'layanan_buka' => '0',
                'layanan_pesan_tutup' => 'Rangkul libur sampai 2 Januari.',
            ]))
            ->assertRedirectToRoute('kelola.pengaturan.layanan')
            ->assertSessionHas('status', 'Pengaturan disimpan.');

        $catatan = AuditLog::query()->sole();
        $this->assertSame('pengaturan.diubah', $catatan->aksi);
        $this->assertSame('layanan', $catatan->sasaran);
        $this->assertSame(['layanan.buka', 'layanan.pesan_tutup'], $catatan->rincian['kunci']);
        $this->assertSame($this->superadmin->id, $catatan->user_id);

        $this->get(route('beranda'))->assertSee('Rangkul libur sampai 2 Januari.');
        $this->post(route('keluar'));
        $this->get(route('masuk'))->assertSee('Rangkul libur sampai 2 Januari.')->assertSee('Masuk dengan akun SPK');
    }

    public function test_menyimpan_tanpa_perubahan_tidak_mencatat_audit(): void
    {
        $this->masukSebagai($this->superadmin)
            ->put(route('kelola.pengaturan.layanan'), $this->formulirLayanan([
                'layanan_pesan_tutup' => PengaturanRangkul::BAWAAN['layanan.pesan_tutup'],
            ]))
            ->assertSessionHas('status', 'Tidak ada yang berubah.');

        $this->assertDatabaseCount('audit_log', 0);
    }

    public function test_masa_simpan_audit_dibatasi(): void
    {
        $this->masukSebagai($this->superadmin)
            ->put(route('kelola.pengaturan.layanan'), $this->formulirLayanan(['audit_retensi_hari' => 4000]))
            ->assertSessionHasErrors('audit_retensi_hari');

        $this->assertDatabaseCount('pengaturan', 0);
    }

    public function test_halaman_depan_dan_kontak_tampil_di_halaman_masuk_tanpa_html_mentah(): void
    {
        $this->masukSebagai($this->superadmin)
            ->put(route('kelola.pengaturan.halaman'), [
                'halaman_judul' => 'Rangkul <script>x</script>',
                'halaman_subjudul' => 'Teman bicara sesama pekerja kampus',
                'halaman_isi' => "Baris pertama\nBaris kedua",
                'kontak_email' => 'rangkul@spk.test',
                'kontak_whatsapp' => '0812-3456-7890',
                'kontak_jam_layanan' => 'Senin–Jumat',
                'kontak_darurat' => 'Hubungi 112.',
            ])
            ->assertSessionHasNoErrors();

        $this->post(route('keluar'));

        $this->get(route('masuk'))
            ->assertOk()
            ->assertSee('Rangkul <script>x</script>')
            ->assertDontSee('<script>x</script>', false)
            ->assertSee('Baris pertama<br />', false)
            ->assertSee('href="https://wa.me/6281234567890"', false)
            ->assertSee('mailto:rangkul@spk.test', false)
            ->assertSee('Hubungi 112.');
    }

    public function test_nomor_whatsapp_tidak_sah_ditolak(): void
    {
        $this->masukSebagai($this->superadmin)
            ->put(route('kelola.pengaturan.halaman'), [
                'halaman_judul' => 'SPK Rangkul',
                'kontak_whatsapp' => 'javascript:alert(1)',
            ])
            ->assertSessionHasErrors('kontak_whatsapp');
    }

    public function test_sandi_smtp_tersimpan_terenkripsi_dan_tidak_masuk_audit_maupun_formulir(): void
    {
        $this->masukSebagai($this->superadmin)
            ->put(route('kelola.pengaturan.surel'), $this->formulirSurel())
            ->assertSessionHasNoErrors();

        $mentah = (string) Pengaturan::query()->findOrFail('surel.password')->nilai;
        $this->assertStringNotContainsString('xsmtpsib-rahasia-uji', $mentah);
        $this->assertSame('xsmtpsib-rahasia-uji', app(PengaturanRangkul::class)->get('surel.password'));

        $catatan = AuditLog::query()->sole();
        $this->assertContains('surel.password', $catatan->rincian['kunci']);
        $this->assertStringNotContainsString('xsmtpsib-rahasia-uji', json_encode($catatan->rincian));

        $this->get(route('kelola.pengaturan.surel'))
            ->assertOk()
            ->assertSee('SMTP key tersimpan')
            ->assertDontSee('xsmtpsib-rahasia-uji');
    }

    public function test_sandi_kosong_mempertahankan_sandi_lama_dan_bisa_dihapus(): void
    {
        $this->masukSebagai($this->superadmin)->put(route('kelola.pengaturan.surel'), $this->formulirSurel());

        $this->put(route('kelola.pengaturan.surel'), $this->formulirSurel(['surel_password' => '', 'surel_port' => 2525]));
        $this->assertSame('xsmtpsib-rahasia-uji', app(PengaturanRangkul::class)->get('surel.password'));

        $this->put(route('kelola.pengaturan.surel'), $this->formulirSurel(['surel_password' => '', 'surel_hapus_password' => '1']));
        $this->assertSame('', app(PengaturanRangkul::class)->get('surel.password'));
    }

    public function test_formulir_surel_sudah_terisi_bawaan_brevo(): void
    {
        $this->masukSebagai($this->superadmin)
            ->get(route('kelola.pengaturan.surel'))
            ->assertOk()
            ->assertSee('value="smtp-relay.brevo.com"', false)
            ->assertSee('value="587"', false)
            ->assertSee('SMTP key');
    }

    public function test_api_key_brevo_ditolak_sebagai_smtp_key(): void
    {
        $this->masukSebagai($this->superadmin)
            ->put(route('kelola.pengaturan.surel'), $this->formulirSurel(['surel_password' => 'xkeysib-bukan-smtp-key']))
            ->assertSessionHasErrors('surel_password');

        $this->assertSame('', app(PengaturanRangkul::class)->get('surel.password'));
    }

    public function test_port_harus_sesuai_jenis_enkripsi(): void
    {
        $this->masukSebagai($this->superadmin);

        $this->put(route('kelola.pengaturan.surel'), $this->formulirSurel(['surel_enkripsi' => 'ssl', 'surel_port' => 587]))
            ->assertSessionHasErrors('surel_port');
        $this->put(route('kelola.pengaturan.surel'), $this->formulirSurel(['surel_enkripsi' => 'tls', 'surel_port' => 465]))
            ->assertSessionHasErrors('surel_port');

        $this->put(route('kelola.pengaturan.surel'), $this->formulirSurel(['surel_enkripsi' => 'ssl', 'surel_port' => 465]))
            ->assertSessionHasNoErrors();
        $this->assertSame('ssl', app(PengaturanRangkul::class)->get('surel.enkripsi'));
    }

    public function test_surel_uji_yang_ditolak_brevo_disertai_saran(): void
    {
        $this->masukSebagai($this->superadmin)->put(route('kelola.pengaturan.surel'), $this->formulirSurel());
        Mail::shouldReceive('purge')->once()->with('smtp');
        Mail::shouldReceive('mailer')->once()->andThrow(new TransportException('Expected response code "235" but got code "535"'));

        $this->post(route('kelola.pengaturan.surel.uji'))
            ->assertSessionHasErrors(['surel', 'surel_saran']);

        $this->assertDatabaseMissing('audit_log', ['aksi' => 'surel.uji_dikirim']);
    }

    public function test_host_wajib_diisi_bila_smtp_diaktifkan(): void
    {
        $this->masukSebagai($this->superadmin)
            ->put(route('kelola.pengaturan.surel'), $this->formulirSurel(['surel_host' => '', 'surel_dari_alamat' => '']))
            ->assertSessionHasErrors(['surel_host', 'surel_dari_alamat']);
    }

    public function test_surel_uji_dikirim_dengan_smtp_tersimpan(): void
    {
        Mail::fake();

        $this->masukSebagai($this->superadmin)->put(route('kelola.pengaturan.surel'), $this->formulirSurel());

        $this->post(route('kelola.pengaturan.surel.uji'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        Mail::assertSent(SurelUji::class, fn (SurelUji $surel): bool => $surel->hasTo('admin@spk.test'));
        $this->assertSame('smtp-relay.brevo.com', config('mail.mailers.smtp.host'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame('rangkul@spk.or.id', config('mail.from.address'));
        $this->assertDatabaseHas('audit_log', ['aksi' => 'surel.uji_dikirim']);
    }

    public function test_surel_uji_ditolak_selama_smtp_belum_aktif(): void
    {
        Mail::fake();

        $this->masukSebagai($this->superadmin)
            ->post(route('kelola.pengaturan.surel.uji'))
            ->assertSessionHasErrors('surel');

        Mail::assertNothingSent();
    }
}
