@props(['baris', 'total' => null, 'satuan' => '', 'warna' => 'var(--c-seri-1)', 'kosong' => 'Belum ada data.'])
{{--
    Batang mendatar satu seri: nama, batang, dan angka per baris; batang
    sejajar pada satu garis dasar. Urutan baris ditentukan pemanggil
    (berperingkat, atau mengikuti alur bila kategorinya berurutan).

    @param list<array{label: string, nilai: int}> $baris
    @param int|null $total  bila diisi, persentase terhadap total ikut ditampilkan
--}}
@php
    $terbesar = max(1, ...array_map(fn ($satu) => (int) $satu['nilai'], $baris ?: [['nilai' => 0]]));
    $adaNilai = collect($baris)->sum('nilai') > 0;
@endphp

@if (! $adaNilai)
    <p class="m-0 rounded-xl bg-permukaan-2 px-4 py-8 text-center text-sm text-redup">{{ $kosong }}</p>
@else
    <ul class="space-y-3">
        @foreach ($baris as $satu)
            @php
                $nilai = (int) $satu['nilai'];
                $persen = $total ? round($nilai / max(1, $total) * 100) : null;
            @endphp
            <li tabindex="0" aria-label="{{ $satu['label'] }}: {{ $nilai }} {{ $satuan }}{{ $persen !== null ? ' ('.$persen.'%)' : '' }}"
                class="group rounded-lg outline-none focus-visible:ring-2 focus-visible:ring-utama/30">
                <div class="mb-1 flex items-baseline justify-between gap-3 text-sm">
                    <span class="min-w-0 text-teks">{{ $satu['label'] }}</span>
                    <span class="shrink-0 text-redup tabular-nums">
                        <strong class="font-semibold text-teks">{{ $nilai }}</strong>
                        @if ($persen !== null)<span class="ml-1">{{ $persen }}%</span>@endif
                    </span>
                </div>
                <div class="h-2.5">
                    @if ($nilai > 0)
                        <div class="h-full rounded-r-[4px] transition group-hover:brightness-110 group-focus-visible:brightness-110"
                             style="width: {{ max(1.5, $nilai / $terbesar * 100) }}%; background: {{ $warna }}"></div>
                    @endif
                </div>
            </li>
        @endforeach
    </ul>
@endif
