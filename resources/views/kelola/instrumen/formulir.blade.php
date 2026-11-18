@php
    use App\Http\Controllers\Kelola\InstrumenController;

    $baru = ! $instrumen->exists;
    $tambah = 5;
    $pilihan = old('pilihan', $instrumen->pilihan ?? []);
    $subskala = old('subskala', collect($instrumen->subskala ?? [])->map(fn ($nama, $kunci) => ['kunci' => $kunci, 'nama' => $nama])->values()->all());
    $rentang = old('rentang', $instrumen->rentang ?? []);
    $butir = old('butir', collect($instrumen->butir ?? [])->map(fn ($satu) => [...$satu, 'pilihan_khusus' => InstrumenController::teksPilihanKhusus($satu['pilihan'] ?? null)])->all());
    $baris = fn (array $daftar, int $tambahan) => array_merge(array_values($daftar), array_fill(0, $tambahan, []));
@endphp
<x-kelola :judul="$baru ? 'Buat instrumen' : 'Ubah '.$instrumen->nama" aktif="instrumen">
    <p class="kecil"><a href="{{ route('kelola.instrumen.index') }}">← Semua instrumen</a></p>

    <form method="POST" action="{{ $baru ? route('kelola.instrumen.store') : route('kelola.instrumen.update', $instrumen) }}">
        @csrf
        @unless ($baru)
            @method('PUT')
        @endunless

        <fieldset>
            <legend>Instrumen</legend>
            <div class="bidang">
                <label for="nama">Nama</label>
                <input type="text" id="nama" name="nama" maxlength="150" required value="{{ old('nama', $instrumen->nama) }}">
            </div>
            <div class="bidang">
                <label for="instruksi">Instruksi untuk anggota</label>
                <textarea id="instruksi" name="instruksi" rows="2" maxlength="1000">{{ old('instruksi', $instrumen->instruksi) }}</textarea>
            </div>
            <div class="bidang">
                <label for="sumber">Sumber dan lisensi</label>
                <input type="text" id="sumber" name="sumber" maxlength="1000" value="{{ old('sumber', $instrumen->sumber) }}">
            </div>
            <div class="dua-kolom">
                <div class="bidang">
                    <label for="metode">Cara menghitung skor</label>
                    <select id="metode" name="metode">
                        <option value="jumlah" @selected(old('metode', $instrumen->metode) === 'jumlah')>Jumlah nilai butir</option>
                        <option value="rerata" @selected(old('metode', $instrumen->metode) === 'rerata')>Rata-rata nilai butir</option>
                    </select>
                </div>
                <div class="bidang">
                    <label for="pengali">Pengali skor</label>
                    <input type="number" id="pengali" name="pengali" step="0.01" min="0.01" required value="{{ old('pengali', (float) $instrumen->pengali) }}">
                    <p class="bantuan">1 untuk kebanyakan instrumen; DASS-21 memakai 2.</p>
                </div>
                <div class="bidang">
                    <label for="urutan">Urutan di formulir</label>
                    <input type="number" id="urutan" name="urutan" min="0" required value="{{ old('urutan', $instrumen->urutan) }}">
                </div>
            </div>
            <div class="bidang">
                <input type="hidden" name="aktif" value="0">
                <label class="pilihan"><input type="checkbox" name="aktif" value="1" @checked(old('aktif', $instrumen->aktif))> Dipakai di formulir pengajuan</label>
            </div>
        </fieldset>

        <fieldset>
            <legend>Pilihan jawaban</legend>
            <p class="bantuan" style="margin-bottom: 10px">Berlaku untuk semua butir, kecuali butir yang diberi pilihan khusus.</p>
            <div class="gulir">
                <table>
                    <thead><tr><th>Label</th><th style="width: 120px">Nilai</th></tr></thead>
                    <tbody>
                        @foreach ($baris($pilihan, 3) as $indeks => $satu)
                            <tr>
                                <td><input type="text" name="pilihan[{{ $indeks }}][label]" maxlength="150" value="{{ $satu['label'] ?? '' }}" aria-label="Label pilihan {{ $indeks + 1 }}"></td>
                                <td><input type="number" step="any" name="pilihan[{{ $indeks }}][nilai]" value="{{ $satu['nilai'] ?? '' }}" aria-label="Nilai pilihan {{ $indeks + 1 }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </fieldset>

        <fieldset>
            <legend>Subskala (opsional)</legend>
            <p class="bantuan" style="margin-bottom: 10px">Kosongkan bila instrumen hanya punya satu skor total. Contoh DASS-21: d = Depresi, a = Kecemasan, s = Stres.</p>
            <div class="gulir">
                <table>
                    <thead><tr><th style="width: 140px">Kunci</th><th>Nama</th></tr></thead>
                    <tbody>
                        @foreach ($baris($subskala, 2) as $indeks => $satu)
                            <tr>
                                <td><input type="text" name="subskala[{{ $indeks }}][kunci]" maxlength="20" value="{{ $satu['kunci'] ?? '' }}" aria-label="Kunci subskala {{ $indeks + 1 }}"></td>
                                <td><input type="text" name="subskala[{{ $indeks }}][nama]" maxlength="100" value="{{ $satu['nama'] ?? '' }}" aria-label="Nama subskala {{ $indeks + 1 }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </fieldset>

        <fieldset>
            <legend>Rentang skor dan tafsiran</legend>
            <p class="bantuan" style="margin-bottom: 10px">Skor yang jatuh di antara min dan maks (termasuk keduanya) diberi label dan saran ini. Kolom subskala dikosongkan untuk skor total.</p>
            <div class="gulir">
                <table>
                    <thead><tr><th style="width: 90px">Subskala</th><th style="width: 90px">Min</th><th style="width: 90px">Maks</th><th>Label</th><th>Saran untuk konselor</th></tr></thead>
                    <tbody>
                        @foreach ($baris($rentang, $tambah) as $indeks => $satu)
                            <tr>
                                <td><input type="text" name="rentang[{{ $indeks }}][subskala]" maxlength="20" value="{{ $satu['subskala'] ?? '' }}" aria-label="Subskala rentang {{ $indeks + 1 }}"></td>
                                <td><input type="number" step="any" name="rentang[{{ $indeks }}][min]" value="{{ $satu['min'] ?? '' }}" aria-label="Batas bawah rentang {{ $indeks + 1 }}"></td>
                                <td><input type="number" step="any" name="rentang[{{ $indeks }}][max]" value="{{ $satu['max'] ?? '' }}" aria-label="Batas atas rentang {{ $indeks + 1 }}"></td>
                                <td><input type="text" name="rentang[{{ $indeks }}][label]" maxlength="100" value="{{ $satu['label'] ?? '' }}" aria-label="Label rentang {{ $indeks + 1 }}"></td>
                                <td><input type="text" name="rentang[{{ $indeks }}][saran]" maxlength="500" value="{{ $satu['saran'] ?? '' }}" aria-label="Saran rentang {{ $indeks + 1 }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </fieldset>

        <fieldset>
            <legend>Butir pertanyaan</legend>
            <p class="bantuan" style="margin-bottom: 10px">
                <strong>Kritis ≥</strong>: jawaban dengan nilai sebesar ini atau lebih menandai permintaan DARURAT (mis. butir pikiran menyakiti diri).
                <strong>Pilihan khusus</strong>: ditulis <code>Label=nilai; Label=nilai</code> bila butir memakai skala yang berbeda.
                Kosongkan teks butir untuk menghapusnya.
            </p>
            <div class="gulir">
                <table>
                    <thead><tr><th style="width: 40px">#</th><th>Teks butir</th><th style="width: 80px">Subskala</th><th style="width: 70px">Terbalik</th><th style="width: 90px">Kritis ≥</th><th>Pilihan khusus</th></tr></thead>
                    <tbody>
                        @foreach ($baris($butir, $tambah) as $indeks => $satu)
                            <tr>
                                <td class="redup">{{ $indeks + 1 }}<input type="hidden" name="butir[{{ $indeks }}][kunci]" value="{{ $satu['kunci'] ?? '' }}"></td>
                                <td><textarea name="butir[{{ $indeks }}][teks]" rows="2" maxlength="500" aria-label="Teks butir {{ $indeks + 1 }}">{{ $satu['teks'] ?? '' }}</textarea></td>
                                <td><input type="text" name="butir[{{ $indeks }}][subskala]" maxlength="20" value="{{ $satu['subskala'] ?? '' }}" aria-label="Subskala butir {{ $indeks + 1 }}"></td>
                                <td><input type="hidden" name="butir[{{ $indeks }}][terbalik]" value="0"><input type="checkbox" name="butir[{{ $indeks }}][terbalik]" value="1" @checked($satu['terbalik'] ?? false) aria-label="Butir {{ $indeks + 1 }} diskor terbalik"></td>
                                <td><input type="number" step="any" name="butir[{{ $indeks }}][kritis_min]" value="{{ $satu['kritis_min'] ?? '' }}" aria-label="Ambang kritis butir {{ $indeks + 1 }}"></td>
                                <td><input type="text" name="butir[{{ $indeks }}][pilihan_khusus]" maxlength="1000" value="{{ $satu['pilihan_khusus'] ?? '' }}" aria-label="Pilihan khusus butir {{ $indeks + 1 }}"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="bantuan">Perlu baris lagi? Simpan dulu; lima baris kosong baru akan muncul.</p>
        </fieldset>

        <button type="submit" class="tombol">{{ $baru ? 'Buat instrumen' : 'Simpan' }}</button>
    </form>
</x-kelola>
