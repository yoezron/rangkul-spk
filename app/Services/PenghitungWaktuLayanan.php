<?php

namespace App\Services;

use App\Enums\StatusPertemuan;
use App\Models\KehadiranPercakapan;
use App\Models\Permintaan;
use App\Models\Pertemuan;
use App\Models\Pesan;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Menghitung waktu layanan tiap konselor dalam satu bulan, dalam menit,
 * sebagai dasar pembayaran.
 *
 * Waktu PERCAKAPAN dihitung hanya bila ketiganya terpenuhi sekaligus:
 *  1. konselor membuka halaman percakapan kasus itu (KehadiranPercakapan);
 *  2. anggota juga sedang membukanya;
 *  3. waktu itu berada di antara dua pesan yang berjarak paling lama
 *     JEDA_MENIT menit. Jeda yang lebih panjang (tanpa balasan) tidak dihitung
 *     sama sekali.
 * Begitu salah satu pihak menutup halaman atau keluar, hitungan berhenti.
 *
 * Waktu PERTEMUAN = durasi pertemuan daring berstatus terjadwal yang waktunya
 * sudah lewat, untuk konselor yang mengusulkannya. Bila percakapan berjalan
 * bersamaan dengan pertemuan, waktunya tidak dihitung dua kali.
 *
 * Rentang yang melewati pergantian bulan dipotong pada batas bulan (WIB).
 * Menit dibulatkan per kasus, lalu dijumlahkan.
 */
class PenghitungWaktuLayanan
{
    public const JEDA_MENIT = 30;

