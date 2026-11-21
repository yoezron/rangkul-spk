<?php

namespace App\Http\Controllers\Kasus;

use App\Http\Controllers\Controller;
use App\Models\Permintaan;
use App\Models\Pesan;
use App\Services\PemberitahuLayanan;
use App\Services\PencatatKehadiran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Percakapan tertulis antara anggota dan konselor pendampingnya.
 *
 * Halaman mengambil pesan baru tiap beberapa detik lewat `daftar` (tanpa
 * websocket, supaya tetap berjalan di hosting bersama). Pesan dari pihak lain
 * ditandai terbaca saat anggota atau konselor pendamping mengambilnya;
 * komite yang memantau tidak mengubah status terbaca.
 */
class PercakapanController extends Controller
{
    public function daftar(Request $request, Permintaan $permintaan, PencatatKehadiran $kehadiran): JsonResponse
    {
        Gate::authorize('lihat', $permintaan);

        $pengguna = $request->user();
        $setelah = max(0, (int) $request->query('setelah', 0));
        $sebagai = match (true) {
            Gate::allows('milik', $permintaan) => 'anggota',
            Gate::allows('dampingi', $permintaan) => 'konselor',
            default => null,
        };
        $peserta = $sebagai !== null;

        // Setiap polling peserta menjadi detak kehadiran untuk menghitung waktu layanan.
        if ($peserta && $permintaan->status->bolehBercakap()) {
            $kehadiran->detak($permintaan, $pengguna, $sebagai);
        }

        $daftar = $permintaan->pesan()->with('pengirim')->where('id', '>', $setelah)->limit(200)->get();

        if ($peserta) {
            Pesan::query()
                ->where('permintaan_id', $permintaan->id)
                ->where('jenis', 'pesan')
                ->where('pengirim_id', '!=', $pengguna->id)
                ->whereNull('dibaca_pada')
                ->update(['dibaca_pada' => now()]);
        }

        return response()->json([
            'pesan' => $daftar->map(fn (Pesan $pesan): array => [
                'id' => $pesan->id,
                'isi' => $pesan->isi,
                'waktu' => $pesan->created_at->format('d/m H.i'),
                'sistem' => $pesan->jenis === 'sistem',
                'milikSaya' => $pesan->pengirim_id === $pengguna->id,
                'pengirim' => match (true) {
                    $pesan->jenis === 'sistem' => 'SPK Rangkul',
                    $pesan->pengirim_id === $pengguna->id => 'Anda',
                    $pesan->pengirim_id === $permintaan->user_id => ($pesan->pengirim?->nama ?? 'Anggota').' (anggota)',
                    default => ($pesan->pengirim?->nama ?? 'Konselor').' (konselor)',
                },
            ])->all(),
        ]);
    }

    /**
     * Hanya pesan pertama tiap pihak yang langsung dikabarkan lewat WhatsApp;
     * pesan berikutnya tidak, supaya percakapan yang sedang berjalan tidak
     * membanjiri ponsel. Pesan yang lama tidak dibuka diingatkan oleh
     * rangkul:ingatkan-pesan-tertunda.
     */
    public function kirim(Request $request, Permintaan $permintaan, PemberitahuLayanan $pemberitahu): JsonResponse|RedirectResponse
    {
        Gate::authorize('bercakap', $permintaan);

        $data = $request->validate(['isi' => ['required', 'string', 'max:5000']]);
        $pengirim = $request->user();

        $pesanPertama = ! $permintaan->pesan()
            ->where('jenis', 'pesan')
            ->where('pengirim_id', $pengirim->id)
            ->exists();

        $permintaan->pesan()->create([
            'pengirim_id' => $pengirim->id,
            'jenis' => 'pesan',
            'isi' => trim($data['isi']),
        ]);

        if ($pesanPertama) {
            $pemberitahu->pesanPertama($permintaan, $pengirim);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->to(url()->previous().'#percakapan');
    }

    /**
     * Catatan otomatis di percakapan (penugasan, jadwal, penutupan).
     */
    public static function sistem(Permintaan $permintaan, string $isi): void
    {
        $permintaan->pesan()->create(['jenis' => 'sistem', 'isi' => $isi]);
    }
}
