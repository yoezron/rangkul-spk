<?php

namespace App\Http\Controllers\Kelola;

use App\Enums\Hak;
use App\Http\Controllers\Controller;
use App\Models\Peran;
use App\Services\PencatatAudit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Membuat dan mengubah peran beserta hak aksesnya.
 *
 * Pagar terhadap penaikan hak: seorang pengelola hanya dapat memberikan hak
 * yang ia miliki sendiri, dan hanya dapat mengubah atau menghapus peran yang
 * seluruh haknya ia miliki. Superadmin memiliki semua hak, jadi pagar ini
 * hanya membatasi pengelola lain yang diberi hak "kelola peran".
 */
class PeranController extends Controller
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function index(Request $request): View
    {
        return view('kelola.peran.index', [
            'daftarPeran' => Peran::query()->withCount('pengguna')->orderBy('nama')->get(),
            'aktor' => $request->user(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('kelola.peran.formulir', ['peran' => new Peran(['hak' => []]), 'aktor' => $request->user()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);

        $peran = Peran::query()->create([
            'kunci' => $this->kunciUnik($data['nama']),
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'],
            'hak' => $data['hak'],
            'bawaan' => false,
        ]);

        $this->audit->catat('peran.dibuat', $peran->nama, ['hak' => $peran->hak]);

        return redirect()->route('kelola.peran.index')->with('status', "Peran {$peran->nama} dibuat.");
    }

    public function edit(Request $request, Peran $peran): View
    {
        $this->pastikanBolehDikelola($request, $peran);

        return view('kelola.peran.formulir', ['peran' => $peran, 'aktor' => $request->user()]);
    }

    public function update(Request $request, Peran $peran): RedirectResponse
    {
        $this->pastikanBolehDikelola($request, $peran);

        $data = $this->validasi($request, $peran);
        $hakLama = $peran->hakBerlaku();

        $peran->update([
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'],
            'hak' => $data['hak'],
        ]);

        $this->audit->catat('peran.diubah', $peran->nama, array_filter([
            'hak_ditambah' => array_values(array_diff($data['hak'], $hakLama)),
            'hak_dicabut' => array_values(array_diff($hakLama, $data['hak'])),
            'nama' => $peran->wasChanged('nama') ? $peran->nama : null,
        ]));

        return redirect()->route('kelola.peran.index')->with('status', "Peran {$peran->nama} disimpan.");
    }

    public function destroy(Request $request, Peran $peran): RedirectResponse
    {
        $this->pastikanBolehDikelola($request, $peran);

        if ($peran->bawaan) {
            return back()->withErrors(['peran' => "Peran bawaan {$peran->nama} tidak dapat dihapus. Hak aksesnya tetap dapat diubah."]);
        }

        $jumlahPemegang = $peran->pengguna()->count();
        $nama = $peran->nama;
        $peran->delete();

        $this->audit->catat('peran.dihapus', $nama, ['pemegang' => $jumlahPemegang]);

        return redirect()->route('kelola.peran.index')->with('status', "Peran {$nama} dihapus dari {$jumlahPemegang} pemegangnya.");
    }

    /**
     * @return array{nama: string, deskripsi: string|null, hak: list<string>}
     */
    private function validasi(Request $request, ?Peran $peran = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100', Rule::unique('peran', 'nama')->ignore($peran?->id)],
            'deskripsi' => ['nullable', 'string', 'max:500'],
            'hak' => ['nullable', 'array'],
            'hak.*' => ['string', Rule::in($request->user()->daftarHak())],
        ], [
            'hak.*.in' => 'Anda tidak dapat memberikan hak akses yang tidak Anda miliki sendiri.',
        ]);

        $hak = array_values(array_unique($data['hak'] ?? []));

        // Hak pengelolaan tidak berguna tanpa pintu masuk ke dashboard.
        foreach ($hak as $nilai) {
            if (Hak::from($nilai)->butuhDasbor()) {
                $hak[] = Hak::DasborLihat->value;
                break;
            }
        }

        return [
            'nama' => trim($data['nama']),
            'deskripsi' => filled($data['deskripsi'] ?? null) ? trim($data['deskripsi']) : null,
            'hak' => array_values(array_intersect(Hak::semuaNilai(), $hak)),
        ];
    }

    private function pastikanBolehDikelola(Request $request, Peran $peran): void
    {
        abort_unless($peran->bolehDikelolaOleh($request->user()), 403, 'Peran ini memuat hak akses yang tidak Anda miliki.');
    }

    private function kunciUnik(string $nama): string
    {
        $dasar = Str::slug($nama, '_') ?: 'peran';
        $kunci = $dasar;

        for ($urutan = 2; Peran::query()->where('kunci', $kunci)->exists(); $urutan++) {
            $kunci = $dasar.'_'.$urutan;
        }

        return $kunci;
    }
}
