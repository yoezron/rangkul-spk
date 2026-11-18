<?php

namespace App\Http\Controllers\Kelola;

use App\Enums\Hak;
use App\Enums\StatusPermintaan;
use App\Enums\StatusPertemuan;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Peran;
use App\Models\Permintaan;
use App\Models\Pertemuan;
use App\Models\User;
use App\Services\KlienFonnte;
use App\Services\PengaturanRangkul;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan dashboard pengelola. Hanya angka gabungan — tidak ada daftar
 * siapa membuka apa.
 */
class DasborController extends Controller
{
    public function __invoke(Request $request, PengaturanRangkul $pengaturan): View
    {
        $peranSuperadmin = mb_strtolower(trim((string) config('services.spk.peran_superadmin')));

        return view('kelola.dasbor', [
            'layananBuka' => $pengaturan->layananBuka(),
            'surelSiap' => $pengaturan->surelSiap(),
            'whatsappSiap' => (new KlienFonnte($pengaturan))->siap(),
            'layanan' => $request->user()->punyaHak(Hak::PermintaanKelola) ? [
                'belumDitugaskan' => Permintaan::query()->whereIn('status', [StatusPermintaan::Diajukan->value, StatusPermintaan::Ditinjau->value])->count(),
                'darurat' => Permintaan::query()->where('darurat', true)->whereNotIn('status', [StatusPermintaan::Draf->value, StatusPermintaan::Selesai->value, StatusPermintaan::Ditolak->value, StatusPermintaan::Dibatalkan->value])->count(),
                'berlangsung' => Permintaan::query()->where('status', StatusPermintaan::Berlangsung->value)->count(),
                'menungguPersetujuan' => Permintaan::query()->where('status', StatusPermintaan::MenungguPenutupan->value)->count()
                    + Pertemuan::query()->where('status', StatusPertemuan::MenungguKomite->value)->count(),
                'selesaiBulanIni' => Permintaan::query()->where('status', StatusPermintaan::Selesai->value)->where('selesai_pada', '>=', now()->startOfMonth())->count(),
            ] : null,
            'jumlahAnggota' => User::query()->count(),
            'aktif30Hari' => User::query()->where('disinkronkan_pada', '>=', now()->subDays(30))->count(),
            'jumlahSuperadmin' => $peranSuperadmin === '' ? 0 : User::query()->whereRaw('LOWER(TRIM(peran)) = ?', [$peranSuperadmin])->count(),
            'daftarPeran' => Peran::query()->withCount('pengguna')->orderBy('nama')->get(),
            'perWilayah' => User::query()
                ->select('wilayah', DB::raw('COUNT(*) AS jumlah'))
                ->groupBy('wilayah')
                ->orderByDesc('jumlah')
                ->limit(8)
                ->get(),
            'auditTerbaru' => $request->user()->punyaHak(Hak::AuditLihat)
                ? AuditLog::query()->latest('id')->limit(8)->get()
                : collect(),
        ]);
    }
}
