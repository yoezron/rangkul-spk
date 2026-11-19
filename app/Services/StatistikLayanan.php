<?php

namespace App\Services;

use App\Enums\StatusPermintaan;
use App\Models\Instrumen;
use App\Models\JawabanAsesmen;
use App\Models\Permintaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Angka gabungan untuk grafik di Ringkasan. Tidak ada data per orang
 * (kecuali nama konselor pada beban kasus), hanya hitungan.
 *
 * Perhitungan dilakukan di PHP, bukan SQL, karena topik dan hasil asesmen
 * disimpan terenkripsi dan karena fungsi tanggal berbeda antara SQLite
 * (lokal/uji) dan MySQL (produksi). Jumlah permintaan layanan ini kecil,
 * jadi biayanya wajar.
 */
class StatistikLayanan
{
    /**
     * Permintaan masuk dan selesai per bulan, 12 bulan terakhir (termasuk bulan ini).
     *
     * @return list<array{bulan: string, label: string, masuk: int, selesai: int}>
     */
    public function trenBulanan(int $jumlahBulan = 12): array
    {
        $awal = now()->startOfMonth()->subMonths($jumlahBulan - 1);
        $bulan = [];

        for ($indeks = 0; $indeks < $jumlahBulan; $indeks++) {
            $tanggal = $awal->copy()->addMonths($indeks);
            $bulan[$tanggal->format('Y-m')] = [
                'bulan' => $tanggal->translatedFormat('F Y'),
                'label' => $tanggal->month === 1 || $indeks === 0 ? $tanggal->translatedFormat('M y') : $tanggal->translatedFormat('M'),
                'masuk' => 0,
                'selesai' => 0,
            ];
        }

        Permintaan::query()
            ->where('status', '!=', StatusPermintaan::Draf->value)
            ->where(fn ($kueri) => $kueri->where('diajukan_pada', '>=', $awal)->orWhere('selesai_pada', '>=', $awal))
            ->get(['diajukan_pada', 'selesai_pada'])
            ->each(function (Permintaan $permintaan) use (&$bulan): void {
                $masuk = $permintaan->diajukan_pada?->format('Y-m');
                $selesai = $permintaan->selesai_pada?->format('Y-m');

                if ($masuk !== null && isset($bulan[$masuk])) {
                    $bulan[$masuk]['masuk']++;
                }

                if ($selesai !== null && isset($bulan[$selesai])) {
                    $bulan[$selesai]['selesai']++;
                }
            });

        return array_values($bulan);
    }

    /**
     * Jumlah kasus terbuka per tahap, urut sesuai alur.
     *
     * @return list<array{label: string, nilai: int}>
     */
    public function alurTerbuka(): array
    {
        $jumlah = Permintaan::query()
            ->whereIn('status', array_map(fn (StatusPermintaan $status): string => $status->value, $this->tahapTerbuka()))
            ->selectRaw('status, COUNT(*) AS jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status');

        return array_map(fn (StatusPermintaan $status): array => [
            'label' => $status->label(),
            'nilai' => (int) ($jumlah[$status->value] ?? 0),
        ], $this->tahapTerbuka());
    }

    /**
     * Topik yang dipilih anggota, 12 bulan terakhir, dari yang terbanyak.
     *
     * @return array{baris: list<array{label: string, nilai: int}>, total: int}
     */
    public function topik(): array
    {
        $hitung = array_fill_keys(array_keys(Permintaan::TOPIK), 0);
        $total = 0;

        foreach ($this->permintaanSetahun()->get(['id', 'topik']) as $permintaan) {
            $total++;

            foreach (array_unique($permintaan->topik ?? []) as $kunci) {
                if (array_key_exists($kunci, $hitung)) {
                    $hitung[$kunci]++;
                }
            }
        }

        arsort($hitung);

        $baris = [];

        foreach ($hitung as $kunci => $nilai) {
            $baris[] = ['label' => Permintaan::TOPIK[$kunci], 'nilai' => $nilai];
        }

        return ['baris' => $baris, 'total' => $total];
    }

