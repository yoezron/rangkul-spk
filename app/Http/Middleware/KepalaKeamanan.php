<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kepala jawaban yang menjaga kerahasiaan kunjungan ke Rangkul.
 *
 *  - `Referrer-Policy: no-referrer` — alamat Rangkul (termasuk alamat SSO yang
 *    memuat token) tidak pernah terkirim ke situs lain sebagai Referer.
 *  - `Cache-Control: no-store` — halaman tidak disimpan peramban, sehingga
 *    tombol "kembali" di komputer bersama tidak menampilkan isi sesi yang
 *    sudah keluar.
 */
class KepalaKeamanan
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $jawaban = $next($request);

        $jawaban->headers->set('Referrer-Policy', 'no-referrer');
        $jawaban->headers->set('Cache-Control', 'no-store, private');
        $jawaban->headers->set('X-Content-Type-Options', 'nosniff');
        $jawaban->headers->set('X-Frame-Options', 'DENY');

        return $jawaban;
    }
}
