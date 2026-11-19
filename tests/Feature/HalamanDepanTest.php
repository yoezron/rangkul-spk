<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PengaturanRangkul;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MemalsukanSpk;
use Tests\TestCase;

class HalamanDepanTest extends TestCase
{
    use MemalsukanSpk, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
    }

    /**
     * @return array<string, mixed>
     */
    private function formulir(array $timpa = []): array
    {
        return array_replace([
            'halaman_judul' => 'SPK Rangkul',
            'halaman_label' => 'Ruang dengar',
            'halaman_tajuk' => 'Cerita Anda didengar',
            'halaman_subjudul' => 'Pendampingan sebaya bagi pekerja kampus.',
            'halaman_teks_tombol' => 'Mulai bercerita',
            'halaman_judul_tentang' => 'Mengenal Rangkul',
            'halaman_isi' => "Paragraf pertama\nParagraf kedua",
            'halaman_tampil_keunggulan' => '1',
            'halaman_keunggulan' => [['judul' => 'Aman', 'teks' => 'Tersimpan terenkripsi.'], ['judul' => '', 'teks' => '']],
            'halaman_tampil_langkah' => '1',
            'halaman_langkah' => [['judul' => 'Masuk', 'teks' => 'Pakai akun SPK.']],
            'halaman_tampil_faq' => '1',
            'halaman_faq' => [['pertanyaan' => 'Apakah berbayar?', 'jawaban' => 'Tidak.'], ['pertanyaan' => '', 'jawaban' => '']],
            'halaman_indeks' => '0',
        ], $timpa);
    }

    public function test_tamu_melihat_halaman_depan_bawaan(): void
    {
        $this->get(route('depan'))
            ->assertOk()
            ->assertSee('Ruang aman untuk bercerita, didampingi sesama pekerja kampus.')
            ->assertSee('Cara kerjanya')
            ->assertSee('Komite menugaskan konselor')
            ->assertSee('Siapa yang dapat membaca cerita saya?')
            ->assertSee('Dirujuk bila perlu')
            ->assertSee('href="https://spk.test/auth?lanjut=rangkul"', false)
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
            ->assertSee('112');
    }

    public function test_anggota_yang_sudah_masuk_diarahkan_ke_berandanya(): void
    {
        $anggota = User::factory()->create();

        $this->actingAs($anggota)->get(route('depan'))
            ->assertOk()
            ->assertSee('Buka beranda saya')
            ->assertSee('href="'.route('beranda').'"', false)
            ->assertDontSee('https://spk.test/auth?lanjut=rangkul', false);

        $this->assertSame(url('/beranda'), route('beranda'));
    }

    public function test_pengelola_mengatur_isi_halaman_depan(): void
    {
        $this->masukSebagai(User::factory()->superadmin()->create())
            ->put(route('kelola.pengaturan.halaman'), $this->formulir())
            ->assertSessionHasNoErrors();

        $this->assertSame([['judul' => 'Aman', 'teks' => 'Tersimpan terenkripsi.']], app(PengaturanRangkul::class)->get('halaman.keunggulan'));
        $this->assertCount(1, app(PengaturanRangkul::class)->get('halaman.faq'));

        $this->post(route('keluar'));

        $this->get(route('depan'))
            ->assertOk()
            ->assertSee('Ruang dengar')
            ->assertSee('Cerita Anda didengar')
            ->assertSee('Mulai bercerita')
            ->assertSee('Mengenal Rangkul')
            ->assertSee('Paragraf pertama<br />', false)
            ->assertSee('Apakah berbayar?')
            ->assertDontSee('Siapa yang dapat membaca cerita saya?')
            ->assertDontSee('Ruang aman untuk bercerita');
    }

    public function test_sakelar_menyembunyikan_bagian(): void
    {
        $this->masukSebagai(User::factory()->superadmin()->create())
            ->put(route('kelola.pengaturan.halaman'), $this->formulir(['halaman_tampil_langkah' => '0', 'halaman_tampil_faq' => '0']));

        $this->post(route('keluar'));

        $this->get(route('depan'))
            ->assertOk()
            ->assertDontSee('Cara kerjanya')
            ->assertDontSee('Apakah berbayar?')
            ->assertDontSee('href="#tanya-jawab"', false)
            ->assertSee('Aman');
    }

    public function test_butir_setengah_terisi_ditolak(): void
    {
        $this->masukSebagai(User::factory()->superadmin()->create())
            ->put(route('kelola.pengaturan.halaman'), $this->formulir(['halaman_faq' => [['pertanyaan' => 'Tanpa jawaban?', 'jawaban' => '']]]))
            ->assertSessionHasErrors('halaman_faq');

        $this->assertCount(5, app(PengaturanRangkul::class)->get('halaman.faq'));
    }

    public function test_isi_halaman_depan_tidak_dirender_sebagai_html(): void
    {
        $this->masukSebagai(User::factory()->superadmin()->create())
            ->put(route('kelola.pengaturan.halaman'), $this->formulir([
                'halaman_tajuk' => '<script>alert(1)</script>Judul',
                'halaman_faq' => [['pertanyaan' => '<b>Tanya</b>', 'jawaban' => '<img src=x onerror=alert(1)>']],
            ]));

        $this->post(route('keluar'));

        $this->get(route('depan'))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertSee('<b>Tanya</b>');
    }

    public function test_mesin_pencari_hanya_diizinkan_bila_diaktifkan(): void
    {
        app(PengaturanRangkul::class)->simpan(['halaman.indeks' => true]);

        $this->get(route('depan'))->assertSee('<meta name="robots" content="index, follow">', false);
        $this->get(route('masuk'))->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_layanan_yang_ditutup_diumumkan_di_halaman_depan(): void
    {
        app(PengaturanRangkul::class)->simpan(['layanan.buka' => false, 'layanan.pesan_tutup' => 'Rangkul libur sampai Januari.']);

        $this->get(route('depan'))->assertOk()->assertSee('Rangkul libur sampai Januari.');
    }
}
