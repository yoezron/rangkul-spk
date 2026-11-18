<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MemalsukanSpk;
use Tests\TestCase;

class MasukKeluarTest extends TestCase
{
    use MemalsukanSpk, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
    }

    public function test_halaman_masuk_hanya_menawarkan_masuk_dengan_akun_spk(): void
    {
        $this->get(route('masuk'))
            ->assertOk()
            ->assertSee('Masuk dengan akun SPK')
            ->assertSee('href="https://spk.test/auth?lanjut=rangkul"', false)
            ->assertDontSee('type="password"', false)
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }

    public function test_halaman_masuk_tanpa_rahasia_tidak_menampilkan_tombol(): void
    {
        config(['services.spk.sso_secret' => null]);

        $this->get(route('masuk'))
            ->assertOk()
            ->assertSee('sedang disiapkan')
            ->assertDontSee('Masuk dengan akun SPK');
    }

    public function test_kode_alasan_yang_tidak_dikenal_mendapat_penjelasan_umum(): void
    {
        $this->withSession(['alasan' => 'kode_baru_dari_spk'])->get(route('masuk'))
            ->assertOk()
            ->assertSee('belum dapat masuk ke SPK Rangkul')
            ->assertDontSee('kode_baru_dari_spk');
    }

    public function test_tamu_dialihkan_dari_beranda_ke_halaman_masuk(): void
    {
        $this->get(route('beranda'))->assertRedirectToRoute('masuk');
    }

    public function test_anggota_yang_sudah_masuk_dialihkan_dari_halaman_masuk_ke_beranda(): void
    {
        $this->actingAs(User::factory()->create())->withSession($this->sesiSso())
            ->get(route('masuk'))
            ->assertRedirectToRoute('beranda');
    }

    public function test_keluar_menghapus_sesi_rangkul(): void
    {
        $anggota = User::factory()->create();

        $jawaban = $this->actingAs($anggota)->withSession($this->sesiSso() + ['titipan' => 'isi sesi'])
            ->post(route('keluar'));

        $jawaban->assertRedirectToRoute('masuk')
            ->assertSessionMissing('titipan')
            ->assertSessionMissing('sso.mulai');
        $this->assertGuest();
        $this->get(route('beranda'))->assertRedirectToRoute('masuk');
    }

    public function test_keluar_tidak_bisa_dipicu_lewat_get(): void
    {
        $anggota = User::factory()->create();

        $this->actingAs($anggota)->withSession($this->sesiSso())->get('/keluar')->assertMethodNotAllowed();

        $this->assertAuthenticatedAs($anggota);
    }

    #[DataProvider('ruteLoginLokal')]
    public function test_tidak_ada_pendaftaran_login_lokal_maupun_reset_password(string $metode, string $alamat): void
    {
        $this->call($metode, $alamat)->assertNotFound();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function ruteLoginLokal(): array
    {
        return [
            'formulir daftar' => ['GET', '/register'],
            'kirim daftar' => ['POST', '/register'],
            'formulir login' => ['GET', '/login'],
            'kirim login' => ['POST', '/login'],
            'lupa password' => ['GET', '/forgot-password'],
            'kirim lupa password' => ['POST', '/forgot-password'],
            'reset password' => ['GET', '/reset-password/token'],
            'kirim reset password' => ['POST', '/reset-password'],
        ];
    }
}