    /**
     * Sebaran tingkat skor asesmen saat pengajuan, 12 bulan terakhir, untuk
     * instrumen yang sedang dipakai. Urutan tingkat mengikuti rentang instrumen
     * (ringan → berat).
     *
     * @return list<array{judul: string, total: int, bagian: list<array{label: string, nilai: int}>}>
     */
    public function tingkatGejala(): array
    {
        $instrumen = Instrumen::query()->dipakai()->get()->keyBy('kunci');

        if ($instrumen->isEmpty()) {
            return [];
        }

        $hitung = [];

        $jawaban = JawabanAsesmen::query()
            ->whereIn('permintaan_id', $this->permintaanSetahun()->select('id'))
            ->get(['id', 'isian']);

        foreach ($jawaban as $satu) {
            $kunci = $satu->isian['instrumen']['kunci'] ?? null;

            if (! $instrumen->has($kunci)) {
                continue;
            }

            foreach ($satu->isian['skor'] ?? [] as $skor) {
                $hitung[$kunci][(string) $skor['subskala']][(string) $skor['label']] = ($hitung[$kunci][(string) $skor['subskala']][(string) $skor['label']] ?? 0) + 1;
            }
        }

        $hasil = [];

        foreach ($instrumen as $kunci => $satu) {
            $daftarSubskala = $satu->subskala === [] ? ['' => null] : $satu->subskala;

            foreach ($daftarSubskala as $subskala => $namaSubskala) {
                $urutan = collect($satu->rentang)->where('subskala', (string) $subskala)->sortBy('min')->pluck('label')->unique()->values()->all();
                $terhitung = $hitung[$kunci][(string) $subskala] ?? [];
                $bagian = [];

                foreach ([...$urutan, ...array_diff(array_keys($terhitung), $urutan)] as $label) {
                    $bagian[] = ['label' => $label, 'nilai' => (int) ($terhitung[$label] ?? 0)];
                }

                $hasil[] = [
                    'judul' => $satu->nama.($namaSubskala ? ' — '.$namaSubskala : ''),
                    'total' => array_sum($terhitung),
                    'bagian' => array_slice($bagian, 0, 6),
                ];
            }
        }

        return $hasil;
    }

    /**
     * Median jam dari pengajuan sampai konselor ditugaskan, 90 hari terakhir.
     */
    public function medianJamTunggu(int $hari = 90): ?float
    {
        $selisih = Permintaan::query()
            ->whereNotNull('ditugaskan_pada')
            ->whereNotNull('diajukan_pada')
            ->where('diajukan_pada', '>=', now()->subDays($hari))
            ->get(['diajukan_pada', 'ditugaskan_pada'])
            ->map(fn (Permintaan $permintaan): float => max(0, $permintaan->diajukan_pada->diffInMinutes($permintaan->ditugaskan_pada)) / 60)
            ->sort()
            ->values();

        if ($selisih->isEmpty()) {
            return null;
        }

        $tengah = intdiv($selisih->count(), 2);

        return $selisih->count() % 2 === 1 ? $selisih[$tengah] : ($selisih[$tengah - 1] + $selisih[$tengah]) / 2;
    }

    /**
     * Kasus aktif per konselor, dari yang terbanyak.
     *
     * @return list<array{label: string, nilai: int}>
     */
    public function bebanKonselor(): array
    {
        $jumlah = Permintaan::query()
            ->whereIn('status', [StatusPermintaan::Berlangsung->value, StatusPermintaan::MenungguPenutupan->value])
            ->whereNotNull('konselor_id')
            ->selectRaw('konselor_id, COUNT(*) AS jumlah')
            ->groupBy('konselor_id')
            ->pluck('jumlah', 'konselor_id');

        $nama = User::query()->whereKey($jumlah->keys())->pluck('nama', 'id');

        return $jumlah
            ->map(fn ($nilai, $id): array => ['label' => (string) ($nama[$id] ?? 'Konselor'), 'nilai' => (int) $nilai])
            ->sortByDesc('nilai')
            ->values()
            ->all();
    }

    /**
     * Anggota terdaftar per wilayah, dari yang terbanyak.
     *
     * @return list<array{label: string, nilai: int}>
     */
    public function anggotaPerWilayah(int $batas = 8): array
    {
        /** @var Collection<int, object{wilayah: string|null, jumlah: int}> $baris */
        $baris = User::query()
            ->selectRaw('wilayah, COUNT(*) AS jumlah')
            ->groupBy('wilayah')
            ->orderByDesc('jumlah')
            ->get();

        $hasil = $baris->take($batas)->map(fn ($satu): array => ['label' => $satu->wilayah ?: '(tidak diisi)', 'nilai' => (int) $satu->jumlah])->all();
        $sisa = (int) $baris->skip($batas)->sum('jumlah');

        if ($sisa > 0) {
            $hasil[] = ['label' => 'Wilayah lain', 'nilai' => $sisa];
        }

        return array_values($hasil);
    }

    /**
     * @return list<StatusPermintaan>
     */
    private function tahapTerbuka(): array
    {
        return [StatusPermintaan::Diajukan, StatusPermintaan::Ditinjau, StatusPermintaan::Berlangsung, StatusPermintaan::MenungguPenutupan];
    }

    /**
     * @return Builder<Permintaan>
     */
    private function permintaanSetahun()
    {
        return Permintaan::query()
            ->where('status', '!=', StatusPermintaan::Draf->value)
            ->where('diajukan_pada', '>=', Carbon::now()->startOfMonth()->subMonths(11));
    }
}
