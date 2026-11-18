<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Endpoint data anggota spk.or.id tidak memberi jawaban yang bisa dipakai.
 *
 * `$sebab` hanya untuk catatan (log) pengelola, tidak pernah ditampilkan ke
 * pengguna. Pesannya sengaja tidak memuat alamat permintaan maupun id akun.
 */
class PermintaanSpkGagal extends RuntimeException
{
    /**
     * @param  string  $sebab  kode singkat penyebab, mis. `jaringan`, `tanda_tangan`, `http_500`
     * @param  bool  $ditolak  true bila spk.or.id menjawab dengan penolakan tegas (rahasia
     *                         tidak cocok atau integrasi dimatikan), bukan sekadar gangguan
     */
    public function __construct(
        public readonly string $sebab,
        public readonly bool $ditolak = false,
    ) {
        parent::__construct('Permintaan data anggota ke spk.or.id gagal: '.$sebab);
    }
}
