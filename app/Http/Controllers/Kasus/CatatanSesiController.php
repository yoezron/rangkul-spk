<?php

namespace App\Http\Controllers\Kasus;

use App\Http\Controllers\Controller;
use App\Models\Permintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Catatan konselor setelah sesi. Hanya bisa ditambah, tidak diubah, supaya
 * riwayat pendampingan tetap utuh untuk tinjauan komite.
 */
class CatatanSesiController extends Controller
{
    public function simpan(Request $request, Permintaan $permintaan): RedirectResponse
    {
        Gate::authorize('catat', $permintaan);

        $data = $request->validate([
            'pertemuan_id' => ['nullable', 'integer', Rule::exists('pertemuan', 'id')->where('permintaan_id', $permintaan->id)],
            'rangkuman' => ['required', 'string', 'max:10000'],
            'analisis' => ['nullable', 'string', 'max:10000'],
            'saran' => ['nullable', 'string', 'max:5000'],
            'rujukan' => ['required', 'boolean'],
            'rujukan_ke' => ['nullable', 'required_if:rujukan,1', 'string', 'max:1000'],
        ], [
            'rangkuman.required' => 'Rangkuman isi sesi wajib diisi.',
            'rujukan_ke.required_if' => 'Tuliskan ke psikolog/lembaga mana anggota disarankan dirujuk.',
        ]);

        $permintaan->catatanSesi()->create([...$data, 'penulis_id' => $request->user()->id]);

        return redirect()->to(url()->previous().'#catatan')->with('status', 'Catatan sesi disimpan.');
    }
}
