<x-kelola judul="Surel (Brevo)" aktif="surel">
    <x-slot:keterangan>Rangkul mengirim surel lewat relay SMTP Brevo. Selama belum diaktifkan di sini, pengaturan surel dari berkas .env yang berlaku.</x-slot:keterangan>

    <section class="kartu">
        <h2>Menyiapkan Brevo</h2>
        <ol class="m-0 list-decimal space-y-2 pl-5 text-[15px]">
            <li>Di Brevo, buka <strong>SMTP &amp; API</strong>, tab <strong>SMTP</strong>. Salin <em>SMTP server</em>, <em>Port</em>, dan <em>Login</em> ke formulir di bawah.</li>
            <li>Di tab yang sama, buat <strong>SMTP key</strong> baru dan isikan sebagai sandi. Bukan API key (berawalan <code>xkeysib-</code>) dan bukan password akun Brevo.</li>
            <li>Daftarkan dan verifikasi alamat pengirim (mis. <code>rangkul@spk.or.id</code>) di menu <strong>Senders, Domains &amp; Dedicated IPs</strong>. Brevo menolak surel dari pengirim yang belum terverifikasi.</li>
            <li>Agar surel tidak masuk spam, autentikasi domain <code>spk.or.id</code> di menu yang sama (catatan DNS DKIM dan DMARC dari Brevo).</li>
        </ol>
    </section>

    <form method="POST" action="{{ route('kelola.pengaturan.surel') }}">
        @csrf
        @method('PUT')

        <fieldset>
            <legend>SMTP Brevo</legend>
            <div class="bidang">
                <input type="hidden" name="surel_aktif" value="0">
                <label class="pilihan">
                    <input type="checkbox" name="surel_aktif" value="1" @checked(old('surel_aktif', $nilai['surel.aktif']))>
                    Kirim surel lewat Brevo
                </label>
            </div>
            <div class="bidang">
                <label for="surel_host">SMTP server</label>
                <input type="text" id="surel_host" name="surel_host" maxlength="255" placeholder="smtp-relay.brevo.com" value="{{ old('surel_host', $nilai['surel.host']) }}">
                <p class="bantuan">Biarkan sesuai yang tertera di tab SMTP Brevo.</p>
            </div>
            <div class="dua-kolom">
                <div class="bidang">
                    <label for="surel_port">Port</label>
                    <input type="number" id="surel_port" name="surel_port" min="1" max="65535" required value="{{ old('surel_port', $nilai['surel.port']) }}">
                    <p class="bantuan">587 (disarankan). Pakai 2525 bila hosting memblokir 587.</p>
                </div>
                <div class="bidang">
                    <label for="surel_enkripsi">Enkripsi</label>
                    <select id="surel_enkripsi" name="surel_enkripsi">
                        <option value="tls" @selected(old('surel_enkripsi', $nilai['surel.enkripsi']) === 'tls')>STARTTLS — port 587 atau 2525</option>
                        <option value="ssl" @selected(old('surel_enkripsi', $nilai['surel.enkripsi']) === 'ssl')>SSL/TLS — port 465</option>
                    </select>
                </div>
            </div>
            <div class="bidang">
                <label for="surel_username">Login</label>
                <input type="text" id="surel_username" name="surel_username" maxlength="255" autocomplete="off" value="{{ old('surel_username', $nilai['surel.username']) }}">
                <p class="bantuan">Login SMTP persis seperti di tab SMTP Brevo — bisa berbeda dari alamat surel akun Brevo Anda.</p>
            </div>
            <div class="bidang">
                <label for="surel_password">SMTP key</label>
                <input type="password" id="surel_password" name="surel_password" maxlength="255" autocomplete="new-password">
                <p class="bantuan">
                    @if ($adaSandi)
                        <span class="lencana">SMTP key tersimpan</span> Kosongkan untuk mempertahankan key lama.
                    @else
                        Belum ada SMTP key tersimpan.
                    @endif
                    Disimpan terenkripsi dan tidak pernah ditampilkan kembali.
                </p>
                @if ($adaSandi)
                    <label class="pilihan">
                        <input type="checkbox" name="surel_hapus_password" value="1"> Hapus SMTP key tersimpan
                    </label>
                @endif
            </div>
        </fieldset>

        <fieldset>
            <legend>Pengirim</legend>
            <div class="bidang">
                <label for="surel_dari_alamat">Alamat pengirim</label>
                <input type="email" id="surel_dari_alamat" name="surel_dari_alamat" maxlength="255" placeholder="rangkul@spk.or.id" value="{{ old('surel_dari_alamat', $nilai['surel.dari_alamat']) }}">
                <p class="bantuan">Harus sudah terverifikasi di Brevo.</p>
            </div>
            <div class="bidang">
                <label for="surel_dari_nama">Nama pengirim</label>
                <input type="text" id="surel_dari_nama" name="surel_dari_nama" maxlength="100" value="{{ old('surel_dari_nama', $nilai['surel.dari_nama']) }}">
            </div>
        </fieldset>

        <button type="submit" class="tombol">Simpan</button>
    </form>

    <section class="kartu mt-6">
        <h2>Kirim surel uji</h2>
        <p class="redup">Surel uji dikirim ke alamat akun SPK Anda ({{ auth()->user()->email ?: 'tidak ada' }}) memakai pengaturan yang sudah disimpan.</p>
        <form method="POST" action="{{ route('kelola.pengaturan.surel.uji') }}">
            @csrf
            <button type="submit" class="tombol sekunder" @disabled(! $surelSiap)>Kirim surel uji</button>
        </form>
    </section>
</x-kelola>
