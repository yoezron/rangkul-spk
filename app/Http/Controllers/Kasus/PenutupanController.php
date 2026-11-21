<?php

namespace App\Http\Controllers\Kasus;

use App\Enums\StatusPermintaan;
use App\Enums\StatusPertemuan;
use App\Http\Controllers\Controller;
use App\Models\Permintaan;
use App\Services\PemberitahuLayanan;
use App\Services\PencatatAudit;
use App\Services\PengaturanRangkul;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Penutupan proses konsultasi: konselor mengusulkan → anggota menyetujui →
 * komite meninjau dan memutuskan. Komite juga dapat menutup langsung, dan
 * anggota dapat membuka kembali kasus yang selesai dalam 30 hari.
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

    /**
     * Komite menutup langsung pendampingan dan menandainya selesai, tanpa
     * menunggu usulan konselor maupun persetujuan anggota — untuk kasus yang
     * menggantung atau sebenarnya sudah tuntas.
     */
    public function tutup(Request $request, Permintaan $permintaan, PencatatAudit $audit): RedirectResponse
    {
        Gate::authorize('kelola', $permintaan);
        abort_unless($permintaan->status->bolehBercakap(), 409);

        $data = $request->validate(['alasan' => ['required', 'string', 'max:2000']], [
            'alasan.required' => 'Tuliskan alasan penutupan (dibaca konselor dan komite).',
        ]);

        DB::transaction(function () use ($permintaan, $request, $data): void {
            $permintaan->update([
                'status' => StatusPermintaan::Selesai,
                'selesai_pada' => now(),
                'alasan_penutupan' => trim($data['alasan']),
                'penutupan_ditinjau_pada' => now(),
                'penutupan_ditinjau_oleh' => $request->user()->id,
                'catatan_tinjauan_penutupan' => null,
            ]);

            $permintaan->pertemuan()
                ->whereIn('status', [StatusPertemuan::Diusulkan->value, StatusPertemuan::MenungguKomite->value, StatusPertemuan::Terjadwal->value])
                ->where('mulai_pada', '>', now())
                ->update(['status' => StatusPertemuan::Dibatalkan->value]);

            PercakapanController::sistem($permintaan, 'Komite menutup proses konsultasi dan menandainya selesai. Bila Anda masih membutuhkan pendampingan untuk hal yang sama, Anda dapat membukanya kembali dalam '.Permintaan::HARI_BUKA_KEMBALI.' hari.');
        });

        $audit->catat('penutupan.komite', $permintaan->kode);
        $this->pemberitahu->ditutupKomite($permintaan);

        return back()->with('status', 'Proses konsultasi ditutup dan ditandai selesai.');
    }

    /**
     * Anggota membuka kembali kasus yang selesai (dalam HARI_BUKA_KEMBALI hari)
     * bersama konselor yang sama. Alasannya menjadi pesan pertama percakapan
     * lanjutan.
     */
    public function bukaKembali(Request $request, Permintaan $permintaan, PengaturanRangkul $pengaturan, PencatatAudit $audit): RedirectResponse
    {
        Gate::authorize('bukaKembali', $permintaan);

        $adaYangBerjalan = $request->user()->permintaan()
            ->whereKeyNot($permintaan->id)
            ->whereNotIn('status', [StatusPermintaan::Draf->value, StatusPermintaan::Selesai->value, StatusPermintaan::Ditolak->value, StatusPermintaan::Dibatalkan->value])
            ->exists();

        if ($adaYangBerjalan) {
            return back()->withErrors(['buka_kembali' => 'Anda masih punya permintaan lain yang sedang berjalan. Lanjutkan percakapan di sana.']);
        }

        if (! $pengaturan->layananBuka()) {
            return back()->withErrors(['buka_kembali' => (string) $pengaturan->get('layanan.pesan_tutup')]);
        }

        $data = $request->validate(['alasan' => ['required', 'string', 'max:5000']], [
            'alasan.required' => 'Ceritakan singkat apa yang ingin Anda lanjutkan bersama konselor.',
        ]);

        DB::transaction(function () use ($permintaan, $request, $data): void {
            $permintaan->update([
                'status' => StatusPermintaan::Berlangsung,
                'dibuka_kembali_pada' => now(),
                'selesai_pada' => null,
                'penutupan_diusulkan_pada' => null,
                'alasan_penutupan' => null,
                'penutupan_disetujui_anggota_pada' => null,
                'penutupan_ditinjau_pada' => null,
                'penutupan_ditinjau_oleh' => null,
                'catatan_tinjauan_penutupan' => null,
                'diingatkan_menggantung_pada' => null,
            ]);

            PercakapanController::sistem($permintaan, 'Anggota membuka kembali proses konsultasi.');
            $permintaan->pesan()->create([
                'pengirim_id' => $request->user()->id,
                'jenis' => 'pesan',
                'isi' => trim($data['alasan']),
            ]);
        });

        $audit->catat('permintaan.dibuka_kembali', $permintaan->kode);
        $this->pemberitahu->dibukaKembali($permintaan->load('konselor'));

        return redirect()->to(route('permintaan.tampil', $permintaan).'#percakapan')
            ->with('status', 'Proses konsultasi dibuka kembali. Konselor pendamping Anda sudah diberi tahu.');
    }
}
