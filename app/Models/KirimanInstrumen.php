<?php

namespace App\Models;

use App\Models\Concerns\PunyaKunciTautan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Instrumen yang dikirim konselor di tengah percakapan untuk diisi anggota.
 *
 * Boleh instrumen apa pun yang disusun komite, termasuk yang tidak aktif di
 * asesmen dasar. Skor dan tafsirannya hanya untuk konselor dan komite.
 *
 * @property int $id
 * @property string $kunci_tautan kunci acak di URL, lihat PunyaKunciTautan
 * @property int $permintaan_id
 * @property int|null $instrumen_id
 * @property int|null $dikirim_oleh
 * @property string $nama_instrumen
 * @property array<string, mixed> $salinan
 * @property string|null $pengantar
 * @property array{instrumen: array<string, mixed>, jawaban: array<string, int|float>, skor: list<array{subskala: string, nama: string, nilai: float, label: string, saran: string}>, kritis: list<string>}|null $isian
 * @property bool $kritis
 * @property Carbon|null $diisi_pada
 * @property Carbon $created_at
 */
#[Fillable(['permintaan_id', 'instrumen_id', 'dikirim_oleh', 'nama_instrumen', 'salinan', 'pengantar', 'isian', 'kritis', 'diisi_pada'])]
class KirimanInstrumen extends Model
{
    use PunyaKunciTautan;

    protected $table = 'kiriman_instrumen';

    /**
     * @return BelongsTo<Permintaan, $this>
     */
    public function permintaan(): BelongsTo
    {
        return $this->belongsTo(Permintaan::class);
    }

    /**
     * @return BelongsTo<Instrumen, $this>
     */
    public function instrumen(): BelongsTo
    {
        return $this->belongsTo(Instrumen::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikirim_oleh');
    }

    public function sudahDiisi(): bool
    {
        return $this->diisi_pada !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'salinan' => 'array',
            'pengantar' => 'encrypted',
            'isian' => 'encrypted:array',
            'kritis' => 'boolean',
            'diisi_pada' => 'datetime',
        ];
    }
}
