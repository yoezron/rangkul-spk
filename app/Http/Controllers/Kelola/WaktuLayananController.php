<?php

namespace App\Http\Controllers\Kelola;

use App\Http\Controllers\Controller;
use App\Services\PencatatAudit;
use App\Services\PenghitungWaktuLayanan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Statistik waktu layanan konselor per bulan (menit), untuk dilaporkan
 * komite/superadmin kepada bendahara sebagai dasar pembayaran.
 *
 * Rincian per kasus hanya memuat kode kasus, tanpa identitas anggota.
 * Setiap unduhan tercatat di audit log.
 */
class WaktuLayananController extends Controller
{
    public function __construct(private readonly PenghitungWaktuLayanan $penghitung) {}

    public function index(Request $request): View
    {
        $bulan = $this->bulan($request);

        return view('kelola.waktu-layanan.index', [
            'bulan' => $bulan,
            'rekap' => $this->penghitung->rekap($bulan),
            'jedaMenit' => PenghitungWaktuLayanan::JEDA_MENIT,
        ]);
    }

    public function unduh(Request $request, PencatatAudit $audit): StreamedResponse
    {
        $bulan = $this->bulan($request);
        $rincian = $request->query('isi') === 'rincian';
        $pemisah = $request->query('pemisah') === 'titik_koma' ? ';' : ',';
        $rekap = $this->penghitung->rekap($bulan);

        $audit->catat('rekap.diunduh', 'Waktu layanan konselor '.$bulan->format('Y-m'), ['jenis' => $rincian ? 'waktu_layanan_rincian' : 'waktu_layanan']);

        $namaBerkas = 'rangkul-waktu-layanan-'.($rincian ? 'rincian-' : '').$bulan->format('Y-m').'.csv';

        return response()->streamDownload(function () use ($rekap, $rincian, $pemisah, $bulan): void {
            $keluaran = fopen('php://output', 'w');
            fwrite($keluaran, "\xEF\xBB\xBF");
            $tulis = fn (array $baris) => fputcsv($keluaran, array_map(RekapController::amankanSel(...), $baris), $pemisah, '"', '');
            $periode = $bulan->format('Y-m');

            if ($rincian) {
                $tulis(['bulan', 'konselor', 'nomor_anggota', 'kampus', 'kode_kasus', 'menit_percakapan', 'menit_pertemuan', 'menit_total']);

                foreach ($rekap as $baris) {
                    foreach ($baris['kasus'] as $kasus) {
                        $tulis([$periode, $baris['konselor']->nama, $baris['konselor']->nomor_anggota, $baris['konselor']->kampus, $kasus['permintaan']?->kode, $kasus['menit_percakapan'], $kasus['menit_pertemuan'], $kasus['menit_total']]);
                    }
                }
            } else {
                $tulis(['bulan', 'konselor', 'nomor_anggota', 'kampus', 'email', 'jumlah_kasus', 'menit_percakapan', 'menit_pertemuan', 'menit_total', 'jam_total']);

                foreach ($rekap as $baris) {
                    $konselor = $baris['konselor'];
                    $tulis([$periode, $konselor->nama, $konselor->nomor_anggota, $konselor->kampus, $konselor->email, count($baris['kasus']), $baris['menit_percakapan'], $baris['menit_pertemuan'], $baris['menit_total'], self::jamMenit($baris['menit_total'])]);
                }

                $tulis([$periode, 'TOTAL', '', '', '', array_sum(array_map(fn (array $baris): int => count($baris['kasus']), $rekap)), array_sum(array_column($rekap, 'menit_percakapan')), array_sum(array_column($rekap, 'menit_pertemuan')), array_sum(array_column($rekap, 'menit_total')), self::jamMenit(array_sum(array_column($rekap, 'menit_total')))]);
            }

            fclose($keluaran);
        }, $namaBerkas, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Mis. 135 menit → "2 jam 15 menit".
     */
    public static function jamMenit(int $menit): string
    {
        return intdiv($menit, 60).' jam '.($menit % 60).' menit';
    }

    /**
     * Bulan dari `?bulan=YYYY-MM`; bawaan bulan berjalan. Bulan mendatang tidak diterima.
     */
    private function bulan(Request $request): Carbon
    {
        $masukan = (string) $request->query('bulan', '');
        $bulan = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $masukan) === 1
            ? Carbon::createFromFormat('Y-m-d H:i:s', $masukan.'-01 00:00:00')
            : now()->startOfMonth();

        return $bulan->gt(now()) ? now()->startOfMonth() : $bulan;
    }
}
