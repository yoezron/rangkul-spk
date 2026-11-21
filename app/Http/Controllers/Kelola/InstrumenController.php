<?php

namespace App\Http\Controllers\Kelola;

use App\Http\Controllers\Controller;
use App\Models\Instrumen;
use App\Services\PencatatAudit;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pembangun asesmen dasar untuk komite.
 *
 * Mengubah instrumen tidak mengubah hasil lama: setiap jawaban menyimpan
 * salinan instrumen saat diisi. Instrumen yang sudah pernah dijawab tidak
 * dapat dihapus, hanya dinonaktifkan.
 *
 * Formulir menampilkan baris kosong tambahan untuk pilihan, subskala, rentang,
 * dan butir; baris yang dibiarkan kosong diabaikan saat disimpan.
 */
class InstrumenController extends Controller
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function index(): View
    {
        return view('kelola.instrumen.index', [
            'daftar' => Instrumen::query()->withCount('jawaban')->orderBy('urutan')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return view('kelola.instrumen.formulir', ['instrumen' => new Instrumen([
            'metode' => 'jumlah', 'pengali' => 1, 'pilihan' => [], 'subskala' => [], 'rentang' => [], 'butir' => [], 'aktif' => false, 'urutan' => 100,
        ])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validasi($request);
        $data['kunci'] = $this->kunciUnik($data['nama']);
        $data['bawaan'] = false;

        $instrumen = Instrumen::query()->create($data);
        $this->audit->catat('instrumen.dibuat', $instrumen->nama, ['butir' => count($instrumen->butir), 'aktif' => $instrumen->aktif]);

        return redirect()->route('kelola.instrumen.edit', $instrumen)->with('status', 'Instrumen dibuat.');
    }

    public function edit(Instrumen $instrumen): View
    {
        return view('kelola.instrumen.formulir', ['instrumen' => $instrumen]);
    }

    public function update(Request $request, Instrumen $instrumen): RedirectResponse
    {
        $instrumen->update($this->validasi($request, $instrumen));
        $this->audit->catat('instrumen.diubah', $instrumen->nama, ['butir' => count($instrumen->butir), 'aktif' => $instrumen->aktif]);

        return redirect()->route('kelola.instrumen.edit', $instrumen)->with('status', 'Instrumen disimpan. Jawaban yang sudah masuk tetap memakai versi lama.');
    }

    public function aktif(Request $request, Instrumen $instrumen): RedirectResponse
    {
        $instrumen->update(['aktif' => $request->boolean('aktif')]);
        $this->audit->catat('instrumen.diubah', $instrumen->nama, ['aktif' => $instrumen->aktif]);

        return back()->with('status', $instrumen->aktif ? "{$instrumen->nama} kini dipakai di formulir." : "{$instrumen->nama} tidak lagi dipakai di formulir.");
    }

    public function destroy(Instrumen $instrumen): RedirectResponse
    {
        if ($instrumen->jawaban()->exists() || $instrumen->kiriman()->exists()) {
            return back()->withErrors(['instrumen' => 'Instrumen yang sudah pernah dijawab tidak dapat dihapus. Nonaktifkan saja supaya tidak lagi muncul di formulir.']);
        }

        $nama = $instrumen->nama;
        $instrumen->delete();
        $this->audit->catat('instrumen.dihapus', $nama);

        return redirect()->route('kelola.instrumen.index')->with('status', "Instrumen {$nama} dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validasi(Request $request, ?Instrumen $instrumen = null): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'instruksi' => ['nullable', 'string', 'max:1000'],
            'sumber' => ['nullable', 'string', 'max:1000'],
            'metode' => ['required', 'in:jumlah,rerata'],
            'pengali' => ['required', 'numeric', 'min:0.01', 'max:1000'],
            'urutan' => ['required', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['required', 'boolean'],
            'pilihan' => ['array'],
            'pilihan.*.label' => ['nullable', 'string', 'max:150'],
            'pilihan.*.nilai' => ['nullable', 'numeric'],
            'subskala' => ['array'],
            'subskala.*.kunci' => ['nullable', 'string', 'max:20', 'regex:/^[a-z0-9_]+$/'],
            'subskala.*.nama' => ['nullable', 'string', 'max:100'],
            'rentang' => ['array'],
            'rentang.*.subskala' => ['nullable', 'string', 'max:20'],
            'rentang.*.min' => ['nullable', 'numeric'],
            'rentang.*.max' => ['nullable', 'numeric'],
            'rentang.*.label' => ['nullable', 'string', 'max:100'],
            'rentang.*.saran' => ['nullable', 'string', 'max:500'],
            'butir' => ['array'],
            'butir.*.kunci' => ['nullable', 'string', 'max:40'],
            'butir.*.teks' => ['nullable', 'string', 'max:500'],
            'butir.*.subskala' => ['nullable', 'string', 'max:20'],
            'butir.*.terbalik' => ['nullable', 'boolean'],
            'butir.*.kritis_min' => ['nullable', 'numeric'],
            'butir.*.pilihan_khusus' => ['nullable', 'string', 'max:1000'],
        ], [
            'subskala.*.kunci.regex' => 'Kunci subskala hanya huruf kecil, angka, dan garis bawah (mis. d, a, s).',
        ]);

        $pilihan = $this->barisPilihan($data['pilihan'] ?? []);
        $subskala = [];

        foreach ($data['subskala'] ?? [] as $baris) {
            if (filled($baris['kunci'] ?? null) && filled($baris['nama'] ?? null)) {
                $subskala[$baris['kunci']] = trim($baris['nama']);
            }
        }

        $kunciSubskala = ['', ...array_keys($subskala)];
        $rentang = [];

        foreach ($data['rentang'] ?? [] as $baris) {
            if (blank($baris['label'] ?? null)) {
                continue;
            }

            $subskalaRentang = (string) ($baris['subskala'] ?? '');
            $this->tolakBila(! in_array($subskalaRentang, $kunciSubskala, true), 'rentang', "Rentang \"{$baris['label']}\" merujuk subskala \"{$subskalaRentang}\" yang tidak ada.");
            $this->tolakBila(! is_numeric($baris['min'] ?? null) || ! is_numeric($baris['max'] ?? null) || (float) $baris['min'] > (float) $baris['max'], 'rentang', "Batas rentang \"{$baris['label']}\" tidak sah (min harus ≤ maks).");

            $rentang[] = [
                'subskala' => $subskalaRentang,
                'min' => $baris['min'] + 0,
                'max' => $baris['max'] + 0,
                'label' => trim($baris['label']),
                'saran' => trim((string) ($baris['saran'] ?? '')),
            ];
        }

        $kunciLama = collect($instrumen?->butir ?? [])->pluck('kunci')->all();
        $butir = [];

        foreach ($data['butir'] ?? [] as $baris) {
            if (blank($baris['teks'] ?? null)) {
                continue;
            }

            $subskalaButir = (string) ($baris['subskala'] ?? '');
            $this->tolakBila(! in_array($subskalaButir, $kunciSubskala, true), 'butir', 'Butir "'.Str::limit($baris['teks'], 40)."\" merujuk subskala \"{$subskalaButir}\" yang tidak ada.");

            $pilihanKhusus = null;

            if (filled($baris['pilihan_khusus'] ?? null)) {
                $pilihanKhusus = $this->uraiPilihanKhusus($baris['pilihan_khusus']);
                $this->tolakBila(count($pilihanKhusus) < 2, 'butir', 'Pilihan khusus butir ditulis "Label=nilai; Label=nilai" dengan sedikitnya dua pilihan.');
            }

            $kunci = in_array($baris['kunci'] ?? null, $kunciLama, true) ? $baris['kunci'] : 'b'.Str::lower(Str::random(8));

            $butir[] = [
                'kunci' => $kunci,
                'teks' => trim($baris['teks']),
                'subskala' => $subskalaButir,
                'terbalik' => (bool) ($baris['terbalik'] ?? false),
                'kritis_min' => is_numeric($baris['kritis_min'] ?? null) ? $baris['kritis_min'] + 0 : null,
                'pilihan' => $pilihanKhusus,
            ];
        }

        $this->tolakBila(count($pilihan) < 2, 'pilihan', 'Isi sedikitnya dua pilihan jawaban.');
        $this->tolakBila($butir === [], 'butir', 'Isi sedikitnya satu butir pertanyaan.');

        return [
            'nama' => trim($data['nama']),
            'instruksi' => $data['instruksi'] ?? null,
            'sumber' => $data['sumber'] ?? null,
            'metode' => $data['metode'],
            'pengali' => $data['pengali'],
            'urutan' => $data['urutan'],
            'aktif' => (bool) $data['aktif'],
            'pilihan' => $pilihan,
            'subskala' => $subskala,
            'rentang' => $rentang,
            'butir' => $butir,
        ];
    }

    /**
     * @param  array<int, array{label?: string|null, nilai?: string|null}>  $baris
     * @return list<array{label: string, nilai: int|float}>
     */
    private function barisPilihan(array $baris): array
    {
        $hasil = [];

        foreach ($baris as $satu) {
            if (filled($satu['label'] ?? null) && is_numeric($satu['nilai'] ?? null)) {
                $hasil[] = ['label' => trim($satu['label']), 'nilai' => $satu['nilai'] + 0];
            }
        }

        return $hasil;
    }

    /**
     * "Sangat tinggi=100; Tinggi=75" → pilihan.
     *
     * @return list<array{label: string, nilai: int|float}>
     */
    private function uraiPilihanKhusus(string $teks): array
    {
        $hasil = [];

        foreach (explode(';', $teks) as $bagian) {
            [$label, $nilai] = array_pad(explode('=', $bagian, 2), 2, null);

            if (filled($label) && is_numeric(trim((string) $nilai))) {
                $hasil[] = ['label' => trim($label), 'nilai' => trim($nilai) + 0];
            }
        }

        return $hasil;
    }

    /**
     * Ubah satu instrumen kembali menjadi teks pilihan khusus untuk formulir.
     *
     * @param  list<array{label: string, nilai: int|float}>|null  $pilihan
     */
    public static function teksPilihanKhusus(?array $pilihan): string
    {
        return implode('; ', array_map(fn (array $satu): string => $satu['label'].'='.$satu['nilai'], $pilihan ?? []));
    }

    private function tolakBila(bool $syarat, string $bidang, string $pesan): void
    {
        if ($syarat) {
            throw ValidationException::withMessages([$bidang => $pesan]);
        }
    }

    private function kunciUnik(string $nama): string
    {
        $dasar = Str::slug($nama, '_') ?: 'instrumen';
        $kunci = $dasar;

        for ($urutan = 2; Instrumen::query()->where('kunci', $kunci)->exists(); $urutan++) {
            $kunci = $dasar.'_'.$urutan;
        }

        return $kunci;
    }
}
