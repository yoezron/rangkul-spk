<?php

namespace Tests\Feature;

use App\Enums\Hak;
use App\Models\AuditLog;
use App\Models\Peran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MemalsukanSpk;
use Tests\TestCase;

class KelolaPeranPenggunaTest extends TestCase
{
    use MemalsukanSpk, RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        $this->superadmin = User::factory()->superadmin()->create();
    }

    private function peranBawaan(string $kunci): Peran
    {
        return Peran::query()->where('kunci', $kunci)->sole();
    }

    private function pengelolaDenganHak(Hak ...$hak): User
    {
        $pengelola = User::factory()->create(['peran' => 'Pengurus']);
        $pengelola->daftarPeran()->attach(Peran::factory()->denganHak(Hak::DasborLihat, ...$hak)->create());

        return $pengelola;
    }

    public function test_superadmin_membuat_peran_dan_hak_dasbor_ditambahkan_otomatis(): void
    {
        $this->masukSebagai($this->superadmin)
            ->post(route('kelola.peran.store'), [
                'nama' => 'Pengawas Etik',
                'deskripsi' => 'Memantau audit log.',
                'hak' => ['audit.lihat'],
            ])
            ->assertRedirectToRoute('kelola.peran.index');

        $peran = Peran::query()->where('nama', 'Pengawas Etik')->sole();
        $this->assertSame('pengawas_etik', $peran->kunci);
        $this->assertSame(['dasbor.lihat', 'audit.lihat'], $peran->hak);
        $this->assertFalse($peran->bawaan);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'peran.dibuat', 'sasaran' => 'Pengawas Etik']);
    }

    public function test_hak_yang_tidak_dikenal_ditolak(): void
    {
        $this->masukSebagai($this->superadmin)
            ->post(route('kelola.peran.store'), ['nama' => 'Aneh', 'hak' => ['semua.boleh']])
            ->assertSessionHasErrors('hak.0');

        $this->assertDatabaseMissing('peran', ['nama' => 'Aneh']);
    }

    public function test_pengelola_tidak_dapat_membuat_peran_dengan_hak_yang_tidak_dimilikinya(): void
    {
        $pengelola = $this->pengelolaDenganHak(Hak::PeranKelola);

        $this->masukSebagai($pengelola)
            ->post(route('kelola.peran.store'), ['nama' => 'Jalan Pintas', 'hak' => ['rekap.unduh']])
            ->assertSessionHasErrors('hak.0');

        $this->assertDatabaseMissing('peran', ['nama' => 'Jalan Pintas']);
    }

    public function test_pengelola_tidak_dapat_mengubah_peran_yang_haknya_melebihi_haknya(): void
    {
        $pengelola = $this->pengelolaDenganHak(Hak::PeranKelola);
        $administrator = $this->peranBawaan('administrator');

        $this->masukSebagai($pengelola);
        $this->get(route('kelola.peran.edit', $administrator))->assertForbidden();
        $this->put(route('kelola.peran.update', $administrator), ['nama' => 'Administrator Rangkul', 'hak' => []])->assertForbidden();
    }

    public function test_perubahan_hak_peran_tercatat(): void
    {
        $peran = Peran::factory()->denganHak(Hak::DasborLihat, Hak::AuditLihat)->create(['nama' => 'Pengawas']);

        $this->masukSebagai($this->superadmin)
            ->put(route('kelola.peran.update', $peran), ['nama' => 'Pengawas', 'hak' => ['rekap.unduh']])
            ->assertRedirectToRoute('kelola.peran.index');

        $this->assertSame(['dasbor.lihat', 'rekap.unduh'], $peran->refresh()->hak);
        $catatan = AuditLog::query()->where('aksi', 'peran.diubah')->sole();
        $this->assertSame(['rekap.unduh'], $catatan->rincian['hak_ditambah']);
        $this->assertSame(['audit.lihat'], $catatan->rincian['hak_dicabut']);
    }

    public function test_peran_bawaan_tidak_dapat_dihapus(): void
    {
        $this->masukSebagai($this->superadmin)
            ->delete(route('kelola.peran.destroy', $this->peranBawaan('peer_konselor')))
            ->assertSessionHasErrors('peran');

        $this->assertDatabaseHas('peran', ['kunci' => 'peer_konselor']);
    }

    public function test_menghapus_peran_mencabutnya_dari_semua_pemegang(): void
    {
        $peran = Peran::factory()->denganHak(Hak::KonselingTangani)->create();
        $pemegang = User::factory()->create();
        $pemegang->daftarPeran()->attach($peran);

        $this->masukSebagai($this->superadmin)->delete(route('kelola.peran.destroy', $peran))->assertRedirectToRoute('kelola.peran.index');

        $this->assertModelMissing($peran);
        $this->assertDatabaseCount('peran_user', 0);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'peran.dihapus']);
    }

    public function test_superadmin_menugaskan_dan_mencabut_peer_konselor(): void
    {
        $anggota = User::factory()->create(['nama' => 'Rina Kurnia']);
        $konselor = $this->peranBawaan('peer_konselor');

        $this->masukSebagai($this->superadmin)
            ->post(route('kelola.pengguna.tugaskan', $anggota), ['peran_id' => $konselor->id])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('peran_user', ['user_id' => $anggota->id, 'peran_id' => $konselor->id, 'ditugaskan_oleh' => $this->superadmin->id]);
        $this->assertTrue($anggota->refresh()->punyaHak(Hak::KonselingTangani));

        $this->get(route('kelola.pengguna.tampil', $anggota))->assertOk()->assertSee('Peer Konselor')->assertSee('oleh '.$this->superadmin->nama);

        $this->delete(route('kelola.pengguna.cabut', [$anggota, $konselor]))->assertSessionHas('status');

        $this->assertDatabaseCount('peran_user', 0);
        $this->assertSame(
            ['penugasan.diberikan', 'penugasan.dicabut'],
            AuditLog::query()->orderBy('id')->pluck('aksi')->all(),
        );
        $this->assertSame('Rina Kurnia (SPK #'.$anggota->spk_id.')', AuditLog::query()->value('sasaran'));
    }

    public function test_administrator_rangkul_tidak_dapat_menugaskan_peran_yang_lebih_berkuasa(): void
    {
        $pengelola = User::factory()->create();
        $pengelola->daftarPeran()->attach($this->peranBawaan('administrator'));
        $berkuasa = Peran::factory()->denganHak(Hak::DasborLihat, Hak::PeranKelola)->create();
        $anggota = User::factory()->create();

        $this->masukSebagai($pengelola)
            ->post(route('kelola.pengguna.tugaskan', $pengelola), ['peran_id' => $berkuasa->id])
            ->assertForbidden();

        $this->post(route('kelola.pengguna.tugaskan', $anggota), ['peran_id' => $this->peranBawaan('peer_konselor')->id])
            ->assertSessionHas('status');

        $this->assertFalse($pengelola->refresh()->punyaHak(Hak::PeranKelola));
        $this->assertTrue($anggota->refresh()->punyaHak(Hak::KonselingTangani));
    }

    public function test_nama_berbahaya_dari_spk_tidak_lolos_ke_skrip_konfirmasi(): void
    {
        $anggota = User::factory()->create(['nama' => "x');alert(1);//"]);
        $anggota->daftarPeran()->attach($this->peranBawaan('peer_konselor'));

        $this->masukSebagai($this->superadmin)
            ->get(route('kelola.pengguna.tampil', $anggota))
            ->assertOk()
            ->assertDontSee("x');alert(1);//", false)
            ->assertSee('u0027);alert(1);', false);
    }

    public function test_menambahkan_anggota_berhak_dari_spk(): void
    {
        $this->palsukanEndpoint($this->jawabanBerhak(['id' => 77, 'nama' => 'Dewi Lestari']));

        $jawaban = $this->masukSebagai($this->superadmin)->post(route('kelola.pengguna.tambah'), ['spk_id' => 77]);

        $baru = User::query()->where('spk_id', 77)->sole();
        $jawaban->assertRedirectToRoute('kelola.pengguna.tampil', $baru);
        $this->assertSame('Dewi Lestari', $baru->nama);
        $this->assertEndpointDitanyaTentang(77);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'pengguna.ditambahkan']);
    }

    public function test_anggota_yang_tidak_berhak_tidak_dapat_ditambahkan(): void
    {
        $this->palsukanEndpoint($this->jawabanTidakBerhak('belum_terkonfirmasi', 77));

        $this->masukSebagai($this->superadmin)
            ->post(route('kelola.pengguna.tambah'), ['spk_id' => 77])
            ->assertSessionHasErrors(['spk_id' => 'Tidak dapat ditambahkan: akun itu masih berstatus Calon Anggota.']);

        $this->assertDatabaseMissing('users', ['spk_id' => 77]);
    }

    public function test_spk_yang_tidak_terjangkau_saat_menambahkan_anggota(): void
    {
        $this->palsukanEndpointPutus();

        $this->masukSebagai($this->superadmin)
            ->post(route('kelola.pengguna.tambah'), ['spk_id' => 77])
            ->assertSessionHasErrors('spk_id');

        $this->assertDatabaseMissing('users', ['spk_id' => 77]);
    }

    public function test_anggota_yang_sudah_ada_tidak_ditanyakan_ulang(): void
    {
        $ada = User::factory()->create(['spk_id' => 77]);

        $this->masukSebagai($this->superadmin)
            ->post(route('kelola.pengguna.tambah'), ['spk_id' => 77])
            ->assertRedirectToRoute('kelola.pengguna.tampil', $ada);

        Http::assertNothingSent();
    }

    public function test_mencari_dan_menyaring_pengguna(): void
    {
        $konselor = User::factory()->create(['nama' => 'Bambang Konselor', 'kampus' => 'Universitas Alpha']);
        $konselor->daftarPeran()->attach($this->peranBawaan('peer_konselor'));
        User::factory()->create(['nama' => 'Citra Anggota', 'kampus' => 'Universitas Beta']);

        $this->masukSebagai($this->superadmin);

        $this->get(route('kelola.pengguna.index', ['cari' => 'Beta']))
            ->assertOk()->assertSee('Citra Anggota')->assertDontSee('Bambang Konselor');

        $this->get(route('kelola.pengguna.index', ['peran' => $this->peranBawaan('peer_konselor')->id]))
            ->assertOk()->assertSee('Bambang Konselor')->assertDontSee('Citra Anggota');

        $this->get(route('kelola.pengguna.index', ['cari' => '%']))
            ->assertOk()->assertSee('Tidak ada pengguna yang cocok.');
    }
}
