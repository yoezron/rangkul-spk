<?php

namespace App\Http\Controllers\Anggota;

use App\Enums\StatusPermintaan;
use App\Enums\StatusPertemuan;
use App\Http\Controllers\Controller;
use App\Models\Instrumen;
use App\Models\JawabanAsesmen;
use App\Models\Permintaan;
use App\Services\Asesmen\PenilaiAsesmen;
use App\Services\PemberitahuLayanan;
use App\Services\PengaturanRangkul;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Pengajuan permintaan layanan oleh anggota, bertahap dan tersimpan sebagai draf.
 *
 * Urutan langkah (rekomendasi untuk anggota yang sedang tertekan):
 *  1. persetujuan & pertanyaan keselamatan — kontak krisis langsung tampil bila perlu;
 *  2. pilihan topik — ringan, membantu anggota mulai bercerita;
 *  3. cerita bebas — selagi topiknya masih segar;
 *  4. asesmen dasar;
 *  5. preferensi layanan dan notifikasi — urusan teknis di akhir;
 *  6. tinjau & kirim.
 *
 * Draf hanya terlihat oleh pemiliknya; komite baru melihat setelah dikirim.
 */
class PermintaanController extends Controller
{
    public function __construct(private readonly PengaturanRangkul $pengaturan) {}

    public function index(Request $request): View
    {
        $daftar = $request->user()->permintaan()->latest('id')->get();

        return view('permintaan.index', [
            'daftar' => $daftar,
            'terbuka' => $daftar->first(fn (Permintaan $permintaan): bool => $permintaan->status->terbuka()),
            'layananBuka' => $this->pengaturan->layananBuka(),
            'pesanTutup' => (string) $this->pengaturan->get('layanan.pesan_tutup'),
            'kontak' => $this->pengaturan->kontak(),
        ]);
    }

    public function mulai(Request $request): RedirectResponse
    {
        $terbuka = $request->user()->permintaan()
            ->whereNotIn('status', [StatusPermintaan::Selesai->value, StatusPermintaan::Ditolak->value, StatusPermintaan::Dibatalkan->value])
            ->latest('id')
            ->first();

        if ($terbuka !== null) {
            return $terbuka->status === StatusPermintaan::Draf
                ? redirect()->route('permintaan.langkah', [$terbuka, $terbuka->langkah])
                : redirect()->route('permintaan.tampil', $terbuka)->with('status', 'Anda masih punya permintaan yang sedang berjalan.');
        }

        if (! $this->pengaturan->layananBuka()) {
            return back()->withErrors(['layanan' => (string) $this->pengaturan->get('layanan.pesan_tutup')]);
        }

        $permintaan = $request->user()->permintaan()->create(['status' => StatusPermintaan::Draf, 'langkah' => 1]);

        return redirect()->route('permintaan.langkah', [$permintaan, 1]);
    }

    public function langkah(Request $request, Permintaan $permintaan, int $langkah): View|RedirectResponse
    {
        Gate::authorize('isiFormulir', $permintaan);

        if (! array_key_exists($langkah, Permintaan::LANGKAH) || $langkah > $permintaan->langkah) {
            return redirect()->route('permintaan.langkah', [$permintaan, $permintaan->langkah]);
        }

        $instrumen = Instrumen::query()->dipakai()->get();

        if ($langkah === 4 && $instrumen->isEmpty()) {
            return redirect()->route('permintaan.langkah', [$permintaan, 5]);
        }

        return view('permintaan.langkah', [
            'permintaan' => $permintaan,
            'langkah' => $langkah,
            'instrumen' => $instrumen,
            'jawabanTersimpan' => $permintaan->jawabanAsesmen->mapWithKeys(fn (JawabanAsesmen $jawaban): array => [$jawaban->instrumen_id => $jawaban->isian['jawaban'] ?? []])->all(),
            'pengguna' => $request->user(),
            'kontak' => $this->pengaturan->kontak(),
            'adaAsesmen' => $instrumen->isNotEmpty(),
        ]);
    }

