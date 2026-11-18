<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MemalsukanSpk;
use Tests\TestCase;

class SsoSpkTest extends TestCase
{
    use MemalsukanSpk, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        $this->freezeTime();
    }

    public function test_token_sah_memasukkan_anggota_dan_mengalihkan_ke_beranda(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak());
        $iat = now()->timestamp - 5;

        $jawaban = $this->get($this->alamatSso($this->tokenSso(['iat' => $iat, 'exp' => $iat + 120])));

        $jawaban->assertRedirectToRoute('beranda');
        $this->assertStringNotContainsString('t=', (string) $jawaban->headers->get('Location'));
        $jawaban->assertHeader('Referrer-Policy', 'no-referrer');

        $anggota = User::query()->where('spk_id', 18)->sole();
        $this->assertAuthenticatedAs($anggota);
        $this->assertSame('Sri Wahyuni', $anggota->nama);
        $this->assertSame('sri@kampus.test', $anggota->email);
        $this->assertSame('1695605719', $anggota->nomor_anggota);
        $this->assertNotNull($anggota->disinkronkan_pada);

        $jawaban->assertSessionHas('sso.mulai', $iat);
        $jawaban->assertSessionHas('sso.diperiksa_pada', now()->timestamp);

        $this->assertEndpointDitanyaTentang(18);
    }

    public function test_setelah_masuk_beranda_menyapa_anggota_dengan_namanya(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak(['nama' => 'Budi <b>Santoso</b>']));

        $this->get($this->alamatSso($this->tokenSso()));

        $this->get(route('beranda'))
            ->assertOk()
            ->assertSee('Halo, Budi <b>Santoso</b>.')
            ->assertDontSee('<b>Santoso</b>', false);
    }

    public function test_masuk_ulang_memperbarui_anggota_yang_sama_tanpa_menggandakan(): void
    {
        $lama = User::factory()->create(['spk_id' => 18, 'nama' => 'Nama Lama', 'email' => 'lama@kampus.test']);
        $this->palsukanEndpoint($this->jawabanBerhak(['email' => 'baru@kampus.test']));

        $this->get($this->alamatSso($this->tokenSso()))->assertRedirectToRoute('beranda');

        $this->assertDatabaseCount('users', 1);
        $this->assertAuthenticatedAs($lama);
        $this->assertSame('Sri Wahyuni', $lama->refresh()->nama);
        $this->assertSame('baru@kampus.test', $lama->email);
    }

    public function test_tanda_tangan_salah_ditolak(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak());

        $jawaban = $this->get($this->alamatSso($this->tokenSso(rahasia: str_repeat('x', 64))));

        $this->assertDitolakSebagaiTautanTidakSah($jawaban);
        Http::assertNothingSent();
    }

    public function test_muatan_yang_diubah_setelah_ditandatangani_ditolak(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak());
        [, $tandaAsli] = explode('.', $this->tokenSso(['uid' => 18]));
        [$muatanLain] = explode('.', $this->tokenSso(['uid' => 1]));

        $jawaban = $this->get($this->alamatSso($muatanLain.'.'.$tandaAsli));

        $this->assertDitolakSebagaiTautanTidakSah($jawaban);
        Http::assertNothingSent();
    }

    public function test_token_kedaluwarsa_ditolak(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak());
        $kini = now()->timestamp;

        $jawaban = $this->get($this->alamatSso($this->tokenSso(['iat' => $kini - 181, 'exp' => $kini - 61])));

        $this->assertDitolakSebagaiTautanTidakSah($jawaban);
        Http::assertNothingSent();
    }

    public function test_token_yang_baru_lewat_masih_diterima_dalam_kelonggaran_jam(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak());
        $kini = now()->timestamp;

        $jawaban = $this->get($this->alamatSso($this->tokenSso(['iat' => $kini - 180, 'exp' => $kini - 60])));

        $jawaban->assertRedirectToRoute('beranda');
        $this->assertAuthenticated();
    }

    public function test_token_dari_masa_depan_ditolak(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak());
        $kini = now()->timestamp;

        $jawaban = $this->get($this->alamatSso($this->tokenSso(['iat' => $kini + 61, 'exp' => $kini + 181])));

        $this->assertDitolakSebagaiTautanTidakSah($jawaban);
    }

    /**
     * @param  array<string, mixed>  $klaim
     */
    #[DataProvider('klaimPeruntukanSalah')]
    public function test_token_untuk_peruntukan_lain_ditolak(array $klaim): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak());

        $jawaban = $this->get($this->alamatSso($this->tokenSso($klaim)));

        $this->assertDitolakSebagaiTautanTidakSah($jawaban);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function klaimPeruntukanSalah(): array
    {
        return [
            'aud layanan lain' => [['aud' => 'advokasi']],
            'aud kosong' => [['aud' => '']],
            'versi lain' => [['v' => 2]],
            'versi berupa teks' => [['v' => '1']],
        ];
    }

    public function test_nonce_tidak_bisa_dipakai_ulang(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak());
        $token = $this->tokenSso();

        $this->get($this->alamatSso($token))->assertRedirectToRoute('beranda');
        $this->post(route('keluar'));

        $jawaban = $this->get($this->alamatSso($token));

        $jawaban->assertRedirectToRoute('masuk')->assertSessionHas('alasan', 'tautan_tidak_sah');
        $this->assertGuest();
        Http::assertSentCount(1);
    }

    public function test_nonce_tetap_tertolak_setelah_jam_bergeser_selama_token_masih_berlaku(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak());
        $token = $this->tokenSso();

        $this->get($this->alamatSso($token))->assertRedirectToRoute('beranda');
        $this->post(route('keluar'));
        $this->travel(170)->seconds();

        $this->get($this->alamatSso($token))
            ->assertRedirectToRoute('masuk')
            ->assertSessionHas('alasan', 'tautan_tidak_sah');
        $this->assertGuest();
        Http::assertSentCount(1);
    }

    #[DataProvider('alasanPenolakan')]
    public function test_anggota_tidak_berhak_ditolak_dengan_penjelasan(string $alasan, string $penjelasan): void
    {
        $this->palsukanEndpoint($this->jawabanTidakBerhak($alasan, $alasan === 'tidak_ditemukan' ? null : 18));

        $jawaban = $this->get($this->alamatSso($this->tokenSso()));

        $jawaban->assertRedirectToRoute('masuk')->assertSessionHas('alasan', $alasan);
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);

        $this->followRedirects($jawaban)->assertOk()->assertSee($penjelasan);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function alasanPenolakan(): array
    {
        return [
            'calon anggota' => ['belum_terkonfirmasi', 'masih berstatus Calon Anggota'],
            'akun nonaktif' => ['tidak_aktif', 'sedang tidak aktif'],
            'wajib ganti password' => ['wajib_ganti_password', 'diwajibkan mengganti password'],
            'akun tidak ada' => ['tidak_ditemukan', 'tidak ditemukan di spk.or.id'],
        ];
    }

    public function test_berhak_harus_true_secara_harfiah(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak(['berhak' => 1]));

        $this->get($this->alamatSso($this->tokenSso()))->assertRedirectToRoute('masuk');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_jawaban_berhak_untuk_akun_lain_tidak_memasukkan_siapa_pun(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak(['id' => 99]));

        $jawaban = $this->get($this->alamatSso($this->tokenSso(['uid' => 18])));

        $jawaban->assertRedirectToRoute('masuk')->assertSessionHas('alasan', 'gangguan');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @param  array<string, mixed>  $badan
     */
    #[DataProvider('kegagalanEndpoint')]
    public function test_kegagalan_endpoint_tidak_memasukkan_dan_tidak_membocorkan_rinciannya(?int $status, array $badan): void
    {
        $status === null ? $this->palsukanEndpointPutus() : $this->palsukanEndpoint($badan, $status);

        $jawaban = $this->get($this->alamatSso($this->tokenSso()));

        $jawaban->assertRedirectToRoute('masuk')->assertSessionHas('alasan', 'gangguan');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);

        $this->followRedirects($jawaban)
            ->assertOk()
            ->assertSee('sedang tidak dapat dihubungi')
            ->assertDontSee('tanda_tangan')
            ->assertDontSee('belum_diatur')
            ->assertDontSee('rangkul/anggota');
    }

    /**
     * @return array<string, array{int|null, array<string, mixed>}>
     */
    public static function kegagalanEndpoint(): array
    {
        return [
            '401 tanda tangan' => [401, ['ok' => false, 'galat' => 'tanda_tangan']],
            '503 belum diatur' => [503, ['ok' => false, 'galat' => 'belum_diatur']],
            '500 galat server' => [500, []],
            '200 tanpa isi yang sah' => [200, ['ok' => false]],
            'jaringan putus' => [null, []],
        ];
    }

    public function test_token_yang_terbit_sebelum_keluarkan_semua_sesi_ditolak(): void
    {
        $iat = now()->timestamp - 30;
        $this->palsukanEndpoint($this->jawabanBerhak(sesiValidSejak: $iat + 10));

        $jawaban = $this->get($this->alamatSso($this->tokenSso(['iat' => $iat, 'exp' => $iat + 120])));

        $jawaban->assertRedirectToRoute('masuk')->assertSessionHas('alasan', 'sesi_berakhir');
        $this->assertGuest();
    }

    #[DataProvider('alamatTanpaTokenUtuh')]
    public function test_alamat_sso_tanpa_token_yang_utuh_hanya_mengalihkan(string $alamat): void
    {
        $jawaban = $this->get($alamat);

        $this->assertDitolakSebagaiTautanTidakSah($jawaban);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function alamatTanpaTokenUtuh(): array
    {
        return [
            'tanpa t' => ['/sso/spk'],
            't kosong' => ['/sso/spk?t='],
            't berupa larik' => ['/sso/spk?t[]=a.b'],
            't tanpa tanda tangan' => ['/sso/spk?t=abc'],
            't bukan base64url' => ['/sso/spk?t=a+b.c%2Fd'],
        ];
    }

    public function test_rahasia_yang_belum_diatur_menolak_semua_token(): void
    {
        config(['services.spk.sso_secret' => '']);

        $jawaban = $this->get($this->alamatSso($this->tokenSso(rahasia: '')));

        $this->assertDitolakSebagaiTautanTidakSah($jawaban);
        Http::assertNothingSent();
    }

    public function test_token_sah_milik_akun_lain_mengganti_sesi_yang_sedang_terbuka(): void
    {
        $lain = User::factory()->create(['spk_id' => 5]);
        $this->palsukanEndpoint($this->jawabanBerhak());

        $this->actingAs($lain)->withSession($this->sesiSso() + ['titipan' => 'milik sesi lama'])
            ->get($this->alamatSso($this->tokenSso(['uid' => 18])))
            ->assertRedirectToRoute('beranda')
            ->assertSessionMissing('titipan');

        $this->assertAuthenticatedAs(User::query()->where('spk_id', 18)->sole());
    }

    public function test_token_sah_milik_akun_lain_yang_tidak_berhak_mengakhiri_sesi_yang_sedang_terbuka(): void
    {
        $lain = User::factory()->create(['spk_id' => 5]);
        $this->palsukanEndpoint($this->jawabanTidakBerhak('tidak_aktif'));

        $this->actingAs($lain)->withSession($this->sesiSso())
            ->get($this->alamatSso($this->tokenSso(['uid' => 18])))
            ->assertRedirectToRoute('masuk');

        $this->assertGuest();
    }

    public function test_terlalu_banyak_token_tertolak_dari_satu_alamat_dibatasi(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak());

        for ($percobaan = 0; $percobaan < 30; $percobaan++) {
            $this->get('/sso/spk?t=abc.def');
        }

        $jawaban = $this->get($this->alamatSso($this->tokenSso()));

        $jawaban->assertRedirectToRoute('masuk')->assertSessionHas('alasan', 'terlalu_sering');
        $this->assertGuest();
    }

    private function assertDitolakSebagaiTautanTidakSah(mixed $jawaban): void
    {
        $jawaban->assertRedirectToRoute('masuk')->assertSessionHas('alasan', 'tautan_tidak_sah');
        $this->assertStringNotContainsString('t=', (string) $jawaban->headers->get('Location'));
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }
}