    /**
     * @return list<array{konselor: User, menit_percakapan: int, menit_pertemuan: int, menit_total: int, kasus: list<array{permintaan: Permintaan, menit_percakapan: int, menit_pertemuan: int, menit_total: int}>}>
     */
    public function rekap(Carbon $bulan): array
    {
        $awal = $bulan->copy()->startOfMonth();
        $akhir = $awal->copy()->addMonth();
        $jendela = [[$awal->getTimestamp(), $akhir->getTimestamp()]];

        $kehadiran = KehadiranPercakapan::query()
            ->where('mulai_pada', '<', $akhir)
            ->where('terakhir_pada', '>', $awal)
            ->get()
            ->groupBy('permintaan_id');

        $pertemuan = Pertemuan::query()
            ->where('status', StatusPertemuan::Terjadwal->value)
            ->where('mulai_pada', '<', $akhir)
            ->where('mulai_pada', '>=', $awal->copy()->subDay())
            ->get()
            ->filter(fn (Pertemuan $satu): bool => $satu->mulai_pada->copy()->addMinutes($satu->durasi_menit)->lte(now()))
            ->groupBy('permintaan_id');

        $idKasus = $kehadiran->keys()->merge($pertemuan->keys())->unique()->values();

        if ($idKasus->isEmpty()) {
            return [];
        }

        $daftarPermintaan = Permintaan::query()->whereKey($idKasus)->get()->keyBy('id');
        $jeda = self::JEDA_MENIT * 60;

        $pesan = Pesan::query()
            ->whereIn('permintaan_id', $idKasus)
            ->where('jenis', 'pesan')
            ->where('created_at', '>=', $awal->copy()->subSeconds($jeda))
            ->where('created_at', '<', $akhir->copy()->addSeconds($jeda))
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'permintaan_id', 'created_at'])
            ->groupBy('permintaan_id');

        /** @var array<int, array<int, array{percakapan: list<array{int, int}>, pertemuan: list<array{int, int}>}>> $rentang konselor => kasus => rentang */
        $rentang = [];

        foreach ($idKasus as $idPermintaan) {
            $kehadiranKasus = $kehadiran->get($idPermintaan, collect());
            $hadirAnggota = self::rapikan($kehadiranKasus->where('sebagai', 'anggota')->map(self::rentangKehadiran(...))->values()->all());
            $aktif = self::rentangAktif($pesan->get($idPermintaan, collect()), $jeda);
            $bersama = self::iris(self::iris($hadirAnggota, $aktif), $jendela);

            foreach ($kehadiranKasus->where('sebagai', 'konselor')->groupBy('user_id') as $idKonselor => $hadirKonselor) {
                $rentang[$idKonselor][$idPermintaan]['percakapan'] = self::iris(
                    self::rapikan($hadirKonselor->map(self::rentangKehadiran(...))->values()->all()),
                    $bersama,
                );
            }

            foreach ($pertemuan->get($idPermintaan, collect()) as $satu) {
                $idKonselor = $satu->diusulkan_oleh ?? $daftarPermintaan->get($idPermintaan)?->konselor_id;

                if ($idKonselor === null) {
                    continue;
                }

                $mulai = $satu->mulai_pada->getTimestamp();
                $rentang[$idKonselor][$idPermintaan]['pertemuan'] = self::rapikan([
                    ...($rentang[$idKonselor][$idPermintaan]['pertemuan'] ?? []),
                    ...self::iris([[$mulai, $mulai + $satu->durasi_menit * 60]], $jendela),
                ]);
            }
        }

        $daftarKonselor = User::query()->whereKey(array_keys($rentang))->get()->keyBy('id');
        $hasil = [];

        foreach ($rentang as $idKonselor => $perKasus) {
            $konselor = $daftarKonselor->get($idKonselor);

            if ($konselor === null) {
                continue;
            }

            $kasus = [];

            foreach ($perKasus as $idPermintaan => $bagian) {
                $detikPertemuan = self::panjang($bagian['pertemuan'] ?? []);
                $detikTotal = self::panjang(self::rapikan([...($bagian['percakapan'] ?? []), ...($bagian['pertemuan'] ?? [])]));
                $menitPertemuan = (int) round($detikPertemuan / 60);
                $menitPercakapan = (int) round(($detikTotal - $detikPertemuan) / 60);

                if ($menitPertemuan + $menitPercakapan === 0) {
                    continue;
                }

                $kasus[] = [
                    'permintaan' => $daftarPermintaan->get($idPermintaan),
                    'menit_percakapan' => $menitPercakapan,
                    'menit_pertemuan' => $menitPertemuan,
                    'menit_total' => $menitPercakapan + $menitPertemuan,
                ];
            }

            usort($kasus, fn (array $kiri, array $kanan): int => strcmp((string) $kiri['permintaan']?->kode, (string) $kanan['permintaan']?->kode));

            $hasil[] = [
                'konselor' => $konselor,
                'menit_percakapan' => array_sum(array_column($kasus, 'menit_percakapan')),
                'menit_pertemuan' => array_sum(array_column($kasus, 'menit_pertemuan')),
                'menit_total' => array_sum(array_column($kasus, 'menit_total')),
                'kasus' => $kasus,
            ];
        }

        usort($hasil, fn (array $kiri, array $kanan): int => strcasecmp($kiri['konselor']->nama, $kanan['konselor']->nama));

        return $hasil;
    }

    /**
     * Rentang di antara dua pesan berurutan yang berjarak paling lama `$jeda` detik.
     *
     * @param  Collection<int, Pesan>  $pesan  urut menurut waktu
     * @return list<array{int, int}>
     */
    private static function rentangAktif(Collection $pesan, int $jeda): array
    {
        $waktu = $pesan->map(fn (Pesan $satu): int => $satu->created_at->getTimestamp())->values()->all();
        $rentang = [];

        for ($i = 1, $jumlah = count($waktu); $i < $jumlah; $i++) {
            if ($jeda >= $waktu[$i] - $waktu[$i - 1]) {
                $rentang[] = [$waktu[$i - 1], $waktu[$i]];
            }
        }

        return self::rapikan($rentang);
    }

    /**
     * @return array{int, int}
     */
    private static function rentangKehadiran(KehadiranPercakapan $kehadiran): array
    {
        return [$kehadiran->mulai_pada->getTimestamp(), $kehadiran->terakhir_pada->getTimestamp()];
    }

    /**
     * Urutkan dan gabungkan rentang yang bertumpuk atau bersambung.
     *
     * @param  list<array{int, int}>  $rentang
     * @return list<array{int, int}>
     */
    private static function rapikan(array $rentang): array
    {
        $rentang = array_values(array_filter($rentang, fn (array $satu): bool => $satu[1] > $satu[0]));
        usort($rentang, fn (array $kiri, array $kanan): int => $kiri[0] <=> $kanan[0]);

        $hasil = [];

        foreach ($rentang as [$mulai, $selesai]) {
            $terakhir = array_key_last($hasil);

            if ($terakhir !== null && $mulai <= $hasil[$terakhir][1]) {
                $hasil[$terakhir][1] = max($hasil[$terakhir][1], $selesai);
            } else {
                $hasil[] = [$mulai, $selesai];
            }
        }

        return $hasil;
    }

    /**
     * Irisan dua himpunan rentang yang sudah dirapikan.
     *
     * @param  list<array{int, int}>  $kiri
     * @param  list<array{int, int}>  $kanan
     * @return list<array{int, int}>
     */
    private static function iris(array $kiri, array $kanan): array
    {
        $hasil = [];
        $i = 0;
        $j = 0;

        while ($i < count($kiri) && $j < count($kanan)) {
            $mulai = max($kiri[$i][0], $kanan[$j][0]);
            $selesai = min($kiri[$i][1], $kanan[$j][1]);

            if ($selesai > $mulai) {
                $hasil[] = [$mulai, $selesai];
            }

            if ($kiri[$i][1] < $kanan[$j][1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $hasil;
    }

    /**
     * @param  list<array{int, int}>  $rentang
     */
    private static function panjang(array $rentang): int
    {
        return array_sum(array_map(fn (array $satu): int => $satu[1] - $satu[0], $rentang));
    }
}
