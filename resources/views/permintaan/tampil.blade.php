@php
    use App\Enums\StatusPermintaan;
    use App\Models\Permintaan;
    $status = $permintaan->status;
    $tahap = [
        ['Diajukan', 'kotak-masuk', true],
        ['Konselor ditetapkan', 'kelompok', $permintaan->konselor_id !== null],
        ['Pendampingan', 'chat', in_array($status, [StatusPermintaan::Berlangsung, StatusPermintaan::MenungguPenutupan, StatusPermintaan::Selesai], true)],
        ['Selesai', 'centang', $status === StatusPermintaan::Selesai],
    ];
@endphp
<x-tata-letak :judul="'Permintaan '.$permintaan->kode" :lebar="true">
    <a href="{{ route('permintaan.index') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-redup no-underline hover:text-teks hover:no-underline">
        <x-ikon nama="panah-kiri" class="size-4" /> Permintaan layanan
    </a>

    <header class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1>Permintaan {{ $permintaan->kode }}</h1>
            <p class="mt-1 text-sm text-redup">Diajukan {{ $permintaan->diajukan_pada?->translatedFormat('j F Y') }}</p>
        </div>
        <span @class(['lencana text-sm', 'peringatan' => ! $status->terbuka()])>{{ $status->label() }}</span>
    </header>

    @if (session('status'))
        <div class="catatan info flex items-start gap-2.5" role="status"><x-ikon nama="centang" class="mt-0.5 size-5 text-utama" /><span>{{ session('status') }}</span></div>
    @endif
    @if ($errors->any())
        <div class="catatan galat" role="alert"><ul>@foreach ($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach</ul></div>
    @endif

    @if ($baruDiajukan)
        <div class="mb-6 flex gap-3 rounded-2xl border border-utama-garis bg-utama-lembut p-5" role="status">
            <span class="grid size-10 shrink-0 place-items-center rounded-full bg-utama text-teks-tombol"><x-ikon nama="centang" class="size-5" /></span>
            <div class="text-sm">
                <strong class="block text-base">Terima kasih, permintaan Anda sudah kami terima.</strong>
                Tim komite akan membacanya dan menugaskan peer konselor. Kami akan memberi kabar lewat surel/WhatsApp, dan percakapan dengan konselor akan muncul di halaman ini.
            </div>
        </div>
    @endif

    @if ($permintaan->darurat && $status->terbuka())
        @include('partials.krisis', ['kontak' => $kontak])
    @endif

    {{-- Perjalanan permintaan --}}
    @unless (in_array($status, [StatusPermintaan::Ditolak, StatusPermintaan::Dibatalkan], true))
        <ol class="mb-6 grid grid-cols-4 gap-2 rounded-2xl border border-garis bg-permukaan p-4 shadow-lembut">
            @foreach ($tahap as [$nama, $ikon, $tercapai])
                <li class="flex flex-col items-center gap-2 text-center">
                    <span @class([
                        'grid size-10 place-items-center rounded-full border-2 transition',
                        'border-utama bg-utama text-teks-tombol' => $tercapai,
                        'border-garis bg-permukaan-2 text-redup' => ! $tercapai,
                    ])><x-ikon :nama="$ikon" class="size-5" /></span>
                    <span @class(['text-xs leading-tight font-medium', 'text-teks' => $tercapai, 'text-redup' => ! $tercapai])>{{ $nama }}</span>
                </li>
            @endforeach
        </ol>
    @endunless

    @if ($status === StatusPermintaan::Ditolak)
        <section class="kartu">
            <h2>Penjelasan dari tim komite</h2>
            <p class="m-0 whitespace-pre-line">{{ $permintaan->alasan_penolakan }}</p>
        </section>
    @elseif (in_array($status, [StatusPermintaan::Diajukan, StatusPermintaan::Ditinjau], true))
        <section class="kartu flex gap-4">
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-aksen-lembut text-aksen"><x-ikon nama="jam" class="size-5" /></span>
            <div>
                <h2 class="mb-1">Langkah berikutnya</h2>
                <p class="m-0 text-[15px] text-redup">Tim komite sedang membaca permintaan Anda dan akan menugaskan peer konselor pendamping. Biasanya dalam 1–2 hari kerja.</p>
            </div>
        </section>
    @endif

    @if ($permintaan->konselor)
        <div class="mb-6 flex items-center gap-3 rounded-2xl border border-garis bg-permukaan p-4">
            <span class="grid size-11 place-items-center rounded-full bg-utama-lembut font-bold text-utama">{{ mb_strtoupper(mb_substr($permintaan->konselor->nama, 0, 1)) }}</span>
            <span class="leading-tight">
                <span class="block text-xs text-redup">Konselor pendamping</span>
                <strong>{{ $permintaan->konselor->nama }}</strong>
            </span>
        </div>
    @endif

    @foreach ($pertemuanTerjadwal->filter(fn ($pertemuan) => $pertemuan->mulai_pada->copy()->addMinutes($pertemuan->durasi_menit)->isFuture()) as $pertemuan)
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-utama-garis bg-utama-lembut p-5 sm:flex-row sm:items-center" role="status">
            <span class="grid size-11 shrink-0 place-items-center rounded-2xl bg-utama text-teks-tombol"><x-ikon nama="video" class="size-5" /></span>
            <div class="flex-1 text-sm">
                <strong class="block text-base">Pertemuan daring terjadwal</strong>
                {{ $pertemuan->waktu() }}
            </div>
            @if ($pertemuan->tautan)
                <a class="tombol" href="{{ $pertemuan->tautan }}" target="_blank" rel="noreferrer noopener">Gabung pertemuan</a>
            @endif
        </div>
    @endforeach

    @include('partials.kasus.penutupan', ['permintaan' => $permintaan, 'peran' => 'anggota'])

    @unless ($status->terbuka())
        <section class="kartu" id="lanjutan">
            <h2>Masih membutuhkan pendampingan?</h2>
            @if (auth()->user()->can('bukaKembali', $permintaan) && ! $adaPermintaanLain)
                <p class="text-[15px] text-redup">Bila Anda ingin melanjutkan hal yang sama bersama {{ $permintaan->konselor?->nama ?? 'konselor yang sama' }}, buka kembali percakapan ini (sampai {{ $permintaan->batasBukaKembali()->translatedFormat('j F Y') }}). Untuk hal yang berbeda, ajukan permintaan baru.</p>
                <details @if ($errors->has('alasan') || $errors->has('buka_kembali')) open @endif>
                    <summary><strong>Buka kembali percakapan</strong></summary>
                    <form method="POST" action="{{ route('penutupan.buka-kembali', $permintaan) }}" class="mt-2">
                        @csrf
                        <div class="bidang">
                            <label for="alasan-buka">Apa yang ingin Anda lanjutkan?</label>
                            <textarea id="alasan-buka" name="alasan" rows="3" maxlength="5000" required>{{ old('alasan') }}</textarea>
                            <p class="bantuan">Tulisan ini menjadi pesan pertama Anda di percakapan, dan konselor diberi tahu lewat surel/WhatsApp.</p>
                        </div>
                        <button type="submit" class="tombol">Buka kembali</button>
                    </form>
                </details>
            @elseif ($status === \App\Enums\StatusPermintaan::Selesai && $adaPermintaanLain)
                <p class="text-[15px] text-redup">Anda sudah punya permintaan lain yang sedang berjalan. Lanjutkan percakapan di sana.</p>
            @else
                <p class="text-[15px] text-redup">Anda dapat mengajukan permintaan layanan baru kapan saja. Tim komite akan membacanya dan menugaskan peer konselor.</p>
            @endif
            @unless ($adaPermintaanLain)
                <form method="POST" action="{{ route('permintaan.mulai') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="tombol sekunder">Ajukan permintaan baru</button>
                </form>
            @endunless
        </section>
    @endunless

    @include('partials.kasus.instrumen', ['permintaan' => $permintaan, 'peran' => 'anggota'])

    @if ($permintaan->konselor_id)
        @include('partials.kasus.percakapan', [
            'permintaan' => $permintaan,
            'bolehKirim' => auth()->user()->can('bercakap', $permintaan),
            'keterangan' => $status->bolehBercakap() ? 'Percakapan ini hanya dibaca Anda, konselor pendamping, dan tim komite yang memantau layanan.' : 'Percakapan sudah ditutup dan hanya dapat dibaca.',
        ])
        @include('partials.kasus.pertemuan', ['permintaan' => $permintaan, 'peran' => 'anggota'])
    @endif

    <details class="kartu group">
        <summary class="flex items-center justify-between font-semibold">
            <span class="flex items-center gap-2"><x-ikon nama="dokumen" class="size-5 text-utama" /> Isian yang Anda kirim</span>
            <span class="text-sm font-normal text-redup group-open:hidden">Tampilkan</span>
        </summary>
        <div class="mt-4">
            @foreach ($permintaan->labelTopik() as $label)
                <span class="lencana">{{ $label }}</span>
            @endforeach
            @if ($permintaan->topik_lainnya)
                <p class="text-sm">{{ $permintaan->topik_lainnya }}</p>
            @endif
            <p class="mt-3 whitespace-pre-line">{{ $permintaan->uraian }}</p>
            @if ($permintaan->harapan)
                <p class="mb-1 text-xs font-semibold tracking-wide text-redup uppercase">Harapan</p>
                <p class="whitespace-pre-line">{{ $permintaan->harapan }}</p>
            @endif
            <p class="m-0 text-sm text-redup">
                {{ Permintaan::BENTUK_LAYANAN[$permintaan->bentuk_layanan] ?? '' }} ·
                {{ $permintaan->jawabanAsesmen->count() }} instrumen asesmen diisi
            </p>
        </div>
    </details>

    @can('batalkan', $permintaan)
        <form method="POST" action="{{ route('permintaan.batalkan', $permintaan) }}" class="mb-6 text-center" onsubmit="return confirm('Batalkan permintaan ini?')">
            @csrf
            <button type="submit" class="cursor-pointer text-sm text-redup underline-offset-2 hover:text-bahaya hover:underline">Batalkan permintaan</button>
        </form>
    @endcan

    @include('partials.kontak', ['kontak' => $kontak])
</x-tata-letak>
