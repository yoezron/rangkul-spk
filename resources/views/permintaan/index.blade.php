@php use App\Enums\StatusPermintaan; @endphp
<x-tata-letak judul="Permintaan layanan" :lebar="true">
    <header class="mb-6">
        <h1>Permintaan layanan</h1>
        <p class="mt-1.5 text-redup">Ceritakan apa yang sedang Anda alami. Tim komite akan menugaskan peer konselor SPK untuk mendampingi Anda.</p>
    </header>

    @if ($errors->any())
        <div class="catatan galat" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="catatan info" role="status">{{ session('status') }}</div>
    @endif

    @if ($terbuka)
        <section class="kartu flex flex-col gap-4 sm:flex-row sm:items-center">
            <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-utama-lembut text-utama"><x-ikon nama="chat" class="size-6" /></span>
            <div class="flex-1">
                <strong class="block">{{ $terbuka->status === StatusPermintaan::Draf ? 'Draf yang belum dikirim' : 'Permintaan yang sedang berjalan' }}</strong>
                <span class="text-sm text-redup">{{ $terbuka->kode ?? 'Belum dikirim' }} · {{ $terbuka->status->label() }}</span>
            </div>
            @if ($terbuka->status === StatusPermintaan::Draf)
                <a class="tombol" href="{{ route('permintaan.langkah', [$terbuka, $terbuka->langkah]) }}">Lanjutkan mengisi <x-ikon nama="panah-kanan" class="size-4" /></a>
            @else
                <a class="tombol" href="{{ route('permintaan.tampil', $terbuka) }}">Buka permintaan <x-ikon nama="panah-kanan" class="size-4" /></a>
            @endif
        </section>
    @elseif ($layananBuka)
        <section class="kartu">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-aksen-lembut text-aksen"><x-ikon nama="hati" class="size-6" /></span>
                <p class="m-0 flex-1 text-[15px]">Mengisi formulir membutuhkan sekitar <strong>10–15 menit</strong>. Isian Anda tersimpan di setiap langkah, jadi Anda dapat berhenti dan melanjutkan nanti.</p>
            </div>
            <form method="POST" action="{{ route('permintaan.mulai') }}" class="mt-5">
                @csrf
                <button type="submit" class="tombol lebar">Ajukan permintaan bantuan <x-ikon nama="panah-kanan" class="size-4" /></button>
            </form>
        </section>
    @else
        <div class="catatan info" role="status">{!! nl2br(e($pesanTutup)) !!}</div>
    @endif

    @include('partials.kontak', ['kontak' => $kontak])

    @php $riwayat = $daftar->reject(fn ($satu) => $terbuka && $satu->is($terbuka)); @endphp
    @if ($riwayat->isNotEmpty())
        <section class="mt-8">
            <h2>Riwayat</h2>
            <ul class="space-y-2">
                @foreach ($riwayat as $satu)
                    <li>
                        <a href="{{ route('permintaan.tampil', $satu) }}" class="flex items-center justify-between gap-3 rounded-xl border border-garis bg-permukaan px-4 py-3 text-teks no-underline transition hover:border-utama-garis hover:no-underline">
                            <span><strong class="block text-sm">{{ $satu->kode }}</strong><span class="text-xs text-redup">Diajukan {{ $satu->diajukan_pada?->format('d/m/Y') }}</span></span>
                            <span class="lencana netral">{{ $satu->status->label() }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-tata-letak>
