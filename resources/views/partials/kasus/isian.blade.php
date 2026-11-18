{{--
    Isian lengkap permintaan untuk konselor pendamping dan komite.

    @param \App\Models\Permintaan $permintaan
--}}
@php
    use App\Models\JawabanAsesmen;
    use App\Models\Permintaan;
    use App\Services\Asesmen\PenilaiAsesmen;
    $anggota = $permintaan->anggota;
@endphp

@if ($permintaan->darurat)
    <div class="catatan galat" role="alert">
        <strong>DARURAT</strong> — kemungkinan risiko keselamatan. Hubungi anggota secepatnya; bila perlu arahkan ke layanan darurat.
        <ul class="mt-1.5">
            @foreach ($permintaan->alasan_darurat ?? [] as $alasan)
                <li>{{ $alasan }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="dua-kolom">
    <section class="kartu">
        <h2>Anggota</h2>
        <table>
            <tbody>
                <tr><th>Nama</th><td>{{ $anggota->nama }}</td></tr>
                <tr><th>No. anggota</th><td>{{ $anggota->nomor_anggota }}</td></tr>
                <tr><th>Kampus</th><td>{{ $anggota->kampus }}</td></tr>
                <tr><th>Wilayah</th><td>{{ $anggota->wilayah }}</td></tr>
                <tr><th>Gender</th><td>{{ $anggota->gender }}</td></tr>
                <tr><th>Peran SPK</th><td>{{ $anggota->peran }}</td></tr>
            </tbody>
        </table>
    </section>

    <section class="kartu">
        <h2>Preferensi layanan</h2>
        <table>
            <tbody>
                <tr><th>Bentuk</th><td>{{ Permintaan::BENTUK_LAYANAN[$permintaan->bentuk_layanan] ?? '—' }}</td></tr>
                <tr><th>Waktu luang</th><td>{{ $permintaan->waktu_luang ?: '—' }}</td></tr>
                <tr><th>Konselor</th><td>{{ Permintaan::GENDER_KONSELOR[$permintaan->gender_konselor] ?? '—' }}@if ($permintaan->hindari_kampus_sama)<br><span class="lencana peringatan">Minta dari kampus lain</span>@endif</td></tr>
                <tr><th>Diajukan</th><td>{{ $permintaan->diajukan_pada?->format('d/m/Y H.i') }}</td></tr>
            </tbody>
        </table>
    </section>
</div>

<section class="kartu">
    <h2>Pertanyaan keselamatan</h2>
    <table>
        <tbody>
            @foreach (Permintaan::KESELAMATAN as $kunci => $pertanyaan)
                @php $jawaban = $permintaan->keselamatan[$kunci] ?? null; @endphp
                <tr>
                    <td>{{ $pertanyaan['teks'] }}</td>
                    <td class="whitespace-nowrap">
                        @if ($jawaban && $jawaban !== 'tidak')
                            <span class="lencana peringatan">{{ $pertanyaan['pilihan'][$jawaban] ?? $jawaban }}</span>
                        @else
                            {{ $pertanyaan['pilihan'][$jawaban] ?? '—' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>

<section class="kartu">
    <h2>Yang ingin didiskusikan</h2>
    @foreach ($permintaan->labelTopik() as $label)
        <span class="lencana">{{ $label }}</span>
    @endforeach
    @if ($permintaan->topik_lainnya)
        <p class="mt-2 mb-0">{{ $permintaan->topik_lainnya }}</p>
    @endif

    <h2 class="mt-5">Cerita anggota</h2>
    <p class="whitespace-pre-line">{{ $permintaan->uraian }}</p>
    @if ($permintaan->harapan)
        <h2>Harapan dari pendampingan</h2>
        <p class="m-0 whitespace-pre-line">{{ $permintaan->harapan }}</p>
    @endif
</section>

<section class="kartu">
    <h2>Asesmen dasar</h2>
    <p class="redup kecil">Hasil skrining, bukan diagnosis. Gunakan sebagai bahan pendalaman bersama anggota.</p>
    @forelse ($permintaan->jawabanAsesmen as $jawaban)
        @php $isian = $jawaban->isian; @endphp
        <div class="border-t border-garis py-3 first:border-t-0">
            <strong>{{ $jawaban->nama_instrumen }}</strong>
            @if ($jawaban->kritis)
                <span class="lencana peringatan">Butir kritis</span>
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
    @empty
        <p class="redup m-0">Tidak ada asesmen yang diisi.</p>
    @endforelse
</section>