    public function simpanLangkah(Request $request, Permintaan $permintaan, int $langkah, PenilaiAsesmen $penilai): RedirectResponse
    {
        Gate::authorize('isiFormulir', $permintaan);
        abort_unless(array_key_exists($langkah, Permintaan::LANGKAH) && $langkah < 6 && $langkah <= $permintaan->langkah, 404);

        $instrumen = Instrumen::query()->dipakai()->get();

        match ($langkah) {
            1 => $this->simpanPersetujuan($request, $permintaan),
            2 => $this->simpanTopik($request, $permintaan),
            3 => $this->simpanCerita($request, $permintaan),
            4 => $this->simpanAsesmen($request, $permintaan, $instrumen, $penilai),
            5 => $this->simpanPreferensi($request, $permintaan),
        };

        $berikut = $langkah + 1 === 4 && $instrumen->isEmpty() ? 5 : $langkah + 1;
        $permintaan->langkah = max($permintaan->langkah, $berikut);
        $this->hitungUlangDarurat($permintaan);
        $permintaan->save();

        return redirect()->route('permintaan.langkah', [$permintaan, $berikut]);
    }

    public function kirim(Request $request, Permintaan $permintaan, PemberitahuLayanan $pemberitahu): RedirectResponse
    {
        Gate::authorize('isiFormulir', $permintaan);

        if (! $this->pengaturan->layananBuka()) {
            return back()->withErrors(['layanan' => (string) $this->pengaturan->get('layanan.pesan_tutup')]);
        }

        $belum = $this->langkahBelumLengkap($permintaan);

        if ($belum !== null) {
            return redirect()->route('permintaan.langkah', [$permintaan, $belum])
                ->withErrors(['langkah' => 'Bagian ini belum lengkap. Silakan lengkapi sebelum mengirim.']);
        }

        DB::transaction(function () use ($permintaan): void {
            $this->hitungUlangDarurat($permintaan);
            $permintaan->status = StatusPermintaan::Diajukan;
            $permintaan->diajukan_pada = now();
            $permintaan->save();
            $permintaan->forceFill(['kode' => $permintaan->buatKode()])->save();
        });

        $pemberitahu->permintaanMasuk($permintaan->refresh());

        return redirect()->route('permintaan.tampil', $permintaan)->with('baru_diajukan', true);
    }

    public function tampil(Request $request, Permintaan $permintaan): View|RedirectResponse
    {
        Gate::authorize('milik', $permintaan);

        if ($permintaan->status === StatusPermintaan::Draf) {
            return redirect()->route('permintaan.langkah', [$permintaan, $permintaan->langkah]);
        }

        $permintaan->load(['konselor', 'pertemuan', 'jawabanAsesmen', 'kirimanInstrumen']);

        return view('permintaan.tampil', [
            'permintaan' => $permintaan,
            'adaPermintaanLain' => $request->user()->permintaan()
                ->whereKeyNot($permintaan->id)
                ->whereNotIn('status', [StatusPermintaan::Draf->value, StatusPermintaan::Selesai->value, StatusPermintaan::Ditolak->value, StatusPermintaan::Dibatalkan->value])
                ->exists(),
            'kontak' => $this->pengaturan->kontak(),
            'baruDiajukan' => $request->session()->get('baru_diajukan') === true,
            'pertemuanTerjadwal' => $permintaan->pertemuan->filter(fn ($pertemuan): bool => $pertemuan->status === StatusPertemuan::Terjadwal),
        ]);
    }

