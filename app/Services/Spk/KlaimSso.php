<?php

namespace App\Services\Spk;

/**
 * Isi token masuk yang sudah lolos pemeriksaan tanda tangan, masa berlaku,
 * dan nonce. Hanya memuat id akun — nama dan data lain diambil dari endpoint.
 */
final readonly class KlaimSso
{
    /**
     * @param  int  $uid  id akun di spk.or.id
     * @param  int  $iat  detik Unix saat token diterbitkan (jam spk.or.id)
     * @param  int  $exp  detik Unix saat token kedaluwarsa (jam spk.or.id)
     */
    public function __construct(
        public int $uid,
        public int $iat,
        public int $exp,
    ) {}
}
