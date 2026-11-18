<x-dokumen judul="Masuk">
    <div class="grid min-h-screen lg:grid-cols-[minmax(0,1.05fr)_minmax(0,1fr)]">
        {{-- Panel sambutan --}}
        <section class="relative overflow-hidden bg-utama px-6 py-10 text-teks-tombol sm:px-10 lg:flex lg:flex-col lg:justify-between lg:gap-12 lg:px-14 lg:py-14">
            <div aria-hidden="true" class="pointer-events-none absolute -top-24 -right-24 size-80 rounded-full bg-white/10 blur-2xl"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-32 -left-20 size-96 rounded-full bg-aksen/25 blur-3xl"></div>

            <div class="relative flex items-center gap-3">
                <svg viewBox="0 0 40 40" aria-hidden="true" class="size-11"><rect width="40" height="40" rx="12" fill="currentColor" fill-opacity=".14"/><circle cx="20" cy="14.5" r="4.5" fill="currentColor"/><path d="M9 20.5c2.6 5.8 6.6 9 11 9s8.4-3.2 11-9" stroke="currentColor" stroke-width="3.2" fill="none" stroke-linecap="round"/><path d="M14 22.6c1.7 2.5 3.7 3.7 6 3.7s4.3-1.2 6-3.7" stroke="var(--c-aksen)" stroke-width="2.4" fill="none" stroke-linecap="round"/></svg>
                <span class="leading-tight">
                    <span class="block text-lg font-bold">SPK Rangkul</span>
                    <span class="block text-sm opacity-80">Serikat Pekerja Kampus</span>
                </span>
            </div>

            <div class="relative mt-10 max-w-lg lg:mt-0">
                <p class="mb-3 inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold tracking-wide uppercase">
                    <x-ikon nama="hati" class="size-4" /> Layanan bantuan psikologis
                </p>
                <h2 class="mb-4 text-3xl leading-tight font-bold text-teks-tombol sm:text-4xl">Ruang aman untuk bercerita, didampingi sesama pekerja kampus.</h2>
                <p class="text-base opacity-90">Beban kerja, konflik, atau perasaan yang berat tidak perlu dipikul sendirian. Ceritakan dengan tenang — peer konselor SPK akan mendengarkan dan mendampingi Anda.</p>

                <ul class="mt-8 grid gap-4 text-sm sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-3">
                    <li class="flex gap-3 rounded-2xl bg-white/10 p-4 backdrop-blur-sm">
                        <x-ikon nama="gembok" class="size-6 opacity-90" />
                        <span><strong class="block">Rahasia</strong><span class="opacity-80">Isian dan percakapan disimpan terenkripsi.</span></span>
                    </li>
                    <li class="flex gap-3 rounded-2xl bg-white/10 p-4 backdrop-blur-sm">
                        <x-ikon nama="kelompok" class="size-6 opacity-90" />
                        <span><strong class="block">Sesama anggota</strong><span class="opacity-80">Didampingi peer konselor SPK yang terlatih.</span></span>
                    </li>
                    <li class="flex gap-3 rounded-2xl bg-white/10 p-4 backdrop-blur-sm">
                        <x-ikon nama="kunci" class="size-6 opacity-90" />
                        <span><strong class="block">Satu akun</strong><span class="opacity-80">Masuk memakai akun spk.or.id Anda.</span></span>
                    </li>
                </ul>
            </div>

            <p class="relative mt-10 hidden text-xs opacity-70 lg:block">© Serikat Pekerja Kampus</p>
        </section>

        {{-- Kartu masuk --}}
        <section class="flex items-center justify-center px-4 py-10 sm:px-8">
            <div class="w-full max-w-md">
                <div class="rounded-3xl border border-garis bg-permukaan p-6 shadow-angkat sm:p-8">
                    <h1>{{ $halaman['judul'] }}</h1>
                    @if ($halaman['subjudul'] !== '')
                        <p class="mt-1 text-redup">{{ $halaman['subjudul'] }}</p>
                    @endif

                    <div class="mt-6">
                        @if ($penjelasan)
                            <div class="catatan flex items-start gap-2.5" role="alert">
                                <x-ikon nama="peringatan" class="mt-0.5 size-5 text-waspada" />
                                <span>{{ $penjelasan }}</span>
                            </div>
                        @elseif ($sudahKeluar)
                            <div class="catatan info flex items-start gap-2.5" role="status">
                                <x-ikon nama="centang" class="mt-0.5 size-5 text-utama" />
                                <span>Anda sudah keluar dari SPK Rangkul. Bila memakai komputer bersama, keluarlah juga dari spk.or.id.</span>
                            </div>
                        @endif

                        @if ($pesanTutup)
                            <div class="catatan info" role="status">{!! nl2br(e($pesanTutup)) !!}</div>
                        @endif

                        @if ($halaman['isi'] !== '')
                            <div class="mb-6 text-[15px] leading-relaxed">{!! nl2br(e($halaman['isi'])) !!}</div>
                        @endif

                        @if ($alamatMasuk)
                            <a class="tombol lebar" href="{{ $alamatMasuk }}" rel="noreferrer">
                                Masuk dengan akun SPK <x-ikon nama="panah-kanan" class="size-4" />
                            </a>
                            <p class="bantuan mt-4 text-center">
                                SPK Rangkul tidak punya password sendiri dan tidak membuka pendaftaran.
                                Anda masuk memakai akun spk.or.id; hanya anggota yang keanggotaannya sudah dikonfirmasi yang dapat masuk.
                            </p>
                        @else
                            <div class="catatan info" role="status">SPK Rangkul sedang disiapkan. Silakan kembali lagi nanti.</div>
                        @endif
                    </div>
                </div>

                <div class="mt-6">
                    @include('partials.kontak', ['kontak' => $kontak])
                </div>
            </div>
        </section>
    </div>
</x-dokumen>
