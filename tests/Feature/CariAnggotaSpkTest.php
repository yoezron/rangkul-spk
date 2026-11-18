<?php

namespace Tests\Feature;

use App\Enums\Hak;
use App\Models\Peran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MemalsukanSpk;
use Tests\Concerns\MembuatKasus;
use Tests\TestCase;

class CariAnggotaSpkTest extends TestCase
{
    use MemalsukanSpk, MembuatKasus, RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        $this->freezeTime();
        $this->superadmin = User::factory()->superadmin()->create();
    }

    /**
     * @param  list<array<string, mixed>>  $hasil
     */
    private function palsukanCari(array $hasil, int $status = 200): void
    {
        Http::fake([
            'spk.test/rangkul/cari*' => Http::response(['ok' => $status === 200, 'waktu' => now()->timestamp, 'hasil' => $hasil], $status),
            'spk.test/rangkul/anggota*' => Http::response($this->jawabanBerhak(['id' => 77, 'nama' => 'Dewi Lestari'])),
        ]);
    }

    public function test_mencari_anggota_menurut_nama_dengan_permintaan_bertanda_tangan(): void
    {
        $this->palsukanCari([
            ['id' => 77, 'nama' => 'Dewi Lestari', 'kampus' => 'Universitas Uji', 'wilayah' => 'Jawa Barat', 'peran' => 'Pengurus'],
            ['id' => 78, 'nama' => 'Dewi Sartika', 'kampus' => 'Politeknik Uji', 'wilayah' => 'Banten', 'peran' => 'Anggota'],
        ]);

        $this->masukSebagai($this->superadmin)
            ->get(route('kelola.pengguna.cari-spk', ['nama' => '  Dewi   ']))
            ->assertOk()
            ->assertSee('Dewi Lestari')
            ->assertSee('Universitas Uji · Jawa Barat · Pengurus')
            ->assertSee('Dewi Sartika')
            ->assertSee('Peer Konselor')
            ->assertDontSee('Id akun SPK');

        Http::assertSent(function (Request $permintaan): bool {
            $waktu = $permintaan->header('X-Rangkul-Waktu')[0] ?? '';

            return $permintaan->url() === self::ALAMAT_SPK.'rangkul/cari?q=Dewi'
                && $waktu === (string) now()->timestamp
                && ($permintaan->header('X-Rangkul-Tanda')[0] ?? '') === hash_hmac('sha256', "cari\nDewi\n{$waktu}", self::RAHASIA);
        });
    }

    public function test_kata_kunci_terlalu_pendek_tidak_dikirim_ke_spk(): void
    {
        Http::fake();

        $this->masukSebagai($this->superadmin)
            ->get(route('kelola.pengguna.cari-spk', ['nama' => 'De']))
            ->assertOk()
            ->assertSee('Ketik 3 sampai 60 huruf');

        Http::assertNothingSent();
    }

    public function test_hasil_kosong_dan_gangguan_dijelaskan(): void
    {
        $this->palsukanCari([]);
        $this->masukSebagai($this->superadmin)->get(route('kelola.pengguna.cari-spk', ['nama' => 'Zzzz']))
            ->assertOk()->assertSee('Tidak ada anggota terkonfirmasi dengan nama');

        Http::fake(['spk.test/*' => Http::failedConnection()]);
        $this->get(route('kelola.pengguna.cari-spk', ['nama' => 'Dewi']))
            ->assertOk()->assertSee('spk.or.id sedang tidak dapat dihubungi');
    }

    public function test_anggota_yang_sudah_di_rangkul_ditandai(): void
    {
        $ada = User::factory()->create(['spk_id' => 78, 'nama' => 'Dewi Sartika']);
        $ada->daftarPeran()->attach($this->peranBawaan('peer_konselor'));
        $this->palsukanCari([['id' => 78, 'nama' => 'Dewi Sartika', 'kampus' => '', 'wilayah' => '', 'peran' => 'Anggota']]);

        $this->masukSebagai($this->superadmin)->get(route('kelola.pengguna.cari-spk', ['nama' => 'Sartika']))
            ->assertOk()
            ->assertSee('Sudah di Rangkul')
            ->assertSee(route('kelola.pengguna.tampil', $ada), false);
    }

    public function test_menambahkan_hasil_pencarian_sekaligus_menugaskan_peran(): void
    {
        $this->palsukanCari([]);
        $konselor = $this->peranBawaan('peer_konselor');

        $this->masukSebagai($this->superadmin)
            ->post(route('kelola.pengguna.tambah'), ['spk_id' => 77, 'peran_id' => $konselor->id])
            ->assertSessionHas('status', 'Dewi Lestari ditambahkan dan kini memegang peran Peer Konselor.');

        $baru = User::query()->where('spk_id', 77)->sole();
        $this->assertTrue($baru->punyaHak(Hak::KonselingTangani));
        $this->assertDatabaseHas('audit_log', ['aksi' => 'pengguna.ditambahkan']);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'penugasan.diberikan']);
    }

    public function test_menugaskan_peran_kepada_anggota_yang_sudah_ada_lewat_pencarian(): void
    {
        Http::fake();
        $ada = User::factory()->create(['spk_id' => 78]);

        $this->masukSebagai($this->superadmin)
            ->post(route('kelola.pengguna.tambah'), ['spk_id' => 78, 'peran_id' => $this->peranBawaan('komite')->id])
            ->assertRedirectToRoute('kelola.pengguna.tampil', $ada);

        $this->assertTrue($ada->refresh()->punyaHak(Hak::PermintaanKelola));
        Http::assertNothingSent();
    }

    public function test_peran_yang_lebih_berkuasa_dari_pengelola_ditolak(): void
    {
        $this->palsukanCari([]);
        $administrator = User::factory()->create();
        $administrator->daftarPeran()->attach($this->peranBawaan('administrator'));
        $berkuasa = Peran::factory()->denganHak(Hak::DasborLihat, Hak::PeranKelola)->create();

        $this->masukSebagai($administrator)
            ->post(route('kelola.pengguna.tambah'), ['spk_id' => 77, 'peran_id' => $berkuasa->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['spk_id' => 77]);
    }
}
