{{--
    Penutupan proses konsultasi.

    @param \App\Models\Permintaan $permintaan
    @param string $peran  anggota | konselor | komite
--}}
@php
    use App\Enums\StatusPermintaan;
    $status = $permintaan->status;
    $menungguAnggota = $status === StatusPermintaan::Berlangsung && $permintaan->penutupan_diusulkan_pada !== null;
@endphp

@if ($status === StatusPermintaan::Selesai)
    <section class="kartu">
        <h2>Proses konsultasi selesai</h2>
        <p class="redup m-0">Ditutup pada {{ $permintaan->selesai_pada?->format('d/m/Y') }}.</p>
        @if ($permintaan->alasan_penutupan && $peran !== 'anggota')
            <p class="kecil mt-2 mb-0">Alasan penutupan: {{ $permintaan->alasan_penutupan }}</p>
        @endif
    </section>
@elseif ($menungguAnggota || $status === StatusPermintaan::MenungguPenutupan || ($peran === 'konselor' && $status === StatusPermintaan::Berlangsung) || ($permintaan->catatan_tinjauan_penutupan && $peran === 'konselor'))
    <section class="kartu" id="penutupan">
        <h2>Penutupan proses konsultasi</h2>

        @if ($permintaan->catatan_tinjauan_penutupan && $status === StatusPermintaan::Berlangsung && $peran !== 'anggota')
            <div class="catatan">Komite mengembalikan usulan penutupan sebelumnya: {{ $permintaan->catatan_tinjauan_penutupan }}</div>
        @endif

        @if ($menungguAnggota)
            <p><strong>Konselor mengusulkan menutup proses konsultasi.</strong></p>
            <p class="whitespace-pre-line">{{ $permintaan->alasan_penutupan }}</p>
            @if ($peran === 'anggota')
                <p class="redup kecil">Penutupan hanya berjalan bila Anda setuju, lalu ditinjau komite. Bila masih ingin didampingi, pilih "Lanjutkan pendampingan".</p>
                <form method="POST" action="{{ route('penutupan.tanggapi', $permintaan) }}" class="baris-aksi">
                    @csrf
                    <button type="submit" name="keputusan" value="setuju" class="tombol">Setuju ditutup</button>
                    <button type="submit" name="keputusan" value="tolak" class="tombol sekunder">Lanjutkan pendampingan</button>
                </form>
            @else
                <p class="redup kecil m-0">Menunggu tanggapan anggota.</p>
            @endif
        @elseif ($status === StatusPermintaan::MenungguPenutupan)
            <p><strong>Anggota menyetujui penutupan.</strong> Menunggu persetujuan komite.</p>
            <p class="whitespace-pre-line">{{ $permintaan->alasan_penutupan }}</p>
            @if ($peran === 'komite')
                <form method="POST" action="{{ route('penutupan.putuskan', $permintaan) }}">
                    @csrf
                    <div class="bidang">
                        <label for="catatan-tinjauan">Catatan untuk konselor (wajib bila dikembalikan)</label>
                        <textarea id="catatan-tinjauan" name="catatan" rows="2" maxlength="2000">{{ old('catatan') }}</textarea>
                    </div>
                    <div class="baris-aksi">
                        <button type="submit" name="keputusan" value="setuju" class="tombol">Setujui penutupan</button>
                        <button type="submit" name="keputusan" value="kembalikan" class="tombol sekunder">Kembalikan, lanjutkan pendampingan</button>
                    </div>
                </form>
            @endif
        @elseif ($peran === 'konselor' && auth()->user()->can('tangani', $permintaan))
            <details @if ($errors->has('alasan')) open @endif>
                <summary><strong>Usulkan penutupan</strong></summary>
                <p class="redup kecil mt-1.5">Usulkan bila tujuan pendampingan tercapai atau anggota sudah dirujuk. Anggota harus menyetujui, lalu komite meninjau. Pastikan catatan sesi sudah lengkap.</p>
                <form method="POST" action="{{ route('penutupan.usul', $permintaan) }}">
                    @csrf
                    <div class="bidang">
                        <label for="alasan-penutupan">Alasan dan ringkasan untuk anggota</label>
                        <textarea id="alasan-penutupan" name="alasan" rows="3" maxlength="2000" required>{{ old('alasan') }}</textarea>
                        <p class="bantuan">Teks ini dibaca anggota.</p>
                    </div>
                    <button type="submit" class="tombol sekunder">Kirim usulan penutupan</button>
                </form>
            </details>
        @endif
    </section>
@endif

@if ($peran === 'komite' && $status === StatusPermintaan::Berlangsung && auth()->user()->can('kelola', $permintaan))
    <section class="kartu" id="tutup-layanan">
        <h2>Tutup layanan konsultasi</h2>
        @if ($menggantung ?? false)
            <div class="catatan">Tidak ada aktivitas percakapan maupun pertemuan lebih dari {{ \App\Models\Permintaan::JAM_MENGGANTUNG }} jam.</div>
        @endif
        <details @if ($errors->has('alasan') && old('_tutup')) open @endif>
            <summary><strong>Tutup dan tandai selesai</strong></summary>
            <p class="redup kecil mt-1.5">Untuk pendampingan yang sudah tuntas atau menggantung tanpa kabar. Tidak perlu usulan konselor maupun persetujuan anggota. Pertemuan yang belum berlangsung ikut dibatalkan. Anggota masih dapat membuka kembali dalam {{ \App\Models\Permintaan::HARI_BUKA_KEMBALI }} hari.</p>
            <form method="POST" action="{{ route('penutupan.tutup', $permintaan) }}" onsubmit="return confirm('Tutup proses konsultasi ini dan tandai selesai? Anggota dan konselor akan diberi tahu.')">
                @csrf
                <input type="hidden" name="_tutup" value="1">
                <div class="bidang">
                    <label for="alasan-tutup">Alasan penutupan</label>
                    <textarea id="alasan-tutup" name="alasan" rows="2" maxlength="2000" required>{{ old('_tutup') ? old('alasan') : '' }}</textarea>
                    <p class="bantuan">Dibaca konselor dan komite, tidak ditampilkan ke anggota.</p>
                </div>
                <button type="submit" class="tombol bahaya">Tutup dan tandai selesai</button>
            </form>
        </details>
    </section>
@endif
