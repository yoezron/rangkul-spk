<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Peran;
use App\Models\User;
use App\Services\PengaturanRangkul;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MemalsukanSpk;
use Tests\TestCase;

class KelolaRekapAuditTest extends TestCase
{
    use MemalsukanSpk, RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->siapkanSpk();
        $this->superadmin = User::factory()->superadmin()->create(['nama' => 'Admin Pusat', 'spk_id' => 1]);
    }

    /**
     * @return list<list<string>>
     */
    private function bacaCsv(string $isi, string $pemisah = ','): array
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $isi);

        $baris = preg_split('/\r?\n/', trim(substr($isi, 3)));

        return array_map(fn (string $satu): array => str_getcsv($satu, $pemisah, '"', ''), $baris);
    }

    public function test_rekap_anggota_berisi_data_lengkap_dan_tercatat(): void
    {
        $anggota = User::factory()->create(['spk_id' => 18, 'nama' => 'Sri Wahyuni', 'telp' => '081200000018', 'kampus' => 'Universitas Uji']);
        $anggota->daftarPeran()->attach(Peran::query()->where('kunci', 'peer_konselor')->sole());

        $jawaban = $this->masukSebagai($this->superadmin)->get(route('kelola.rekap.unduh', 'anggota'));

        $jawaban->assertOk()->assertDownload();
        $this->assertStringContainsString('text/csv', (string) $jawaban->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $jawaban->headers->get('Cache-Control'));

        $baris = $this->bacaCsv($jawaban->streamedContent());
        $this->assertSame('id_akun_spk', $baris[0][0]);
        $this->assertCount(3, $baris);

        $sri = collect($baris)->first(fn (array $satu): bool => $satu[0] === '18');
        $this->assertSame('Sri Wahyuni', $sri[2]);
        $this->assertSame('081200000018', $sri[4]);
        $this->assertSame('Universitas Uji', $sri[6]);
        $this->assertSame('Peer Konselor', $sri[9]);

        $catatan = AuditLog::query()->sole();
        $this->assertSame('rekap.diunduh', $catatan->aksi);
        $this->assertSame(['jenis' => 'anggota'], $catatan->rincian);
    }

    public function test_sel_yang_menyerupai_rumus_diberi_awalan_petik(): void
    {
        User::factory()->create(['nama' => '=HYPERLINK("http://jahat.test","klik")', 'telp' => '+6281200000000']);

        $isi = $this->masukSebagai($this->superadmin)->get(route('kelola.rekap.unduh', 'anggota'))->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $isi);
        $this->assertStringContainsString("'+6281200000000", $isi);
        $this->assertStringNotContainsString(',=HYPERLINK', $isi);
    }

    public function test_pemisah_titik_koma_untuk_excel_berbahasa_indonesia(): void
    {
        $isi = $this->masukSebagai($this->superadmin)
            ->get(route('kelola.rekap.unduh', ['jenis' => 'anggota', 'pemisah' => 'titik_koma']))
            ->streamedContent();

        $baris = $this->bacaCsv($isi, ';');
        $this->assertSame(['id_akun_spk', 'nomor_anggota', 'nama'], array_slice($baris[0], 0, 3));
    }

    public function test_rekap_penugasan_menyebut_peran_dan_penugasnya(): void
    {
        $anggota = User::factory()->create(['nama' => 'Rina Kurnia']);
        $anggota->daftarPeran()->attach(Peran::query()->where('kunci', 'peer_konselor')->sole(), ['ditugaskan_oleh' => $this->superadmin->id]);

        $baris = $this->bacaCsv($this->masukSebagai($this->superadmin)->get(route('kelola.rekap.unduh', 'penugasan'))->streamedContent());

        $this->assertSame(['peran', 'nama'], array_slice($baris[0], 0, 2));
        $this->assertSame('Peer Konselor', $baris[1][0]);
        $this->assertSame('Rina Kurnia', $baris[1][1]);
        $this->assertSame('Admin Pusat', $baris[1][6]);
    }

    public function test_rekap_audit_memuat_unduhannya_sendiri(): void
    {
        $baris = $this->bacaCsv($this->masukSebagai($this->superadmin)->get(route('kelola.rekap.unduh', 'audit'))->streamedContent());

        $this->assertSame('Rekap diunduh', $baris[1][2]);
        $this->assertSame('Admin Pusat (SPK #1)', $baris[1][1]);
    }

    public function test_jenis_rekap_yang_tidak_dikenal(): void
    {
        $this->masukSebagai($this->superadmin)->get(route('kelola.rekap.unduh', 'konseling'))->assertNotFound();

        $this->assertDatabaseCount('audit_log', 0);
    }

    public function test_halaman_audit_menampilkan_dan_menyaring_catatan(): void
    {
        $this->masukSebagai($this->superadmin);
        $this->get(route('kelola.rekap.unduh', 'anggota'));
        $this->post(route('kelola.peran.store'), ['nama' => 'Pengawas', 'hak' => ['audit.lihat']]);

        $this->get(route('kelola.audit.index'))
            ->assertOk()->assertSee('Rekap diunduh')->assertSee('Peran dibuat')->assertSee('Admin Pusat (SPK #1)');

        $this->get(route('kelola.audit.index', ['aksi' => 'peran.dibuat']))
            ->assertOk()->assertSee('Pengawas')->assertDontSee('Anggota terdaftar');
    }

    public function test_alamat_ip_tidak_dicatat_bila_dimatikan(): void
    {
        app(PengaturanRangkul::class)->simpan(['audit.catat_ip' => false]);

        $this->masukSebagai($this->superadmin)->get(route('kelola.rekap.unduh', 'anggota'));

        $this->assertNull(AuditLog::query()->value('alamat_ip'));
    }

    public function test_pemangkasan_membuang_catatan_yang_melewati_masa_simpan(): void
    {
        app(PengaturanRangkul::class)->simpan(['audit.retensi_hari' => 30]);
        $lama = AuditLog::query()->create(['aktor' => 'Sistem', 'aksi' => 'rekap.diunduh', 'dibuat_pada' => now()->subDays(31)]);
        $baru = AuditLog::query()->create(['aktor' => 'Sistem', 'aksi' => 'rekap.diunduh', 'dibuat_pada' => now()->subDays(29)]);

        $this->artisan('rangkul:pangkas-audit')->assertSuccessful();

        $this->assertModelMissing($lama);
        $this->assertModelExists($baru);
        $this->assertDatabaseHas('audit_log', ['aksi' => 'audit.dipangkas', 'aktor' => 'Sistem']);
    }

    public function test_masa_simpan_nol_menyimpan_selamanya(): void
    {
        app(PengaturanRangkul::class)->simpan(['audit.retensi_hari' => 0]);
        $lama = AuditLog::query()->create(['aktor' => 'Sistem', 'aksi' => 'rekap.diunduh', 'dibuat_pada' => now()->subYears(5)]);

        $this->artisan('rangkul:pangkas-audit')->assertSuccessful();

        $this->assertModelExists($lama);
    }
}
