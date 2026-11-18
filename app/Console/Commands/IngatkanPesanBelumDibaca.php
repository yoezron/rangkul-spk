<?php

namespace App\Console\Commands;

use App\Enums\StatusPermintaan;
use App\Models\Permintaan;
use App\Models\Pesan;
use App\Models\User;
use App\Services\PemberitahuLayanan;
use Illuminate\Console\Command;

/**
 * Mengingatkan penerima pesan yang belum dibaca lebih dari 24 jam.
 *
 * Satu pengingat per percakapan per penerima, sekali saja untuk setiap pesan.
 * Bila yang belum membaca adalah konselor, komite ikut diberi tahu.
 * Dijadwalkan tiap jam di routes/console.php.
 */
class IngatkanPesanBelumDibaca extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rangkul:ingatkan-pesan';

    /**
     * @var string
     */
    protected $description = 'Kirim pengingat untuk pesan percakapan yang belum dibaca lebih dari 24 jam.';

    public function handle(PemberitahuLayanan $pemberitahu): int
    {
        $pesan = Pesan::query()
            ->where('jenis', 'pesan')
            ->whereNull('dibaca_pada')
            ->whereNull('diingatkan_pada')
            ->where('created_at', '<=', now()->subHours(24))
            ->whereHas('permintaan', fn ($kueri) => $kueri->whereIn('status', [StatusPermintaan::Berlangsung->value, StatusPermintaan::MenungguPenutupan->value]))
            ->with('permintaan.anggota', 'permintaan.konselor')
            ->get();

        $terkirim = 0;

        foreach ($pesan->groupBy(fn (Pesan $satu): string => $satu->permintaan_id.':'.$satu->pengirim_id) as $kelompok) {
            /** @var Permintaan $permintaan */
            $permintaan = $kelompok->first()->permintaan;
            $penerima = $kelompok->first()->pengirim_id === $permintaan->user_id ? $permintaan->konselor : $permintaan->anggota;

            if ($penerima instanceof User) {
                $pemberitahu->pesanBelumDibaca($permintaan, $penerima);
                $terkirim++;
            }

            Pesan::query()->whereKey($kelompok->modelKeys())->update(['diingatkan_pada' => now()]);
        }

        $this->info("{$terkirim} pengingat dikirim.");

        return self::SUCCESS;
    }
}
