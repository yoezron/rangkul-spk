<?php

namespace App\Http\Controllers\Kasus;

use App\Enums\StatusPermintaan;
use App\Http\Controllers\Controller;
use App\Models\Permintaan;
use App\Services\PemberitahuLayanan;
use App\Services\PencatatAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Penutupan proses konsultasi: konselor mengusulkan → anggota menyetujui →
 * komite meninjau dan memutuskan.
 */
class PenutupanController extends Controller
{
    public function __construct(private readonly PemberitahuLayanan $pemberitahu) {}

    public function usul(Request $request, Permintaan $permintaan): RedirectResponse
    {
        Gate::authorize('tangani', $permintaan);
        abort_unless($permintaan->status === StatusPermintaan::Berlangsung && $permintaan->penutupan_diusulkan_pada === null, 409);

        $data = $request->validate(['alasan' => ['required', 'string', 'max:2000']], [
            'alasan.required' => 'Tuliskan alasan penutupan untuk anggota.',
        ]);

        $permintaan->update([
            'penutupan_diusulkan_pada' => now(),
            'alasan_penutupan' => trim($data['alasan']),
            'penutupan_disetujui_anggota_pada' => null,
        ]);

        PercakapanController::sistem($permintaan, 'Konselor mengusulkan menutup proses konsultasi. Penutupan hanya berjalan bila Anda setuju.');
        $this->pemberitahu->penutupanDiusulkan($permintaan);

        return back()->with('status', 'Usulan penutupan dikirim ke anggota.');
    }

    /**
     * Anggota menyetujui atau menolak usulan penutupan.
     */
    public function tanggapi(Request $request, Permintaan $permintaan): RedirectResponse
    {
        Gate::authorize('milik', $permintaan);
        abort_unless($permintaan->status === StatusPermintaan::Berlangsung && $permintaan->penutupan_diusulkan_pada !== null, 409);

        $data = $request->validate(['keputusan' => ['required', 'in:setuju,tolak']]);

        if ($data['keputusan'] === 'tolak') {
            $permintaan->update(['penutupan_diusulkan_pada' => null, 'alasan_penutupan' => null]);
            PercakapanController::sistem($permintaan, 'Anggota memilih melanjutkan pendampingan.');
            $this->pemberitahu->penutupanDitolakAnggota($permintaan);

            return back()->with('status', 'Pendampingan dilanjutkan.');
        }

        $permintaan->update([
            'status' => StatusPermintaan::MenungguPenutupan,
            'penutupan_disetujui_anggota_pada' => now(),
        ]);

        PercakapanController::sistem($permintaan, 'Anggota menyetujui penutupan. Menunggu persetujuan komite.');
        $this->pemberitahu->penutupanPerluTinjauan($permintaan);

        return back()->with('status', 'Terima kasih. Penutupan menunggu persetujuan komite.');
    }

    /**
     * Komite menyetujui penutupan atau mengembalikannya.
     */
    public function putuskan(Request $request, Permintaan $permintaan, PencatatAudit $audit): RedirectResponse
    {
        Gate::authorize('kelola', $permintaan);
        abort_unless($permintaan->status === StatusPermintaan::MenungguPenutupan, 409);

        $data = $request->validate([
            'keputusan' => ['required', 'in:setuju,kembalikan'],
            'catatan' => ['nullable', 'required_if:keputusan,kembalikan', 'string', 'max:2000'],
        ], ['catatan.required_if' => 'Tuliskan catatan untuk konselor mengapa pendampingan perlu dilanjutkan.']);

        $disetujui = $data['keputusan'] === 'setuju';

        $permintaan->update([
            'status' => $disetujui ? StatusPermintaan::Selesai : StatusPermintaan::Berlangsung,
            'selesai_pada' => $disetujui ? now() : null,
            'penutupan_ditinjau_pada' => now(),
            'penutupan_ditinjau_oleh' => $request->user()->id,
            'catatan_tinjauan_penutupan' => $data['catatan'] ?? null,
            ...($disetujui ? [] : ['penutupan_diusulkan_pada' => null, 'penutupan_disetujui_anggota_pada' => null]),
        ]);

        PercakapanController::sistem($permintaan, $disetujui
            ? 'Komite menyetujui penutupan. Proses konsultasi selesai. Terima kasih.'
            : 'Komite meminta pendampingan dilanjutkan.');

        $audit->catat('penutupan.diputuskan', $permintaan->kode, ['keputusan' => $data['keputusan']]);
        $this->pemberitahu->penutupanDiputuskan($permintaan, $disetujui);

        return back()->with('status', $disetujui ? 'Penutupan disetujui; kasus selesai.' : 'Penutupan dikembalikan ke konselor.');
    }
}
