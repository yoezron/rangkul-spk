<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Menulis audit log tindakan pengelola.
 *
 * Yang dicatat hanya tindakan di dashboard (mengubah pengaturan, menugaskan
 * peran, mengunduh rekap). Kunjungan dan kegiatan anggota tidak dicatat di
 * sini. Nilai rahasia tidak pernah masuk `rincian` — hanya nama kuncinya.
 */
class PencatatAudit
{
    public function __construct(private readonly PengaturanRangkul $pengaturan) {}

    /**
     * Cara menyebut seorang pengguna di audit log.
     */
    public static function sebut(User $pengguna): string
    {
        return $pengguna->nama.' (SPK #'.$pengguna->spk_id.')';
    }

    /**
     * @param  string  $aksi  salah satu kunci AuditLog::AKSI
     * @param  array<string, mixed>  $rincian
     */
    public function catat(string $aksi, ?string $sasaran = null, array $rincian = []): AuditLog
    {
        $aktor = Auth::user();

        return AuditLog::query()->create([
            'user_id' => $aktor?->getKey(),
            'aktor' => $aktor instanceof User ? self::sebut($aktor) : 'Sistem',
            'aksi' => $aksi,
            'sasaran' => $sasaran,
            'rincian' => $rincian === [] ? null : $rincian,
            'alamat_ip' => $aktor !== null && $this->pengaturan->get('audit.catat_ip') ? request()->ip() : null,
            'dibuat_pada' => now(),
        ]);
    }
}
