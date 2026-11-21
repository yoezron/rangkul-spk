<?php

namespace App\Http\Controllers\Konseling;

use App\Enums\StatusPermintaan;
use App\Enums\StatusPertemuan;
use App\Http\Controllers\Controller;
use App\Models\Permintaan;
use App\Services\PenghitungWaktuLayanan;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Statistik layanan milik peer konselor sendiri: waktu layanan per bulan
 * (aturan hitungan sama dengan rekap pembayaran komite) dan riwayat kasus
 * yang pernah didampinginya.
 */
class StatistikController extends Controller
{
    public const JUMLAH_BULAN = 12;

    public function __invoke(Request $request, PenghitungWaktuLayanan $penghitung): View
    {
        $konselor = $request->user();
        $bulanIni = now()->startOfMonth();

        $perBulan = collect(range(self::JUMLAH_BULAN - 1, 0))->map(function (int $mundur) use ($penghitung, $konselor, $bulanIni): array {
            $awal = $bulanIni->copy()->subMonths($mundur);
            $baris = $penghitung->rekapKonselor($konselor, $awal, $awal->copy()->addMonth());

            return [
                'bulan' => $awal,
                'kasus' => count($baris['kasus'] ?? []),
                'menit_percakapan' => $baris['menit_percakapan'] ?? 0,
                'menit_pertemuan' => $baris['menit_pertemuan'] ?? 0,
                'menit_total' => $baris['menit_total'] ?? 0,
            ];
        });

        $sepanjangWaktu = $penghitung->rekapKonselor($konselor, Carbon::create(2000), now()->addMinute());
        $menitPerKasus = collect($sepanjangWaktu['kasus'] ?? [])
            ->mapWithKeys(fn (array $kasus): array => [$kasus['permintaan']->id => $kasus['menit_total']]);

        $riwayat = Permintaan::query()
            ->where(fn (Builder $kueri) => $kueri->where('konselor_id', $konselor->id)->orWhereIn('id', $menitPerKasus->keys()))
            ->withCount([
                'pesan as pesan_saya_count' => fn (Builder $kueri) => $kueri->where('jenis', 'pesan')->where('pengirim_id', $konselor->id),
                'pertemuan as pertemuan_terlaksana_count' => fn (Builder $kueri) => $kueri->where('status', StatusPertemuan::Terjadwal->value)->where('mulai_pada', '<', now()),
            ])
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', [StatusPermintaan::Berlangsung->value, StatusPermintaan::MenungguPenutupan->value])
            ->orderByDesc('ditugaskan_pada')
            ->get();

        return view('konseling.statistik', [
            'perBulan' => $perBulan,
            'bulanIni' => $perBulan->last(),
            'menitSepanjangWaktu' => $sepanjangWaktu['menit_total'] ?? 0,
            'menitPerKasus' => $menitPerKasus,
            'riwayat' => $riwayat,
            'jedaMenit' => PenghitungWaktuLayanan::JEDA_MENIT,
        ]);
    }
}
