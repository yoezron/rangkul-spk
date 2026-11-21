{{--
    Isian lengkap permintaan untuk konselor pendamping dan komite.

    @param \App\Models\Permintaan $permintaan
--}}
@php
    use App\Models\Permintaan;
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
        @include('partials.kasus.hasil-instrumen', ['nama' => $jawaban->nama_instrumen, 'isian' => $jawaban->isian, 'kritis' => $jawaban->kritis])
    @empty
        <p class="redup m-0">Tidak ada asesmen yang diisi.</p>
    @endforelse
</section>
