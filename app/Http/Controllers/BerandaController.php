<?php

namespace App\Http\Controllers;

use App\Services\PengaturanRangkul;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BerandaController extends Controller
{
    public function __invoke(Request $request, PengaturanRangkul $pengaturan): View
    {
        return view('beranda', [
            'anggota' => $request->user(),
            'pesanTutup' => $pengaturan->layananBuka() ? null : (string) $pengaturan->get('layanan.pesan_tutup'),
            'kontak' => $pengaturan->kontak(),
        ]);
    }
}
