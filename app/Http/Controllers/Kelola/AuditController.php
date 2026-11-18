<?php

namespace App\Http\Controllers\Kelola;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __invoke(Request $request): View
    {
        $aksi = (string) $request->query('aksi', '');

        if (! array_key_exists($aksi, AuditLog::AKSI)) {
            $aksi = '';
        }

        return view('kelola.audit.index', [
            'catatan' => AuditLog::query()
                ->when($aksi !== '', fn ($kueri) => $kueri->where('aksi', $aksi))
                ->latest('id')
                ->simplePaginate(50)
                ->withQueryString(),
            'aksi' => $aksi,
        ]);
    }
}
