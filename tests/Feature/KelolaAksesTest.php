<?php

namespace Tests\Feature;

use App\Enums\Hak;
use App\Models\Peran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\MemalsukanSpk;
use Tests\TestCase;

class KelolaAksesTest extends TestCase
{
    use MemalsukanSpk, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
    }

    public function test_tamu_dialihkan_ke_halaman_masuk(): void
    {
        $this->get(route('kelola.dasbor'))->assertRedirectToRoute('masuk');
    }

    public function test_anggota_biasa_tidak_dapat_membuka_dashboard(): void
    {
        $anggota = User::factory()->create(['peran' => 'Anggota']);

        $this->masukSebagai($anggota)->get(route('kelola.dasbor'))->assertForbidden();
        $this->get(route('beranda'))->assertOk()->assertDontSee('Dashboard pengelola');
    }

    public function test_administrator_spk_adalah_superadmin_dengan_seluruh_menu(): void
    {
        $superadmin = User::factory()->superadmin()->create();

        $jawaban = $this->masukSebagai($superadmin)->get(route('kelola.dasbor'));

        $jawaban->assertOk()
            ->assertSee('Superadmin')
            ->assertSee('Surel (Brevo)')
            ->assertSee('Peran &amp; hak akses', false)
            ->assertSee('Rekap &amp; unduhan', false);
        $this->get(route('beranda'))->assertSee('Dashboard pengelola');
    }

    public function test_nama_peran_superadmin_dibandingkan_tanpa_peduli_huruf_besar(): void
    {
        $superadmin = User::factory()->create(['peran' => '  administrator ']);

        $this->masukSebagai($superadmin)->get(route('kelola.dasbor'))->assertOk();
    }

    public function test_peran_spk_lain_tidak_menjadi_superadmin(): void
    {
        $ketua = User::factory()->create(['peran' => 'Ketua']);

        $this->masukSebagai($ketua)->get(route('kelola.dasbor'))->assertForbidden();
    }

    public function test_administrator_rangkul_hanya_membuka_bagian_sesuai_haknya(): void
    {
        $pengelola = User::factory()->create(['peran' => 'Pengurus']);
        $pengelola->daftarPeran()->attach(Peran::query()->where('kunci', 'administrator')->sole());

        $this->masukSebagai($pengelola);

        $this->get(route('kelola.dasbor'))->assertOk()->assertDontSee('Surel (Brevo)');
        $this->get(route('kelola.pengaturan.layanan'))->assertOk();
        $this->get(route('kelola.pengaturan.halaman'))->assertOk();
        $this->get(route('kelola.pengguna.index'))->assertOk();
        $this->get(route('kelola.audit.index'))->assertOk();
        $this->get(route('kelola.pengaturan.surel'))->assertForbidden();
        $this->get(route('kelola.peran.index'))->assertForbidden();
        $this->get(route('kelola.rekap.index'))->assertForbidden();
    }

    public function test_peer_konselor_tidak_dapat_membuka_dashboard(): void
    {
        $konselor = User::factory()->create();
        $konselor->daftarPeran()->attach(Peran::query()->where('kunci', 'peer_konselor')->sole());

        $this->masukSebagai($konselor)->get(route('kelola.dasbor'))->assertForbidden();
        $this->assertTrue($konselor->refresh()->punyaHak(Hak::KonselingTangani));
    }

    /**
     * @param  array<int, string|int>  $parameter
     */
    #[DataProvider('ruteBerhak')]
    public function test_setiap_bagian_dashboard_menuntut_haknya_sendiri(string $metode, string $rute, array $parameter = []): void
    {
        $pengelola = User::factory()->create();
        $pengelola->daftarPeran()->attach(Peran::factory()->denganHak(Hak::DasborLihat)->create());
        $sasaran = User::factory()->create();
        $peran = Peran::query()->where('kunci', 'peer_konselor')->sole();

        $parameter = array_map(fn (string|int $nilai) => match ($nilai) {
            'sasaran' => $sasaran,
            'peran' => $peran,
            default => $nilai,
        }, $parameter);

        $this->masukSebagai($pengelola)->call($metode, route($rute, $parameter))->assertForbidden();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2?: array<int, string|int>}>
     */
    public static function ruteBerhak(): array
    {
        return [
            'lihat layanan' => ['GET', 'kelola.pengaturan.layanan'],
            'simpan layanan' => ['PUT', 'kelola.pengaturan.layanan'],
            'simpan halaman' => ['PUT', 'kelola.pengaturan.halaman'],
            'lihat surel' => ['GET', 'kelola.pengaturan.surel'],
            'uji surel' => ['POST', 'kelola.pengaturan.surel.uji'],
            'lihat whatsapp' => ['GET', 'kelola.pengaturan.whatsapp'],
            'simpan whatsapp' => ['PUT', 'kelola.pengaturan.whatsapp'],
            'uji whatsapp' => ['POST', 'kelola.pengaturan.whatsapp.uji'],
            'periksa perangkat whatsapp' => ['POST', 'kelola.pengaturan.whatsapp.periksa'],
            'daftar pengguna' => ['GET', 'kelola.pengguna.index'],
            'detail pengguna' => ['GET', 'kelola.pengguna.tampil', ['sasaran']],
            'tambah pengguna' => ['POST', 'kelola.pengguna.tambah'],
            'cari anggota di spk' => ['GET', 'kelola.pengguna.cari-spk'],
            'tugaskan peran' => ['POST', 'kelola.pengguna.tugaskan', ['sasaran']],
            'cabut peran' => ['DELETE', 'kelola.pengguna.cabut', ['sasaran', 'peran']],
            'daftar peran' => ['GET', 'kelola.peran.index'],
            'buat peran' => ['POST', 'kelola.peran.store'],
            'hapus peran' => ['DELETE', 'kelola.peran.destroy', ['peran']],
            'audit log' => ['GET', 'kelola.audit.index'],
            'rekap' => ['GET', 'kelola.rekap.index'],
            'unduh rekap' => ['GET', 'kelola.rekap.unduh', ['anggota']],
        ];
    }

    public function test_status_superadmin_ikut_dicabut_saat_peran_spk_berubah(): void
    {
        $this->freezeTime();
        $mantan = User::factory()->superadmin()->create(['spk_id' => 18]);
        $this->palsukanEndpoint($this->jawabanBerhak(['peran' => 'Anggota']));
        $sesi = $this->sesiSso();
        $this->travel(16)->minutes();

        $this->actingAs($mantan)->withSession($sesi)->get(route('kelola.dasbor'))->assertForbidden();

        $this->assertSame('Anggota', $mantan->refresh()->peran);
        $this->assertAuthenticatedAs($mantan);
    }

    public function test_peran_yang_dicabut_langsung_menutup_akses(): void
    {
        $pengelola = User::factory()->create();
        $administrator = Peran::query()->where('kunci', 'administrator')->sole();
        $pengelola->daftarPeran()->attach($administrator);

        $this->masukSebagai($pengelola)->get(route('kelola.dasbor'))->assertOk();

        $pengelola->daftarPeran()->detach($administrator);

        // Pengguna dimuat ulang dari basis data di setiap permintaan nyata.
        $this->actingAs($pengelola->fresh())->get(route('kelola.dasbor'))->assertForbidden();
    }
}
