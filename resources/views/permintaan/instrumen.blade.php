@php use App\Services\Asesmen\PenilaiAsesmen; $salinan = $kiriman->salinan; @endphp
<x-tata-letak :judul="$kiriman->nama_instrumen">
    <a href="{{ route('permintaan.tampil', $permintaan) }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-redup no-underline hover:text-teks hover:no-underline">
        <x-ikon nama="panah-kiri" class="size-4" /> Kembali ke percakapan
    </a>

    <h1 class="mb-1">{{ $kiriman->nama_instrumen }}</h1>
    <p class="mt-0 mb-5 text-sm text-redup">Permintaan {{ $permintaan->kode }} · dikirim konselor pendamping {{ $kiriman->created_at->format('d/m/Y H.i') }}</p>

    @if ($errors->any())
        <div class="catatan galat" role="alert"><ul>@foreach ($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach</ul></div>
    @endif

    <div class="catatan info flex items-start gap-2.5">
        <x-ikon nama="kilau" class="mt-0.5 size-5 text-utama" />
        <span>Jawaban ini membantu konselor memahami keadaan Anda. Ini bukan tes dan bukan diagnosis; tidak ada jawaban benar atau salah. Hasilnya hanya dibaca konselor dan komite.</span>
    </div>

    @if ($kiriman->pengantar)
        <div class="kartu">
            <p class="mb-1 text-xs font-semibold tracking-wide text-redup uppercase">Pesan konselor</p>
            <p class="m-0 whitespace-pre-line">{{ $kiriman->pengantar }}</p>
        </div>
    @endif

    <form method="POST" action="{{ route('instrumen-kasus.simpan', [$permintaan, $kiriman]) }}">
        @csrf
        <section class="kartu">
            <div class="mb-2 flex items-start justify-between gap-3">
                <h2 class="mb-0">{{ $kiriman->nama_instrumen }}</h2>
                <span class="lencana netral shrink-0">{{ count($salinan['butir'] ?? []) }} butir</span>
            </div>
            @if ($salinan['instruksi'] ?? null)
                <p class="text-sm text-redup">{{ $salinan['instruksi'] }}</p>
            @endif
            @foreach ($salinan['butir'] ?? [] as $nomor => $butir)
                @php $nama = "jawaban[{$butir['kunci']}]"; $lama = old("jawaban.{$butir['kunci']}"); @endphp
                <fieldset class="butir m-0 rounded-none border-0 border-t bg-transparent p-0 py-4 shadow-none first-of-type:border-t-0">
                    <legend class="float-left mb-3 w-full p-0 text-[15px] font-normal"><span class="mr-1 font-semibold text-redup">{{ $nomor + 1 }}.</span> {{ $butir['teks'] }}</legend>
                    <div class="skala clear-both">
                        @foreach (PenilaiAsesmen::pilihanButir($salinan, $butir) as $pilihan)
                            <label><input type="radio" name="{{ $nama }}" value="{{ $pilihan['nilai'] }}" required @checked($lama !== null && (string) $lama === (string) $pilihan['nilai'])> {{ $pilihan['label'] }}</label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
        </section>
        <div class="flex justify-end">
            <button type="submit" class="tombol">Kirim jawaban <x-ikon nama="panah-kanan" class="size-4" /></button>
        </div>
    </form>

    @include('partials.kontak', ['kontak' => $kontak])
</x-tata-letak>
