<?php

namespace App\Models;

use App\Enums\Hak;
use Database\Factories\PeranFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Peran di dalam Rangkul: sebuah nama dengan sekumpulan hak akses.
 *
 * @property int $id
 * @property string $kunci
 * @property string $nama
 * @property string|null $deskripsi
 * @property list<string> $hak
 * @property bool $bawaan
 */
#[Fillable(['kunci', 'nama', 'deskripsi', 'hak', 'bawaan'])]
class Peran extends Model
{
    /** @use HasFactory<PeranFactory> */
    use HasFactory;

    protected $table = 'peran';

    /**
     * @return BelongsToMany<User, $this>
     */
    public function pengguna(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'peran_user')->withPivot('ditugaskan_oleh')->withTimestamps();
    }

    /**
     * Hak peran ini yang masih dikenal kode (hak usang diabaikan).
     *
     * @return list<string>
     */
    public function hakBerlaku(): array
    {
        return array_values(array_intersect(Hak::semuaNilai(), $this->hak ?? []));
    }

    /**
     * Seseorang hanya boleh menugaskan atau mengubah peran yang seluruh haknya
     * ia miliki sendiri — kalau tidak, hak "menugaskan peran" menjadi jalan
     * untuk menaikkan hak diri sendiri.
     */
    public function bolehDikelolaOleh(User $aktor): bool
    {
        return array_diff($this->hakBerlaku(), $aktor->daftarHak()) === [];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hak' => 'array',
            'bawaan' => 'boolean',
        ];
    }
}
