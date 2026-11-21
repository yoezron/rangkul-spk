<?php

namespace App\Console\Commands;

use App\Enums\StatusPertemuan;
use App\Models\Permintaan;
use App\Services\PemberitahuLayanan;
use Illuminate\Console\Command;

/**
 * Memberi tahu komite tentang pendampingan yang belum selesai tetapi tanpa
 * aktivitas lebih dari 48 jam, supaya dapat ditindaklanjuti atau ditutup.
 *
 * Satu notifikasi ringkas untuk semua kasus sekaligus. Kasus yang sudah
 * dilaporkan tidak dilaporkan lagi sampai ada aktivitas baru lalu kembali
 * diam 48 jam. Dijadwalkan tiap jam di routes/console.php.
 */
class IngatkanKasusMenggantung extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rangkul:ingatkan-kasus-menggantung';

    /**
     * @var string
     */
    protected $description = 'Beri tahu komite tentang kasus berjalan yang tanpa aktivitas lebih dari 48 jam.';

    public function handle(PemberitahuLayanan $pemberitahu): int
    {
        $daftar = Permintaan::query()
            ->menggantung()
            ->withMax('pesan as aktivitas_pesan_pada', 'created_at')
            ->withMax(['pertemuan as aktivitas_pertemuan_pada' => fn ($kueri) => $kueri->where('status', StatusPertemuan::Terjadwal->value)], 'mulai_pada')
            ->orderBy('id')
            ->get()
            ->filter(function (Permintaan $permintaan): bool {
                $aktivitas = $permintaan->aktivitasTerakhir();

                return $permintaan->diingatkan_menggantung_pada === null
                    || ($aktivitas !== null && $permintaan->diingatkan_menggantung_pada->lt($aktivitas));
            })
            ->values();

        if ($daftar->isNotEmpty()) {
            $pemberitahu->kasusMenggantung($daftar);
            Permintaan::query()->whereKey($daftar->modelKeys())->update(['diingatkan_menggantung_pada' => now()]);
        }

        $this->info("{$daftar->count()} kasus menggantung dilaporkan.");

        return self::SUCCESS;
    }
}
