<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Token masuk dari spk.or.id tidak lolos pemeriksaan.
 *
 * `$sebab` hanya untuk catatan pengelola. Kepada pengguna semua penolakan
 * token ditampilkan dengan satu pesan yang sama.
 */
class TokenSsoDitolak extends RuntimeException
{
    /**
     * @param  string  $sebab  `belum_diatur` | `bentuk` | `tanda_tangan` | `peruntukan` | `kedaluwarsa` | `waktu` | `nonce_terpakai`
     */
    public function __construct(public readonly string $sebab)
    {
        parent::__construct('Token masuk SSO ditolak: '.$sebab);
    }
}
