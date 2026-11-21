<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Kunci acak di tautan (mis. /konseling/jDhsk8sS2k1Q) sebagai pengganti ID
 * angka, supaya tautan di surel/WhatsApp tidak membuka urutan maupun jumlah
 * kasus. Hak akses tetap diperiksa policy; kunci ini bukan pengganti otorisasi.
 *
 * Model yang memakainya butuh kolom unik `kunci_tautan`.
 */
trait PunyaKunciTautan
{
    public const PANJANG_KUNCI_TAUTAN = 12;

    public static function bootPunyaKunciTautan(): void
    {
        static::creating(function (self $model): void {
            $model->kunci_tautan ??= static::buatKunciTautan();
        });
    }

    public static function buatKunciTautan(): string
    {
        do {
            $kunci = Str::random(self::PANJANG_KUNCI_TAUTAN);
        } while (static::query()->where('kunci_tautan', $kunci)->exists());

        return $kunci;
    }

    public function getRouteKeyName(): string
    {
        return 'kunci_tautan';
    }
}
