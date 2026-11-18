<x-kelola judul="Halaman depan & kontak" aktif="halaman">
    <x-slot:keterangan>Isi halaman yang dilihat anggota sebelum masuk, dan kontak yang tampil di halaman depan serta beranda.</x-slot:keterangan>

    <form method="POST" action="{{ route('kelola.pengaturan.halaman') }}">
        @csrf
        @method('PUT')

        <fieldset>
            <legend>Halaman depan</legend>
            <div class="bidang">
                <label for="halaman_judul">Judul</label>
                <input type="text" id="halaman_judul" name="halaman_judul" maxlength="120" required value="{{ old('halaman_judul', $nilai['halaman.judul']) }}">
            </div>
            <div class="bidang">
                <label for="halaman_subjudul">Subjudul</label>
                <input type="text" id="halaman_subjudul" name="halaman_subjudul" maxlength="255" value="{{ old('halaman_subjudul', $nilai['halaman.subjudul']) }}">
            </div>
            <div class="bidang">
                <label for="halaman_isi">Penjelasan layanan</label>
                <textarea id="halaman_isi" name="halaman_isi" rows="6" maxlength="5000">{{ old('halaman_isi', $nilai['halaman.isi']) }}</textarea>
                <p class="bantuan">Teks biasa; baris baru dipertahankan. Tampil di atas tombol masuk.</p>
            </div>
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

        <button type="submit" class="tombol">Simpan</button>
    </form>
</x-kelola>
