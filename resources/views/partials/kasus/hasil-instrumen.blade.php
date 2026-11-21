{{--
    Skor, butir kritis, dan jawaban per butir dari satu instrumen yang sudah
    diisi. Hanya untuk konselor pendamping dan komite.

    @param string $nama
    @param array $isian  ['instrumen' => salinan, 'jawaban' => [...], 'skor' => [...], 'kritis' => [...]]
    @param bool $kritis
    @param string|null $keterangan  baris kecil di samping nama, mis. waktu diisi
--}}
@php
    use App\Models\JawabanAsesmen;
    use App\Services\Asesmen\PenilaiAsesmen;
@endphp
<div class="border-t border-garis py-3 first:border-t-0">
    <strong>{{ $nama }}</strong>
    @if ($kritis)
        <span class="lencana peringatan">Butir kritis</span>
    @endif
    @if ($keterangan ?? null)
        <span class="redup kecil">· {{ $keterangan }}</span>
    @endif
    <table class="mt-1.5">
        <thead><tr><th>Skor</th><th>Nilai</th><th>Tafsiran</th><th>Saran</th></tr></thead>
        <tbody>
            @foreach ($isian['skor'] ?? [] as $skor)
                <tr>
                    <td>{{ $skor['nama'] }}</td>
                    <td>{{ JawabanAsesmen::angka($skor['nilai']) }}</td>
                    <td>{{ $skor['label'] }}</td>
                    <td class="kecil">{{ $skor['saran'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    @foreach ($isian['kritis'] ?? [] as $butirKritis)
        <p class="kecil mt-1.5 mb-0 text-bahaya">⚠ {{ $butirKritis }}</p>
    @endforeach
    <details class="mt-1.5">
        <summary class="kecil">Lihat jawaban per butir</summary>
        <table class="mt-1.5">
            <tbody>
                @foreach ($isian['instrumen']['butir'] ?? [] as $nomor => $butir)
                    @php
                        $nilai = $isian['jawaban'][$butir['kunci']] ?? null;
                        $label = collect(PenilaiAsesmen::pilihanButir($isian['instrumen'], $butir))->first(fn ($pilihan) => (string) $pilihan['nilai'] === (string) $nilai)['label'] ?? '—';
                    @endphp
                    <tr><td class="kecil">{{ $nomor + 1 }}. {{ $butir['teks'] }}</td><td class="kecil whitespace-nowrap">{{ $label }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </details>
</div>
