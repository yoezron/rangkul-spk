<?php

namespace App\Models;

use App\Enums\StatusPertemuan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pertemuan daring yang diusulkan konselor.
 *
 * @property int $id
 * @property int $permintaan_id
 * @property int|null $diusulkan_oleh
 * @property Carbon $mulai_pada
 * @property int $durasi_menit
 * @property string|null $tautan
 * @property string|null $catatan
 * @property StatusPertemuan $status
 * @property Carbon|null $disetujui_anggota_pada
 * @property Carbon|null $disetujui_komite_pada
 * @property string|null $alasan_penolakan
 */
#[Fillable(['permintaan_id', 'diusulkan_oleh', 'mulai_pada', 'durasi_menit', 'tautan', 'catatan', 'status', 'disetujui_anggota_pada', 'disetujui_komite_pada', 'disetujui_komite_oleh', 'alasan_penolakan'])]
class Pertemuan extends Model
{
    protected $table = 'pertemuan';

    /**
     * @return BelongsTo<Permintaan, $this>
     */
    public function permintaan(): BelongsTo
    {
        return $this->belongsTo(Permintaan::class);
    }

    public function waktu(): string
    {
        return $this->mulai_pada->translatedFormat('l, j F Y \p\u\k\u\l H.i').' WIB · '.$this->durasi_menit.' menit';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mulai_pada' => 'datetime',
            'durasi_menit' => 'integer',
            'tautan' => 'encrypted',
            'catatan' => 'encrypted',
            'status' => StatusPertemuan::class,
            'disetujui_anggota_pada' => 'datetime',
            'disetujui_komite_pada' => 'datetime',
            'alasan_penolakan' => 'encrypted',
        ];
    }
}
