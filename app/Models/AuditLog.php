<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Satu catatan tindakan pengelola. Ditulis lewat App\Services\PencatatAudit.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $aktor
 * @property string $aksi
 * @property string|null $sasaran
 * @property array<string, mixed>|null $rincian
 * @property string|null $alamat_ip
 * @property Carbon $dibuat_pada
 */
#[Fillable(['user_id', 'aktor', 'aksi', 'sasaran', 'rincian', 'alamat_ip', 'dibuat_pada'])]
class AuditLog extends Model
{
    public const AKSI = [
        'pengaturan.diubah' => 'Pengaturan diubah',
        'surel.uji_dikirim' => 'Surel uji dikirim',
        'whatsapp.uji_dikirim' => 'WhatsApp uji dikirim',
        'peran.dibuat' => 'Peran dibuat',
        'peran.diubah' => 'Peran diubah',
        'peran.dihapus' => 'Peran dihapus',
        'penugasan.diberikan' => 'Peran ditugaskan',
        'penugasan.dicabut' => 'Peran dicabut',
        'pengguna.ditambahkan' => 'Anggota ditambahkan dari spk.or.id',
        'rekap.diunduh' => 'Rekap diunduh',
        'audit.dipangkas' => 'Audit log dipangkas',
    ];

    public $timestamps = false;

    protected $table = 'audit_log';

    public function labelAksi(): string
    {
        return self::AKSI[$this->aksi] ?? $this->aksi;
    }

    /**
     * Rincian dalam satu baris teks, mis. `kunci: layanan.buka, layanan.pesan_tutup`.
     */
    public function ringkasanRincian(): string
    {
        $teks = fn (mixed $nilai): string => match (true) {
            is_bool($nilai) => $nilai ? 'ya' : 'tidak',
            is_array($nilai) => implode(', ', array_map(fn (mixed $satu): string => is_scalar($satu) ? (string) $satu : '…', $nilai)),
            is_scalar($nilai) => (string) $nilai,
            default => '',
        };

        return collect($this->rincian ?? [])
            ->map(fn (mixed $nilai, string $kunci): string => $kunci.': '.$teks($nilai))
            ->implode('; ');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rincian' => 'array',
            'dibuat_pada' => 'datetime',
        ];
    }
}
