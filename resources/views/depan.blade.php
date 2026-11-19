@php
    $tombolUtama = function (string $kelas = '') use ($sudahMasuk, $alamatMasuk, $isi) {
        if ($sudahMasuk) {
            return '<a class="tombol '.$kelas.'" href="'.e(route('beranda')).'">Buka beranda saya</a>';
        }

        return $alamatMasuk
            ? '<a class="tombol '.$kelas.'" href="'.e($alamatMasuk).'" rel="noreferrer">'.e($isi['teks_tombol']).'</a>'
            : '<span class="tombol '.$kelas.' pointer-events-none opacity-60">Segera hadir</span>';
    };
    // Ditulis utuh supaya terbaca Tailwind saat membangun CSS.
    $kolomLebar = [1 => 'lg:grid-cols-1', 2 => 'lg:grid-cols-2', 3 => 'lg:grid-cols-3', 4 => 'lg:grid-cols-4'];
    $navigasi = array_filter([
        'tentang' => ($isi['isi'] !== '' || $isi['keunggulan'] !== []) ? 'Tentang' : null,
        'cara-kerja' => $isi['langkah'] !== [] ? 'Cara kerja' : null,
        'tanya-jawab' => $isi['faq'] !== [] ? 'Tanya jawab' : null,
        'kontak' => 'Kontak',
    ]);
