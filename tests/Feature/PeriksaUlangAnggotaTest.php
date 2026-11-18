<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MemalsukanSpk;
use Tests\TestCase;

class PeriksaUlangAnggotaTest extends TestCase
{
    use MemalsukanSpk, RefreshDatabase;

    private User $anggota;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        $this->freezeTime();

        $this->anggota = User::factory()->create(['spk_id' => 18, 'nama' => 'Nama Lama']);
    }

    public function test_sebelum_15_menit_tidak_bertanya_ke_spk(): void
    {
        $this->palsukanEndpoint($this->jawabanTidakBerhak('tidak_aktif'));
        $sesi = $this->sesiSso();
        $this->travel(14)->minutes();

        $this->actingAs($this->anggota)->withSession($sesi)->get(route('beranda'))->assertOk();

        Http::assertNothingSent();
        $this->assertAuthenticatedAs($this->anggota);
    }

    public function test_setelah_15_menit_anggota_yang_masih_berhak_tetap_masuk_dan_datanya_diperbarui(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak());
        $sesi = $this->sesiSso();
        $this->travel(15)->minutes();

        $this->actingAs($this->anggota)->withSession($sesi)->get(route('beranda'))
            ->assertOk()
            ->assertSee('Halo, Sri Wahyuni.')
            ->assertSessionHas('sso.diperiksa_pada', now()->timestamp);

        $this->assertEndpointDitanyaTentang(18);
        $this->assertSame('Sri Wahyuni', $this->anggota->refresh()->nama);
    }

    public function test_pemeriksaan_yang_berhasil_menunda_pemeriksaan_berikutnya_15_menit(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak());
        $sesi = $this->sesiSso();
        $this->travel(15)->minutes();

        $this->actingAs($this->anggota)->withSession($sesi)->get(route('beranda'))->assertOk();
        $this->travel(14)->minutes();
        $this->get(route('beranda'))->assertOk();

        Http::assertSentCount(1);
    }

    #[DataProvider('alasanPencabutan')]
    public function test_anggota_yang_tidak_lagi_berhak_diputus(string $alasan): void
    {
        $this->palsukanEndpoint($this->jawabanTidakBerhak($alasan));
        $sesi = $this->sesiSso();
        $this->travel(16)->minutes();

        $jawaban = $this->actingAs($this->anggota)->withSession($sesi + ['titipan' => 'isi sesi'])->get(route('beranda'));

        $jawaban->assertRedirectToRoute('masuk')
            ->assertSessionHas('alasan', $alasan)
            ->assertSessionMissing('titipan')
            ->assertSessionMissing('sso.mulai');
        $this->assertGuest();
        $this->get(route('beranda'))->assertRedirectToRoute('masuk');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function alasanPencabutan(): array
    {
        return [
            'dinonaktifkan' => ['tidak_aktif'],
            'wajib ganti password' => ['wajib_ganti_password'],
            'akun dihapus' => ['tidak_ditemukan'],
        ];
    }

    public function test_sesi_yang_dimulai_sebelum_keluarkan_semua_sesi_diputus(): void
    {
        $mulai = now()->timestamp;
        $this->palsukanEndpoint($this->jawabanBerhak(sesiValidSejak: $mulai + 1));
        $sesi = $this->sesiSso(mulai: $mulai);
        $this->travel(16)->minutes();

        $jawaban = $this->actingAs($this->anggota)->withSession($sesi)->get(route('beranda'));

        $jawaban->assertRedirectToRoute('masuk')->assertSessionHas('alasan', 'sesi_berakhir');
        $this->assertGuest();
    }

    public function test_sesi_yang_dimulai_tepat_pada_atau_setelah_keluarkan_semua_sesi_dipertahankan(): void
    {
        $mulai = now()->timestamp;
        $this->palsukanEndpoint($this->jawabanBerhak(sesiValidSejak: $mulai));
        $sesi = $this->sesiSso(mulai: $mulai);
        $this->travel(16)->minutes();

        $this->actingAs($this->anggota)->withSession($sesi)->get(route('beranda'))->assertOk();

        $this->assertAuthenticatedAs($this->anggota);
    }

    public function test_gangguan_sementara_mempertahankan_sesi_selama_masa_tenggang(): void
    {
        $this->palsukanEndpointPutus();
        $sesi = $this->sesiSso();
        $this->travel(16)->minutes();

        $this->actingAs($this->anggota)->withSession($sesi)->get(route('beranda'))->assertOk();
        $this->travel(30)->seconds();
        $this->get(route('beranda'))->assertOk();

        $this->assertAuthenticatedAs($this->anggota);
        Http::assertSentCount(1);

        $this->travel(31)->seconds();
        $this->get(route('beranda'))->assertOk();

        Http::assertSentCount(2);
    }

    public function test_gangguan_yang_melewati_masa_tenggang_memutus_sesi(): void
    {
        $this->palsukanEndpoint([], 500);
        $sesi = $this->sesiSso();
        $this->travel(31)->minutes();

        $jawaban = $this->actingAs($this->anggota)->withSession($sesi)->get(route('beranda'));

        $jawaban->assertRedirectToRoute('masuk')->assertSessionHas('alasan', 'gangguan');
        $this->assertGuest();
    }

    /**
     * @param  array<string, mixed>  $badan
     */
    #[DataProvider('penolakanTegas')]
    public function test_penolakan_tegas_dari_spk_memutus_sesi_seketika(int $status, array $badan): void
    {
        $this->palsukanEndpoint($badan, $status);
        $sesi = $this->sesiSso();
        $this->travel(16)->minutes();

        $jawaban = $this->actingAs($this->anggota)->withSession($sesi)->get(route('beranda'));

        $jawaban->assertRedirectToRoute('masuk')->assertSessionHas('alasan', 'gangguan');
        $this->assertGuest();
    }

    /**
     * @return array<string, array{int, array<string, mixed>}>
     */
    public static function penolakanTegas(): array
    {
        return [
            'rahasia tidak lagi cocok' => [401, ['ok' => false, 'galat' => 'tanda_tangan']],
            'integrasi dimatikan' => [503, ['ok' => false, 'galat' => 'belum_diatur']],
        ];
    }

    public function test_sesi_tanpa_penanda_sso_diputus(): void
    {
        $jawaban = $this->actingAs($this->anggota)->get(route('beranda'));

        $jawaban->assertRedirectToRoute('masuk');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_permintaan_json_dari_sesi_yang_diputus_dijawab_401(): void
    {
        $this->palsukanEndpoint($this->jawabanTidakBerhak('tidak_aktif'));
        $sesi = $this->sesiSso();
        $this->travel(16)->minutes();

        $this->actingAs($this->anggota)->withSession($sesi)->getJson(route('beranda'))->assertUnauthorized();

        $this->assertGuest();
    }
}
