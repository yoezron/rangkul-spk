@props(['bagian', 'total'])
{{--
    Batang tersegmen tinggi: satu keseluruhan dibagi menjadi tingkat-tingkat
    berurutan (ringan → berat), diwarnai gradasi satu warna.

    @param list<array{label: string, nilai: int}> $bagian  paling banyak 6, sudah berurutan
--}}
@php
    $jumlah = count($bagian);
    // Petakan tiap tingkat ke anak tangga gradasi 1–5 secara merata.
    $anakTangga = fn (int $indeks) => $jumlah <= 1 ? 3 : min(5, 1 + (int) round($indeks * 4 / max(1, $jumlah - 1)));
@endphp

@if ($total === 0)
    <p class="m-0 rounded-lg bg-permukaan-2 px-3 py-2.5 text-sm text-redup">Belum ada jawaban.</p>
@else
    <div class="flex h-7 gap-[2px]" role="img"
         aria-label="{{ collect($bagian)->filter(fn ($satu) => $satu['nilai'] > 0)->map(fn ($satu) => $satu['label'].' '.$satu['nilai'].' ('.round($satu['nilai'] / $total * 100).'%)')->implode(', ') }}">
        @foreach ($bagian as $indeks => $satu)
            @continue($satu['nilai'] === 0)
            @php
                $persen = $satu['nilai'] / $total * 100;
                $tangga = $anakTangga($indeks);
            @endphp
            <div tabindex="0" aria-label="{{ $satu['label'] }}: {{ $satu['nilai'] }} ({{ round($persen) }}%)"
                 class="group relative flex min-w-0 items-center rounded-[4px] px-2 outline-none focus-visible:ring-2 focus-visible:ring-utama/40"
                 style="width: {{ $persen }}%; background: var(--c-ordinal-{{ $tangga }}); color: var(--c-ordinal-teks-{{ $tangga }})">
                @if ($persen >= 12)
                    <span class="text-xs font-semibold tabular-nums">{{ round($persen) }}%</span>
                @endif
                <span class="pointer-events-none absolute bottom-full left-0 z-20 mb-2 hidden rounded-xl border border-garis bg-permukaan px-3 py-2 text-xs whitespace-nowrap text-teks shadow-angkat group-hover:block group-focus-visible:block">
                    <strong class="tabular-nums">{{ $satu['nilai'] }}</strong> <span class="text-redup">({{ round($persen) }}%)</span> · {{ $satu['label'] }}
                </span>
            </div>
        @endforeach
    </div>
    <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-redup">
        @foreach ($bagian as $indeks => $satu)
            <li class="flex items-center gap-1.5">
                <span class="size-2.5 rounded-[3px]" style="background: var(--c-ordinal-{{ $anakTangga($indeks) }})"></span>
                {{ $satu['label'] }} <span class="tabular-nums">· {{ $satu['nilai'] }}</span>
            </li>
        @endforeach
    </ul>
@endif
