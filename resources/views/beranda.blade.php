<x-tata-letak judul="Beranda" :lebar="true">
    {{-- Sapaan --}}
    <section class="relative mb-6 overflow-hidden rounded-3xl bg-utama p-6 text-teks-tombol shadow-angkat sm:p-8">
        <div aria-hidden="true" class="pointer-events-none absolute -top-16 -right-10 size-56 rounded-full bg-white/10 blur-2xl"></div>
        <div aria-hidden="true" class="pointer-events-none absolute -bottom-20 left-10 size-56 rounded-full bg-aksen/25 blur-3xl"></div>
        <p class="relative mb-1 text-sm font-medium opacity-85">Selamat datang di SPK Rangkul</p>
        <h1 class="relative text-teks-tombol">Halo, {{ $anggota->nama }}.</h1>
        <p class="relative mt-2 max-w-xl text-[15px] opacity-90">Semoga hari Anda baik. Bila sedang terasa berat, kami ada untuk mendengarkan.</p>
    </section>

    {{-- Aksi utama --}}
    @if ($permintaanTerbuka)
        <section class="kartu flex flex-col gap-4 sm:flex-row sm:items-center">
            <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-utama-lembut text-utama"><x-ikon nama="chat" class="size-6" /></span>
            <div class="flex-1">
                @if ($permintaanTerbuka->status === \App\Enums\StatusPermintaan::Draf)
                    <strong class="block">Anda punya draf permintaan yang belum dikirim.</strong>
                    <span class="text-sm text-redup">Isian Anda tersimpan; lanjutkan kapan saja.</span>
                @else
                    <strong class="block">Permintaan {{ $permintaanTerbuka->kode }}</strong>
                    <span class="text-sm text-redup">
                        {{ $permintaanTerbuka->status->label() }}
                        @if ($pesanBelumDibaca > 0)
                            · <span class="lencana peringatan">{{ $pesanBelumDibaca }} pesan baru</span>
                        @endif
                    </span>
                @endif
            </div>
            @if ($permintaanTerbuka->status === \App\Enums\StatusPermintaan::Draf)
                <a class="tombol" href="{{ route('permintaan.langkah', [$permintaanTerbuka, $permintaanTerbuka->langkah]) }}">Lanjutkan mengisi <x-ikon nama="panah-kanan" class="size-4" /></a>
            @else
                <a class="tombol" href="{{ route('permintaan.tampil', $permintaanTerbuka) }}">Buka permintaan <x-ikon nama="panah-kanan" class="size-4" /></a>
            @endif
        </section>
    @elseif ($pesanTutup)
        <div class="catatan info" role="status">{!! nl2br(e($pesanTutup)) !!}</div>
    @else
        <section class="kartu">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-aksen-lembut text-aksen"><x-ikon nama="hati" class="size-6" /></span>
                <div class="flex-1">
                    <h2 class="mb-1">Butuh teman bicara?</h2>
                    <p class="m-0 text-[15px] text-redup">Sedang menghadapi tekanan di tempat kerja, keluarga, atau perasaan yang berat? Ceritakan kepada kami — peer konselor SPK akan mendampingi Anda.</p>
                </div>
            </div>
            <a class="tombol lebar mt-5" href="{{ route('permintaan.index') }}">Ajukan permintaan bantuan <x-ikon nama="panah-kanan" class="size-4" /></a>
            <ol class="mt-5 grid gap-3 text-sm sm:grid-cols-3">
                @foreach ([['dokumen', 'Ceritakan keadaan Anda', 'Formulir 10–15 menit, tersimpan otomatis.'], ['kelompok', 'Komite menugaskan konselor', 'Biasanya dalam 1–2 hari kerja.'], ['chat', 'Mulai didampingi', 'Lewat chat, dan pertemuan daring bila perlu.']] as $nomor => [$ikon, $judulLangkah, $teks])
                    <li class="flex gap-3 rounded-xl bg-permukaan-2 p-3">
                        <span class="grid size-8 shrink-0 place-items-center rounded-full bg-utama-lembut text-xs font-bold text-utama">{{ $nomor + 1 }}</span>
                        <span><strong class="block text-teks">{{ $judulLangkah }}</strong><span class="text-redup">{{ $teks }}</span></span>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- Pintasan peran --}}
    @if (auth()->user()->can('konseling.tangani') || auth()->user()->can('dasbor.lihat'))
        <div class="mb-6 grid gap-3 sm:grid-cols-2">
            @can('konseling.tangani')
                <a href="{{ route('konseling.index') }}" class="group flex items-center gap-4 rounded-2xl border border-garis bg-permukaan p-4 text-teks no-underline shadow-lembut transition hover:-translate-y-0.5 hover:shadow-angkat hover:no-underline">
                    <span class="grid size-11 place-items-center rounded-xl bg-utama-lembut text-utama"><x-ikon nama="kelompok" class="size-5" /></span>
                    <span class="flex-1">
                        <strong class="block">Kasus pendampingan saya</strong>
                        <span class="text-sm text-redup">{{ $kasusAktif > 0 ? $kasusAktif.' kasus aktif' : 'Belum ada kasus aktif' }}</span>
                    </span>
                    <x-ikon nama="panah-kanan" class="size-4 text-redup transition group-hover:translate-x-0.5" />
                </a>
            @endcan
            @can('dasbor.lihat')
                <a href="{{ route('kelola.dasbor') }}" class="group flex items-center gap-4 rounded-2xl border border-garis bg-permukaan p-4 text-teks no-underline shadow-lembut transition hover:-translate-y-0.5 hover:shadow-angkat hover:no-underline">
                    <span class="grid size-11 place-items-center rounded-xl bg-aksen-lembut text-aksen"><x-ikon nama="grafik" class="size-5" /></span>
                    <span class="flex-1">
                        <strong class="block">Dashboard pengelola</strong>
                        <span class="text-sm text-redup">Permintaan, pengaturan, dan rekap</span>
                    </span>
                    <x-ikon nama="panah-kanan" class="size-4 text-redup transition group-hover:translate-x-0.5" />
                </a>
            @endcan
        </div>
    @endif

    @include('partials.kontak', ['kontak' => $kontak])

    <p class="mt-6 text-center text-sm"><a href="{{ route('permintaan.index') }}">Riwayat permintaan saya</a></p>
</x-tata-letak>
