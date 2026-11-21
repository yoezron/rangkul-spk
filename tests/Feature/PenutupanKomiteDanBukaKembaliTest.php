<?php

namespace Tests\Feature;

use App\Enums\StatusPermintaan;
use App\Enums\StatusPertemuan;
use App\Models\Permintaan;
use App\Models\User;
use App\Notifications\NotifikasiRangkul;
use App\Notifications\SaluranWhatsapp;
use App\Services\PengaturanRangkul;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MemalsukanSpk;
use Tests\Concerns\MembuatKasus;
use Tests\TestCase;

class PenutupanKomiteDanBukaKembaliTest extends TestCase
{
    use MemalsukanSpk, MembuatKasus, RefreshDatabase;

    private User $komite;

    private User $konselor;

    private User $anggota;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        Notification::fake();
        $this->komite = $this->anggotaKomite();
        $this->konselor = $this->peerKonselor(['nama' => 'Konselor Sari', 'email' => 'sari@kampus.test', 'telp' => '081100000001']);
        $this->anggota = $this->anggotaBiasa(['nama' => 'Anggota Rina']);
    }

    private function kasusSelesai(mixed $selesaiPada = null): Permintaan
    {
        return Permintaan::factory()->for($this->anggota, 'anggota')->selesai($this->konselor, $selesaiPada)->create();
    }

    public function test_komite_menutup_langsung_kasus_berlangsung_dan_menandainya_selesai(): void
    {
        $permintaan = $this->kasusBerlangsung($this->anggota, $this->konselor);
        $akanDatang = $permintaan->pertemuan()->create(['mulai_pada' => now()->addDay(), 'durasi_menit' => 60, 'status' => StatusPertemuan::Terjadwal]);

        $this->masukSebagai($this->komite)
            ->post(route('penutupan.tutup', $permintaan), ['alasan' => 'Tidak ada kabar dari anggota dua minggu.'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $permintaan->refresh();
        $this->assertSame(StatusPermintaan::Selesai, $permintaan->status);
        $this->assertNotNull($permintaan->selesai_pada);
        $this->assertSame($this->komite->id, $permintaan->penutupan_ditinjau_oleh);
        $this->assertSame('Tidak ada kabar dari anggota dua minggu.', $permintaan->alasan_penutupan);
        $this->assertSame(StatusPertemuan::Dibatalkan, $akanDatang->refresh()->status);
        $this->assertStringContainsString('Komite menutup proses konsultasi', $permintaan->pesan()->where('jenis', 'sistem')->latest('id')->first()->isi);

        Notification::assertSentTo($this->anggota, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi, array $saluran, User $kepada): bool => $notifikasi->jenis === 'penutupan.komite'
            && ! str_contains($notifikasi->toWhatsapp($kepada), 'dua minggu'));
        Notification::assertSentTo($this->konselor, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'penutupan.komite');
        $this->assertDatabaseHas('audit_log', ['aksi' => 'penutupan.komite']);
    }

    public function test_tombol_tutup_hanya_untuk_komite_dan_wajib_beralasan(): void
    {
        $permintaan = $this->kasusBerlangsung($this->anggota, $this->konselor);

        $this->masukSebagai($this->komite)->get(route('kelola.permintaan.tampil', $permintaan))->assertSee('Tutup dan tandai selesai');
        $this->masukSebagai($this->komite)->post(route('penutupan.tutup', $permintaan), ['alasan' => ''])->assertSessionHasErrors('alasan');

        $this->masukSebagai($this->konselor)->post(route('penutupan.tutup', $permintaan), ['alasan' => 'Saya tutup sendiri.'])->assertForbidden();
        $this->masukSebagai($this->anggota)->post(route('penutupan.tutup', $permintaan), ['alasan' => 'Saya tutup sendiri.'])->assertForbidden();
        $this->assertSame(StatusPermintaan::Berlangsung, $permintaan->refresh()->status);
    }

    public function test_superadmin_dapat_menutup_kasus(): void
    {
        $permintaan = $this->kasusBerlangsung($this->anggota, $this->konselor);

        $this->masukSebagai(User::factory()->superadmin()->create())
            ->post(route('penutupan.tutup', $permintaan), ['alasan' => 'Sudah tuntas.'])
            ->assertRedirect();

        $this->assertSame(StatusPermintaan::Selesai, $permintaan->refresh()->status);
    }

    public function test_anggota_membuka_kembali_kasus_dalam_30_hari_dan_konselor_diberi_tahu_surel_dan_whatsapp(): void
    {
        $permintaan = $this->kasusSelesai(now()->subDays(10));
        $permintaan->update(['alasan_penutupan' => 'Tujuan tercapai.', 'catatan_tinjauan_penutupan' => 'Catatan lama.']);

        $this->masukSebagai($this->anggota)->get(route('permintaan.tampil', $permintaan))->assertSee('Buka kembali percakapan')->assertSee('Ajukan permintaan baru');

        $this->post(route('penutupan.buka-kembali', $permintaan), ['alasan' => 'Masalah di kantor muncul lagi minggu ini.'])
            ->assertRedirect(route('permintaan.tampil', $permintaan).'#percakapan');

        $permintaan->refresh();
        $this->assertSame(StatusPermintaan::Berlangsung, $permintaan->status);
        $this->assertNotNull($permintaan->dibuka_kembali_pada);
        $this->assertNull($permintaan->selesai_pada);
        $this->assertNull($permintaan->alasan_penutupan);
        $this->assertNull($permintaan->catatan_tinjauan_penutupan);
        $this->assertSame($this->konselor->id, $permintaan->konselor_id);
        $this->assertSame('Masalah di kantor muncul lagi minggu ini.', $permintaan->pesan()->where('jenis', 'pesan')->latest('id')->first()->isi);

        Notification::assertSentTo($this->konselor, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi, array $saluran, User $kepada): bool => $notifikasi->jenis === 'permintaan.dibuka_kembali'
            && $saluran === ['mail', SaluranWhatsapp::class]
            && ! str_contains($notifikasi->toWhatsapp($kepada), 'kantor'));
        Notification::assertSentTo($this->komite, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'permintaan.dibuka_kembali_komite' && ! $notifikasi->mendesak);

        $this->post(route('percakapan.kirim', $permintaan), ['isi' => 'Halo lagi.'])->assertRedirect();
    }

    public function test_komite_diberi_tanda_mendesak_bila_konselor_lama_tidak_lagi_aktif(): void
    {
        $permintaan = $this->kasusSelesai(now()->subDays(2));
        $this->konselor->daftarPeran()->detach();

        $this->masukSebagai($this->anggota)->post(route('penutupan.buka-kembali', $permintaan), ['alasan' => 'Ingin melanjutkan.'])->assertRedirect();

        Notification::assertSentTo($this->komite, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'permintaan.dibuka_kembali_komite' && $notifikasi->mendesak);
    }

    public function test_lewat_30_hari_hanya_dapat_mengajukan_permintaan_baru(): void
    {
        $permintaan = $this->kasusSelesai(now()->subDays(31));

        $this->masukSebagai($this->anggota)->get(route('permintaan.tampil', $permintaan))
            ->assertDontSee('Buka kembali percakapan')
            ->assertSee('Ajukan permintaan baru');

        $this->post(route('penutupan.buka-kembali', $permintaan), ['alasan' => 'Ingin melanjutkan.'])->assertForbidden();
        $this->assertSame(StatusPermintaan::Selesai, $permintaan->refresh()->status);
        Notification::assertNothingSent();

        $this->post(route('permintaan.mulai'))->assertRedirect();
        $this->assertSame(StatusPermintaan::Draf, $this->anggota->permintaan()->latest('id')->first()->status);
    }

    public function test_tidak_dapat_membuka_kembali_bila_ada_permintaan_lain_berjalan_atau_bukan_pemilik(): void
    {
        $permintaan = $this->kasusSelesai(now()->subDay());
        $this->kasusBerlangsung($this->anggota, $this->konselor);

        $this->masukSebagai($this->anggota)->post(route('penutupan.buka-kembali', $permintaan), ['alasan' => 'Ingin melanjutkan.'])
            ->assertSessionHasErrors('buka_kembali');
        $this->assertSame(StatusPermintaan::Selesai, $permintaan->refresh()->status);

        $this->masukSebagai($this->anggotaBiasa())->post(route('penutupan.buka-kembali', $permintaan), ['alasan' => 'Bukan milik saya.'])->assertForbidden();
    }

    public function test_tidak_dapat_membuka_kembali_saat_layanan_ditutup(): void
    {
        $permintaan = $this->kasusSelesai(now()->subDay());
        app(PengaturanRangkul::class)->simpan(['layanan.buka' => false]);

        $this->masukSebagai($this->anggota)->post(route('penutupan.buka-kembali', $permintaan), ['alasan' => 'Ingin melanjutkan.'])
            ->assertSessionHasErrors('buka_kembali');
        $this->assertSame(StatusPermintaan::Selesai, $permintaan->refresh()->status);
    }
}
