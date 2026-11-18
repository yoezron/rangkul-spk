<?php

namespace App\Http\Controllers\Kelola;

use App\Enums\Hak;
use App\Enums\StatusPermintaan;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CatatanSesi;
use App\Models\JawabanAsesmen;
use App\Models\Permintaan;
use App\Models\Pertemuan;
use App\Models\Pesan;
use App\Models\User;
use App\Services\PencatatAudit;
use Generator;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan rekap dalam bentuk CSV.
 *
 * Rekap permintaan layanan dan percakapan memuat isi lengkap (cerita, skor
 * asesmen, catatan sesi) sesuai keputusan pemilik proyek, dan hanya bagi
 * pemegang hak permintaan.rekap. Rekap lain memerlukan hak rekap.unduh.
 *
 * Setiap unduhan tercatat di audit log sebelum berkasnya dikirim.
 */
class RekapController extends Controller
{
    /** @var array<string, array{judul: string, keterangan: string, hak: Hak}> */
    public const JENIS = [
        'permintaan' => [
            'judul' => 'Permintaan layanan (lengkap)',
            'keterangan' => 'Satu baris per permintaan: identitas anggota, status, tanda darurat, topik, cerita, harapan, preferensi, skor asesmen, konselor, pertemuan, catatan sesi, rujukan, dan penutupan.',
            'hak' => Hak::PermintaanRekap,
        ],
        'percakapan' => [
            'judul' => 'Percakapan (lengkap)',
            'keterangan' => 'Seluruh pesan antara anggota dan konselor, per permintaan, berikut catatan otomatis.',
            'hak' => Hak::PermintaanRekap,
        ],
        'anggota' => [
            'judul' => 'Anggota terdaftar',
            'keterangan' => 'Semua anggota yang pernah masuk atau ditambahkan ke Rangkul, dengan data dari spk.or.id dan peran Rangkul-nya.',
            'hak' => Hak::RekapUnduh,
        ],
        'penugasan' => [
            'judul' => 'Penugasan peran',
            'keterangan' => 'Siapa memegang peran apa (peer konselor, administrator, dll.), siapa yang menugaskan, dan sejak kapan.',
            'hak' => Hak::RekapUnduh,
        ],
        'audit' => [
            'judul' => 'Audit log',
            'keterangan' => 'Seluruh catatan tindakan pengelola yang masih tersimpan.',
            'hak' => Hak::RekapUnduh,
        ],
    ];

    public function __construct(private readonly PencatatAudit $audit) {}

    public function index(Request $request): View
    {
        $boleh = array_filter(self::JENIS, fn (array $jenis): bool => $request->user()->punyaHak($jenis['hak']));
        abort_if($boleh === [], 403);

        return view('kelola.rekap.index', [
            'jenis' => $boleh,
            'jumlah' => [
                'permintaan' => Permintaan::query()->where('status', '!=', StatusPermintaan::Draf->value)->count(),
                'percakapan' => Pesan::query()->count(),
                'anggota' => User::query()->count(),
                'penugasan' => DB::table('peran_user')->count(),
                'audit' => AuditLog::query()->count(),
            ],
        ]);
    }

