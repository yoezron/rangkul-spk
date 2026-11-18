<?php

namespace App\Http\Controllers\Kelola;

use App\Enums\Hak;
use App\Enums\StatusPermintaan;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Kasus\PercakapanController;
use App\Models\Permintaan;
use App\Models\User;
use App\Services\PemberitahuLayanan;
use App\Services\PencatatAudit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Daftar dan tindakan komite atas permintaan layanan.
 *
 * Setiap kali komite membuka sebuah kasus, audit log mencatatnya (tanpa isi),
 * karena halaman itu memuat cerita dan hasil asesmen anggota.
 */
class PermintaanController extends Controller
{
    public function __construct(
        private readonly PencatatAudit $audit,
        private readonly PemberitahuLayanan $pemberitahu,
    ) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'terbuka');
        $cari = trim((string) $request->query('cari', ''));

        $permintaan = Permintaan::query()
            ->with(['anggota', 'konselor'])
            ->where('status', '!=', StatusPermintaan::Draf->value)
            ->when($status === 'terbuka', fn (Builder $kueri) => $kueri->whereNotIn('status', [StatusPermintaan::Selesai->value, StatusPermintaan::Ditolak->value, StatusPermintaan::Dibatalkan->value]))
            ->when($status === 'belum_ditugaskan', fn (Builder $kueri) => $kueri->whereNull('konselor_id')->whereIn('status', [StatusPermintaan::Diajukan->value, StatusPermintaan::Ditinjau->value]))
            ->when($status === 'darurat', fn (Builder $kueri) => $kueri->where('darurat', true)->whereNotIn('status', [StatusPermintaan::Selesai->value, StatusPermintaan::Ditolak->value, StatusPermintaan::Dibatalkan->value]))
            ->when(StatusPermintaan::tryFrom($status) !== null, fn (Builder $kueri) => $kueri->where('status', $status))
            ->when($cari !== '', function (Builder $kueri) use ($cari): void {
                $pola = '%'.addcslashes($cari, '%_\\').'%';
                $kueri->where(fn (Builder $kueri) => $kueri->where('kode', 'like', $pola)->orWhereHas('anggota', fn (Builder $anggota) => $anggota->where('nama', 'like', $pola)->orWhere('kampus', 'like', $pola)));
            })
            ->orderByDesc('darurat')
            ->orderByDesc('diajukan_pada')
            ->simplePaginate(30)
            ->withQueryString();

        return view('kelola.permintaan.index', ['daftar' => $permintaan, 'status' => $status, 'cari' => $cari]);
    }

    public function tampil(Request $request, Permintaan $permintaan): View
    {
        Gate::authorize('kelola', $permintaan);

        $permintaan->load(['anggota', 'konselor', 'jawabanAsesmen', 'pertemuan', 'catatanSesi.penulis', 'catatanSesi.pertemuan']);
        $this->audit->catat('permintaan.dibuka', $permintaan->kode);

        return view('kelola.permintaan.tampil', [
            'permintaan' => $permintaan,
            'calonKonselor' => $this->calonKonselor($permintaan),
        ]);
    }

    public function tinjau(Permintaan $permintaan): RedirectResponse
    {
        Gate::authorize('kelola', $permintaan);
        abort_unless($permintaan->status === StatusPermintaan::Diajukan, 409);

        $permintaan->update(['status' => StatusPermintaan::Ditinjau]);
        $this->audit->catat('permintaan.ditinjau', $permintaan->kode);

        return back()->with('status', 'Permintaan ditandai sedang ditinjau.');
    }

    public function tugaskan(Request $request, Permintaan $permintaan): RedirectResponse
    {
        Gate::authorize('kelola', $permintaan);
        abort_unless($permintaan->status->terbuka(), 409);

        $calon = $this->calonKonselor($permintaan);
        $data = $request->validate([
            'konselor_id' => ['required', 'integer', Rule::in($calon->modelKeys())],
            'konfirmasi_kampus' => ['nullable', 'boolean'],
        ], ['konselor_id.in' => 'Pilih peer konselor dari daftar.']);

        $konselor = $calon->find($data['konselor_id']);

        if ($permintaan->bentrokKampus($konselor) && ! $request->boolean('konfirmasi_kampus')) {
            return back()->withInput()->withErrors(['konselor_id' => "Anggota meminta konselor dari kampus lain, sedangkan {$konselor->nama} sekampus dengannya. Centang konfirmasi bila tetap ingin menugaskannya."]);
        }

        if ($permintaan->konselor_id === $konselor->id) {
            return back()->with('status', "{$konselor->nama} sudah menjadi konselor pendamping.");
        }

        $pengganti = $permintaan->konselor_id !== null;

        $permintaan->update([
            'konselor_id' => $konselor->id,
            'ditugaskan_pada' => now(),
            'ditugaskan_oleh' => $request->user()->id,
            'status' => $permintaan->status->belumDitangani() ? StatusPermintaan::Berlangsung : $permintaan->status,
        ]);

        PercakapanController::sistem($permintaan, $pengganti
            ? "Konselor pendamping diganti. Kini Anda didampingi {$konselor->nama}."
            : "{$konselor->nama} ditugaskan sebagai konselor pendamping. Silakan mulai bercerita di sini.");

        $this->audit->catat('permintaan.ditugaskan', $permintaan->kode, ['konselor' => PencatatAudit::sebut($konselor), 'pengganti' => $pengganti]);
        $this->pemberitahu->konselorDitugaskan($permintaan->refresh(), $pengganti);

        return back()->with('status', "{$konselor->nama} ditugaskan sebagai konselor pendamping.");
    }

    public function tolak(Request $request, Permintaan $permintaan): RedirectResponse
    {
        Gate::authorize('kelola', $permintaan);
        abort_unless(in_array($permintaan->status, [StatusPermintaan::Diajukan, StatusPermintaan::Ditinjau], true), 409);

        $data = $request->validate(['alasan' => ['required', 'string', 'max:3000']], [
            'alasan.required' => 'Tuliskan penjelasan dan saran untuk anggota, mis. layanan lain yang lebih sesuai.',
        ]);

        $permintaan->update(['status' => StatusPermintaan::Ditolak, 'alasan_penolakan' => trim($data['alasan'])]);
        $this->audit->catat('permintaan.ditolak', $permintaan->kode);
        $this->pemberitahu->permintaanDitolak($permintaan);

        return back()->with('status', 'Permintaan ditutup dan anggota diberi penjelasan.');
    }

    public function darurat(Request $request, Permintaan $permintaan): RedirectResponse
    {
        Gate::authorize('kelola', $permintaan);

        $darurat = $request->boolean('darurat');
        $permintaan->darurat = $darurat;

        if ($darurat) {
            $permintaan->tandaiDarurat('Ditandai komite');
        }

        $permintaan->save();
        $this->audit->catat('permintaan.darurat', $permintaan->kode, ['darurat' => $darurat]);

        return back()->with('status', $darurat ? 'Permintaan ditandai darurat.' : 'Tanda darurat dihapus.');
    }

    public function catatan(Request $request, Permintaan $permintaan): RedirectResponse
    {
        Gate::authorize('kelola', $permintaan);

        $data = $request->validate(['catatan_komite' => ['nullable', 'string', 'max:10000']]);
        $permintaan->update(['catatan_komite' => filled($data['catatan_komite'] ?? null) ? trim($data['catatan_komite']) : null]);

        return back()->with('status', 'Catatan komite disimpan.');
    }

    /**
     * Pengguna yang dapat menjadi konselor pendamping: pemegang hak
     * konseling.tangani (termasuk komite yang merangkap konselor dan
     * superadmin), kecuali anggota yang mengajukan.
     *
     * @return Collection<int, User>
     */
    private function calonKonselor(Permintaan $permintaan): Collection
    {
        $peranSuperadmin = mb_strtolower(trim((string) config('services.spk.peran_superadmin')));

        return User::query()
            ->where(function (Builder $kueri) use ($peranSuperadmin): void {
                $kueri->pemegangPeranDenganHak(Hak::KonselingTangani);

                if ($peranSuperadmin !== '') {
                    $kueri->orWhereRaw('LOWER(TRIM(peran)) = ?', [$peranSuperadmin]);
                }
            })
            ->whereKeyNot($permintaan->user_id)
            ->withCount(['kasusDampingan as kasus_aktif_count' => fn (Builder $kueri) => $kueri->whereIn('status', [StatusPermintaan::Berlangsung->value, StatusPermintaan::MenungguPenutupan->value])])
            ->orderBy('nama')
            ->get();
    }
}
