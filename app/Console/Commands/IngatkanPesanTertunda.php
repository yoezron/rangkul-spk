<?php

namespace App\Console\Commands;

use App\Enums\StatusPermintaan;
use App\Models\Permintaan;
use App\Models\Pesan;
use App\Models\User;
use App\Services\PemberitahuLayanan;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Pengingat WhatsApp untuk pesan percakapan yang lebih dari 2 jam belum dibuka
 * maupun dibalas, ke arah mana pun (anggota → konselor, konselor → anggota).
 *
 * Supaya percakapan yang sedang berjalan tidak menghasilkan WhatsApp untuk
 * setiap pesan:
 *  - pesan yang sudah terbaca, atau sudah dibalas penerimanya, tidak dihitung;
 *  - satu pengingat untuk satu rangkaian pesan yang menunggu. Pesan yang
 *    menyusul tidak memicu pengingat baru sampai penerima membuka percakapan
 *    (pesan terbaca) atau membalas, lalu ada pesan baru yang kembali menunggu.
 *
 * Dijadwalkan tiap 10 menit di routes/console.php, jadi pengingat tiba antara
 * 2 jam dan 2 jam 10 menit setelah pesan pertama yang menunggu.
 */
class IngatkanPesanTertunda extends Command
{
    public const JAM_TUNGGU = 2;

    /**
     * @var string
     */
    protected $signature = 'rangkul:ingatkan-pesan-tertunda';

    /**
     * @var string
     */
    protected $description = 'Kirim pengingat WhatsApp untuk pesan percakapan yang lebih dari 2 jam belum dibuka atau dibalas.';

    public function handle(PemberitahuLayanan $pemberitahu): int
    {
        $batas = now()->subHours(self::JAM_TUNGGU);

        $daftar = Permintaan::query()
            ->whereIn('status', [StatusPermintaan::Berlangsung->value, StatusPermintaan::MenungguPenutupan->value])
            ->whereNotNull('konselor_id')
            ->whereHas('pesan', fn ($kueri) => $kueri
                ->where('jenis', 'pesan')
                ->whereNull('dibaca_pada')
                ->whereNull('diingatkan_whatsapp_pada')
                ->where('created_at', '<=', $batas))
            ->with('anggota', 'konselor')
            ->get();

        $terkirim = 0;

        foreach ($daftar as $permintaan) {
            foreach ([[$permintaan->anggota, $permintaan->konselor], [$permintaan->konselor, $permintaan->anggota]] as [$pengirim, $penerima]) {
                if (! $pengirim instanceof User || ! $penerima instanceof User) {
                    continue;
                }

                $menunggu = $this->pesanMenunggu($permintaan, $pengirim, $penerima, $batas);

                if ($menunggu->isEmpty()) {
                    continue;
                }

                Pesan::query()->whereKey($menunggu->modelKeys())->update(['diingatkan_whatsapp_pada' => now()]);
                $pemberitahu->pesanTertunda($permintaan, $penerima, self::JAM_TUNGGU);
                $terkirim++;
            }
        }

        $this->info("{$terkirim} pengingat WhatsApp dikirim.");

        return self::SUCCESS;
    }

    /**
     * Rangkaian pesan dari `$pengirim` yang belum dibaca dan belum dibalas
     * `$penerima`; kosong bila belum ada yang melewati batas atau rangkaian ini
     * sudah pernah diingatkan.
     *
     * @return Collection<int, Pesan>
     */
    private function pesanMenunggu(Permintaan $permintaan, User $pengirim, User $penerima, Carbon $batas): Collection
    {
        $balasanTerakhir = $permintaan->pesan()
            ->where('jenis', 'pesan')
            ->where('pengirim_id', $penerima->id)
            ->max('created_at');

        $menunggu = $permintaan->pesan()
            ->where('jenis', 'pesan')
            ->where('pengirim_id', $pengirim->id)
            ->whereNull('dibaca_pada')
            ->when($balasanTerakhir !== null, fn ($kueri) => $kueri->where('created_at', '>', $balasanTerakhir))
            ->get(['id', 'created_at', 'diingatkan_whatsapp_pada']);

        $sudahDiingatkan = $menunggu->contains(fn (Pesan $pesan): bool => $pesan->diingatkan_whatsapp_pada !== null);
        $lewatBatas = $menunggu->contains(fn (Pesan $pesan): bool => $pesan->created_at->lte($batas));

        return $sudahDiingatkan || ! $lewatBatas ? new Collection : $menunggu;
    }
}
