<?php

namespace App\Http\Controllers\Kasus;

use App\Http\Controllers\Controller;
use App\Models\Instrumen;
use App\Models\KirimanInstrumen;
use App\Models\Permintaan;
use App\Services\Asesmen\PenilaiAsesmen;
use App\Services\PemberitahuLayanan;
use App\Services\PengaturanRangkul;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Instrumen di tengah percakapan: konselor mengirim instrumen apa pun yang
 * disusun komite (aktif di asesmen dasar maupun tidak), anggota mengisinya,
 * lalu konselor membaca hasilnya dan melanjutkan percakapan.
 *
 * Skor dan tafsiran hanya terlihat konselor dan komite. Butir kritis
 * menandai permintaan DARURAT dan memberi tahu komite seketika, sama seperti
 * asesmen dasar.
 */
class KirimanInstrumenController extends Controller
{
    public function __construct(private readonly PemberitahuLayanan $pemberitahu) {}

    public function kirim(Request $request, Permintaan $permintaan): RedirectResponse
    {
        Gate::authorize('tangani', $permintaan);

        $data = $request->validate([
            'instrumen_id' => ['required', 'integer', Rule::exists('instrumen', 'id')],
            'pengantar' => ['nullable', 'string', 'max:1000'],
        ], ['instrumen_id.required' => 'Pilih instrumen yang akan dikirim.']);

        $instrumen = Instrumen::query()->findOrFail($data['instrumen_id']);

        if ($instrumen->butir === []) {
            return back()->withErrors(['instrumen_id' => "{$instrumen->nama} belum memiliki butir pertanyaan."]);
        }

        $masihMenunggu = $permintaan->kirimanInstrumen()
            ->where('instrumen_id', $instrumen->id)
            ->whereNull('diisi_pada')
            ->exists();

        if ($masihMenunggu) {
            return back()->withErrors(['instrumen_id' => "{$instrumen->nama} sudah dikirim dan belum diisi anggota."]);
        }

        $kiriman = $permintaan->kirimanInstrumen()->create([
            'instrumen_id' => $instrumen->id,
            'dikirim_oleh' => $request->user()->id,
            'nama_instrumen' => $instrumen->nama,
            'salinan' => $instrumen->salinan(),
            'pengantar' => filled($data['pengantar'] ?? null) ? trim($data['pengantar']) : null,
        ]);

        PercakapanController::sistem($permintaan, "Konselor meminta Anda mengisi kuesioner {$instrumen->nama}. Silakan buka bagian Kuesioner di halaman ini.");
        $this->pemberitahu->instrumenDikirim($kiriman);

        return redirect()->to(route('konseling.tampil', $permintaan).'#instrumen')->with('status', "{$instrumen->nama} dikirim ke anggota.");
    }

    public function isi(Request $request, Permintaan $permintaan, KirimanInstrumen $kiriman, PengaturanRangkul $pengaturan): View|RedirectResponse
    {
        $this->pastikanMilik($permintaan, $kiriman);
        Gate::authorize('milik', $permintaan);

        if ($kiriman->sudahDiisi() || ! $permintaan->status->bolehBercakap()) {
            return redirect()->route('permintaan.tampil', $permintaan)->with('status', $kiriman->sudahDiisi()
                ? 'Kuesioner ini sudah Anda isi. Terima kasih.'
                : 'Pendampingan sudah ditutup, sehingga kuesioner tidak dapat diisi lagi.');
        }

        return view('permintaan.instrumen', [
            'permintaan' => $permintaan,
            'kiriman' => $kiriman,
            'kontak' => $pengaturan->kontak(),
        ]);
    }

    public function simpan(Request $request, Permintaan $permintaan, KirimanInstrumen $kiriman, PenilaiAsesmen $penilai): RedirectResponse
    {
        $this->pastikanMilik($permintaan, $kiriman);
        Gate::authorize('milik', $permintaan);
        abort_unless($permintaan->status->bolehBercakap() && ! $kiriman->sudahDiisi(), 409);

        $salinan = $kiriman->salinan;
        $aturan = [];
        $pesan = [];

        foreach ($salinan['butir'] as $nomor => $butir) {
            $nilaiSah = array_map(fn (array $pilihan): string => (string) $pilihan['nilai'], PenilaiAsesmen::pilihanButir($salinan, $butir));
            $aturan["jawaban.{$butir['kunci']}"] = ['required', Rule::in($nilaiSah)];
            $pesan["jawaban.{$butir['kunci']}.required"] = 'Butir '.($nomor + 1).' belum dijawab.';
        }

        $data = $request->validate($aturan, $pesan);
        $jawaban = array_map(fn (string $nilai): int|float => $nilai + 0, $data['jawaban']);
        $hasil = $penilai->nilai($salinan, $jawaban);

        DB::transaction(function () use ($permintaan, $kiriman, $salinan, $jawaban, $hasil): void {
            $kiriman->update([
                'isian' => ['instrumen' => $salinan, 'jawaban' => $jawaban, ...$hasil],
                'kritis' => $hasil['kritis'] !== [],
                'diisi_pada' => now(),
            ]);

            foreach ($hasil['kritis'] as $butir) {
                $permintaan->tandaiDarurat('Kuesioner '.$kiriman->nama_instrumen.' (di tengah pendampingan) — '.$butir);
            }

            $permintaan->save();

            PercakapanController::sistem($permintaan, "Anggota sudah mengisi kuesioner {$kiriman->nama_instrumen}. Hasilnya dibaca konselor.");
        });

        $this->pemberitahu->instrumenDiisi($kiriman->setRelation('permintaan', $permintaan));

        return redirect()->to(route('permintaan.tampil', $permintaan).'#percakapan')
            ->with('status', 'Terima kasih, jawaban Anda sudah terkirim ke konselor.');
    }

    public function batal(Request $request, Permintaan $permintaan, KirimanInstrumen $kiriman): RedirectResponse
    {
        $this->pastikanMilik($permintaan, $kiriman);
        Gate::authorize('tangani', $permintaan);
        abort_if($kiriman->sudahDiisi(), 409);

        $kiriman->delete();
        PercakapanController::sistem($permintaan, "Konselor membatalkan permintaan mengisi kuesioner {$kiriman->nama_instrumen}.");

        return redirect()->to(route('konseling.tampil', $permintaan).'#instrumen')->with('status', 'Kiriman kuesioner dibatalkan.');
    }

    private function pastikanMilik(Permintaan $permintaan, KirimanInstrumen $kiriman): void
    {
        abort_unless($kiriman->permintaan_id === $permintaan->id, 404);
    }
}
