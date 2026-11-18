<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Fonnte tidak menerima permintaan. `$sebab` adalah kode singkat atau alasan
 * dari Fonnte (mis. "token invalid"); tidak memuat nomor tujuan maupun isi pesan.
 */
class GagalKirimWhatsapp extends RuntimeException
{
    public function __construct(public readonly string $sebab)
    {
        parent::__construct('Permintaan ke Fonnte gagal: '.$sebab);
    }
}
