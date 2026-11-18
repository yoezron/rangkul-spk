<x-tata-letak judul="Permintaan layanan" :lebar="true">
    <p class="kecil"><a href="{{ route('beranda') }}">← Beranda</a></p>
    <h1>Permintaan layanan</h1>
    <p class="redup">Ceritakan apa yang sedang Anda alami. Tim komite akan menugaskan peer konselor SPK untuk mendampingi Anda.</p>

    @if ($errors->any())
        <div class="catatan galat" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="catatan info" role="status">{{ session('status') }}</div>
    @endif

    @if ($terbuka)
        <div class="kartu">
            <strong>{{ $terbuka->status === \App\Enums\StatusPermintaan::Draf ? 'Draf yang belum dikirim' : 'Permintaan yang sedang berjalan' }}</strong>
            <p class="redup" style="margin: 4px 0 12px">
                {{ $terbuka->kode ?? 'Belum dikirim' }} · {{ $terbuka->status->label() }}
            </p>
            @if ($terbuka->status === \App\Enums\StatusPermintaan::Draf)
                <a class="tombol" href="{{ route('permintaan.langkah', [$terbuka, $terbuka->langkah]) }}">Lanjutkan mengisi</a>
            @else
                <a class="tombol" href="{{ route('permintaan.tampil', $terbuka) }}">Buka permintaan</a>
            @endif
        </div>
    @elseif ($layananBuka)
        <form method="POST" action="{{ route('permintaan.mulai') }}">
            @csrf
            <button type="submit" class="tombol lebar">Ajukan permintaan bantuan</button>
        </form>
        <p class="redup kecil" style="margin-top: 8px">Mengisi formulir membutuhkan sekitar 10–15 menit. Isian Anda tersimpan di setiap langkah, jadi Anda dapat berhenti dan melanjutkan nanti.</p>
    @else
        <div class="catatan info" role="status">{!! nl2br(e($pesanTutup)) !!}</div>
    @endif

    @include('partials.kontak', ['kontak' => $kontak])

    @php $riwayat = $daftar->reject(fn ($satu) => $terbuka && $satu->is($terbuka)); @endphp
    @if ($riwayat->isNotEmpty())
        <h2 style="margin-top: 24px">Riwayat</h2>
        <table>
            <thead><tr><th>Kode</th><th>Diajukan</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($riwayat as $satu)
                    <tr>
                        <td><a href="{{ route('permintaan.tampil', $satu) }}">{{ $satu->kode }}</a></td>
                        <td>{{ $satu->diajukan_pada?->format('d/m/Y') }}</td>
                        <td>{{ $satu->status->label() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</x-tata-letak>
