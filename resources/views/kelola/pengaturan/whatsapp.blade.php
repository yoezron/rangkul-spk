<x-kelola judul="WhatsApp (Fonnte)" aktif="whatsapp">
    <x-slot:keterangan>Gerbang pengirim notifikasi WhatsApp lewat layanan Fonnte (fonnte.com). Token didapat dari menu Device di dashboard Fonnte.</x-slot:keterangan>

    <div class="catatan" role="note">
        Pesan WhatsApp melewati server Fonnte dan nomor pengirim, lalu tampil di layar kunci ponsel penerima.
        Notifikasi Rangkul hanya berisi ajakan umum untuk membuka Rangkul — tidak pernah isi cerita, topik konseling,
        atau keterangan bahwa seseorang sedang mencari bantuan.
    </div>

    <form method="POST" action="{{ route('kelola.pengaturan.whatsapp') }}">
        @csrf
        @method('PUT')

        <fieldset>
            <legend>Fonnte</legend>
            <div class="bidang">
                <input type="hidden" name="whatsapp_aktif" value="0">
                <label class="pilihan">
                    <input type="checkbox" name="whatsapp_aktif" value="1" @checked(old('whatsapp_aktif', $nilai['whatsapp.aktif']))>
                    Kirim notifikasi WhatsApp lewat Fonnte
                </label>
            </div>
            <div class="bidang">
                <label for="whatsapp_token">Token perangkat</label>
                <input type="password" id="whatsapp_token" name="whatsapp_token" maxlength="255" autocomplete="new-password">
                <p class="bantuan">
                    @if ($adaToken)
                        <span class="lencana">Token tersimpan</span> Kosongkan untuk mempertahankan token lama.
                    @else
                        Belum ada token tersimpan.
                    @endif
                    Token disimpan terenkripsi dan tidak pernah ditampilkan kembali. Gunakan token perangkat, bukan token akun.
                </p>
                @if ($adaToken)
                    <label class="pilihan">
                        <input type="checkbox" name="whatsapp_hapus_token" value="1"> Hapus token tersimpan
                    </label>
                @endif
            </div>
            <div class="bidang">
                <label for="whatsapp_kode_negara">Kode negara</label>
                <input type="text" id="whatsapp_kode_negara" name="whatsapp_kode_negara" maxlength="4" inputmode="numeric" required value="{{ old('whatsapp_kode_negara', $nilai['whatsapp.kode_negara']) }}">
                <p class="bantuan">Pengganti angka 0 di depan nomor tujuan (62 untuk Indonesia). Isi 0 bila nomor selalu ditulis lengkap dengan kode negara.</p>
            </div>
        </fieldset>

        <button type="submit" class="tombol">Simpan</button>
    </form>

    <div class="dua-kolom mt-6">
        <section class="kartu">
            <h2>Status perangkat</h2>
            @if ($perangkat)
                <table>
                    <tbody>
                        <tr><th>Nomor</th><td>{{ $perangkat['nomor'] }}</td></tr>
                        <tr><th>Nama</th><td>{{ $perangkat['nama'] }}</td></tr>
                        <tr>
                            <th>Sambungan</th>
                            <td>
                                @if ($perangkat['status'] === 'connect')
                                    <span class="lencana">Tersambung</span>
                                @else
                                    <span class="lencana peringatan">{{ $perangkat['status'] === 'disconnect' ? 'Terputus' : ($perangkat['status'] ?: 'Tidak diketahui') }}</span>
                                @endif
                            </td>
                        </tr>
                        <tr><th>Sisa kuota</th><td>{{ $perangkat['kuota'] }}</td></tr>
                        <tr><th>Paket</th><td>{{ $perangkat['paket'] }}</td></tr>
                        <tr><th>Berlaku sampai</th><td>{{ $perangkat['kedaluwarsa'] }}</td></tr>
                    </tbody>
                </table>
                @if ($perangkat['status'] === 'disconnect')
                    <p class="bantuan">Perangkat terputus: pindai ulang kode QR di dashboard Fonnte. Selama terputus, pesan tidak terkirim.</p>
                @endif
            @else
                <p class="redup">Periksa apakah ponsel pengirim tersambung dan berapa sisa kuotanya.</p>
            @endif
            <form method="POST" action="{{ route('kelola.pengaturan.whatsapp.periksa') }}">
                @csrf
                <button type="submit" class="tombol sekunder" @disabled(! $adaToken)>Periksa perangkat</button>
            </form>
        </section>

        <section class="kartu">
            <h2>Kirim pesan uji</h2>
            <form method="POST" action="{{ route('kelola.pengaturan.whatsapp.uji') }}">
                @csrf
                <div class="bidang">
                    <label for="nomor_uji">Nomor tujuan</label>
                    <input type="text" id="nomor_uji" name="nomor_uji" maxlength="20" inputmode="tel" required value="{{ old('nomor_uji', $nomorUji) }}">
                    <p class="bantuan">Bawaannya nomor akun SPK Anda.</p>
                </div>
                <button type="submit" class="tombol sekunder" @disabled(! $siap)>Kirim pesan uji</button>
            </form>
        </section>
    </div>
</x-kelola>