    public function batalkan(Request $request, Permintaan $permintaan): RedirectResponse
    {
        Gate::authorize('batalkan', $permintaan);

        if ($permintaan->status === StatusPermintaan::Draf) {
            $permintaan->delete();

            return redirect()->route('permintaan.index')->with('status', 'Draf permintaan dihapus.');
        }

        $permintaan->update(['status' => StatusPermintaan::Dibatalkan]);

        return redirect()->route('permintaan.tampil', $permintaan)->with('status', 'Permintaan dibatalkan. Anda dapat mengajukan permintaan baru kapan saja.');
    }

    private function simpanPersetujuan(Request $request, Permintaan $permintaan): void
    {
        $aturan = [
            'persetujuan_layanan' => ['accepted'],
            'persetujuan_data' => ['accepted'],
        ];

        foreach (Permintaan::KESELAMATAN as $kunci => $pertanyaan) {
            $aturan["keselamatan.{$kunci}"] = ['required', Rule::in(array_keys($pertanyaan['pilihan']))];
        }

        $data = $request->validate($aturan, [
            'persetujuan_layanan.accepted' => 'Mohon centang pernyataan bahwa Anda memahami sifat layanan ini.',
            'persetujuan_data.accepted' => 'Mohon centang persetujuan tentang siapa yang dapat membaca isian Anda.',
            'keselamatan.*.required' => 'Mohon jawab setiap pertanyaan keselamatan.',
        ]);

        $permintaan->persetujuan_pada ??= now();
        $permintaan->keselamatan = $data['keselamatan'];
    }

    private function simpanTopik(Request $request, Permintaan $permintaan): void
    {
        $data = $request->validate([
            'topik' => ['required', 'array', 'min:1'],
            'topik.*' => [Rule::in(array_keys(Permintaan::TOPIK))],
            'topik_lainnya' => ['nullable', 'string', 'max:500'],
        ], ['topik.required' => 'Pilih sedikitnya satu hal yang ingin didiskusikan.']);

        $permintaan->topik = array_values(array_unique($data['topik']));
        $permintaan->topik_lainnya = filled($data['topik_lainnya'] ?? null) ? trim($data['topik_lainnya']) : null;
    }

    private function simpanCerita(Request $request, Permintaan $permintaan): void
    {
        $data = $request->validate([
            'uraian' => ['required', 'string', 'min:20', 'max:10000'],
            'harapan' => ['nullable', 'string', 'max:3000'],
        ], [
            'uraian.required' => 'Ceritakan sedikit apa yang Anda alami atau rasakan.',
            'uraian.min' => 'Ceritakan sedikit lebih banyak (sedikitnya 20 karakter) agar konselor dapat memahami keadaan Anda.',
        ]);

        $permintaan->uraian = trim($data['uraian']);
        $permintaan->harapan = filled($data['harapan'] ?? null) ? trim($data['harapan']) : null;
    }

    /**
     * @param  Collection<int, Instrumen>  $daftarInstrumen
     */
    private function simpanAsesmen(Request $request, Permintaan $permintaan, Collection $daftarInstrumen, PenilaiAsesmen $penilai): void
    {
        $aturan = [];
        $pesan = [];

        foreach ($daftarInstrumen as $instrumen) {
            foreach ($instrumen->butir as $nomor => $butir) {
                $nilaiSah = array_map(fn (array $pilihan): string => (string) $pilihan['nilai'], PenilaiAsesmen::pilihanButir($instrumen->salinan(), $butir));
                $aturan["jawaban.{$instrumen->id}.{$butir['kunci']}"] = ['required', Rule::in($nilaiSah)];
                $pesan["jawaban.{$instrumen->id}.{$butir['kunci']}.required"] = "{$instrumen->nama}: butir ".($nomor + 1).' belum dijawab.';
            }
        }

        $data = $request->validate($aturan, $pesan);

        DB::transaction(function () use ($permintaan, $daftarInstrumen, $data, $penilai): void {
            $permintaan->jawabanAsesmen()->delete();

            foreach ($daftarInstrumen as $instrumen) {
                $salinan = $instrumen->salinan();
                $jawaban = array_map(fn (string $nilai): int|float => $nilai + 0, $data['jawaban'][$instrumen->id]);
                $hasil = $penilai->nilai($salinan, $jawaban);

                $permintaan->jawabanAsesmen()->create([
                    'instrumen_id' => $instrumen->id,
                    'nama_instrumen' => $instrumen->nama,
                    'isian' => ['instrumen' => $salinan, 'jawaban' => $jawaban, ...$hasil],
                    'kritis' => $hasil['kritis'] !== [],
                ]);
            }
        });

        $permintaan->unsetRelation('jawabanAsesmen');
    }

