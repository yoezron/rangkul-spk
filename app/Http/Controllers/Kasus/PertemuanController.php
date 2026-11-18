<?php

namespace App\Http\Controllers\Kasus;

use App\Enums\StatusPertemuan;
use App\Http\Controllers\Controller;
use App\Models\Permintaan;
use App\Models\Pertemuan;
use App\Services\PemberitahuLayanan;
use App\Services\PencatatAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Pertemuan daring: konselor mengusulkan, anggota menyetujui, komite
 * menyetujui pertemuan pertama setiap kasus, lalu tautan tampil bagi anggota.
 */
class PertemuanController extends Controller
{
    public function __construct(private readonly PemberitahuLayanan $pemberitahu) {}

    public function usul(Request $request, Permintaan $permintaan): RedirectResponse
    {
        Gate::authorize('tangani', $permintaan);

        $data = $request->validate([
            'mulai_pada' => ['required', 'date', 'after:now'],
            'durasi_menit' => ['required', 'integer', 'min:15', 'max:180'],
            'tautan' => ['nullable', 'url:https', 'max:500'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ], [
            'mulai_pada.after' => 'Waktu pertemuan harus di masa mendatang.',
            'tautan.url' => 'Tautan harus alamat https://, mis. tautan Zoom atau Google Meet.',
        ]);

        if ($permintaan->adaPertemuanAktif()) {
            return back()->withErrors(['pertemuan' => 'Masih ada usulan pertemuan yang menunggu tanggapan. Batalkan dulu bila ingin mengusulkan waktu lain.']);
        }

        $pertemuan = $permintaan->pertemuan()->create([
            ...$data,
            'diusulkan_oleh' => $request->user()->id,
            'status' => StatusPertemuan::Diusulkan,
        ]);

        PercakapanController::sistem($permintaan, "Konselor mengusulkan pertemuan daring pada {$pertemuan->waktu()}. Silakan setujui atau tolak di bagian Pertemuan.");
        $this->pemberitahu->pertemuanDiusulkan($pertemuan);

        return back()->with('status', 'Usulan pertemuan dikirim ke anggota.');
    }

    /**
     * Anggota menyetujui atau menolak usulan.
     */
    public function tanggapi(Request $request, Permintaan $permintaan, Pertemuan $pertemuan): RedirectResponse
    {
        $this->pastikanMilik($permintaan, $pertemuan);
        Gate::authorize('milik', $permintaan);
        abort_unless($permintaan->status->bolehBercakap() && $pertemuan->status === StatusPertemuan::Diusulkan, 409);

        $data = $request->validate([
            'keputusan' => ['required', 'in:setuju,tolak'],
            'alasan' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['keputusan'] === 'tolak') {
            $pertemuan->update(['status' => StatusPertemuan::Ditolak, 'alasan_penolakan' => $data['alasan'] ?? null]);
            PercakapanController::sistem($permintaan, 'Anggota belum dapat menghadiri pertemuan yang diusulkan.'.(filled($data['alasan'] ?? null) ? ' Alasan: '.$data['alasan'] : ''));
            $this->pemberitahu->pertemuanBerubah($pertemuan, $permintaan->konselor, "Anggota pada permintaan {$permintaan->kode} menolak usulan pertemuan {$pertemuan->waktu()}.");

            return back()->with('status', 'Usulan pertemuan ditolak. Anda dapat menyepakati waktu lain lewat percakapan.');
        }

        $perluKomite = $permintaan->pertemuanPerluKomite();
        $pertemuan->update([
            'status' => $perluKomite ? StatusPertemuan::MenungguKomite : StatusPertemuan::Terjadwal,
            'disetujui_anggota_pada' => now(),
        ]);

        if ($perluKomite) {
            PercakapanController::sistem($permintaan, 'Anggota menyetujui pertemuan. Sebagai pertemuan daring pertama, pertemuan menunggu persetujuan komite.');
            $this->pemberitahu->pertemuanPerluKomite($pertemuan);

            return back()->with('status', 'Terima kasih. Pertemuan menunggu persetujuan komite; kami beri kabar segera.');
        }

        PercakapanController::sistem($permintaan, "Pertemuan daring terjadwal pada {$pertemuan->waktu()}.");
        $this->pemberitahu->pertemuanTerjadwal($pertemuan);

        return back()->with('status', 'Pertemuan terjadwal.');
    }

    /**
     * Komite memutuskan pertemuan daring pertama.
     */
    public function putuskan(Request $request, Permintaan $permintaan, Pertemuan $pertemuan, PencatatAudit $audit): RedirectResponse
    {
        $this->pastikanMilik($permintaan, $pertemuan);
        Gate::authorize('kelola', $permintaan);
        abort_unless($pertemuan->status === StatusPertemuan::MenungguKomite, 409);

        $data = $request->validate([
            'keputusan' => ['required', 'in:setuju,tolak'],
            'alasan' => ['nullable', 'required_if:keputusan,tolak', 'string', 'max:1000'],
        ], ['alasan.required_if' => 'Tuliskan alasan penolakan untuk konselor.']);

        if ($data['keputusan'] === 'setuju') {
            $pertemuan->update([
                'status' => StatusPertemuan::Terjadwal,
                'disetujui_komite_pada' => now(),
                'disetujui_komite_oleh' => $request->user()->id,
            ]);
            $permintaan->update(['daring_disetujui_pada' => now(), 'daring_disetujui_oleh' => $request->user()->id]);
            PercakapanController::sistem($permintaan, "Komite menyetujui pertemuan daring. Pertemuan terjadwal pada {$pertemuan->waktu()}.");
            $this->pemberitahu->pertemuanTerjadwal($pertemuan);
        } else {
            $pertemuan->update(['status' => StatusPertemuan::Ditolak, 'alasan_penolakan' => $data['alasan']]);
            PercakapanController::sistem($permintaan, 'Komite belum menyetujui pertemuan daring yang diusulkan. Pendampingan berlanjut lewat percakapan.');
            $this->pemberitahu->pertemuanBerubah($pertemuan, $permintaan->konselor, "Komite belum menyetujui pertemuan daring pertama untuk {$permintaan->kode}. Baca alasannya di halaman kasus.");
        }

        $audit->catat('pertemuan.diputuskan', $permintaan->kode, ['keputusan' => $data['keputusan']]);

        return back()->with('status', $data['keputusan'] === 'setuju' ? 'Pertemuan disetujui dan terjadwal.' : 'Pertemuan ditolak.');
    }

    /**
     * Konselor mengisi atau mengganti tautan Zoom/Meet.
     */
    public function tautan(Request $request, Permintaan $permintaan, Pertemuan $pertemuan): RedirectResponse
    {
        $this->pastikanMilik($permintaan, $pertemuan);
        Gate::authorize('tangani', $permintaan);
        abort_unless($pertemuan->status->aktif(), 409);

        $data = $request->validate(['tautan' => ['required', 'url:https', 'max:500']], [
            'tautan.url' => 'Tautan harus alamat https://, mis. tautan Zoom atau Google Meet.',
        ]);

        $pertemuan->update(['tautan' => $data['tautan']]);

        if ($pertemuan->status === StatusPertemuan::Terjadwal) {
            PercakapanController::sistem($permintaan, 'Konselor memperbarui tautan pertemuan daring.');
        }

        return back()->with('status', 'Tautan pertemuan disimpan.');
    }

    public function batal(Request $request, Permintaan $permintaan, Pertemuan $pertemuan): RedirectResponse
    {
        $this->pastikanMilik($permintaan, $pertemuan);
        Gate::authorize('tangani', $permintaan);
        abort_unless($pertemuan->status->aktif(), 409);

        $pertemuan->update(['status' => StatusPertemuan::Dibatalkan]);
        PercakapanController::sistem($permintaan, "Pertemuan {$pertemuan->waktu()} dibatalkan konselor.");
        $this->pemberitahu->pertemuanBerubah($pertemuan, $permintaan->anggota, "Pertemuan daring {$pertemuan->waktu()} untuk permintaan {$permintaan->kode} dibatalkan konselor.");

        return back()->with('status', 'Pertemuan dibatalkan.');
    }

    private function pastikanMilik(Permintaan $permintaan, Pertemuan $pertemuan): void
    {
        abort_unless($pertemuan->permintaan_id === $permintaan->id, 404);
    }
}
