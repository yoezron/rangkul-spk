<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jawaban satu instrumen, berikut salinan instrumen saat diisi dan skornya.
 *
 * `isian` (terenkripsi) berbentuk:
 *   ['instrumen' => salinan, 'jawaban' => [kunci butir => nilai], 'skor' => [...], 'kritis' => [...]]
 *
 * @property int $id
 * @property int $permintaan_id
 * @property int|null $instrumen_id
 * @property string $nama_instrumen
 * @property array{instrumen: array<string, mixed>, jawaban: array<string, int|float>, skor: list<array{subskala: string, nama: string, nilai: float, label: string, saran: string}>, kritis: list<string>} $isian
 * @property bool $kritis
 */
#[Fillable(['permintaan_id', 'instrumen_id', 'nama_instrumen', 'isian', 'kritis'])]
class JawabanAsesmen extends Model
{
    protected $table = 'jawaban_asesmen';

    /**
     * @return BelongsTo<Permintaan, $this>
     */
    public function permintaan(): BelongsTo
    {
        return $this->belongsTo(Permintaan::class);
    }

    /**
     * Ringkasan satu baris, mis. "PHQ-9 — gejala depresi: 12 (Sedang)".
     */
    public function ringkasan(): string
    {
        $skor = array_map(
            fn (array $satu): string => ($satu['subskala'] === '' ? '' : $satu['nama'].' ').self::angka($satu['nilai']).' ('.$satu['label'].')',
            $this->isian['skor'] ?? [],
        );

        return $this->nama_instrumen.': '.implode(', ', $skor);
    }

    public static function angka(float|int $nilai): string
    {
        return rtrim(rtrim(number_format((float) $nilai, 2, ',', ''), '0'), ',');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'isian' => 'encrypted:array',
            'kritis' => 'boolean',
        ];
    }
}