@endphp
<x-dokumen judul="Layanan bantuan psikologis" :indeks="$isi['indeks']" :deskripsi="$isi['subjudul']">
    {{-- Kepala halaman --}}
    <header class="sticky top-0 z-30 border-b border-garis/70 bg-latar/85 backdrop-blur-md">
        <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
            <a href="{{ route('depan') }}" class="flex items-center gap-2.5 text-teks no-underline hover:no-underline">
                <x-logo class="size-9" />
                <span class="leading-tight">
                    <span class="block font-bold">SPK Rangkul</span>
                    <span class="hidden text-[11px] text-redup sm:block">Serikat Pekerja Kampus</span>
                </span>
            </a>
            <nav class="hidden items-center gap-1 md:flex" aria-label="Bagian halaman">
                @foreach ($navigasi as $jangkar => $label)
                    <a href="#{{ $jangkar }}" class="rounded-lg px-3 py-2 text-sm font-medium text-redup no-underline hover:bg-permukaan hover:text-teks hover:no-underline">{{ $label }}</a>
                @endforeach
            </nav>
            {!! $tombolUtama('ringkas sm:px-4 sm:py-2 sm:text-sm') !!}
        </div>
    </header>

    <main>
        {{-- Pembuka --}}
        <section class="relative overflow-hidden">
            <div aria-hidden="true" class="pointer-events-none absolute -top-32 -right-32 size-[28rem] rounded-full bg-utama/10 blur-3xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute top-40 -left-40 size-[24rem] rounded-full bg-aksen/10 blur-3xl"></div>

            <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 py-14 sm:px-6 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] lg:py-24">
                <div>
                    @if ($pesanTutup)
                        <div class="catatan info mb-6 flex items-start gap-2.5" role="status">
                            <x-ikon nama="jam" class="mt-0.5 size-5 text-utama" />
                            <span>{!! nl2br(e($pesanTutup)) !!}</span>
                        </div>
                    @endif

                    @if ($isi['label'] !== '')
                        <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-utama-garis bg-utama-lembut px-3 py-1 text-xs font-semibold tracking-wide text-utama uppercase">
                            <x-ikon nama="hati" class="size-4" /> {{ $isi['label'] }}
                        </p>
                    @endif
                    <h1 class="text-4xl leading-[1.1] font-bold tracking-tight sm:text-5xl lg:text-[3.4rem]">{{ $isi['tajuk'] }}</h1>
                    @if ($isi['subjudul'] !== '')
                        <p class="mt-5 max-w-xl text-lg text-redup">{{ $isi['subjudul'] }}</p>
                    @endif

                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        {!! $tombolUtama('px-6 py-3 text-base') !!}
                        @if ($isi['langkah'] !== [])
                            <a href="#cara-kerja" class="tombol sekunder px-6 py-3 text-base">Lihat cara kerjanya</a>
                        @endif
                    </div>
                    @unless ($sudahMasuk)
                        <p class="mt-4 flex items-center gap-2 text-sm text-redup">
                            <x-ikon nama="kunci" class="size-4" /> Tanpa pendaftaran — cukup akun spk.or.id Anda.
                        </p>
                    @endunless
                </div>

                {{-- Ilustrasi --}}
                <div aria-hidden="true" class="relative mx-auto w-full max-w-md">
                    <div class="absolute inset-6 rounded-[2.5rem] bg-utama/15 blur-2xl"></div>
                    <div class="relative rounded-[2rem] border border-garis bg-permukaan p-6 shadow-angkat">
                        <svg viewBox="0 0 320 220" class="w-full">
                            <circle cx="160" cy="112" r="92" fill="var(--c-utama-lembut)"/>
                            <circle cx="160" cy="72" r="22" fill="var(--c-utama)"/>
                            <path d="M96 120c14 34 38 54 64 54s50-20 64-54" stroke="var(--c-utama)" stroke-width="16" fill="none" stroke-linecap="round"/>
                            <path d="M124 128c10 18 22 28 36 28s26-10 36-28" stroke="var(--c-aksen)" stroke-width="10" fill="none" stroke-linecap="round"/>
                            <rect x="226" y="26" width="78" height="40" rx="14" fill="var(--c-permukaan)" stroke="var(--c-garis)" stroke-width="2"/>
                            <rect x="238" y="40" width="40" height="5" rx="2.5" fill="var(--c-utama)"/>
                            <rect x="238" y="50" width="54" height="5" rx="2.5" fill="var(--c-garis)"/>
                            <rect x="16" y="140" width="84" height="40" rx="14" fill="var(--c-aksen-lembut)" stroke="var(--c-garis)" stroke-width="2"/>
                            <rect x="28" y="154" width="54" height="5" rx="2.5" fill="var(--c-aksen)"/>
                            <rect x="28" y="164" width="36" height="5" rx="2.5" fill="var(--c-garis)"/>
                        </svg>
                        <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                            <span class="rounded-xl bg-permukaan-2 px-2 py-2.5"><x-ikon nama="gembok" class="mx-auto mb-1 size-5 text-utama" />Rahasia</span>
                            <span class="rounded-xl bg-permukaan-2 px-2 py-2.5"><x-ikon nama="chat" class="mx-auto mb-1 size-5 text-utama" />Didengarkan</span>
                            <span class="rounded-xl bg-permukaan-2 px-2 py-2.5"><x-ikon nama="kelompok" class="mx-auto mb-1 size-5 text-utama" />Didampingi</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Tentang dan keunggulan --}}
        @if ($isi['isi'] !== '' || $isi['keunggulan'] !== [])
            <section id="tentang" class="scroll-mt-20 border-t border-garis/70 bg-permukaan">
                <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
                    <div class="max-w-2xl">
                        <h2 class="text-3xl font-bold tracking-tight">{{ $isi['judul_tentang'] }}</h2>
                        @if ($isi['isi'] !== '')
                            <div class="mt-4 text-lg leading-relaxed text-redup">{!! nl2br(e($isi['isi'])) !!}</div>
                        @endif
                    </div>
                    @if ($isi['keunggulan'] !== [])
                        @php $ikonKeunggulan = ['gembok', 'kelompok', 'kunci', 'hati', 'perisai', 'chat']; @endphp
                        <ul class="mt-10 grid gap-4 sm:grid-cols-2 {{ $kolomLebar[min(4, count($isi['keunggulan']))] }}">
                            @foreach ($isi['keunggulan'] as $indeks => $satu)
                                <li class="rounded-2xl border border-garis bg-latar p-6">
                                    <span class="mb-4 grid size-11 place-items-center rounded-xl {{ $indeks % 2 ? 'bg-aksen-lembut text-aksen' : 'bg-utama-lembut text-utama' }}">
                                        <x-ikon :nama="$ikonKeunggulan[$indeks % count($ikonKeunggulan)]" class="size-5" />
                                    </span>
                                    <h3 class="mb-1.5 font-semibold">{{ $satu['judul'] }}</h3>
                                    <p class="m-0 text-[15px] text-redup">{{ $satu['teks'] }}</p>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        @endif

        {{-- Cara kerja --}}
        @if ($isi['langkah'] !== [])
            <section id="cara-kerja" class="scroll-mt-20">
                <div class="mx-auto max-w-6xl px-4 py-16 sm:px-6 lg:py-20">
                    <h2 class="text-3xl font-bold tracking-tight">Cara kerjanya</h2>
                    <p class="mt-2 max-w-2xl text-lg text-redup">Dari bercerita sampai didampingi, langkahnya sederhana.</p>
                    <ol class="mt-10 grid gap-4 md:grid-cols-2 {{ $kolomLebar[min(4, count($isi['langkah']))] }}">
                        @foreach ($isi['langkah'] as $nomor => $satu)
                            <li class="relative rounded-2xl border border-garis bg-permukaan p-6 shadow-lembut">
                                <span class="mb-4 grid size-10 place-items-center rounded-full bg-utama text-sm font-bold text-teks-tombol">{{ $nomor + 1 }}</span>
                                <h3 class="mb-1.5 font-semibold">{{ $satu['judul'] }}</h3>
                                <p class="m-0 text-[15px] text-redup">{{ $satu['teks'] }}</p>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>
        @endif

        {{-- Tanya jawab --}}
        @if ($isi['faq'] !== [])
            <section id="tanya-jawab" class="scroll-mt-20 border-t border-garis/70 bg-permukaan">
                <div class="mx-auto grid max-w-6xl gap-10 px-4 py-16 sm:px-6 lg:grid-cols-[minmax(0,0.8fr)_minmax(0,1.2fr)] lg:py-20">
                    <div>
                        <h2 class="text-3xl font-bold tracking-tight">Tanya jawab</h2>
                        <p class="mt-2 text-lg text-redup">Hal-hal yang sering ditanyakan sebelum mulai bercerita.</p>
                    </div>
                    <div class="space-y-3">
                        @foreach ($isi['faq'] as $satu)
                            <details class="group rounded-2xl border border-garis bg-latar px-5 py-4 open:bg-permukaan open:shadow-lembut">
                                <summary class="flex list-none items-center justify-between gap-4 font-semibold [&::-webkit-details-marker]:hidden">
                                    {{ $satu['pertanyaan'] }}
                                    <span class="grid size-7 shrink-0 place-items-center rounded-full bg-utama-lembut text-utama transition group-open:rotate-45">
                                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                                    </span>
                                </summary>
                                <p class="mt-3 mb-0 text-[15px] text-redup">{!! nl2br(e($satu['jawaban'])) !!}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- Kontak dan ajakan --}}
        <section id="kontak" class="scroll-mt-20">
            <div class="mx-auto grid max-w-6xl gap-6 px-4 py-16 sm:px-6 lg:grid-cols-2 lg:py-20">
                <div class="relative overflow-hidden rounded-3xl bg-utama p-8 text-teks-tombol shadow-angkat sm:p-10">
                    <div aria-hidden="true" class="pointer-events-none absolute -right-16 -bottom-20 size-64 rounded-full bg-white/10 blur-2xl"></div>
                    <h2 class="relative text-3xl font-bold tracking-tight text-teks-tombol">Siap bercerita?</h2>
                    <p class="relative mt-3 text-[15px] opacity-90">Anda tidak harus menunggu sampai keadaan terasa sangat berat. Mulailah dengan menceritakan apa yang sedang Anda alami.</p>
                    <div class="relative mt-6">
                        @if ($sudahMasuk)
                            <a class="tombol border-white/30 bg-white/15 px-6 py-3 text-base text-teks-tombol hover:bg-white/25" href="{{ route('beranda') }}">Buka beranda saya</a>
                        @elseif ($alamatMasuk)
                            <a class="tombol border-white/30 bg-white/15 px-6 py-3 text-base text-teks-tombol hover:bg-white/25" href="{{ $alamatMasuk }}" rel="noreferrer">{{ $isi['teks_tombol'] }}</a>
                        @endif
                    </div>
                </div>
                <div>
                    <h2 class="mb-4 text-2xl font-bold tracking-tight">Kontak</h2>
                    @include('partials.kontak', ['kontak' => $kontak])
                    @if ($kontak['email'] === '' && $kontak['whatsapp'] === '' && $kontak['jam_layanan'] === '')
                        <p class="text-sm text-redup">Pertanyaan seputar layanan dapat disampaikan kepada pengurus Serikat Pekerja Kampus di wilayah Anda.</p>
                    @endif
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-garis/70">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-3 px-4 py-6 text-xs text-redup sm:px-6">
            <span class="flex items-center gap-2"><x-logo class="size-6" /> SPK Rangkul · Serikat Pekerja Kampus</span>
            <span class="flex items-center gap-1.5"><x-ikon nama="gembok" class="size-4" /> Isian dan percakapan disimpan terenkripsi.</span>
            <span class="flex items-center gap-1.5"><x-ikon nama="hati" class="size-4 text-aksen" /> Dalam bahaya? Segera hubungi layanan darurat 112.</span>
        </div>
    </footer>
</x-dokumen>
