@php
    use App\Models\Permintaan;
    use App\Services\Asesmen\PenilaiAsesmen;
    $aksi = route('permintaan.simpan', [$permintaan, $langkah]);
    $sebelum = $langkah === 5 && ! $adaAsesmen ? 3 : $langkah - 1;
@endphp
<x-tata-letak :judul="Permintaan::LANGKAH[$langkah]" :lebar="true">
    <p class="kecil"><a href="{{ route('permintaan.index') }}">← Permintaan layanan</a></p>

    <ol class="langkah-nav" aria-label="Langkah pengajuan">
        @foreach (Permintaan::LANGKAH as $nomor => $nama)
            @continue($nomor === 4 && ! $adaAsesmen)
            <li @if ($nomor === $langkah) aria-current="step" @endif>
                @if ($nomor <= $permintaan->langkah && $nomor !== $langkah)
                    <a href="{{ route('permintaan.langkah', [$permintaan, $nomor]) }}">{{ $nomor }}. {{ $nama }}</a>
                @else
                    {{ $nomor }}. {{ $nama }}
                @endif
            </li>
        @endforeach
    </ol>

    <h1>{{ Permintaan::LANGKAH[$langkah] }}</h1>

    @if ($errors->any())
        <div class="catatan galat" role="alert">
            <ul>@foreach ($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($permintaan->darurat)
        @include('partials.krisis', ['kontak' => $kontak])
    @endif

    @if ($langkah === 1)
        <form method="POST" action="{{ $aksi }}">
            @csrf
            @method('PUT')
            <div class="kartu">
                <h2>Sebelum mulai</h2>
                <ul style="margin: 0 0 12px; padding-left: 20px">
                    <li>SPK Rangkul adalah pendampingan oleh <strong>peer konselor</strong> — sesama anggota SPK yang dilatih untuk mendengarkan dan mendampingi. Bukan layanan psikolog/psikiater dan bukan layanan gawat darurat.</li>
                    <li>Isian Anda dapat dibaca oleh <strong>tim komite Rangkul</strong>, <strong>peer konselor yang ditugaskan</strong>, dan <strong>superadmin</strong> pengelola sistem. Isian disimpan terenkripsi dan tidak dikirim ke spk.or.id.</li>
                    <li>Kerahasiaan dijaga, kecuali bila ada risiko serius bagi keselamatan Anda atau orang lain — tim dapat menghubungi Anda secepatnya atau menyarankan bantuan profesional.</li>
                    <li>Bila diperlukan, konselor dapat menyarankan rujukan ke psikolog profesional di luar tim Rangkul.</li>
                </ul>
                <label class="pilihan"><input type="checkbox" name="persetujuan_layanan" value="1" @checked(old('persetujuan_layanan', $permintaan->persetujuan_pada !== null))> Saya memahami sifat layanan peer konseling ini.</label>
                <label class="pilihan"><input type="checkbox" name="persetujuan_data" value="1" @checked(old('persetujuan_data', $permintaan->persetujuan_pada !== null))> Saya setuju isian saya dibaca oleh pihak yang disebut di atas untuk keperluan pendampingan.</label>
            </div>

            <div class="kartu">
                <h2>Pertanyaan keselamatan</h2>
                <p class="redup kecil">Tiga pertanyaan ini membantu kami tahu apakah Anda perlu dihubungi segera.</p>
                @foreach (Permintaan::KESELAMATAN as $kunci => $pertanyaan)
                    <div class="butir">
                        <p>{{ $pertanyaan['teks'] }}</p>
                        <div class="skala">
                            @foreach ($pertanyaan['pilihan'] as $nilai => $label)
                                <label><input type="radio" name="keselamatan[{{ $kunci }}]" value="{{ $nilai }}" required @checked(old("keselamatan.$kunci", $permintaan->keselamatan[$kunci] ?? null) === $nilai)> {{ $label }}</label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <button type="submit" class="tombol">Simpan dan lanjut</button>
        </form>
    @elseif ($langkah === 2)
        <form method="POST" action="{{ $aksi }}">
            @csrf
            @method('PUT')
            <p>Apa yang ingin Anda diskusikan bersama tim RANGKUL?</p>
            <p class="redup kecil">Boleh memilih lebih dari satu. Pilihan ini digunakan untuk mempersiapkan layanan, bukan untuk menetapkan diagnosis.</p>
            @php $dipilih = old('topik', $permintaan->topik ?? []); @endphp
            <div class="kartu">
                @foreach (Permintaan::TOPIK as $kunci => $label)
                    <label class="pilihan"><input type="checkbox" name="topik[]" value="{{ $kunci }}" @checked(in_array($kunci, $dipilih, true))> {{ $label }}</label>
                @endforeach
                <div class="bidang" style="margin-top: 12px">
                    <label for="topik_lainnya">Bila memilih "Lainnya", jelaskan singkat (opsional)</label>
                    <input type="text" id="topik_lainnya" name="topik_lainnya" maxlength="500" value="{{ old('topik_lainnya', $permintaan->topik_lainnya) }}">
                </div>
            </div>
            <div class="baris-aksi">
                <a class="tombol sekunder" href="{{ route('permintaan.langkah', [$permintaan, $sebelum]) }}">Kembali</a>
                <button type="submit" class="tombol">Simpan dan lanjut</button>
            </div>
        </form>
    @elseif ($langkah === 3)
        <form method="POST" action="{{ $aksi }}">
            @csrf
            @method('PUT')
            <div class="bidang">
                <label for="uraian">Apa yang sedang Anda alami atau rasakan?</label>
                <p class="bantuan" style="margin: 0 0 6px">Ceritakan dengan kata-kata Anda sendiri: apa yang terjadi, sejak kapan, dan bagaimana pengaruhnya pada pekerjaan, tidur, atau hubungan Anda. Tidak perlu rapi.</p>
                <textarea id="uraian" name="uraian" rows="10" maxlength="10000" required>{{ old('uraian', $permintaan->uraian) }}</textarea>
            </div>
            <div class="bidang">
                <label for="harapan">Apa yang Anda harapkan dari pendampingan ini? (opsional)</label>
                <p class="bantuan" style="margin: 0 0 6px">Misalnya: ingin didengarkan, ingin mencari jalan keluar, ingin tahu langkah yang bisa diambil, atau ingin dirujuk ke psikolog.</p>
                <textarea id="harapan" name="harapan" rows="4" maxlength="3000">{{ old('harapan', $permintaan->harapan) }}</textarea>
            </div>
            <div class="baris-aksi">
                <a class="tombol sekunder" href="{{ route('permintaan.langkah', [$permintaan, $sebelum]) }}">Kembali</a>
                <button type="submit" class="tombol">Simpan dan lanjut</button>
            </div>
        </form>
    @elseif ($langkah === 4)
        <form method="POST" action="{{ $aksi }}">
            @csrf
            @method('PUT')
            <p class="redup">Jawaban ini membantu konselor memahami keadaan Anda. Ini bukan tes dan bukan diagnosis; tidak ada jawaban benar atau salah. Hasilnya hanya dibaca konselor dan komite.</p>
            @foreach ($instrumen as $satu)
                @php $salinan = $satu->salinan(); @endphp
                <section class="kartu">
                    <h2>{{ $satu->nama }}</h2>
                    @if ($satu->instruksi)
                        <p class="redup">{{ $satu->instruksi }}</p>
                    @endif
                    @foreach ($satu->butir as $nomor => $butir)
                        @php $nama = "jawaban[{$satu->id}][{$butir['kunci']}]"; $lama = old("jawaban.{$satu->id}.{$butir['kunci']}", $jawabanTersimpan[$satu->id][$butir['kunci']] ?? null); @endphp
                        <fieldset class="butir" style="border: 0; border-top: 1px solid var(--garis); border-radius: 0; padding: 12px 0; margin: 0">
                            <legend style="padding: 0; font-weight: 400; margin-bottom: 8px">{{ $nomor + 1 }}. {{ $butir['teks'] }}</legend>
                            <div class="skala">
                                @foreach (PenilaiAsesmen::pilihanButir($salinan, $butir) as $pilihan)
                                    <label><input type="radio" name="{{ $nama }}" value="{{ $pilihan['nilai'] }}" required @checked($lama !== null && (string) $lama === (string) $pilihan['nilai'])> {{ $pilihan['label'] }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </section>
            @endforeach
            <div class="baris-aksi">
                <a class="tombol sekunder" href="{{ route('permintaan.langkah', [$permintaan, $sebelum]) }}">Kembali</a>
                <button type="submit" class="tombol">Simpan dan lanjut</button>
            </div>
        </form>
    @elseif ($langkah === 5)
        <form method="POST" action="{{ $aksi }}">
            @csrf
            @method('PUT')
            <div class="kartu">
                <h2>Bentuk layanan</h2>
                @foreach (Permintaan::BENTUK_LAYANAN as $kunci => $label)
                    <label class="pilihan"><input type="radio" name="bentuk_layanan" value="{{ $kunci }}" required @checked(old('bentuk_layanan', $permintaan->bentuk_layanan ?? 'chat') === $kunci)> {{ $label }}</label>
                @endforeach
                <p class="bantuan">Pendampingan selalu dimulai lewat percakapan tertulis. Pertemuan daring dapat disepakati kemudian bersama konselor.</p>
                <div class="bidang" style="margin-top: 12px">
                    <label for="waktu_luang">Waktu yang nyaman untuk dihubungi (opsional)</label>
                    <input type="text" id="waktu_luang" name="waktu_luang" maxlength="500" placeholder="Mis. hari kerja setelah pukul 19.00" value="{{ old('waktu_luang', $permintaan->waktu_luang) }}">
                </div>
            </div>

            <div class="kartu">
                <h2>Konselor pendamping</h2>
                <input type="hidden" name="hindari_kampus_sama" value="0">
                <label class="pilihan"><input type="checkbox" name="hindari_kampus_sama" value="1" @checked(old('hindari_kampus_sama', $permintaan->hindari_kampus_sama))> Saya lebih nyaman didampingi konselor dari kampus lain ({{ $pengguna->kampus ?: 'kampus saya' }}).</label>
                <div class="bidang" style="margin-top: 12px">
                    <label for="gender_konselor">Preferensi jenis kelamin konselor</label>
                    <select id="gender_konselor" name="gender_konselor">
                        @foreach (Permintaan::GENDER_KONSELOR as $kunci => $label)
                            <option value="{{ $kunci }}" @selected(old('gender_konselor', $permintaan->gender_konselor) === $kunci)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="bantuan">Komite berusaha memenuhinya sesuai ketersediaan konselor.</p>
                </div>
            </div>

            <div class="kartu">
                <h2>Pemberitahuan</h2>
                <p class="redup kecil">Kami memberi tahu bila konselor ditetapkan, ada pesan yang belum Anda baca, atau ada jadwal pertemuan. Pemberitahuan tidak pernah memuat isi cerita atau percakapan.</p>
                <input type="hidden" name="notif_surel" value="0">
                <label class="pilihan"><input type="checkbox" name="notif_surel" value="1" @checked(old('notif_surel', $pengguna->notif_surel))> Lewat surel ({{ $pengguna->email ?: 'belum ada alamat surel di akun SPK' }})</label>
                <input type="hidden" name="notif_whatsapp" value="0">
                <label class="pilihan"><input type="checkbox" name="notif_whatsapp" value="1" @checked(old('notif_whatsapp', $pengguna->notif_whatsapp))> Lewat WhatsApp</label>
                <div class="bidang" style="margin-top: 8px">
                    <label for="nomor_whatsapp">Nomor WhatsApp (opsional)</label>
                    <input type="text" id="nomor_whatsapp" name="nomor_whatsapp" maxlength="20" inputmode="tel" placeholder="{{ $pengguna->telp ?: '08…' }}" value="{{ old('nomor_whatsapp', $pengguna->nomor_whatsapp) }}">
                    <p class="bantuan">Kosongkan untuk memakai nomor di akun SPK Anda{{ $pengguna->telp ? ' ('.$pengguna->telp.')' : '' }}.</p>
                </div>
            </div>

            <div class="baris-aksi">
                <a class="tombol sekunder" href="{{ route('permintaan.langkah', [$permintaan, $sebelum]) }}">Kembali</a>
                <button type="submit" class="tombol">Simpan dan lanjut</button>
            </div>
        </form>
    @else
        <p class="redup">Periksa kembali isian Anda. Setelah dikirim, permintaan masuk ke tim komite dan tidak dapat diubah lagi.</p>

        <div class="kartu">
            <h2>Yang ingin didiskusikan</h2>
            @foreach ($permintaan->labelTopik() as $label)
                <span class="lencana">{{ $label }}</span>
            @endforeach
            @if ($permintaan->topik_lainnya)
                <p class="kecil" style="margin: 8px 0 0">{{ $permintaan->topik_lainnya }}</p>
            @endif
            <p class="kecil" style="margin: 8px 0 0"><a href="{{ route('permintaan.langkah', [$permintaan, 2]) }}">Ubah</a></p>
        </div>

        <div class="kartu">
            <h2>Cerita Anda</h2>
            <p style="white-space: pre-line">{{ $permintaan->uraian }}</p>
            @if ($permintaan->harapan)
                <p class="redup kecil" style="margin-bottom: 4px"><strong>Harapan</strong></p>
                <p style="white-space: pre-line">{{ $permintaan->harapan }}</p>
            @endif
            <p class="kecil" style="margin: 0"><a href="{{ route('permintaan.langkah', [$permintaan, 3]) }}">Ubah</a></p>
        </div>

        @if ($adaAsesmen)
            <div class="kartu">
                <h2>Asesmen dasar</h2>
                <p class="redup" style="margin: 0">{{ $permintaan->jawabanAsesmen->count() }} instrumen sudah diisi. <a href="{{ route('permintaan.langkah', [$permintaan, 4]) }}">Ubah</a></p>
            </div>
        @endif

        <div class="kartu">
            <h2>Preferensi</h2>
            <p class="kecil" style="margin: 0">
                {{ Permintaan::BENTUK_LAYANAN[$permintaan->bentuk_layanan] ?? '—' }}
                @if ($permintaan->waktu_luang) · {{ $permintaan->waktu_luang }} @endif
                @if ($permintaan->hindari_kampus_sama) · konselor dari kampus lain @endif
                · konselor: {{ Permintaan::GENDER_KONSELOR[$permintaan->gender_konselor] ?? '—' }}
            </p>
            <p class="kecil" style="margin: 8px 0 0"><a href="{{ route('permintaan.langkah', [$permintaan, 5]) }}">Ubah</a></p>
        </div>

        <form method="POST" action="{{ route('permintaan.kirim', $permintaan) }}">
            @csrf
            <div class="baris-aksi">
                <a class="tombol sekunder" href="{{ route('permintaan.langkah', [$permintaan, 5]) }}">Kembali</a>
                <button type="submit" class="tombol">Kirim permintaan</button>
            </div>
        </form>
    @endif

    <form method="POST" action="{{ route('permintaan.batalkan', $permintaan) }}" style="margin-top: 28px"
          onsubmit="return confirm('Hapus draf ini? Isian yang sudah Anda tulis akan hilang.')">
        @csrf
        <button type="submit" class="tombol bahaya ringkas">Hapus draf</button>
    </form>
</x-tata-letak>
