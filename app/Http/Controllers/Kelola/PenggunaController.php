<?php

namespace App\Http\Controllers\Kelola;

use App\Exceptions\PermintaanSpkGagal;
use App\Http\Controllers\Controller;
use App\Models\Peran;
use App\Models\User;
use App\Services\PencatatAudit;
use App\Services\Spk\KlienSpk;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Daftar pengguna dan penugasan peran (peer konselor, administrator, dll.).
 *
 * Yang bisa ditugaskan hanya anggota yang berhak menurut spk.or.id. Anggota
 * yang belum pernah membuka Rangkul dapat ditambahkan lewat id akun SPK-nya;
 * datanya diambil dari endpoint data anggota, sama seperti saat masuk.
 */
class PenggunaController extends Controller
{
    /** @var array<string, string> */
    private const ALASAN_TIDAK_BERHAK = [
        'belum_terkonfirmasi' => 'akun itu masih berstatus Calon Anggota',
        'tidak_aktif' => 'akun itu sedang tidak aktif',
        'wajib_ganti_password' => 'akun itu sedang diwajibkan mengganti password',
        'tidak_ditemukan' => 'tidak ada akun dengan id itu',
    ];

    public function __construct(private readonly PencatatAudit $audit) {}

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari', ''));
        $peranId = (int) $request->query('peran', 0);

        $pengguna = User::query()
            ->with('daftarPeran')
            ->when($cari !== '', function ($kueri) use ($cari): void {
                $pola = '%'.addcslashes($cari, '%_\\').'%';

                $kueri->where(function ($kueri) use ($pola, $cari): void {
                    $kueri->where('nama', 'like', $pola)
                        ->orWhere('nomor_anggota', 'like', $pola)
                        ->orWhere('kampus', 'like', $pola)
                        ->orWhere('wilayah', 'like', $pola);

                    if (ctype_digit($cari)) {
                        $kueri->orWhere('spk_id', (int) $cari);
                    }
                });
            })
            ->when($peranId > 0, fn ($kueri) => $kueri->whereHas('daftarPeran', fn ($kueri) => $kueri->whereKey($peranId)))
            ->orderBy('nama')
            ->simplePaginate(25)
            ->withQueryString();

        return view('kelola.pengguna.index', [
            'pengguna' => $pengguna,
            'cari' => $cari,
            'peranId' => $peranId,
            'semuaPeran' => Peran::query()->orderBy('nama')->get(),
        ]);
    }

    public function tampil(Request $request, User $pengguna): View
    {
        $aktor = $request->user();
        $pengguna->load('daftarPeran');

        $penugas = User::query()
            ->whereKey($pengguna->daftarPeran->pluck('pivot.ditugaskan_oleh')->filter())
            ->get()
            ->keyBy('id');

        return view('kelola.pengguna.tampil', [
            'pengguna' => $pengguna,
            'penugas' => $penugas,
            'aktor' => $aktor,
            'bisaDitugaskan' => Peran::query()
                ->orderBy('nama')
                ->get()
                ->reject(fn (Peran $peran): bool => $pengguna->daftarPeran->contains($peran))
                ->filter(fn (Peran $peran): bool => $peran->bolehDikelolaOleh($aktor))
                ->values(),
        ]);
    }

    /**
     * Tambahkan anggota yang belum pernah membuka Rangkul, lewat id akun SPK.
     */
    public function tambah(Request $request, KlienSpk $klien): RedirectResponse
    {
        $data = $request->validate(['spk_id' => ['required', 'integer', 'min:1']]);
        $spkId = (int) $data['spk_id'];

        $ada = User::query()->where('spk_id', $spkId)->first();

        if ($ada !== null) {
            return redirect()->route('kelola.pengguna.tampil', $ada)->with('status', 'Anggota ini sudah terdaftar di Rangkul.');
        }

        try {
            $jawaban = $klien->anggota($spkId);
        } catch (PermintaanSpkGagal) {
            return back()->withInput()->withErrors(['spk_id' => 'spk.or.id sedang tidak dapat dihubungi. Silakan coba lagi nanti.']);
        }

        if (! $jawaban->berhak) {
            $alasan = self::ALASAN_TIDAK_BERHAK[$jawaban->alasan] ?? 'akun itu tidak berhak memakai Rangkul';

            return back()->withInput()->withErrors(['spk_id' => "Tidak dapat ditambahkan: {$alasan}."]);
        }

        $pengguna = User::sinkronkanDariSpk($jawaban);
        $this->audit->catat('pengguna.ditambahkan', PencatatAudit::sebut($pengguna));

        return redirect()->route('kelola.pengguna.tampil', $pengguna)->with('status', 'Anggota ditambahkan. Sekarang Anda dapat menugaskan perannya.');
    }

    public function tugaskan(Request $request, User $pengguna): RedirectResponse
    {
        $data = $request->validate(['peran_id' => ['required', 'integer', 'exists:peran,id']]);
        $peran = Peran::query()->findOrFail($data['peran_id']);
        $aktor = $request->user();

        abort_unless($peran->bolehDikelolaOleh($aktor), 403, 'Anda tidak dapat menugaskan peran dengan hak akses yang tidak Anda miliki sendiri.');

        if ($pengguna->daftarPeran()->whereKey($peran->id)->exists()) {
            return back()->with('status', "{$pengguna->nama} sudah memegang peran {$peran->nama}.");
        }

        $pengguna->daftarPeran()->attach($peran->id, ['ditugaskan_oleh' => $aktor->id]);
        $this->audit->catat('penugasan.diberikan', PencatatAudit::sebut($pengguna), ['peran' => $peran->nama]);

        return back()->with('status', "{$pengguna->nama} kini memegang peran {$peran->nama}.");
    }

    public function cabut(Request $request, User $pengguna, Peran $peran): RedirectResponse
    {
        abort_unless($peran->bolehDikelolaOleh($request->user()), 403, 'Anda tidak dapat mencabut peran dengan hak akses yang tidak Anda miliki sendiri.');

        if ($pengguna->daftarPeran()->detach($peran->id) > 0) {
            $this->audit->catat('penugasan.dicabut', PencatatAudit::sebut($pengguna), ['peran' => $peran->nama]);
        }

        return back()->with('status', "Peran {$peran->nama} dicabut dari {$pengguna->nama}.");
    }
}
