<?php

namespace App\Services\Asesmen;

/**
 * Menghitung skor satu instrumen dari jawaban anggota.
 *
 * Instrumen diberikan dalam bentuk larik (salinan saat diisi), supaya hasil
 * lama tetap dapat dihitung ulang dan dibaca walaupun instrumennya kemudian
 * diubah komite.
 */
class PenilaiAsesmen
{
    /**
     * Pilihan jawaban yang berlaku untuk satu butir.
     *
     * @param  array<string, mixed>  $instrumen
     * @param  array<string, mixed>  $butir
     * @return list<array{label: string, nilai: int|float}>
     */
    public static function pilihanButir(array $instrumen, array $butir): array
    {
        return array_values($butir['pilihan'] ?? null ?: $instrumen['pilihan'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $instrumen
     * @param  array<string, int|float>  $jawaban  kunci butir => nilai pilihan
     * @return array{skor: list<array{subskala: string, nama: string, nilai: float, label: string, saran: string}>, kritis: list<string>}
     */
    public function nilai(array $instrumen, array $jawaban): array
    {
        $kelompok = [];
        $kritis = [];

        foreach ($instrumen['butir'] as $nomor => $butir) {
            if (! array_key_exists($butir['kunci'], $jawaban)) {
                continue;
            }

            $nilaiAsli = (float) $jawaban[$butir['kunci']];
            $nilaiPilihan = array_map(fn (array $pilihan): float => (float) $pilihan['nilai'], self::pilihanButir($instrumen, $butir));
            $nilai = ($butir['terbalik'] ?? false) && $nilaiPilihan !== []
                ? max($nilaiPilihan) + min($nilaiPilihan) - $nilaiAsli
                : $nilaiAsli;

            if (($butir['kritis_min'] ?? null) !== null && $nilaiAsli >= (float) $butir['kritis_min']) {
                $kritis[] = 'Butir '.($nomor + 1).': '.$butir['teks'];
            }

            $kelompok[(string) ($butir['subskala'] ?? '')][] = $nilai;
        }

        $skor = [];

        foreach ($kelompok as $subskala => $daftarNilai) {
            $dasar = ($instrumen['metode'] ?? 'jumlah') === 'rerata'
                ? array_sum($daftarNilai) / count($daftarNilai)
                : array_sum($daftarNilai);
            $nilai = round($dasar * (float) ($instrumen['pengali'] ?? 1), 2);
            $rentang = $this->cariRentang($instrumen['rentang'] ?? [], $subskala, $nilai);

            $skor[] = [
                'subskala' => $subskala,
                'nama' => $subskala === '' ? 'Skor total' : ($instrumen['subskala'][$subskala] ?? $subskala),
                'nilai' => $nilai,
                'label' => $rentang['label'] ?? '—',
                'saran' => $rentang['saran'] ?? '',
            ];
        }

        return ['skor' => $skor, 'kritis' => $kritis];
    }

    /**
     * @param  list<array<string, mixed>>  $daftarRentang
     * @return array<string, mixed>|null
     */
    private function cariRentang(array $daftarRentang, string $subskala, float $nilai): ?array
    {
        foreach ($daftarRentang as $rentang) {
            if ((string) ($rentang['subskala'] ?? '') === $subskala
                && $nilai >= (float) $rentang['min'] && $nilai <= (float) $rentang['max']) {
                return $rentang;
            }
        }

        return null;
    }
}
