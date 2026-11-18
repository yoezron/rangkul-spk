@props(['label', 'nilai', 'ikon' => 'grafik', 'warna' => 'utama', 'tautan' => null])
{{-- Kartu angka ringkas di dashboard. --}}
@php
    $nada = [
        'utama' => 'bg-utama-lembut text-utama',
        'aksen' => 'bg-aksen-lembut text-aksen',
        'bahaya' => 'bg-bahaya-lembut text-bahaya',
        'waspada' => 'bg-waspada-lembut text-waspada',
        'sukses' => 'bg-sukses-lembut text-sukses',
        'netral' => 'bg-permukaan-2 text-redup',
    ][$warna] ?? 'bg-utama-lembut text-utama';
    $kelas = 'group flex flex-col gap-3 rounded-2xl border border-garis bg-permukaan p-5 text-teks no-underline shadow-lembut transition hover:no-underline';
@endphp
@if ($tautan)
    <a href="{{ $tautan }}" class="{{ $kelas }} hover:-translate-y-0.5 hover:shadow-angkat">
@else
    <div class="{{ $kelas }}">
@endif
        <div class="flex items-start justify-between gap-3">
            <span class="text-sm font-medium text-redup">{{ $label }}</span>
            <span class="grid size-9 shrink-0 place-items-center rounded-xl {{ $nada }}"><x-ikon :nama="$ikon" class="size-[18px]" /></span>
        </div>
        <span @class(['text-3xl leading-none font-bold tracking-tight', 'text-bahaya' => $warna === 'bahaya'])>{{ $nilai }}</span>
        @if ($slot->hasActualContent())
            <span class="text-sm">{{ $slot }}</span>
        @endif
@if ($tautan)
    </a>
@else
    </div>
@endif
