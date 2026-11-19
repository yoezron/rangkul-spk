@props(['data', 'seri', 'kosong' => 'Belum ada data.'])
{{--
    Kolom berpasangan per periode (mis. masuk vs selesai per bulan).

    @param list<array{label: string, bulan: string}&array<string, int>> $data
    @param list<array{kunci: string, nama: string, warna: string}> $seri
--}}
@php
    $nilaiTerbesar = 0;
    foreach ($data as $titik) {
        foreach ($seri as $satu) {
            $nilaiTerbesar = max($nilaiTerbesar, (int) $titik[$satu['kunci']]);
        }
    }

    // Skala sumbu: kelipatan 1–2–5 yang rapi, empat garis bantu.
    $mentah = max(1, $nilaiTerbesar) / 4;
    $besaran = 10 ** floor(log10($mentah));
    $normal = $mentah / $besaran;
    $langkah = max(1, (int) (($normal <= 1 ? 1 : ($normal <= 2 ? 2 : ($normal <= 5 ? 5 : 10))) * $besaran));
    $puncak = $langkah * max(1, (int) ceil(max(1, $nilaiTerbesar) / $langkah));
    $garis = range(0, $puncak, $langkah);
    $jumlahTitik = count($data);
@endphp

@if ($nilaiTerbesar === 0)
    <p class="m-0 rounded-xl bg-permukaan-2 px-4 py-8 text-center text-sm text-redup">{{ $kosong }}</p>
@else
    <ul class="mb-3 flex flex-wrap gap-x-5 gap-y-1 text-sm text-redup" aria-label="Keterangan">
        @foreach ($seri as $satu)
            <li class="flex items-center gap-2"><span class="size-2.5 rounded-[3px]" style="background: {{ $satu['warna'] }}"></span>{{ $satu['nama'] }}</li>
        @endforeach
    </ul>

    <div class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-2" role="img" aria-label="Grafik kolom {{ collect($seri)->pluck('nama')->implode(' dan ') }} per bulan; angka lengkap ada di tabel di bawahnya.">
        {{-- Sumbu Y --}}
        <div class="relative h-48 w-6 text-right text-[11px] text-redup tabular-nums">
            @foreach ($garis as $nilai)
                <span class="absolute right-0 translate-y-1/2 leading-none" style="bottom: {{ $nilai / $puncak * 100 }}%">{{ $nilai }}</span>
            @endforeach
        </div>

        {{-- Bidang gambar --}}
        <div class="relative h-48 border-b" style="border-color: var(--c-dasar)">
            @foreach ($garis as $nilai)
                @if ($nilai > 0)
                    <span class="absolute inset-x-0 h-px" style="bottom: {{ $nilai / $puncak * 100 }}%; background: var(--c-kisi)"></span>
                @endif
            @endforeach

            <div class="absolute inset-0 flex items-end">
                @foreach ($data as $indeks => $titik)
                    @php
                        $ringkas = $titik['bulan'].': '.collect($seri)->map(fn ($satu) => $satu['nama'].' '.$titik[$satu['kunci']])->implode(', ');
                        $posisi = $indeks < 2 ? 'left-0' : ($indeks >= $jumlahTitik - 2 ? 'right-0' : 'left-1/2 -translate-x-1/2');
                    @endphp
                    <div tabindex="0" aria-label="{{ $ringkas }}"
                         class="group relative flex h-full min-w-0 flex-1 items-end justify-center gap-[2px] rounded-md outline-none hover:bg-permukaan-2 focus-visible:bg-permukaan-2 focus-visible:ring-2 focus-visible:ring-utama/30">
                        @foreach ($seri as $satu)
                            @php $nilai = (int) $titik[$satu['kunci']]; @endphp
                            <span class="block w-[38%] max-w-[14px] rounded-t-[4px]"
                                  style="height: {{ $nilai > 0 ? max(2, $nilai / $puncak * 100) : 0 }}%; background: {{ $satu['warna'] }}"></span>
                        @endforeach

                        <span class="pointer-events-none absolute bottom-full z-20 mb-2 hidden min-w-36 rounded-xl border border-garis bg-permukaan px-3 py-2 text-xs shadow-angkat group-hover:block group-focus-visible:block {{ $posisi }}">
                            <span class="mb-1 block font-semibold whitespace-nowrap text-teks">{{ $titik['bulan'] }}</span>
                            @foreach ($seri as $satu)
                                <span class="flex items-center gap-2 whitespace-nowrap">
                                    <span class="h-0.5 w-3 rounded-full" style="background: {{ $satu['warna'] }}"></span>
                                    <strong class="text-teks tabular-nums">{{ $titik[$satu['kunci']] }}</strong>
                                    <span class="text-redup">{{ $satu['nama'] }}</span>
                                </span>
                            @endforeach
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Sumbu X --}}
        <div></div>
        <div class="mt-1.5 flex">
            @foreach ($data as $titik)
                <span class="min-w-0 flex-1 truncate text-center text-[11px] text-redup">{{ $titik['label'] }}</span>
            @endforeach
        </div>
    </div>

    <details class="mt-4 text-sm">
        <summary class="text-redup hover:text-teks">Lihat sebagai tabel</summary>
        <div class="gulir mt-2">
            <table>
                <thead><tr><th>Bulan</th>@foreach ($seri as $satu)<th class="text-right">{{ $satu['nama'] }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($data as $titik)
                        <tr><td>{{ $titik['bulan'] }}</td>@foreach ($seri as $satu)<td class="text-right tabular-nums">{{ $titik[$satu['kunci']] }}</td>@endforeach</tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
@endif
