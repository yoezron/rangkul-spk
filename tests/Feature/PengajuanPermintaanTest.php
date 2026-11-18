<?php

namespace Tests\Feature;

use App\Enums\StatusPermintaan;
use App\Models\Instrumen;
use App\Models\Permintaan;
use App\Models\User;
use App\Notifications\NotifikasiRangkul;
use App\Services\PengaturanRangkul;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MemalsukanSpk;
use Tests\Concerns\MembuatKasus;
use Tests\TestCase;

class PengajuanPermintaanTest extends TestCase
{
    use MemalsukanSpk, MembuatKasus, RefreshDatabase;

    private User $anggota;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        Notification::fake();
        $this->anggota = $this->anggotaBiasa(['nama' => 'Ratna Dewi']);
    }

    public function test_anggota_mengajukan_permintaan_lengkap_dan_komite_diberi_tahu(): void
    {
        $komite = $this->anggotaKomite();

        $permintaan = $this->masukSebagai($this->anggota)->ajukanPermintaan();

        $this->assertSame(StatusPermintaan::Diajukan, $permintaan->status);
        $this->assertMatchesRegularExpression('/^RGK-\d{4}-\d{4}$/', $permintaan->kode);
        $this->assertFalse($permintaan->darurat);
        $this->assertSame(['stres_kerja', 'burnout'], $permintaan->topik);
        $this->assertTrue($permintaan->hindari_kampus_sama);
        $this->assertSame(3, $permintaan->jawabanAsesmen()->count());
        $this->assertNotNull($permintaan->diajukan_pada);

        $this->anggota->refresh();
        $this->assertTrue($this->anggota->notif_surel);
        $this->assertFalse($this->anggota->notif_whatsapp);

        Notification::assertSentTo($komite, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'permintaan.masuk' && ! $notifikasi->mendesak);
        Notification::assertSentTo($this->anggota, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'permintaan.diterima');

        $this->get(route('permintaan.tampil', $permintaan))
            ->assertOk()
            ->assertSee($permintaan->kode)
            ->assertSee('Terima kasih, permintaan Anda sudah kami terima');
    }

    public function test_cerita_dan_jawaban_tersimpan_terenkripsi(): void
    {
        $permintaan = $this->masukSebagai($this->anggota)->ajukanPermintaan();

        $baris = (array) DB::table('permintaan')->where('id', $permintaan->id)->first();
        $this->assertStringNotContainsString('memeriksa tugas mahasiswa', (string) $baris['uraian']);
        $this->assertStringNotContainsString('stres_kerja', (string) $baris['topik']);
        $this->assertStringNotContainsString('PHQ', (string) DB::table('jawaban_asesmen')->value('isian'));
        $this->assertSame('Setiap malam saya memeriksa tugas mahasiswa sampai larut dan merasa tidak sanggup lagi.', $permintaan->uraian);
    }

    public function test_jawaban_keselamatan_menandai_darurat_dan_menampilkan_kontak_krisis(): void
    {
        $komite = $this->anggotaKomite();
        $this->masukSebagai($this->anggota)->post(route('permintaan.mulai'));
        $permintaan = Permintaan::query()->sole();

        $this->put(route('permintaan.simpan', [$permintaan, 1]), $this->isianLangkah([1 => [
            'persetujuan_layanan' => '1',
            'persetujuan_data' => '1',
            'keselamatan' => ['pikiran_menyakiti_diri' => 'sering', 'terancam' => 'tidak', 'butuh_segera' => 'tidak'],
        ]])[1])->assertRedirectToRoute('permintaan.langkah', [$permintaan, 2]);

        $this->assertTrue($permintaan->refresh()->darurat);
        $this->get(route('permintaan.langkah', [$permintaan, 2]))
            ->assertOk()
            ->assertSee('Anda tidak sendirian')
            ->assertSee('112');

        foreach ([2, 3, 4, 5] as $langkah) {
            $this->put(route('permintaan.simpan', [$permintaan, $langkah]), $this->isianLangkah()[$langkah])->assertSessionHasNoErrors();
        }
        $this->post(route('permintaan.kirim', $permintaan));

        $permintaan->refresh();
        $this->assertTrue($permintaan->darurat);
        $this->assertStringContainsString('menyakiti diri', implode(' ', $permintaan->alasan_darurat));
        Notification::assertSentTo($komite, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'permintaan.masuk' && $notifikasi->mendesak);
    }

    public function test_butir_kritis_phq9_menandai_darurat(): void
    {
        $permintaan = $this->masukSebagai($this->anggota)->ajukanPermintaan([
            4 => ['jawaban' => $this->jawabanAsesmen(fn ($instrumen, $butir, $pilihan) => $butir['kunci'] === 'phq9' ? 2 : $pilihan[0]['nilai'])],
        ]);

        $this->assertTrue($permintaan->darurat);
        $this->assertStringContainsString('Pikiran bahwa lebih baik Anda mati', implode(' ', $permintaan->alasan_darurat));
        $this->assertTrue($permintaan->jawabanAsesmen()->where('kritis', true)->exists());
    }

    public function test_skor_asesmen_dihitung_sesuai_instrumen(): void
    {
        $permintaan = $this->masukSebagai($this->anggota)->ajukanPermintaan([
            4 => ['jawaban' => $this->jawabanAsesmen(fn ($instrumen, $butir, $pilihan) => $instrumen->kunci === 'cbi_kerja' ? 100 : 2)],
        ]);

        $skor = $permintaan->jawabanAsesmen->mapWithKeys(fn ($jawaban) => [$jawaban->isian['instrumen']['kunci'] => $jawaban->isian['skor'][0]]);
        $this->assertSame(18.0, (float) $skor['phq9']['nilai']);
        $this->assertSame('Cukup berat', $skor['phq9']['label']);
        $this->assertSame(14.0, (float) $skor['gad7']['nilai']);
        $this->assertSame('Sedang', $skor['gad7']['label']);
        // Enam butir bernilai 100, butir ke-7 terbalik (100 → 0): rerata 85,71.
        $this->assertSame(85.71, (float) $skor['cbi_kerja']['nilai']);
        $this->assertSame('Sangat tinggi', $skor['cbi_kerja']['label']);
    }

    public function test_semua_butir_asesmen_wajib_dijawab(): void
    {
        $this->masukSebagai($this->anggota)->post(route('permintaan.mulai'));
        $permintaan = Permintaan::query()->sole();
        foreach ([1, 2, 3] as $langkah) {
            $this->put(route('permintaan.simpan', [$permintaan, $langkah]), $this->isianLangkah()[$langkah]);
        }

        $jawaban = $this->jawabanAsesmen(fn () => 0);
        $phq = Instrumen::query()->where('kunci', 'phq9')->sole();
        unset($jawaban[$phq->id]['phq9']);

        $this->put(route('permintaan.simpan', [$permintaan, 4]), ['jawaban' => $jawaban])
            ->assertSessionHasErrors("jawaban.{$phq->id}.phq9");
        $this->assertSame(0, $permintaan->jawabanAsesmen()->count());
    }

    public function test_langkah_tidak_dapat_dilompati_dan_kirim_menolak_isian_belum_lengkap(): void
    {
        $this->masukSebagai($this->anggota)->post(route('permintaan.mulai'));
        $permintaan = Permintaan::query()->sole();

        $this->get(route('permintaan.langkah', [$permintaan, 4]))->assertRedirectToRoute('permintaan.langkah', [$permintaan, 1]);
        $this->put(route('permintaan.simpan', [$permintaan, 3]), $this->isianLangkah()[3])->assertNotFound();

        $this->post(route('permintaan.kirim', $permintaan))->assertRedirectToRoute('permintaan.langkah', [$permintaan, 1]);
        $this->assertSame(StatusPermintaan::Draf, $permintaan->refresh()->status);
    }

    public function test_langkah_asesmen_dilewati_bila_tidak_ada_instrumen_dipakai(): void
    {
        Instrumen::query()->update(['aktif' => false]);
        $this->masukSebagai($this->anggota)->post(route('permintaan.mulai'));
        $permintaan = Permintaan::query()->sole();

        foreach ([1, 2] as $langkah) {
            $this->put(route('permintaan.simpan', [$permintaan, $langkah]), $this->isianLangkah()[$langkah]);
        }

        $this->put(route('permintaan.simpan', [$permintaan, 3]), $this->isianLangkah()[3])
            ->assertRedirectToRoute('permintaan.langkah', [$permintaan, 5]);
        $this->put(route('permintaan.simpan', [$permintaan, 5]), $this->isianLangkah()[5]);
        $this->post(route('permintaan.kirim', $permintaan));

        $this->assertSame(StatusPermintaan::Diajukan, $permintaan->refresh()->status);
    }

    public function test_layanan_yang_ditutup_menolak_pengajuan_baru(): void
    {
        app(PengaturanRangkul::class)->simpan(['layanan.buka' => false, 'layanan.pesan_tutup' => 'Libur akhir tahun.']);

        $this->masukSebagai($this->anggota)
            ->post(route('permintaan.mulai'))
            ->assertSessionHasErrors(['layanan' => 'Libur akhir tahun.']);

        $this->assertDatabaseCount('permintaan', 0);
    }

    public function test_permintaan_yang_masih_berjalan_tidak_digandakan(): void
    {
        $permintaan = $this->masukSebagai($this->anggota)->ajukanPermintaan();

        $this->post(route('permintaan.mulai'))->assertRedirectToRoute('permintaan.tampil', $permintaan);

        $this->assertDatabaseCount('permintaan', 1);
    }

    public function test_anggota_lain_tidak_dapat_membuka_permintaan_maupun_drafnya(): void
    {
        $draf = Permintaan::factory()->draf()->for($this->anggota, 'anggota')->create();
        $terkirim = Permintaan::factory()->for($this->anggota, 'anggota')->create();
        $lain = $this->anggotaBiasa();

        $this->masukSebagai($lain);
        $this->get(route('permintaan.langkah', [$draf, 1]))->assertForbidden();
        $this->put(route('permintaan.simpan', [$draf, 1]), $this->isianLangkah()[1])->assertForbidden();
        $this->get(route('permintaan.tampil', $terkirim))->assertForbidden();
        $this->getJson(route('percakapan.daftar', $terkirim))->assertForbidden();
    }

    public function test_komite_tidak_melihat_draf(): void
    {
        $draf = Permintaan::factory()->draf()->for($this->anggota, 'anggota')->create();

        $this->masukSebagai($this->anggotaKomite())->get(route('kelola.permintaan.tampil', $draf))->assertForbidden();
        $this->get(route('kelola.permintaan.index', ['status' => 'semua']))->assertOk()->assertDontSee('Ratna Dewi');
    }

    public function test_draf_dapat_dihapus_dan_permintaan_dapat_dibatalkan_sebelum_ditangani(): void
    {
        $this->masukSebagai($this->anggota)->post(route('permintaan.mulai'));
        $draf = Permintaan::query()->sole();

        $this->post(route('permintaan.batalkan', $draf))->assertRedirectToRoute('permintaan.index');
        $this->assertModelMissing($draf);

        $permintaan = $this->ajukanPermintaan();
        $this->post(route('permintaan.batalkan', $permintaan))->assertRedirectToRoute('permintaan.tampil', $permintaan);
        $this->assertSame(StatusPermintaan::Dibatalkan, $permintaan->refresh()->status);

        $berjalan = $this->kasusBerlangsung($this->anggota);
        $this->post(route('permintaan.batalkan', $berjalan))->assertForbidden();
    }

    public function test_beranda_menuntun_anggota_ke_pengajuan_dan_permintaannya(): void
    {
        $this->masukSebagai($this->anggota)->get(route('beranda'))->assertSee('Ajukan permintaan bantuan');

        $permintaan = $this->ajukanPermintaan();

        $this->get(route('beranda'))->assertSee($permintaan->kode)->assertSee('Buka permintaan');
    }
}
