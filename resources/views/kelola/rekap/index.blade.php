<x-kelola judul="Rekap & unduhan" aktif="rekap">
    <x-slot:keterangan>Berkas CSV yang dapat dibuka dengan Excel, LibreOffice, atau Google Sheets.</x-slot:keterangan>

    <div class="catatan" role="note">
        Berkas rekap memuat data pribadi anggota — rekap permintaan dan percakapan memuat cerita, hasil asesmen, dan catatan konselor apa adanya.
        Simpan di perangkat yang terkunci, jangan dibagikan lewat grup percakapan atau surel, dan hapus bila tidak lagi diperlukan.
        Setiap unduhan tercatat di audit log.
    </div>

    @foreach ($jenis as $kunci => $keterangan)
        <section class="kartu">
            <h2>{{ $keterangan['judul'] }} <span class="redup kecil">· {{ number_format($jumlah[$kunci], 0, ',', '.') }} baris</span></h2>
            <p class="redup">{{ $keterangan['keterangan'] }}</p>
            <form method="GET" action="{{ route('kelola.rekap.unduh', $kunci) }}" class="baris-aksi">
                <div style="flex: 0 1 300px">
                    <label for="pemisah-{{ $kunci }}" class="kecil">Pemisah kolom</label>
                    <select id="pemisah-{{ $kunci }}" name="pemisah">
                        <option value="koma">Koma (standar)</option>
                        <option value="titik_koma">Titik koma (Excel berbahasa Indonesia)</option>
                    </select>
                </div>
                <button type="submit" class="tombol" style="align-self: flex-end">Unduh CSV</button>
            </form>
        </section>
    @endforeach
</x-kelola>
