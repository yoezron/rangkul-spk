<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu pesan percakapan antara anggota dan konselor pendampingnya.
 * `jenis` = `sistem` untuk catatan otomatis (penugasan, jadwal, dll.).
 *
 * @property int $id
 * @property int $permintaan_id
 * @property int|null $pengirim_id
 * @property string $jenis
 * @property string $isi
 * @property Carbon|null $dibaca_pada
 * @property Carbon|null $diingatkan_pada
 * @property Carbon $created_at
 */
#[Fillable(['permintaan_id', 'pengirim_id', 'jenis', 'isi', 'dibaca_pada', 'diingatkan_pada'])]
class Pesan extends Model
{
    protected $table = 'pesan';

    /**
     * @return BelongsTo<Permintaan, $this>
     */
    public function permintaan(): BelongsTo
    {
        return $this->belongsTo(Permintaan::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pengirim_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'isi' => 'encrypted',
            'dibaca_pada' => 'datetime',
            'diingatkan_pada' => 'datetime',
        ];
    }
}