    public function unduh(Request $request, string $jenis): StreamedResponse
    {
        abort_unless(array_key_exists($jenis, self::JENIS), 404);
        abort_unless($request->user()->punyaHak(self::JENIS[$jenis]['hak']), 403);

        $pemisah = $request->query('pemisah') === 'titik_koma' ? ';' : ',';

        // Dicatat sebelum berkas dibuat, supaya unduhan yang terputus pun
        // tetap meninggalkan jejak.
        $this->audit->catat('rekap.diunduh', self::JENIS[$jenis]['judul'], ['jenis' => $jenis]);

        $namaBerkas = 'rangkul-rekap-'.$jenis.'-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($jenis, $pemisah): void {
            $keluaran = fopen('php://output', 'w');

            // BOM supaya Excel membaca huruf beraksen sebagai UTF-8.
            fwrite($keluaran, "\xEF\xBB\xBF");

            foreach ($this->baris($jenis) as $baris) {
                fputcsv($keluaran, array_map($this->amankanSel(...), $baris), $pemisah, '"', '');
            }

            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Generator<int, list<string|int|null>>
     */
    private function baris(string $jenis): Generator
    {
        $waktu = fn ($tanggal): string => $tanggal?->format('Y-m-d H:i:s') ?? '';

        if ($jenis === 'permintaan') {
            yield [
                'kode', 'status', 'darurat', 'alasan_darurat', 'diajukan_pada', 'id_akun_spk', 'nomor_anggota', 'nama', 'email', 'telp',
                'gender', 'kampus', 'wilayah', 'jawaban_keselamatan', 'topik', 'topik_lainnya', 'uraian', 'harapan', 'bentuk_layanan',
                'waktu_luang', 'minta_kampus_lain', 'preferensi_gender_konselor', 'asesmen', 'butir_kritis', 'konselor', 'ditugaskan_pada',
                'jumlah_pesan', 'pertemuan', 'catatan_sesi', 'dirujuk_ke', 'alasan_penutupan', 'catatan_tinjauan_penutupan',
                'alasan_penolakan', 'catatan_komite', 'selesai_pada',
            ];

            $kueri = Permintaan::query()
                ->where('status', '!=', StatusPermintaan::Draf->value)
                ->with(['anggota', 'konselor', 'jawabanAsesmen', 'pertemuan', 'catatanSesi'])
                ->withCount(['pesan as jumlah_pesan' => fn ($pesan) => $pesan->where('jenis', 'pesan')])
                ->orderBy('id');

            foreach ($kueri->lazy(100) as $permintaan) {
                $anggota = $permintaan->anggota;

                yield [
                    $permintaan->kode, $permintaan->status->label(), $permintaan->darurat ? 'ya' : 'tidak',
                    implode(' | ', $permintaan->alasan_darurat ?? []), $waktu($permintaan->diajukan_pada),
                    $anggota?->spk_id, $anggota?->nomor_anggota, $anggota?->nama, $anggota?->email, $anggota?->telp,
                    $anggota?->gender, $anggota?->kampus, $anggota?->wilayah,
                    collect($permintaan->keselamatan ?? [])->map(fn (string $nilai, string $kunci): string => $kunci.'='.$nilai)->implode('; '),
                    implode('; ', $permintaan->labelTopik()), $permintaan->topik_lainnya, $permintaan->uraian, $permintaan->harapan,
                    Permintaan::BENTUK_LAYANAN[$permintaan->bentuk_layanan] ?? $permintaan->bentuk_layanan, $permintaan->waktu_luang,
                    $permintaan->hindari_kampus_sama ? 'ya' : 'tidak', $permintaan->gender_konselor,
                    $permintaan->jawabanAsesmen->map(fn (JawabanAsesmen $jawaban): string => $jawaban->ringkasan())->implode(' | '),
                    $permintaan->jawabanAsesmen->flatMap(fn (JawabanAsesmen $jawaban): array => array_map(fn (string $butir): string => $jawaban->nama_instrumen.': '.$butir, $jawaban->isian['kritis'] ?? []))->implode(' | '),
                    $permintaan->konselor?->nama, $waktu($permintaan->ditugaskan_pada), $permintaan->jumlah_pesan,
                    $permintaan->pertemuan->map(fn (Pertemuan $pertemuan): string => $pertemuan->mulai_pada->format('Y-m-d H:i').' ('.$pertemuan->status->label().')')->implode(' | '),
                    $permintaan->catatanSesi->reverse()->map(fn (CatatanSesi $catatan): string => '['.$catatan->created_at->format('Y-m-d').'] Rangkuman: '.$catatan->rangkuman
                        .($catatan->analisis ? ' — Analisis: '.$catatan->analisis : '')
                        .($catatan->saran ? ' — Saran: '.$catatan->saran : ''))->implode(' | '),
                    $permintaan->catatanSesi->where('rujukan', true)->pluck('rujukan_ke')->implode(' | '),
                    $permintaan->alasan_penutupan, $permintaan->catatan_tinjauan_penutupan, $permintaan->alasan_penolakan,
                    $permintaan->catatan_komite, $waktu($permintaan->selesai_pada),
                ];
            }

            return;
        }

        if ($jenis === 'percakapan') {
            yield ['kode', 'waktu', 'pengirim', 'sebagai', 'isi', 'dibaca_pada'];

            $kueri = Pesan::query()->with(['permintaan:id,kode,user_id,konselor_id', 'pengirim:id,nama'])->orderBy('permintaan_id')->orderBy('id');

            foreach ($kueri->lazy(500) as $pesan) {
                yield [
                    $pesan->permintaan?->kode,
                    $waktu($pesan->created_at),
                    $pesan->jenis === 'sistem' ? 'SPK Rangkul' : $pesan->pengirim?->nama,
                    match (true) {
                        $pesan->jenis === 'sistem' => 'sistem',
                        $pesan->pengirim_id === $pesan->permintaan?->user_id => 'anggota',
                        default => 'konselor',
                    },
                    $pesan->isi,
                    $waktu($pesan->dibaca_pada),
                ];
            }

            return;
        }

        if ($jenis === 'anggota') {
            yield ['id_akun_spk', 'nomor_anggota', 'nama', 'email', 'telp', 'gender', 'kampus', 'wilayah', 'peran_spk', 'peran_rangkul', 'terdaftar_di_rangkul', 'terakhir_disinkronkan'];

            foreach (User::query()->with('daftarPeran')->orderBy('id')->lazy(200) as $pengguna) {
                yield [
                    $pengguna->spk_id, $pengguna->nomor_anggota, $pengguna->nama, $pengguna->email, $pengguna->telp,
                    $pengguna->gender, $pengguna->kampus, $pengguna->wilayah, $pengguna->peran,
                    $pengguna->daftarPeran->pluck('nama')->implode(', '),
                    $waktu($pengguna->created_at), $waktu($pengguna->disinkronkan_pada),
                ];
            }

            return;
        }

        if ($jenis === 'penugasan') {
            yield ['peran', 'nama', 'id_akun_spk', 'nomor_anggota', 'kampus', 'wilayah', 'ditugaskan_oleh', 'sejak'];

            $kueri = DB::table('peran_user')
                ->join('users', 'users.id', '=', 'peran_user.user_id')
                ->join('peran', 'peran.id', '=', 'peran_user.peran_id')
                ->leftJoin('users as penugas', 'penugas.id', '=', 'peran_user.ditugaskan_oleh')
                ->select('peran_user.id', 'peran.nama as peran', 'users.nama', 'users.spk_id', 'users.nomor_anggota', 'users.kampus', 'users.wilayah', 'penugas.nama as penugas', 'peran_user.created_at');

            foreach ($kueri->lazyById(200, 'peran_user.id', 'id') as $baris) {
                yield [$baris->peran, $baris->nama, $baris->spk_id, $baris->nomor_anggota, $baris->kampus, $baris->wilayah, $baris->penugas, $baris->created_at];
            }

            return;
        }

        yield ['waktu', 'aktor', 'aksi', 'sasaran', 'rincian', 'alamat_ip'];

        foreach (AuditLog::query()->orderBy('id')->lazy(500) as $catatan) {
            yield [$waktu($catatan->dibuat_pada), $catatan->aktor, $catatan->labelAksi(), $catatan->sasaran, $catatan->ringkasanRincian(), $catatan->alamat_ip];
        }
    }

    /**
     * Cegah injeksi rumus spreadsheet: sel yang diawali =, +, -, @, tab, atau
     * carriage return diberi awalan petik supaya dibaca sebagai teks.
     */
    private function amankanSel(string|int|null $sel): string
    {
        $sel = (string) $sel;

        return $sel !== '' && str_contains("=+-@\t\r", $sel[0]) ? "'".$sel : $sel;
    }
}
