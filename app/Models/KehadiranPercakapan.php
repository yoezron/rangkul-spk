<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu rentang waktu seorang peserta (anggota atau konselor) membuka halaman
 * percakapan sebuah kasus. Rentang berakhir saat polling halaman berhenti:
 * tab ditutup, pindah halaman, atau keluar dari aplikasi.
 *
 * @property int $id
 * @property int $permintaan_id
 * @property int $user_id
 * @property string $sebagai anggota|konselor
 * @property Carbon $mulai_pada
 * @property Carbon $terakhir_pada
 */
#[Fillable(['permintaan_id', 'user_id', 'sebagai', 'mulai_pada', 'terakhir_pada'])]
class KehadiranPercakapan extends Model
{
    public $timestamps = false;

    protected $table = 'kehadiran_percakapan';

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
    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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
            'terakhir_pada' => 'datetime',
        ];
    }
}
