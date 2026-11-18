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
        <ul style="margin-top: 6px">
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
                    <td style="white-space: nowrap">
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
        <p style="margin: 8px 0 0">{{ $permintaan->topik_lainnya }}</p>
    @endif

    <h2 style="margin-top: 18px">Cerita anggota</h2>
    <p style="white-space: pre-line">{{ $permintaan->uraian }}</p>
    @if ($permintaan->harapan)
        <h2>Harapan dari pendampingan</h2>
        <p style="white-space: pre-line; margin: 0">{{ $permintaan->harapan }}</p>
    @endif
</section>

<section class="kartu">
    <h2>Asesmen dasar</h2>
    <p class="redup kecil">Hasil skrining, bukan diagnosis. Gunakan sebagai bahan pendalaman bersama anggota.</p>
    @forelse ($permintaan->jawabanAsesmen as $jawaban)
        @php $isian = $jawaban->isian; @endphp
        <div style="padding: 10px 0; border-top: 1px solid var(--garis)">
            <strong>{{ $jawaban->nama_instrumen }}</strong>
            @if ($jawaban->kritis)
                <span class="lencana peringatan">Butir kritis</span>
            @endif
            <table style="margin-top: 6px">
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
                <p class="kecil" style="margin: 6px 0 0; color: var(--galat)">⚠ {{ $butirKritis }}</p>
            @endforeach
            <details style="margin-top: 6px">
                <summary class="kecil">Lihat jawaban per butir</summary>
                <table style="margin-top: 6px">
                    <tbody>
                        @foreach ($isian['instrumen']['butir'] ?? [] as $nomor => $butir)
                            @php
                                $nilai = $isian['jawaban'][$butir['kunci']] ?? null;
                                $label = collect(PenilaiAsesmen::pilihanButir($isian['instrumen'], $butir))->first(fn ($pilihan) => (string) $pilihan['nilai'] === (string) $nilai)['label'] ?? '—';
                            @endphp
                            <tr><td class="kecil">{{ $nomor + 1 }}. {{ $butir['teks'] }}</td><td class="kecil" style="white-space: nowrap">{{ $label }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </details>
        </div>
    @empty
        <p class="redup" style="margin: 0">Tidak ada asesmen yang diisi.</p>
    @endforelse
</section>
