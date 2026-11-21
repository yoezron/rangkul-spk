<?php

namespace App\Http\Controllers\Konseling;

use App\Enums\StatusPermintaan;
use App\Http\Controllers\Controller;
use App\Models\Instrumen;
use App\Models\Permintaan;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Ruang kerja peer konselor: hanya kasus yang ditugaskan kepadanya.
 */
class KasusController extends Controller
{
    public function index(Request $request): View
    {
        $pengguna = $request->user();

        $daftar = $pengguna->kasusDampingan()
            ->with('anggota')
            ->withCount(['pesan as belum_dibaca_count' => fn (Builder $kueri) => $kueri
                ->where('jenis', 'pesan')
                ->whereNull('dibaca_pada')
                ->where('pengirim_id', '!=', $pengguna->id)])
            ->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', [StatusPermintaan::Berlangsung->value, StatusPermintaan::MenungguPenutupan->value])
            ->orderByDesc('darurat')
            ->orderByDesc('ditugaskan_pada')
            ->get();

        return view('konseling.index', ['daftar' => $daftar]);
    }

    public function tampil(Request $request, Permintaan $permintaan): View
    {
        Gate::authorize('dampingi', $permintaan);

        $permintaan->load(['anggota', 'jawabanAsesmen', 'kirimanInstrumen', 'pertemuan', 'catatanSesi.penulis', 'catatanSesi.pertemuan']);

        return view('konseling.tampil', [
            'permintaan' => $permintaan,
            'daftarInstrumen' => Instrumen::query()->orderBy('urutan')->orderBy('id')->get(['id', 'nama', 'aktif', 'butir']),
        ]);
    }
}