    private function simpanPreferensi(Request $request, Permintaan $permintaan): void
    {
        $data = $request->validate([
            'bentuk_layanan' => ['required', Rule::in(array_keys(Permintaan::BENTUK_LAYANAN))],
            'waktu_luang' => ['nullable', 'string', 'max:500'],
            'hindari_kampus_sama' => ['required', 'boolean'],
            'gender_konselor' => ['required', Rule::in(array_keys(Permintaan::GENDER_KONSELOR))],
            'notif_surel' => ['required', 'boolean'],
            'notif_whatsapp' => ['required', 'boolean'],
            'nomor_whatsapp' => ['nullable', 'string', 'regex:/^\+?[0-9 ()-]{8,20}$/'],
        ], [
            'nomor_whatsapp.regex' => 'Nomor WhatsApp hanya boleh berisi angka, spasi, tanda kurung, tanda hubung, dan + di depan.',
        ]);

        $permintaan->fill([
            'bentuk_layanan' => $data['bentuk_layanan'],
            'waktu_luang' => filled($data['waktu_luang'] ?? null) ? trim($data['waktu_luang']) : null,
            'hindari_kampus_sama' => (bool) $data['hindari_kampus_sama'],
            'gender_konselor' => $data['gender_konselor'],
        ]);

        $request->user()->update([
            'notif_surel' => (bool) $data['notif_surel'],
            'notif_whatsapp' => (bool) $data['notif_whatsapp'],
            'nomor_whatsapp' => filled($data['nomor_whatsapp'] ?? null) ? trim($data['nomor_whatsapp']) : null,
        ]);
    }

    /**
     * Susun ulang tanda DARURAT dari jawaban keselamatan dan butir kritis asesmen.
     */
    private function hitungUlangDarurat(Permintaan $permintaan): void
    {
        $permintaan->darurat = false;
        $permintaan->alasan_darurat = null;

        foreach (Permintaan::KESELAMATAN as $kunci => $pertanyaan) {
            $jawaban = $permintaan->keselamatan[$kunci] ?? 'tidak';

            if ($jawaban !== 'tidak') {
                $permintaan->tandaiDarurat('Pertanyaan keselamatan: '.$pertanyaan['teks'].' — '.($pertanyaan['pilihan'][$jawaban] ?? $jawaban));
            }
        }

        foreach ($permintaan->jawabanAsesmen()->where('kritis', true)->get() as $jawaban) {
            foreach ($jawaban->isian['kritis'] ?? [] as $butir) {
                $permintaan->tandaiDarurat('Asesmen '.$jawaban->nama_instrumen.' — '.$butir);
            }
        }
    }

    private function langkahBelumLengkap(Permintaan $permintaan): ?int
    {
        if ($permintaan->persetujuan_pada === null || $permintaan->keselamatan === null) {
            return 1;
        }

        if (blank($permintaan->topik)) {
            return 2;
        }

        if (blank($permintaan->uraian)) {
            return 3;
        }

        $wajib = Instrumen::query()->dipakai()->pluck('id');
        $terisi = $permintaan->jawabanAsesmen()->pluck('instrumen_id');

        if ($wajib->diff($terisi)->isNotEmpty()) {
            return 4;
        }

        return $permintaan->bentuk_layanan === null ? 5 : null;
    }
}
