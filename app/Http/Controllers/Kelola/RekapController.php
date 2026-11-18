<?php

namespace App\Http\Controllers\Kelola;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
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
 * Rekap record konseling belum ada karena fitur konselingnya belum dibangun;
 * jenis rekap baru cukup ditambahkan di JENIS dan baris().
 *
 * Setiap unduhan tercatat di audit log sebelum berkasnya dikirim.
 */
class RekapController extends Controller
{
    /** @var array<string, array{judul: string, keterangan: string}> */
    public const JENIS = [
        'anggota' => [
            'judul' => 'Anggota terdaftar',
            'keterangan' => 'Semua anggota yang pernah masuk atau ditambahkan ke Rangkul, dengan data dari spk.or.id dan peran Rangkul-nya.',
        ],
        'penugasan' => [
            'judul' => 'Penugasan peran',
            'keterangan' => 'Siapa memegang peran apa (peer konselor, administrator, dll.), siapa yang menugaskan, dan sejak kapan.',
        ],
        'audit' => [
            'judul' => 'Audit log',
            'keterangan' => 'Seluruh catatan tindakan pengelola yang masih tersimpan.',
        ],
    ];

    public function __construct(private readonly PencatatAudit $audit) {}

    public function index(): View
    {
        return view('kelola.rekap.index', [
            'jenis' => self::JENIS,
            'jumlah' => [
                'anggota' => User::query()->count(),
                'penugasan' => DB::table('peran_user')->count(),
                'audit' => AuditLog::query()->count(),
            ],
        ]);
    }

    public function unduh(Request $request, string $jenis): StreamedResponse
    {
        abort_unless(array_key_exists($jenis, self::JENIS), 404);

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
