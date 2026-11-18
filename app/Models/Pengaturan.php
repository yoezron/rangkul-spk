<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris pengaturan. Dibaca dan ditulis lewat App\Services\PengaturanRangkul,
 * yang mengurus nilai bawaan, bentuk JSON, dan enkripsi nilai rahasia.
 *
 * @property string $kunci
 * @property string|null $nilai
 */
#[Fillable(['kunci', 'nilai'])]
class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    protected $primaryKey = 'kunci';

    protected $keyType = 'string';

    public $incrementing = false;
}
