<?php

namespace Tests\Feature;

use App\Enums\StatusPermintaan;
use App\Enums\StatusPertemuan;
use App\Models\Permintaan;
use App\Models\Pertemuan;
use App\Models\Pesan;
use App\Models\User;
use App\Notifications\NotifikasiRangkul;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MemalsukanSpk;
use Tests\Concerns\MembuatKasus;
use Tests\TestCase;

class PendampinganTest extends TestCase
{
    use MemalsukanSpk, MembuatKasus, RefreshDatabase;

    private User $komite;

    private User $konselor;

    private User $anggota;

    private Permintaan $permintaan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        Notification::fake();
        $this->komite = $this->anggotaKomite();
        $this->konselor = $this->peerKonselor(['nama' => 'Konselor Sari']);
        $this->anggota = $this->anggotaBiasa(['nama' => 'Anggota Rina']);
        $this->permintaan = $this->kasusBerlangsung($this->anggota, $this->konselor);
    }

    public function test_anggota_dan_konselor_bercakap_dan_pesan_ditandai_terbaca(): void
    {
        $this->masukSebagai($this->anggota)->postJson(route('percakapan.kirim', $this->permintaan), ['isi' => 'Halo, saya butuh teman bicara.'])->assertOk();

        $this->masukSebagai($this->konselor)->getJson(route('percakapan.daftar', $this->permintaan))
            ->assertOk()
            ->assertJsonPath('pesan.0.isi', 'Halo, saya butuh teman bicara.')
            ->assertJsonPath('pesan.0.pengirim', 'Anggota Rina (anggota)')
            ->assertJsonPath('pesan.0.milikSaya', false);

        $this->assertNotNull(Pesan::query()->sole()->dibaca_pada);

        $this->postJson(route('percakapan.kirim', $this->permintaan), ['isi' => 'Halo Rina, saya Sari.'])->assertOk();
        $this->getJson(route('percakapan.daftar', [$this->permintaan, 'setelah' => Pesan::query()->min('id')]))
            ->assertJsonCount(1, 'pesan')
            ->assertJsonPath('pesan.0.milikSaya', true);
    }

    public function test_komite_memantau_tanpa_mengirim_dan_tanpa_menandai_terbaca(): void
    {
        $this->masukSebagai($this->anggota)->postJson(route('percakapan.kirim', $this->permintaan), ['isi' => 'Pesan untuk konselor.']);

        $this->masukSebagai($this->komite)->getJson(route('percakapan.daftar', $this->permintaan))->assertOk()->assertJsonCount(1, 'pesan');
        $this->postJson(route('percakapan.kirim', $this->permintaan), ['isi' => 'Komite menyela.'])->assertForbidden();

        $this->assertNull(Pesan::query()->sole()->dibaca_pada);
    }

    public function test_pesan_dikembalikan_apa_adanya_untuk_dipasang_sebagai_teks(): void
    {
        $this->masukSebagai($this->anggota)->postJson(route('percakapan.kirim', $this->permintaan), ['isi' => '<img src=x onerror=alert(1)>']);

        $this->getJson(route('percakapan.daftar', $this->permintaan))->assertJsonPath('pesan.0.isi', '<img src=x onerror=alert(1)>');
        $this->get(route('permintaan.tampil', $this->permintaan))->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_percakapan_belum_dibuka_sebelum_konselor_ditugaskan(): void
    {
        $baru = Permintaan::factory()->for($this->anggota, 'anggota')->create(['status' => StatusPermintaan::Ditinjau]);

        $this->masukSebagai($this->anggota)->postJson(route('percakapan.kirim', $baru), ['isi' => 'Halo?'])->assertForbidden();
    }

    public function test_pertemuan_daring_pertama_butuh_persetujuan_komite_lalu_berikutnya_tidak(): void
    {
        $waktu = now()->addDays(2)->setTime(19, 0);

        $this->masukSebagai($this->konselor)->post(route('pertemuan.usul', $this->permintaan), [
            'mulai_pada' => $waktu->format('Y-m-d\TH:i'),
            'durasi_menit' => 60,
            'tautan' => 'https://meet.google.com/abc-defg-hij',
        ])->assertSessionHasNoErrors();

        $pertemuan = Pertemuan::query()->sole();
        $this->assertSame(StatusPertemuan::Diusulkan, $pertemuan->status);
        Notification::assertSentTo($this->anggota, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'pertemuan.diusulkan');

        $this->masukSebagai($this->anggota)->get(route('permintaan.tampil', $this->permintaan))->assertDontSee('meet.google.com/abc-defg-hij');
        $this->post(route('pertemuan.tanggapi', [$this->permintaan, $pertemuan]), ['keputusan' => 'setuju']);

        $this->assertSame(StatusPertemuan::MenungguKomite, $pertemuan->refresh()->status);
        Notification::assertSentTo($this->komite, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'pertemuan.perlu_komite');
        $this->get(route('permintaan.tampil', $this->permintaan))->assertDontSee('meet.google.com/abc-defg-hij');

        $this->masukSebagai($this->komite)->post(route('pertemuan.putuskan', [$this->permintaan, $pertemuan]), ['keputusan' => 'setuju']);

        $this->assertSame(StatusPertemuan::Terjadwal, $pertemuan->refresh()->status);
        $this->assertNotNull($this->permintaan->refresh()->daring_disetujui_pada);
        Notification::assertSentTo($this->anggota, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'pertemuan.terjadwal');
        $this->masukSebagai($this->anggota)->get(route('permintaan.tampil', $this->permintaan))->assertSee('https://meet.google.com/abc-defg-hij');

        $this->masukSebagai($this->konselor)->post(route('pertemuan.usul', $this->permintaan), [
            'mulai_pada' => $waktu->copy()->addWeek()->format('Y-m-d\TH:i'),
            'durasi_menit' => 45,
        ]);
        $kedua = Pertemuan::query()->latest('id')->first();
        $this->masukSebagai($this->anggota)->post(route('pertemuan.tanggapi', [$this->permintaan, $kedua]), ['keputusan' => 'setuju']);

        $this->assertSame(StatusPertemuan::Terjadwal, $kedua->refresh()->status);
    }

    public function test_tautan_pertemuan_harus_https_dan_hanya_diubah_konselor_pendamping(): void
    {
        $this->masukSebagai($this->konselor)->post(route('pertemuan.usul', $this->permintaan), [
            'mulai_pada' => now()->addDay()->format('Y-m-d\TH:i'),
            'durasi_menit' => 60,
            'tautan' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('tautan');

        $pertemuan = Pertemuan::query()->create([
            'permintaan_id' => $this->permintaan->id, 'mulai_pada' => now()->addDay(), 'durasi_menit' => 60, 'status' => StatusPertemuan::Terjadwal,
        ]);

        $this->masukSebagai($this->peerKonselor())->put(route('pertemuan.tautan', [$this->permintaan, $pertemuan]), ['tautan' => 'https://zoom.us/j/1'])->assertForbidden();
        $this->masukSebagai($this->anggota)->put(route('pertemuan.tautan', [$this->permintaan, $pertemuan]), ['tautan' => 'https://zoom.us/j/1'])->assertForbidden();
        $this->masukSebagai($this->konselor)->put(route('pertemuan.tautan', [$this->permintaan, $pertemuan]), ['tautan' => 'https://zoom.us/j/123'])->assertSessionHasNoErrors();

        $this->assertSame('https://zoom.us/j/123', $pertemuan->refresh()->tautan);
    }

    public function test_pertemuan_kasus_lain_tidak_dapat_disentuh_lewat_kasus_ini(): void
    {
        $lain = $this->kasusBerlangsung();
        $pertemuanLain = Pertemuan::query()->create([
            'permintaan_id' => $lain->id, 'mulai_pada' => now()->addDay(), 'durasi_menit' => 60, 'status' => StatusPertemuan::Diusulkan,
        ]);

        $this->masukSebagai($this->anggota)->post(route('pertemuan.tanggapi', [$this->permintaan, $pertemuanLain]), ['keputusan' => 'setuju'])->assertNotFound();
        $this->assertSame(StatusPertemuan::Diusulkan, $pertemuanLain->refresh()->status);
    }

    public function test_penutupan_diusulkan_konselor_disetujui_anggota_lalu_komite(): void
    {
        $this->masukSebagai($this->konselor)->post(route('penutupan.usul', $this->permintaan), ['alasan' => 'Tujuan pendampingan sudah tercapai.']);
        Notification::assertSentTo($this->anggota, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'penutupan.diusulkan');

        $this->masukSebagai($this->komite)->post(route('penutupan.putuskan', $this->permintaan), ['keputusan' => 'setuju'])->assertStatus(409);

        $this->masukSebagai($this->anggota)->get(route('permintaan.tampil', $this->permintaan))->assertSee('Tujuan pendampingan sudah tercapai.');
        $this->post(route('penutupan.tanggapi', $this->permintaan), ['keputusan' => 'setuju']);

        $this->assertSame(StatusPermintaan::MenungguPenutupan, $this->permintaan->refresh()->status);
        Notification::assertSentTo($this->komite, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'penutupan.perlu_tinjauan');

        $this->masukSebagai($this->komite)->post(route('penutupan.putuskan', $this->permintaan), ['keputusan' => 'setuju']);

        $this->permintaan->refresh();
        $this->assertSame(StatusPermintaan::Selesai, $this->permintaan->status);
        $this->assertNotNull($this->permintaan->selesai_pada);
        Notification::assertSentTo([$this->anggota, $this->konselor], NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'penutupan.diputuskan');
        $this->assertDatabaseHas('audit_log', ['aksi' => 'penutupan.diputuskan', 'sasaran' => $this->permintaan->kode]);

        $this->masukSebagai($this->anggota)->postJson(route('percakapan.kirim', $this->permintaan), ['isi' => 'Masih bisa?'])->assertForbidden();
    }

    public function test_anggota_dapat_menolak_penutupan_dan_komite_dapat_mengembalikannya(): void
    {
        $this->masukSebagai($this->konselor)->post(route('penutupan.usul', $this->permintaan), ['alasan' => 'Sudah membaik.']);
        $this->masukSebagai($this->anggota)->post(route('penutupan.tanggapi', $this->permintaan), ['keputusan' => 'tolak']);

        $this->assertNull($this->permintaan->refresh()->penutupan_diusulkan_pada);
        Notification::assertSentTo($this->konselor, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'penutupan.ditolak_anggota');

        $this->masukSebagai($this->konselor)->post(route('penutupan.usul', $this->permintaan), ['alasan' => 'Sudah membaik.']);
        $this->masukSebagai($this->anggota)->post(route('penutupan.tanggapi', $this->permintaan), ['keputusan' => 'setuju']);

        $this->masukSebagai($this->komite)->post(route('penutupan.putuskan', $this->permintaan), ['keputusan' => 'kembalikan'])->assertSessionHasErrors('catatan');
        $this->post(route('penutupan.putuskan', $this->permintaan), ['keputusan' => 'kembalikan', 'catatan' => 'Skor PHQ-9 masih tinggi; lanjutkan dua sesi lagi.']);

        $this->assertSame(StatusPermintaan::Berlangsung, $this->permintaan->refresh()->status);
        $this->masukSebagai($this->konselor)->get(route('konseling.tampil', $this->permintaan))->assertSee('lanjutkan dua sesi lagi');
    }

    public function test_catatan_sesi_hanya_ditulis_konselor_dan_tidak_terlihat_anggota(): void
    {
        $catatan = [
            'rangkuman' => 'Anggota menceritakan tekanan target publikasi.',
            'analisis' => 'Gejala kecemasan sedang, dukungan sosial baik.',
            'saran' => 'Latihan pernapasan, bicarakan beban dengan kaprodi.',
            'rujukan' => '1',
            'rujukan_ke' => 'Psikolog klinis Puskesmas setempat',
        ];

        $this->masukSebagai($this->anggota)->post(route('catatan.simpan', $this->permintaan), $catatan)->assertForbidden();
        $this->masukSebagai($this->komite)->post(route('catatan.simpan', $this->permintaan), $catatan)->assertForbidden();

        $this->masukSebagai($this->konselor)->post(route('catatan.simpan', $this->permintaan), [...$catatan, 'rujukan_ke' => ''])->assertSessionHasErrors('rujukan_ke');
        $this->post(route('catatan.simpan', $this->permintaan), $catatan)->assertSessionHasNoErrors();

        $this->assertSame($this->konselor->id, $this->permintaan->catatanSesi()->sole()->penulis_id);

        $this->masukSebagai($this->anggota)->get(route('permintaan.tampil', $this->permintaan))
            ->assertDontSee('tekanan target publikasi')
            ->assertDontSee('Psikolog klinis Puskesmas');
        $this->masukSebagai($this->komite)->get(route('kelola.permintaan.tampil', $this->permintaan))
            ->assertSee('tekanan target publikasi')
            ->assertSee('Psikolog klinis Puskesmas');
    }

    public function test_konselor_hanya_melihat_kasus_yang_ditugaskan_kepadanya(): void
    {
        $kasusLain = $this->kasusBerlangsung();

        $this->masukSebagai($this->konselor);
        $this->get(route('konseling.index'))->assertOk()->assertSee($this->permintaan->kode)->assertDontSee($kasusLain->kode);
        $this->get(route('konseling.tampil', $kasusLain))->assertForbidden();
        $this->getJson(route('percakapan.daftar', $kasusLain))->assertForbidden();
        $this->get(route('kelola.permintaan.tampil', $this->permintaan))->assertForbidden();
    }

    public function test_skor_asesmen_tidak_ditampilkan_kepada_anggota(): void
    {
        $this->permintaan->jawabanAsesmen()->create([
            'nama_instrumen' => 'PHQ-9 — gejala depresi',
            'isian' => ['instrumen' => ['butir' => [], 'pilihan' => []], 'jawaban' => [], 'skor' => [['subskala' => '', 'nama' => 'Skor total', 'nilai' => 17, 'label' => 'Cukup berat', 'saran' => 'Disarankan rujukan']], 'kritis' => []],
            'kritis' => false,
        ]);

        $this->masukSebagai($this->anggota)->get(route('permintaan.tampil', $this->permintaan))->assertDontSee('Cukup berat');
        $this->masukSebagai($this->konselor)->get(route('konseling.tampil', $this->permintaan))->assertSee('Cukup berat');
    }
}
