<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Catatan konselor setelah sesi: rangkuman, analisis/prognosis, saran, dan
 * rujukan ke profesional di luar tim Rangkul. Hanya terbaca oleh konselor
 * pendamping, komite, dan superadmin — tidak oleh anggota.
 *
 * @property int $id
 * @property int $permintaan_id
 * @property int|null $pertemuan_id
 * @property int|null $penulis_id
 * @property string $rangkuman
 * @property string|null $analisis
 * @property string|null $saran
 * @property bool $rujukan
 * @property string|null $rujukan_ke
 * @property Carbon $created_at
 */
#[Fillable(['permintaan_id', 'pertemuan_id', 'penulis_id', 'rangkuman', 'analisis', 'saran', 'rujukan', 'rujukan_ke'])]
class CatatanSesi extends Model
{
    protected $table = 'catatan_sesi';

    /**
     * @return BelongsTo<User, $this>
     */
    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penulis_id');
    }

    /**
     * @return BelongsTo<Pertemuan, $this>
     */
    public function pertemuan(): BelongsTo
    {
        return $this->belongsTo(Pertemuan::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rangkuman' => 'encrypted',
            'analisis' => 'encrypted',
            'saran' => 'encrypted',
            'rujukan' => 'boolean',
            'rujukan_ke' => 'encrypted',
        ];
    }
}
