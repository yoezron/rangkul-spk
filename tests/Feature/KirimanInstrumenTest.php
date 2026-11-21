<?php

namespace Tests\Feature;

use App\Models\Instrumen;
use App\Models\KirimanInstrumen;
use App\Models\Permintaan;
use App\Models\User;
use App\Notifications\NotifikasiRangkul;
use App\Services\Asesmen\PenilaiAsesmen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\MemalsukanSpk;
use Tests\Concerns\MembuatKasus;
use Tests\TestCase;

/**
 * Konselor mengirim instrumen (termasuk yang tidak aktif di asesmen dasar)
 * di tengah percakapan; anggota mengisi; hasil hanya untuk konselor & komite.
 */
class KirimanInstrumenTest extends TestCase
{
    use MemalsukanSpk, MembuatKasus, RefreshDatabase;

    private User $komite;

    private User $konselor;

    private User $anggota;

    private Permintaan $permintaan;

    private Instrumen $srq;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        Notification::fake();
        $this->komite = $this->anggotaKomite();
        $this->konselor = $this->peerKonselor();
        $this->anggota = $this->anggotaBiasa();
        $this->permintaan = $this->kasusBerlangsung($this->anggota, $this->konselor);
        $this->srq = Instrumen::query()->where('kunci', 'srq20')->sole();
    }

    private function kirimSrq(): KirimanInstrumen
    {
        $this->masukSebagai($this->konselor)
            ->post(route('instrumen-kasus.kirim', $this->permintaan), ['instrumen_id' => $this->srq->id, 'pengantar' => 'Supaya saya lebih memahami keadaan Anda.'])
            ->assertRedirect(route('konseling.tampil', $this->permintaan).'#instrumen');

        return $this->permintaan->kirimanInstrumen()->sole();
    }

    /**
     * @return array<string, int>
     */
    private function jawaban(KirimanInstrumen $kiriman, bool $kritis): array
    {
        $jawaban = [];

        foreach ($kiriman->salinan['butir'] as $butir) {
            $pilihan = PenilaiAsesmen::pilihanButir($kiriman->salinan, $butir);
            $jawaban[$butir['kunci']] = ($kritis && ($butir['kritis_min'] ?? null) !== null) ? end($pilihan)['nilai'] : $pilihan[0]['nilai'];
        }

        return $jawaban;
    }

    public function test_konselor_mengirim_instrumen_nonaktif_dan_anggota_diberi_tahu(): void
    {
        $this->assertFalse($this->srq->aktif);

        $this->masukSebagai($this->konselor)->get(route('konseling.tampil', $this->permintaan))->assertSee($this->srq->nama);
        $kiriman = $this->kirimSrq();

        $this->assertSame($this->srq->nama, $kiriman->nama_instrumen);
        $this->assertSame($this->srq->butir, $kiriman->salinan['butir']);
        $this->assertNull($kiriman->diisi_pada);
        $this->assertStringContainsString('Konselor meminta Anda mengisi kuesioner', $this->permintaan->pesan()->where('jenis', 'sistem')->latest('id')->first()->isi);
        Notification::assertSentTo($this->anggota, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'instrumen.dikirim');

        $this->masukSebagai($this->anggota)->get(route('permintaan.tampil', $this->permintaan))
            ->assertSee('Isi sekarang')
            ->assertSee('Supaya saya lebih memahami keadaan Anda.');

        $this->masukSebagai($this->konselor)
            ->post(route('instrumen-kasus.kirim', $this->permintaan), ['instrumen_id' => $this->srq->id])
            ->assertSessionHasErrors('instrumen_id');
        $this->assertSame(1, $this->permintaan->kirimanInstrumen()->count());
    }

    public function test_hanya_konselor_pendamping_yang_boleh_mengirim(): void
    {
        foreach ([$this->anggota, $this->komite, $this->peerKonselor()] as $pengguna) {
            $this->masukSebagai($pengguna)->post(route('instrumen-kasus.kirim', $this->permintaan), ['instrumen_id' => $this->srq->id])->assertForbidden();
        }

        $this->assertSame(0, KirimanInstrumen::query()->count());
    }

    public function test_anggota_mengisi_dan_hasilnya_hanya_terlihat_konselor_dan_komite(): void
    {
        $kiriman = $this->kirimSrq();

        $this->masukSebagai($this->anggota)->get(route('instrumen-kasus.isi', [$this->permintaan, $kiriman]))
            ->assertOk()
            ->assertSee($this->srq->butir[0]['teks']);

        $this->post(route('instrumen-kasus.simpan', [$this->permintaan, $kiriman]), ['jawaban' => []])->assertSessionHasErrors();

        $this->post(route('instrumen-kasus.simpan', [$this->permintaan, $kiriman]), ['jawaban' => $this->jawaban($kiriman, kritis: false)])
            ->assertRedirect(route('permintaan.tampil', $this->permintaan).'#percakapan');

        $kiriman->refresh();
        $this->assertNotNull($kiriman->diisi_pada);
        $this->assertFalse($kiriman->kritis);
        $this->assertSame('Di bawah ambang', $kiriman->isian['skor'][0]['label']);
        $this->assertFalse($this->permintaan->refresh()->darurat);
        Notification::assertSentTo($this->konselor, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'instrumen.diisi' && ! $notifikasi->mendesak);
        Notification::assertNotSentTo($this->komite, NotifikasiRangkul::class);

        $this->get(route('permintaan.tampil', $this->permintaan))->assertSee('sudah Anda isi')->assertDontSee('Di bawah ambang');
        $this->get(route('instrumen-kasus.isi', [$this->permintaan, $kiriman]))->assertRedirect(route('permintaan.tampil', $this->permintaan));
        $this->post(route('instrumen-kasus.simpan', [$this->permintaan, $kiriman]), ['jawaban' => $this->jawaban($kiriman, kritis: false)])->assertStatus(409);

        $this->masukSebagai($this->konselor)->get(route('konseling.tampil', $this->permintaan))->assertSee('Di bawah ambang');
        $this->masukSebagai($this->komite)->get(route('kelola.permintaan.tampil', $this->permintaan))->assertSee('Di bawah ambang');
    }

    public function test_butir_kritis_menandai_darurat_dan_memberi_tahu_komite(): void
    {
        $kiriman = $this->kirimSrq();

        $this->masukSebagai($this->anggota)->post(route('instrumen-kasus.simpan', [$this->permintaan, $kiriman]), ['jawaban' => $this->jawaban($kiriman, kritis: true)])->assertRedirect();

        $this->assertTrue($kiriman->refresh()->kritis);
        $this->permintaan->refresh();
        $this->assertTrue($this->permintaan->darurat);
        $this->assertStringContainsString('Kuesioner '.$this->srq->nama, implode(' ', $this->permintaan->alasan_darurat));
        Notification::assertSentTo($this->konselor, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'instrumen.diisi' && $notifikasi->mendesak);
        Notification::assertSentTo($this->komite, NotifikasiRangkul::class, fn (NotifikasiRangkul $notifikasi): bool => $notifikasi->jenis === 'instrumen.kritis' && $notifikasi->mendesak);
    }

    public function test_orang_lain_tidak_dapat_mengisi_dan_konselor_dapat_membatalkan(): void
    {
        $kiriman = $this->kirimSrq();

        $this->masukSebagai($this->konselor)->get(route('instrumen-kasus.isi', [$this->permintaan, $kiriman]))->assertForbidden();
        $this->masukSebagai($this->anggotaBiasa())->post(route('instrumen-kasus.simpan', [$this->permintaan, $kiriman]), ['jawaban' => $this->jawaban($kiriman, kritis: false)])->assertForbidden();

        $kasusLain = $this->kasusBerlangsung();
        $this->masukSebagai($this->anggota)->get(route('instrumen-kasus.isi', [$kasusLain, $kiriman]))->assertNotFound();

        $this->masukSebagai($this->konselor)->post(route('instrumen-kasus.batal', [$this->permintaan, $kiriman]))->assertRedirect();
        $this->assertModelMissing($kiriman);
    }

    public function test_instrumen_yang_pernah_dikirim_tidak_dapat_dihapus(): void
    {
        $this->kirimSrq();
        $pengelolaAsesmen = User::factory()->superadmin()->create();

        $this->masukSebagai($pengelolaAsesmen)->delete(route('kelola.instrumen.destroy', $this->srq))->assertSessionHasErrors('instrumen');
        $this->assertModelExists($this->srq);
    }
}
