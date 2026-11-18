<x-tata-letak judul="Masuk">
    <h1>{{ $halaman['judul'] }}</h1>
    @if ($halaman['subjudul'] !== '')
        <p class="redup">{{ $halaman['subjudul'] }}</p>
    @endif

    @if ($penjelasan)
        <div class="catatan" role="alert">{{ $penjelasan }}</div>
    @elseif ($sudahKeluar)
        <div class="catatan info" role="status">
            Anda sudah keluar dari SPK Rangkul. Bila memakai komputer bersama, keluarlah juga dari spk.or.id.
        </div>
    @endif

    @if ($pesanTutup)
        <div class="catatan info" role="status">{!! nl2br(e($pesanTutup)) !!}</div>
    @endif

    @if ($halaman['isi'] !== '')
        <div class="isi-halaman">{!! nl2br(e($halaman['isi'])) !!}</div>
    @endif

    @if ($alamatMasuk)
        <p><a class="tombol lebar" href="{{ $alamatMasuk }}" rel="noreferrer">Masuk dengan akun SPK</a></p>
        <p class="redup kecil">
            SPK Rangkul tidak punya password sendiri dan tidak membuka pendaftaran.
            Anda masuk memakai akun spk.or.id; hanya anggota yang keanggotaannya sudah dikonfirmasi yang dapat masuk.
        </p>
    @else
        <div class="catatan info" role="status">SPK Rangkul sedang disiapkan. Silakan kembali lagi nanti.</div>
    @endif

    @include('partials.kontak', ['kontak' => $kontak])
</x-tata-letak>
