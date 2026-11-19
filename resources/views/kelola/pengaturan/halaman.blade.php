@php
    $sakelar = fn (string $nama, string $kunci, string $teks) => '<input type="hidden" name="'.$nama.'" value="0"><label class="pilihan"><input type="checkbox" name="'.$nama.'" value="1" '.(old($nama, $nilai[$kunci]) ? 'checked' : '').'> '.e($teks).'</label>';
@endphp
<x-kelola judul="Halaman depan & kontak" aktif="halaman">
    <x-slot:keterangan>Atur isi halaman depan publik SPK Rangkul, kartu masuk, dan kontak yang tampil bagi anggota.</x-slot:keterangan>

    <div class="mb-6 flex flex-wrap items-center gap-3">
        <a class="tombol sekunder" href="{{ route('depan') }}" target="_blank" rel="noopener"><x-ikon nama="panah-kanan" class="size-4" /> Lihat halaman depan</a>
        <span class="text-sm text-redup">Membuka di tab baru. Simpan dulu agar perubahan tampil.</span>
    </div>

    <form method="POST" action="{{ route('kelola.pengaturan.halaman') }}">
        @csrf
        @method('PUT')

        <fieldset>
            <legend>Pembuka</legend>
            <p class="bantuan mb-4">Bagian paling atas halaman depan.</p>
            <div class="bidang">
                <label for="halaman_label">Label kecil di atas judul</label>
                <input type="text" id="halaman_label" name="halaman_label" maxlength="60" value="{{ old('halaman_label', $nilai['halaman.label']) }}">
            </div>
            <div class="bidang">
                <label for="halaman_tajuk">Judul utama</label>
                <textarea id="halaman_tajuk" name="halaman_tajuk" rows="2" maxlength="160" class="min-h-16">{{ old('halaman_tajuk', $nilai['halaman.tajuk']) }}</textarea>
            </div>
            <div class="bidang">
                <label for="halaman_subjudul">Kalimat penjelas</label>
                <input type="text" id="halaman_subjudul" name="halaman_subjudul" maxlength="255" value="{{ old('halaman_subjudul', $nilai['halaman.subjudul']) }}">
                <p class="bantuan">Juga dipakai sebagai deskripsi halaman di mesin pencari dan di kartu masuk.</p>
            </div>
            <div class="dua-kolom">
                <div class="bidang">
                    <label for="halaman_teks_tombol">Teks tombol masuk</label>
                    <input type="text" id="halaman_teks_tombol" name="halaman_teks_tombol" maxlength="40" value="{{ old('halaman_teks_tombol', $nilai['halaman.teks_tombol']) }}">
                </div>
                <div class="bidang">
                    <label for="halaman_judul">Nama layanan di kartu masuk</label>
                    <input type="text" id="halaman_judul" name="halaman_judul" maxlength="120" required value="{{ old('halaman_judul', $nilai['halaman.judul']) }}">
                </div>
            </div>
        </fieldset>

        <fieldset>
            <legend>Tentang layanan</legend>
            <div class="bidang">
                <label for="halaman_judul_tentang">Judul bagian</label>
                <input type="text" id="halaman_judul_tentang" name="halaman_judul_tentang" maxlength="80" value="{{ old('halaman_judul_tentang', $nilai['halaman.judul_tentang']) }}">
            </div>
            <div class="bidang">
                <label for="halaman_isi">Penjelasan layanan</label>
                <textarea id="halaman_isi" name="halaman_isi" rows="5" maxlength="5000">{{ old('halaman_isi', $nilai['halaman.isi']) }}</textarea>
                <p class="bantuan">Teks biasa; baris baru dipertahankan. Tampil di halaman depan dan di kartu masuk.</p>
            </div>
            <div class="bidang mb-3">{!! $sakelar('halaman_tampil_keunggulan', 'halaman.tampil_keunggulan', 'Tampilkan kartu keunggulan') !!}</div>
            @include('kelola.pengaturan.daftar-baris', [
                'nama' => 'halaman_keunggulan',
                'baris' => $nilai['halaman.keunggulan'],
                'isiBaru' => 'Keunggulan baru',
                'kolom' => [['kunci' => 'judul', 'label' => 'Judul singkat', 'panjang' => 80], ['kunci' => 'teks', 'label' => 'Penjelasan', 'panjang' => 300, 'area' => true]],
            ])
        </fieldset>

        <fieldset>
            <legend>Cara kerja</legend>
            <div class="bidang mb-3">{!! $sakelar('halaman_tampil_langkah', 'halaman.tampil_langkah', 'Tampilkan bagian cara kerja') !!}</div>
            @include('kelola.pengaturan.daftar-baris', [
                'nama' => 'halaman_langkah',
                'baris' => $nilai['halaman.langkah'],
                'isiBaru' => 'Langkah baru',
                'kolom' => [['kunci' => 'judul', 'label' => 'Nama langkah', 'panjang' => 80], ['kunci' => 'teks', 'label' => 'Penjelasan', 'panjang' => 300, 'area' => true]],
            ])
        </fieldset>

        <fieldset>
            <legend>Tanya jawab</legend>
            <div class="bidang mb-3">{!! $sakelar('halaman_tampil_faq', 'halaman.tampil_faq', 'Tampilkan bagian tanya jawab') !!}</div>
            @include('kelola.pengaturan.daftar-baris', [
                'nama' => 'halaman_faq',
                'baris' => $nilai['halaman.faq'],
                'isiBaru' => 'Pertanyaan baru',
                'kolom' => [['kunci' => 'pertanyaan', 'label' => 'Pertanyaan', 'panjang' => 200], ['kunci' => 'jawaban', 'label' => 'Jawaban', 'panjang' => 1000, 'area' => true]],
            ])
        </fieldset>

        <fieldset>
            <legend>Kontak</legend>
            <div class="bidang">
                <label for="kontak_email">Surel</label>
                <input type="email" id="kontak_email" name="kontak_email" maxlength="255" value="{{ old('kontak_email', $nilai['kontak.email']) }}">
            </div>
            <div class="bidang">
                <label for="kontak_whatsapp">WhatsApp</label>
                <input type="text" id="kontak_whatsapp" name="kontak_whatsapp" maxlength="30" inputmode="tel" placeholder="0812 3456 7890" value="{{ old('kontak_whatsapp', $nilai['kontak.whatsapp']) }}">
            </div>
            <div class="bidang">
                <label for="kontak_jam_layanan">Jam layanan</label>
                <input type="text" id="kontak_jam_layanan" name="kontak_jam_layanan" maxlength="255" placeholder="Senin–Jumat, 09.00–17.00 WIB" value="{{ old('kontak_jam_layanan', $nilai['kontak.jam_layanan']) }}">
            </div>
            <div class="bidang">
                <label for="kontak_darurat">Pesan keadaan darurat</label>
                <textarea id="kontak_darurat" name="kontak_darurat" rows="3" maxlength="1000">{{ old('kontak_darurat', $nilai['kontak.darurat']) }}</textarea>
                <p class="bantuan">Tampil menonjol di halaman depan dan beranda. Peer konselor bukan layanan gawat darurat — cantumkan nomor yang dapat dihubungi saat krisis.</p>
            </div>
        </fieldset>

        <fieldset>
            <legend>Mesin pencari</legend>
            <div class="bidang">
                {!! $sakelar('halaman_indeks', 'halaman.indeks', 'Izinkan mesin pencari (Google, dll.) menampilkan halaman depan') !!}
                <p class="bantuan">Hanya halaman depan yang dapat diindeks; halaman lain selalu tertutup bagi mesin pencari.</p>
            </div>
        </fieldset>

        <div class="sticky bottom-0 -mx-4 border-t border-garis bg-latar/90 px-4 py-3 backdrop-blur sm:-mx-8 sm:px-8">
            <button type="submit" class="tombol">Simpan halaman depan</button>
        </div>
    </form>
</x-kelola>
