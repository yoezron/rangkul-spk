<?php

namespace App\Services;

use App\Models\KehadiranPercakapan;
use App\Models\Permintaan;
use App\Models\User;

/**
 * Mencatat kehadiran peserta di halaman percakapan dari polling halaman
 * (tiap 10 detik; di tab latar belakang peramban memperlambatnya sampai
 * sekitar 1 menit).
 *
 * Detak yang datang kurang dari JEDA_DETIK setelah detak sebelumnya
 * memperpanjang rentang yang sama; selebihnya dianggap peserta sempat keluar
 * dan rentang baru dimulai. Supaya tidak menulis ke basis data tiap 10 detik,
 * rentang baru diperpanjang bila sudah lewat PERBARUI_DETIK.
 */
class PencatatKehadiran
{
    public const JEDA_DETIK = 120;

    public const PERBARUI_DETIK = 20;

    public function detak(Permintaan $permintaan, User $pengguna, string $sebagai): void
    {
        $sekarang = now();

        $terakhir = KehadiranPercakapan::query()
            ->where('permintaan_id', $permintaan->id)
            ->where('user_id', $pengguna->id)
            ->latest('terakhir_pada')
            ->first();

        if ($terakhir !== null && $terakhir->sebagai === $sebagai && $terakhir->terakhir_pada->gte($sekarang->copy()->subSeconds(self::JEDA_DETIK))) {
            if ($terakhir->terakhir_pada->lte($sekarang->copy()->subSeconds(self::PERBARUI_DETIK))) {
                $terakhir->update(['terakhir_pada' => $sekarang]);
            }

            return;
        }

        KehadiranPercakapan::query()->create([
            'permintaan_id' => $permintaan->id,
            'user_id' => $pengguna->id,
            'sebagai' => $sebagai,
            'mulai_pada' => $sekarang,
            'terakhir_pada' => $sekarang,
        ]);
    }
}
