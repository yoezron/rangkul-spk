<?php

namespace Tests\Feature;

use App\Enums\StatusPermintaan;
use App\Models\AuditLog;
use App\Models\Permintaan;
use App\Models\User;
use App\Notifications\NotifikasiRangkul;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MemalsukanSpk;
use Tests\Concerns\MembuatKasus;
use Tests\TestCase;

class KomitePermintaanTest extends TestCase
{
    use MemalsukanSpk, MembuatKasus, RefreshDatabase;

    private User $komite;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        Notification::fake();
        $this->komite = $this->anggotaKomite();
    }

    public function test_daftar_menampilkan_darurat_lebih_dulu_dan_hanya_untuk_komite(): void
    {
        $biasa = Permintaan::factory()->for($this->anggotaBiasa(['nama' => 'Budi Biasa']), 'anggota')->create(['diajukan_pada' => now()]);
        $darurat = Permintaan::factory()->darurat()->for($this->anggotaBiasa(['nama' => 'Citra Darurat']), 'anggota')->create(['diajukan_pada' => now()->subDay()]);

        $this->masukSebagai($this->komite)->get(route('kelola.permintaan.index'))
            ->assertOk()
            ->assertSeeInOrder(['Citra Darurat', 'Budi Biasa'])
            ->assertSee('DARURAT');

        $this->masukSebagai($this->peerKonselor())->get(route('kelola.permintaan.index'))->assertForbidden();
        $this->masukSebagai($this->anggotaBiasa())->get(route('kelola.permintaan.tampil', $biasa))->assertForbidden();
        $this->assertNotNull($darurat);
    }

    public function test_komite_membaca_isian_lengkap_dan_pembukaannya_tercatat(): void
    {
        $permintaan = Permintaan::factory()->create(['uraian' => 'Atasan saya terus merendahkan saya di rapat jurusan.']);

        $this->masukSebagai($this->komite)->get(route('kelola.permintaan.tampil', $permintaan))
            ->assertOk()
            ->assertSee('Atasan saya terus merendahkan saya di rapat jurusan.')
            ->assertSee('Stres dan tekanan pekerjaan');

        $catatan = AuditLog::query()->where('aksi', 'permintaan.dibuka')->sole();
        $this->assertSame($permintaan->kode, $catatan->sasaran);
        $this->assertStringNotContainsString('rapat jurusan', json_encode($catatan->toArray()));
    }

    public function test_komite_menugaskan_konselor_dan_kedua_pihak_diberi_tahu(): void
    {
        $permintaan = Permintaan::factory()->for($this->anggotaBiasa(), 'anggota')->create();
        $konselor = $this->peerKonselor(['nama' => 'Konselor Sari', 'kampus' => 'Universitas Beta']);

        $this->masukSebagai($this->komite)
            ->post(route('kelola.permintaan.tugaskan', $permintaan), ['konselor_id' => $konselor->id])
            ->assertSessionHasNoErrors();

        $permintaan->refresh();
        $this->assertSame(StatusPermintaan::Berlangsung, $permintaan->status);
        $this->assertSame($konselor->id, $permintaan->konselor_id);
        $this->assertSame($this->komite->id, (int) $permintaan->getAttribute('ditugaskan_oleh'));
        $this->assertSame('sistem', $permintaan->pesan()->sole()->jenis);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'permintaan.ditugaskan', 'sasaran' => $permintaan->kode]);

        Notification::assertSentTo($konselor, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'penugasan.konselor');
        Notification::assertSentTo($permintaan->anggota, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'penugasan.anggota');

        $this->masukSebagai($konselor)->get(route('konseling.tampil', $permintaan))->assertOk()->assertSee($permintaan->uraian);
    }

    public function test_hanya_pemegang_hak_konseling_yang_dapat_ditugaskan_dan_bukan_pengaju_sendiri(): void
    {
        $pengajuKonselor = $this->peerKonselor();
        $permintaan = Permintaan::factory()->for($pengajuKonselor, 'anggota')->create();
        $bukanKonselor = $this->anggotaBiasa();

        $this->masukSebagai($this->komite);
        $this->post(route('kelola.permintaan.tugaskan', $permintaan), ['konselor_id' => $bukanKonselor->id])->assertSessionHasErrors('konselor_id');
        $this->post(route('kelola.permintaan.tugaskan', $permintaan), ['konselor_id' => $pengajuKonselor->id])->assertSessionHasErrors('konselor_id');

        $this->assertNull($permintaan->refresh()->konselor_id);
    }

    public function test_komite_yang_merangkap_konselor_dapat_ditugaskan(): void
    {
        $komiteKonselor = $this->anggotaKomite();
        $komiteKonselor->daftarPeran()->attach($this->peranBawaan('peer_konselor'));
        $permintaan = Permintaan::factory()->create();

        $this->masukSebagai($this->komite)
            ->post(route('kelola.permintaan.tugaskan', $permintaan), ['konselor_id' => $komiteKonselor->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($komiteKonselor->id, $permintaan->refresh()->konselor_id);
    }

    public function test_konselor_sekampus_butuh_konfirmasi_bila_anggota_memintanya(): void
    {
        $permintaan = Permintaan::factory()->for($this->anggotaBiasa(['kampus' => 'Universitas Alfa']), 'anggota')->create(['hindari_kampus_sama' => true]);
        $sekampus = $this->peerKonselor(['kampus' => 'universitas alfa ']);

        $this->masukSebagai($this->komite);
        $this->get(route('kelola.permintaan.tampil', $permintaan))->assertSee('SEKAMPUS');

        $this->post(route('kelola.permintaan.tugaskan', $permintaan), ['konselor_id' => $sekampus->id])->assertSessionHasErrors('konselor_id');
        $this->assertNull($permintaan->refresh()->konselor_id);

        $this->post(route('kelola.permintaan.tugaskan', $permintaan), ['konselor_id' => $sekampus->id, 'konfirmasi_kampus' => '1'])->assertSessionHasNoErrors();
        $this->assertSame($sekampus->id, $permintaan->refresh()->konselor_id);
    }

    public function test_mengganti_konselor_mencabut_akses_konselor_lama(): void
    {
        $lama = $this->peerKonselor();
        $baru = $this->peerKonselor();
        $permintaan = $this->kasusBerlangsung(konselor: $lama);

        $this->masukSebagai($this->komite)->post(route('kelola.permintaan.tugaskan', $permintaan), ['konselor_id' => $baru->id]);

        Notification::assertSentTo($permintaan->anggota, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => str_contains($notifikasi->judul, 'diganti'));
        $this->masukSebagai($lama)->get(route('konseling.tampil', $permintaan))->assertForbidden();
        $this->postJson(route('percakapan.kirim', $permintaan), ['isi' => 'Halo'])->assertForbidden();
    }

    public function test_komite_menolak_permintaan_dengan_penjelasan_untuk_anggota(): void
    {
        $anggota = $this->anggotaBiasa();
        $permintaan = Permintaan::factory()->for($anggota, 'anggota')->create();

        $this->masukSebagai($this->komite)->post(route('kelola.permintaan.tolak', $permintaan), ['alasan' => ''])->assertSessionHasErrors('alasan');
        $this->post(route('kelola.permintaan.tolak', $permintaan), ['alasan' => 'Silakan hubungi Layanan Advokasi SPK untuk persoalan ini.']);

        $this->assertSame(StatusPermintaan::Ditolak, $permintaan->refresh()->status);
        Notification::assertSentTo($anggota, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'permintaan.ditolak');

        $this->masukSebagai($anggota)->get(route('permintaan.tampil', $permintaan))->assertSee('Silakan hubungi Layanan Advokasi SPK');
    }

    public function test_tanda_darurat_dan_status_ditinjau_dapat_diubah_komite(): void
    {
        $permintaan = Permintaan::factory()->create();

        $this->masukSebagai($this->komite)->post(route('kelola.permintaan.tinjau', $permintaan));
        $this->assertSame(StatusPermintaan::Ditinjau, $permintaan->refresh()->status);

        $this->post(route('kelola.permintaan.darurat', $permintaan), ['darurat' => '1']);
        $this->assertTrue($permintaan->refresh()->darurat);
        $this->post(route('kelola.permintaan.darurat', $permintaan), ['darurat' => '0']);
        $this->assertFalse($permintaan->refresh()->darurat);
    }

    public function test_catatan_komite_tidak_terlihat_anggota_maupun_konselor(): void
    {
        $permintaan = $this->kasusBerlangsung();

        $this->masukSebagai($this->komite)->post(route('kelola.permintaan.catatan', $permintaan), ['catatan_komite' => 'Pantau ketat, riwayat konflik dengan dekan.']);

        $this->masukSebagai($permintaan->anggota)->get(route('permintaan.tampil', $permintaan))->assertDontSee('riwayat konflik dengan dekan');
        $this->masukSebagai($permintaan->konselor)->get(route('konseling.tampil', $permintaan))->assertDontSee('riwayat konflik dengan dekan');
    }

    public function test_superadmin_dapat_membuka_semua_permintaan(): void
    {
        $permintaan = Permintaan::factory()->create(['uraian' => 'Cerita yang dibaca superadmin.']);

        $this->masukSebagai(User::factory()->superadmin()->create())
            ->get(route('kelola.permintaan.tampil', $permintaan))
            ->assertOk()
            ->assertSee('Cerita yang dibaca superadmin.');
    }
}
