<x-tata-letak judul="Beranda">
    <h1>Halo, {{ $anggota->nama }}.</h1>
    <p class="redup">Selamat datang di SPK Rangkul.</p>

    @if ($pesanTutup)
        <div class="catatan info" role="status">{!! nl2br(e($pesanTutup)) !!}</div>
    @else
        <p>Layanan ini sedang disiapkan. Belum ada yang perlu Anda lakukan di sini.</p>
    @endif

    @include('partials.kontak', ['kontak' => $kontak])

    @can('dasbor.lihat')
        <p><a class="tombol lebar" href="{{ route('kelola.dasbor') }}">Dashboard pengelola</a></p>
    @endcan

    <form method="POST" action="{{ route('keluar') }}">
        @csrf
        <button type="submit" class="tombol sekunder lebar">Keluar</button>
    </form>
</x-tata-letak>
