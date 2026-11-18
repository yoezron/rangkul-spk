<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Services\PencatatAudit;
use App\Services\PengaturanRangkul;
use Illuminate\Console\Command;

/**
 * Membuang audit log yang lebih tua dari masa simpan di pengaturan.
 * Dijadwalkan harian di routes/console.php.
 */
class PangkasAuditLog extends Command
{
    /**
     * @var string
     */
    protected $signature = 'rangkul:pangkas-audit';

    /**
     * @var string
     */
    protected $description = 'Hapus audit log yang melewati masa simpan di pengaturan Rangkul.';

    public function handle(PengaturanRangkul $pengaturan, PencatatAudit $audit): int
    {
        $hari = (int) $pengaturan->get('audit.retensi_hari');

        if ($hari <= 0) {
            $this->info('Masa simpan audit log tidak dibatasi; tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        $terhapus = AuditLog::query()->where('dibuat_pada', '<', now()->subDays($hari))->delete();

        if ($terhapus > 0) {
            $audit->catat('audit.dipangkas', null, ['baris' => $terhapus, 'masa_simpan_hari' => $hari]);
        }

        $this->info("{$terhapus} baris audit log dihapus.");

        return self::SUCCESS;
    }
}
