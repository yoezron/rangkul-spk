@php
    use App\Models\Permintaan;
    use App\Services\Asesmen\PenilaiAsesmen;
    $aksi = route('permintaan.simpan', [$permintaan, $langkah]);
    $sebelum = $langkah === 5 && ! $adaAsesmen ? 3 : $langkah - 1;
    $daftarLangkah = collect(Permintaan::LANGKAH)->reject(fn ($nama, $nomor) => $nomor === 4 && ! $adaAsesmen);
    $posisi = $daftarLangkah->keys()->search($langkah) + 1;
    $persen = (int) round(($posisi - 1) / max(1, $daftarLangkah->count() - 1) * 100);
    $ubin = 'flex cursor-pointer items-start gap-3 rounded-xl border border-garis bg-permukaan p-3.5 text-[15px] font-normal transition hover:border-utama-garis has-checked:border-utama has-checked:bg-utama-lembut has-focus-visible:ring-4 has-focus-visible:ring-utama/20';
@endphp
<x-tata-letak :judul="Permintaan::LANGKAH[$langkah]" :lebar="true">
    <a href="{{ route('permintaan.index') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-redup no-underline hover:text-teks hover:no-underline">
        <x-ikon nama="panah-kiri" class="size-4" /> Permintaan layanan
    </a>

    {{-- Penanda langkah --}}
    <nav aria-label="Langkah pengajuan" class="mb-7">
        <div class="mb-3 flex items-center justify-between text-sm">
            <span class="font-semibold text-utama">Langkah {{ $posisi }} dari {{ $daftarLangkah->count() }}</span>
            <span class="text-redup">Tersimpan otomatis tiap langkah</span>
        </div>
        <div class="mb-4 h-1.5 overflow-hidden rounded-full bg-garis">
            <div class="h-full rounded-full bg-utama transition-all" style="width: {{ max(6, $persen) }}%"></div>
        </div>
        <ol class="flex flex-wrap gap-1.5">
            @foreach ($daftarLangkah as $nomor => $nama)
                @php $selesai = $nomor < $langkah; $kini = $nomor === $langkah; $bisa = $nomor <= $permintaan->langkah && ! $kini; @endphp
                <li @if ($kini) aria-current="step" @endif>
                    @if ($bisa)
                        <a href="{{ route('permintaan.langkah', [$permintaan, $nomor]) }}" class="inline-flex items-center gap-1.5 rounded-full border border-garis bg-permukaan px-3 py-1 text-xs font-medium text-teks no-underline hover:border-utama-garis hover:no-underline">
                            @if ($selesai)<x-ikon nama="centang" class="size-3.5 text-utama" />@endif {{ $nama }}
                        </a>
                    @else
                        <span @class([
                            'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium',
                            'border-utama bg-utama text-teks-tombol' => $kini,
                            'border-garis text-redup' => ! $kini,
                        ])>{{ $nama }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>

    <h1 class="mb-5">{{ Permintaan::LANGKAH[$langkah] }}</h1>

    @if ($errors->any())
        <div class="catatan galat flex items-start gap-2.5" role="alert">
            <x-ikon nama="peringatan" class="mt-0.5 size-5" />
            <ul class="list-none! pl-0!">@foreach ($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach</ul>
        </div>
    @endif

    @if ($permintaan->darurat)
        @include('partials.krisis', ['kontak' => $kontak])
    @endif

    @if ($langkah === 1)
        <form method="POST" action="{{ $aksi }}">
            @csrf
            @method('PUT')
            <section class="kartu">
                <h2 class="flex items-center gap-2"><x-ikon nama="perisai" class="size-5 text-utama" /> Sebelum mulai</h2>
                <ul class="mb-5 space-y-3 text-[15px]">
                    <li class="flex gap-3"><x-ikon nama="kelompok" class="mt-0.5 size-5 text-redup" /><span>SPK Rangkul adalah pendampingan oleh <strong>peer konselor</strong> — sesama anggota SPK yang dilatih untuk mendengarkan dan mendampingi. Bukan layanan psikolog/psikiater dan bukan layanan gawat darurat.</span></li>
                    <li class="flex gap-3"><x-ikon nama="gembok" class="mt-0.5 size-5 text-redup" /><span>Isian Anda dapat dibaca oleh <strong>tim komite Rangkul</strong>, <strong>peer konselor yang ditugaskan</strong>, dan <strong>superadmin</strong> pengelola sistem. Isian disimpan terenkripsi dan tidak dikirim ke spk.or.id.</span></li>
                    <li class="flex gap-3"><x-ikon nama="hati" class="mt-0.5 size-5 text-redup" /><span>Kerahasiaan dijaga, kecuali bila ada risiko serius bagi keselamatan Anda atau orang lain — tim dapat menghubungi Anda secepatnya atau menyarankan bantuan profesional.</span></li>
                    <li class="flex gap-3"><x-ikon nama="panah-kanan" class="mt-0.5 size-5 text-redup" /><span>Bila diperlukan, konselor dapat menyarankan rujukan ke psikolog profesional di luar tim Rangkul.</span></li>
                </ul>
                <div class="space-y-2">
                    <label class="{{ $ubin }}"><input type="checkbox" name="persetujuan_layanan" value="1" class="mt-1" @checked(old('persetujuan_layanan', $permintaan->persetujuan_pada !== null))> Saya memahami sifat layanan peer konseling ini.</label>
                    <label class="{{ $ubin }}"><input type="checkbox" name="persetujuan_data" value="1" class="mt-1" @checked(old('persetujuan_data', $permintaan->persetujuan_pada !== null))> Saya setuju isian saya dibaca oleh pihak yang disebut di atas untuk keperluan pendampingan.</label>
                </div>
            </section>

            <section class="kartu">
                <h2 class="mb-1 flex items-center gap-2"><x-ikon nama="hati" class="size-5 text-aksen" /> Pertanyaan keselamatan</h2>
                <p class="text-sm text-redup">Tiga pertanyaan ini membantu kami tahu apakah Anda perlu dihubungi segera.</p>
                @foreach (Permintaan::KESELAMATAN as $kunci => $pertanyaan)
                    <div class="butir">
                        <p class="mb-3 font-medium">{{ $pertanyaan['teks'] }}</p>
                        <div class="skala">
                            @foreach ($pertanyaan['pilihan'] as $nilai => $label)
                                <label><input type="radio" name="keselamatan[{{ $kunci }}]" value="{{ $nilai }}" required @checked(old("keselamatan.$kunci", $permintaan->keselamatan[$kunci] ?? null) === $nilai)> {{ $label }}</label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </section>

            <div class="flex justify-end">
                <button type="submit" class="tombol">Simpan dan lanjut <x-ikon nama="panah-kanan" class="size-4" /></button>
            </div>
        </form>
    @elseif ($langkah === 2)
        <form method="POST" action="{{ $aksi }}">
            @csrf
            @method('PUT')
            <section class="kartu">
                <h2 class="mb-1">Apa yang ingin Anda diskusikan bersama tim RANGKUL?</h2>
                <p class="text-sm text-redup">Boleh memilih lebih dari satu. Pilihan ini digunakan untuk mempersiapkan layanan, bukan untuk menetapkan diagnosis.</p>
                @php $dipilih = old('topik', $permintaan->topik ?? []); @endphp
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach (Permintaan::TOPIK as $kunci => $label)
                        <label class="{{ $ubin }}"><input type="checkbox" name="topik[]" value="{{ $kunci }}" class="mt-1" @checked(in_array($kunci, $dipilih, true))> {{ $label }}</label>
                    @endforeach
                </div>
                <div class="bidang mt-5 mb-0">
                    <label for="topik_lainnya">Bila memilih "Lainnya", jelaskan singkat (opsional)</label>
                    <input type="text" id="topik_lainnya" name="topik_lainnya" maxlength="500" value="{{ old('topik_lainnya', $permintaan->topik_lainnya) }}">
                </div>
            </section>
            <div class="flex justify-between gap-2">
                <a class="tombol sekunder" href="{{ route('permintaan.langkah', [$permintaan, $sebelum]) }}"><x-ikon nama="panah-kiri" class="size-4" /> Kembali</a>
                <button type="submit" class="tombol">Simpan dan lanjut <x-ikon nama="panah-kanan" class="size-4" /></button>
            </div>
        </form>
    @elseif ($langkah === 3)
        <form method="POST" action="{{ $aksi }}">
            @csrf
            @method('PUT')
            <section class="kartu">
                <div class="bidang">
                    <label for="uraian" class="text-base">Apa yang sedang Anda alami atau rasakan?</label>
                    <p class="bantuan mb-2">Ceritakan dengan kata-kata Anda sendiri: apa yang terjadi, sejak kapan, dan bagaimana pengaruhnya pada pekerjaan, tidur, atau hubungan Anda. Tidak perlu rapi.</p>
                    <textarea id="uraian" name="uraian" rows="10" maxlength="10000" required class="leading-relaxed">{{ old('uraian', $permintaan->uraian) }}</textarea>
                </div>
                <div class="bidang mb-0">
                    <label for="harapan" class="text-base">Apa yang Anda harapkan dari pendampingan ini? <span class="font-normal text-redup">(opsional)</span></label>
                    <p class="bantuan mb-2">Misalnya: ingin didengarkan, ingin mencari jalan keluar, ingin tahu langkah yang bisa diambil, atau ingin dirujuk ke psikolog.</p>
                    <textarea id="harapan" name="harapan" rows="4" maxlength="3000">{{ old('harapan', $permintaan->harapan) }}</textarea>
                </div>
            </section>
            <div class="flex justify-between gap-2">
                <a class="tombol sekunder" href="{{ route('permintaan.langkah', [$permintaan, $sebelum]) }}"><x-ikon nama="panah-kiri" class="size-4" /> Kembali</a>
                <button type="submit" class="tombol">Simpan dan lanjut <x-ikon nama="panah-kanan" class="size-4" /></button>
            </div>
        </form>
    @elseif ($langkah === 4)
        <form method="POST" action="{{ $aksi }}">
            @csrf
            @method('PUT')
            <div class="catatan info flex items-start gap-2.5">
                <x-ikon nama="kilau" class="mt-0.5 size-5 text-utama" />
                <span>Jawaban ini membantu konselor memahami keadaan Anda. Ini bukan tes dan bukan diagnosis; tidak ada jawaban benar atau salah. Hasilnya hanya dibaca konselor dan komite.</span>
            </div>
            @foreach ($instrumen as $satu)
                @php $salinan = $satu->salinan(); @endphp
                <section class="kartu">
                    <div class="mb-2 flex items-start justify-between gap-3">
                        <h2 class="mb-0">{{ $satu->nama }}</h2>
                        <span class="lencana netral shrink-0">{{ count($satu->butir) }} butir</span>
                    </div>
                    @if ($satu->instruksi)
                        <p class="text-sm text-redup">{{ $satu->instruksi }}</p>
                    @endif
                    @foreach ($satu->butir as $nomor => $butir)
                        @php $nama = "jawaban[{$satu->id}][{$butir['kunci']}]"; $lama = old("jawaban.{$satu->id}.{$butir['kunci']}", $jawabanTersimpan[$satu->id][$butir['kunci']] ?? null); @endphp
                        <fieldset class="butir m-0 rounded-none border-0 border-t bg-transparent p-0 py-4 shadow-none first-of-type:border-t-0">
                            <legend class="float-left mb-3 w-full p-0 text-[15px] font-normal"><span class="mr-1 font-semibold text-redup">{{ $nomor + 1 }}.</span> {{ $butir['teks'] }}</legend>
                            <div class="skala clear-both">
                                @foreach (PenilaiAsesmen::pilihanButir($salinan, $butir) as $pilihan)
                                    <label><input type="radio" name="{{ $nama }}" value="{{ $pilihan['nilai'] }}" required @checked($lama !== null && (string) $lama === (string) $pilihan['nilai'])> {{ $pilihan['label'] }}</label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </section>
            @endforeach
            <div class="flex justify-between gap-2">
                <a class="tombol sekunder" href="{{ route('permintaan.langkah', [$permintaan, $sebelum]) }}"><x-ikon nama="panah-kiri" class="size-4" /> Kembali</a>
                <button type="submit" class="tombol">Simpan dan lanjut <x-ikon nama="panah-kanan" class="size-4" /></button>
            </div>
        </form>
    @elseif ($langkah === 5)
        <form method="POST" action="{{ $aksi }}">
            @csrf
            @method('PUT')
            <section class="kartu">
                <h2 class="flex items-center gap-2"><x-ikon nama="chat" class="size-5 text-utama" /> Bentuk layanan</h2>
                <div class="grid gap-2">
                    @foreach (Permintaan::BENTUK_LAYANAN as $kunci => $label)
                        <label class="{{ $ubin }}"><input type="radio" name="bentuk_layanan" value="{{ $kunci }}" class="mt-1" required @checked(old('bentuk_layanan', $permintaan->bentuk_layanan ?? 'chat') === $kunci)> {{ $label }}</label>
                    @endforeach
                </div>
                <p class="bantuan">Pendampingan selalu dimulai lewat percakapan tertulis. Pertemuan daring dapat disepakati kemudian bersama konselor.</p>
                <div class="bidang mt-5 mb-0">
                    <label for="waktu_luang">Waktu yang nyaman untuk dihubungi <span class="font-normal text-redup">(opsional)</span></label>
                    <input type="text" id="waktu_luang" name="waktu_luang" maxlength="500" placeholder="Mis. hari kerja setelah pukul 19.00" value="{{ old('waktu_luang', $permintaan->waktu_luang) }}">
                </div>
            </section>

            <section class="kartu">
                <h2 class="flex items-center gap-2"><x-ikon nama="kelompok" class="size-5 text-utama" /> Konselor pendamping</h2>
                <input type="hidden" name="hindari_kampus_sama" value="0">
                <label class="{{ $ubin }}"><input type="checkbox" name="hindari_kampus_sama" value="1" class="mt-1" @checked(old('hindari_kampus_sama', $permintaan->hindari_kampus_sama))> Saya lebih nyaman didampingi konselor dari kampus lain ({{ $pengguna->kampus ?: 'kampus saya' }}).</label>
                <div class="bidang mt-4 mb-0">
                    <label for="gender_konselor">Preferensi jenis kelamin konselor</label>
                    <select id="gender_konselor" name="gender_konselor">
                        @foreach (Permintaan::GENDER_KONSELOR as $kunci => $label)
                            <option value="{{ $kunci }}" @selected(old('gender_konselor', $permintaan->gender_konselor) === $kunci)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="bantuan">Komite berusaha memenuhinya sesuai ketersediaan konselor.</p>
                </div>
            </section>

            <section class="kartu">
                <h2 class="mb-1 flex items-center gap-2"><x-ikon nama="surel" class="size-5 text-utama" /> Pemberitahuan</h2>
                <p class="text-sm text-redup">Kami memberi tahu bila konselor ditetapkan, ada pesan yang belum Anda baca, atau ada jadwal pertemuan. Pemberitahuan tidak pernah memuat isi cerita atau percakapan.</p>
                <div class="grid gap-2 sm:grid-cols-2">
                    <input type="hidden" name="notif_surel" value="0">
                    <label class="{{ $ubin }}"><input type="checkbox" name="notif_surel" value="1" class="mt-1" @checked(old('notif_surel', $pengguna->notif_surel))> <span>Lewat surel<span class="block text-xs text-redup">{{ $pengguna->email ?: 'belum ada alamat surel di akun SPK' }}</span></span></label>
                    <input type="hidden" name="notif_whatsapp" value="0">
                    <label class="{{ $ubin }}"><input type="checkbox" name="notif_whatsapp" value="1" class="mt-1" @checked(old('notif_whatsapp', $pengguna->notif_whatsapp))> <span>Lewat WhatsApp<span class="block text-xs text-redup">{{ $pengguna->nomor_whatsapp ?: ($pengguna->telp ?: 'isi nomor di bawah') }}</span></span></label>
                </div>
                <div class="bidang mt-4 mb-0">
                    <label for="nomor_whatsapp">Nomor WhatsApp <span class="font-normal text-redup">(opsional)</span></label>
                    <input type="text" id="nomor_whatsapp" name="nomor_whatsapp" maxlength="20" inputmode="tel" placeholder="{{ $pengguna->telp ?: '08…' }}" value="{{ old('nomor_whatsapp', $pengguna->nomor_whatsapp) }}">
                    <p class="bantuan">Kosongkan untuk memakai nomor di akun SPK Anda{{ $pengguna->telp ? ' ('.$pengguna->telp.')' : '' }}.</p>
                </div>
            </section>

            <div class="flex justify-between gap-2">
                <a class="tombol sekunder" href="{{ route('permintaan.langkah', [$permintaan, $sebelum]) }}"><x-ikon nama="panah-kiri" class="size-4" /> Kembali</a>
                <button type="submit" class="tombol">Simpan dan lanjut <x-ikon nama="panah-kanan" class="size-4" /></button>
            </div>
        </form>
    @else
        <p class="text-redup">Periksa kembali isian Anda. Setelah dikirim, permintaan masuk ke tim komite dan tidak dapat diubah lagi.</p>

        <section class="kartu">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="mb-0 flex items-center gap-2"><x-ikon nama="chat" class="size-5 text-utama" /> Yang ingin didiskusikan</h2>
                <a class="text-sm" href="{{ route('permintaan.langkah', [$permintaan, 2]) }}">Ubah</a>
            </div>
            @foreach ($permintaan->labelTopik() as $label)
                <span class="lencana">{{ $label }}</span>
            @endforeach
            @if ($permintaan->topik_lainnya)
                <p class="mt-2 text-sm">{{ $permintaan->topik_lainnya }}</p>
            @endif
        </section>

        <section class="kartu">
            <div class="mb-3 flex items-center justify-between gap-2">
                <h2 class="mb-0 flex items-center gap-2"><x-ikon nama="dokumen" class="size-5 text-utama" /> Cerita Anda</h2>
                <a class="text-sm" href="{{ route('permintaan.langkah', [$permintaan, 3]) }}">Ubah</a>
            </div>
            <p class="whitespace-pre-line">{{ $permintaan->uraian }}</p>
            @if ($permintaan->harapan)
                <p class="mb-1 text-xs font-semibold tracking-wide text-redup uppercase">Harapan</p>
                <p class="whitespace-pre-line">{{ $permintaan->harapan }}</p>
            @endif
        </section>

        <div class="mb-6 grid gap-3 sm:grid-cols-2">
            @if ($adaAsesmen)
                <section class="rounded-2xl border border-garis bg-permukaan p-5 shadow-lembut">
                    <div class="mb-1 flex items-center justify-between gap-2">
                        <strong class="flex items-center gap-2"><x-ikon nama="klipboard" class="size-5 text-utama" /> Asesmen dasar</strong>
                        <a class="text-sm" href="{{ route('permintaan.langkah', [$permintaan, 4]) }}">Ubah</a>
                    </div>
                    <p class="text-sm text-redup">{{ $permintaan->jawabanAsesmen->count() }} instrumen sudah diisi.</p>
                </section>
            @endif
            <section class="rounded-2xl border border-garis bg-permukaan p-5 shadow-lembut">
                <div class="mb-1 flex items-center justify-between gap-2">
                    <strong class="flex items-center gap-2"><x-ikon nama="susun" class="size-5 text-utama" /> Preferensi</strong>
                    <a class="text-sm" href="{{ route('permintaan.langkah', [$permintaan, 5]) }}">Ubah</a>
                </div>
                <p class="text-sm text-redup">
                    {{ Permintaan::BENTUK_LAYANAN[$permintaan->bentuk_layanan] ?? '—' }}
                    @if ($permintaan->waktu_luang) · {{ $permintaan->waktu_luang }} @endif
                    @if ($permintaan->hindari_kampus_sama) · konselor dari kampus lain @endif
                    · konselor: {{ Permintaan::GENDER_KONSELOR[$permintaan->gender_konselor] ?? '—' }}
                </p>
            </section>
        </div>

        <form method="POST" action="{{ route('permintaan.kirim', $permintaan) }}">
            @csrf
            <div class="flex justify-between gap-2">
                <a class="tombol sekunder" href="{{ route('permintaan.langkah', [$permintaan, 5]) }}"><x-ikon nama="panah-kiri" class="size-4" /> Kembali</a>
                <button type="submit" class="tombol px-6">Kirim permintaan <x-ikon nama="panah-kanan" class="size-4" /></button>
            </div>
        </form>
    @endif

    <form method="POST" action="{{ route('permintaan.batalkan', $permintaan) }}" class="mt-10 border-t border-garis pt-6 text-center"
          onsubmit="return confirm('Hapus draf ini? Isian yang sudah Anda tulis akan hilang.')">
        @csrf
        <button type="submit" class="cursor-pointer text-sm text-redup underline-offset-2 hover:text-bahaya hover:underline">Hapus draf ini</button>
    </form>
</x-tata-letak>
