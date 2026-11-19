{{--
    Daftar baris yang dapat diubah (keunggulan, langkah, tanya jawab).
    Baris yang dikosongkan dihapus saat disimpan; tersedia baris kosong tambahan.

    @param string $nama  nama bidang formulir, mis. halaman_faq
    @param list<array{kunci: string, label: string, panjang: int, area?: bool}> $kolom
    @param list<array<string, string>> $baris
    @param string $isiBaru  label untuk baris kosong
--}}
@php
    $daftar = array_values(old($nama, $baris));
    $daftar = array_merge($daftar, array_fill(0, 2, []));
@endphp
<ol class="space-y-3">
    @foreach ($daftar as $indeks => $satu)
        @php $kosong = $indeks >= count($daftar) - 2; @endphp
        <li @class(['rounded-xl border p-4', 'border-garis bg-permukaan-2' => ! $kosong, 'border-dashed border-garis' => $kosong])>
            <p class="mb-2 text-xs font-semibold tracking-wide text-redup uppercase">{{ $kosong ? $isiBaru : 'Butir '.($indeks + 1) }}</p>
            <div class="grid gap-3">
                @foreach ($kolom as $satuKolom)
                    @php $id = $nama.'-'.$indeks.'-'.$satuKolom['kunci']; @endphp
                    <div>
                        <label for="{{ $id }}" class="sr-only">{{ $satuKolom['label'] }} {{ $indeks + 1 }}</label>
                        @if ($satuKolom['area'] ?? false)
                            <textarea id="{{ $id }}" name="{{ $nama }}[{{ $indeks }}][{{ $satuKolom['kunci'] }}]" rows="2" maxlength="{{ $satuKolom['panjang'] }}" placeholder="{{ $satuKolom['label'] }}" class="min-h-16">{{ $satu[$satuKolom['kunci']] ?? '' }}</textarea>
                        @else
                            <input type="text" id="{{ $id }}" name="{{ $nama }}[{{ $indeks }}][{{ $satuKolom['kunci'] }}]" maxlength="{{ $satuKolom['panjang'] }}" placeholder="{{ $satuKolom['label'] }}" value="{{ $satu[$satuKolom['kunci']] ?? '' }}" class="font-semibold">
                        @endif
                    </div>
                @endforeach
            </div>
        </li>
    @endforeach
</ol>
<p class="bantuan">Kosongkan seluruh isi sebuah butir untuk menghapusnya. Perlu lebih banyak? Simpan dulu; dua baris kosong baru akan muncul.</p>
