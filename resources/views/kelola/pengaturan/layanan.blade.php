<x-kelola judul="Layanan & audit log" aktif="layanan">
    <form method="POST" action="{{ route('kelola.pengaturan.layanan') }}">
        @csrf
        @method('PUT')

        <fieldset>
            <legend>Status layanan</legend>
            <div class="bidang">
                <input type="hidden" name="layanan_buka" value="0">
                <label class="pilihan">
                    <input type="checkbox" name="layanan_buka" value="1" @checked(old('layanan_buka', $nilai['layanan.buka']))>
                    Layanan dibuka untuk anggota
                </label>
                <p class="bantuan">Saat ditutup, anggota tetap bisa masuk dan melihat pesan di bawah; pengelola tetap bisa membuka dashboard.</p>
            </div>
            <div class="bidang">
                <label for="layanan_pesan_tutup">Pesan saat layanan ditutup</label>
                <textarea id="layanan_pesan_tutup" name="layanan_pesan_tutup" rows="3" maxlength="1000" required>{{ old('layanan_pesan_tutup', $nilai['layanan.pesan_tutup']) }}</textarea>
            </div>
        </fieldset>

        <fieldset>
            <legend>Audit log</legend>
            <p class="bantuan mb-3">
                Audit log mencatat tindakan pengelola: perubahan pengaturan, penugasan peran, pembuatan peran, dan unduhan rekap.
                Kegiatan anggota tidak dicatat di sini.
            </p>
            <div class="bidang">
                <label for="audit_retensi_hari">Masa simpan (hari)</label>
                <input type="number" id="audit_retensi_hari" name="audit_retensi_hari" min="0" max="3650" required value="{{ old('audit_retensi_hari', $nilai['audit.retensi_hari']) }}">
                <p class="bantuan">Catatan yang lebih tua dihapus otomatis setiap malam. Isi 0 untuk menyimpan selamanya.</p>
            </div>
            <div class="bidang">
                <input type="hidden" name="audit_catat_ip" value="0">
                <label class="pilihan">
                    <input type="checkbox" name="audit_catat_ip" value="1" @checked(old('audit_catat_ip', $nilai['audit.catat_ip']))>
                    Catat alamat IP pengelola
                </label>
            </div>
        </fieldset>

        <button type="submit" class="tombol">Simpan</button>
    </form>
</x-kelola>
