<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Instrumen asesmen dasar yang disusun komite.
 *
 * @property int $id
 * @property string $kunci
 * @property string $nama
 * @property string|null $instruksi
 * @property list<array{label: string, nilai: int|float}> $pilihan
 * @property string $metode jumlah|rerata
 * @property string $pengali
 * @property array<string, string> $subskala
 * @property list<array{subskala: string, min: int|float, max: int|float, label: string, saran: string}> $rentang
 * @property list<array{kunci: string, teks: string, subskala: string, terbalik: bool, kritis_min: int|float|null, pilihan: list<array{label: string, nilai: int|float}>|null}> $butir
 * @property string|null $sumber
 * @property bool $aktif
 * @property int $urutan
 * @property bool $bawaan
 */
#[Fillable(['kunci', 'nama', 'instruksi', 'pilihan', 'metode', 'pengali', 'subskala', 'rentang', 'butir', 'sumber', 'aktif', 'urutan', 'bawaan'])]
class Instrumen extends Model
{
    protected $table = 'instrumen';

    /**
     * @return HasMany<JawabanAsesmen, $this>
     */
    public function jawaban(): HasMany
    {
        return $this->hasMany(JawabanAsesmen::class);
    }

    /**
     * @return HasMany<KirimanInstrumen, $this>
     */
    public function kiriman(): HasMany
    {
        return $this->hasMany(KirimanInstrumen::class);
    }

    /**
     * @param  Builder<self>  $kueri
     */
    public function scopeDipakai(Builder $kueri): void
    {
        $kueri->where('aktif', true)->orderBy('urutan')->orderBy('id');
    }

    /**
     * Salinan lengkap instrumen untuk disimpan bersama jawaban.
     *
     * @return array<string, mixed>
     */
    public function salinan(): array
    {
        return [
            'kunci' => $this->kunci,
            'nama' => $this->nama,
            'instruksi' => $this->instruksi,
            'pilihan' => $this->pilihan,
            'metode' => $this->metode,
            'pengali' => (float) $this->pengali,
            'subskala' => $this->subskala,
            'rentang' => $this->rentang,
            'butir' => $this->butir,
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pilihan' => 'array',
            'subskala' => 'array',
            'rentang' => 'array',
            'butir' => 'array',
            'aktif' => 'boolean',
            'bawaan' => 'boolean',
            'urutan' => 'integer',
        ];
    }
}
