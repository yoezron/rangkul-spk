<x-tata-letak judul="Beranda">
    <h1>Halo, {{ $anggota->nama }}.</h1>
    <p class="redup">Selamat datang di SPK Rangkul.</p>

    @if ($permintaanTerbuka)
        <div class="kartu">
            @if ($permintaanTerbuka->status === \App\Enums\StatusPermintaan::Draf)
                <strong>Anda punya draf permintaan yang belum dikirim.</strong>
                <p class="redup" style="margin: 4px 0 12px">Isian Anda tersimpan; lanjutkan kapan saja.</p>
                <a class="tombol lebar" href="{{ route('permintaan.langkah', [$permintaanTerbuka, $permintaanTerbuka->langkah]) }}">Lanjutkan mengisi</a>
            @else
                <strong>Permintaan {{ $permintaanTerbuka->kode }}</strong>
                <p class="redup" style="margin: 4px 0 12px">
                    {{ $permintaanTerbuka->status->label() }}
                    @if ($pesanBelumDibaca > 0)
                        · <strong>{{ $pesanBelumDibaca }} pesan baru</strong>
                    @endif
                </p>
                <a class="tombol lebar" href="{{ route('permintaan.tampil', $permintaanTerbuka) }}">Buka permintaan</a>
            @endif
        </div>
    @elseif ($pesanTutup)
        <div class="catatan info" role="status">{!! nl2br(e($pesanTutup)) !!}</div>
    @else
        <p>Sedang menghadapi tekanan di tempat kerja, keluarga, atau perasaan yang berat? Ceritakan kepada kami — peer konselor SPK akan mendampingi Anda.</p>
        <p><a class="tombol lebar" href="{{ route('permintaan.index') }}">Ajukan permintaan bantuan</a></p>
    @endif

    @include('partials.kontak', ['kontak' => $kontak])

    @can('konseling.tangani')
        <p><a class="tombol sekunder lebar" href="{{ route('konseling.index') }}">Kasus pendampingan saya @if ($kasusAktif > 0)({{ $kasusAktif }} aktif)@endif</a></p>
    @endcan

    @can('dasbor.lihat')
        <p><a class="tombol sekunder lebar" href="{{ route('kelola.dasbor') }}">Dashboard pengelola</a></p>
    @endcan

    <p class="kecil"><a href="{{ route('permintaan.index') }}">Riwayat permintaan saya</a></p>

    <form method="POST" action="{{ route('keluar') }}">
        @csrf
        <button type="submit" class="tombol sekunder lebar">Keluar</button>
    </form>
</x-tata-letak>
