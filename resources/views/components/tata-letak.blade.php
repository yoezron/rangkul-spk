@props(['judul' => null, 'lebar' => false])
{{-- Tata letak halaman anggota: kepala halaman, isi di tengah, kaki halaman. --}}
@php
    $tautan = [
        ['beranda', 'Beranda', 'rumah', true],
        ['permintaan.index', 'Permintaan saya', 'chat', true],
        ['konseling.index', 'Kasus pendampingan', 'kelompok', auth()->user()?->can('konseling.tangani')],
        ['kelola.dasbor', 'Pengelola', 'grafik', auth()->user()?->can('dasbor.lihat')],
    ];
@endphp
<x-dokumen :judul="$judul">
    <div class="flex min-h-screen flex-col">
        <header class="sticky top-0 z-30 border-b border-garis/80 bg-latar/85 backdrop-blur-md">
            <div class="mx-auto flex max-w-5xl items-center justify-between gap-3 px-4 py-3 sm:px-6">
                <a href="{{ auth()->check() ? route('beranda') : route('masuk') }}" class="flex items-center gap-2.5 text-teks no-underline hover:no-underline">
                    <x-logo class="size-8" />
                    <span class="leading-tight">
                        <span class="block text-[15px] font-bold">SPK Rangkul</span>
                        <span class="hidden text-[11px] text-redup sm:block">Ruang aman anggota Serikat Pekerja Kampus</span>
                    </span>
                </a>

                @auth
                    <nav class="flex items-center gap-1" aria-label="Menu utama">
                        @foreach ($tautan as [$rute, $label, $ikon, $tampil])
                            @if ($tampil)
                                <a href="{{ route($rute) }}" title="{{ $label }}"
                                   @class([
                                       'flex items-center gap-2 rounded-lg px-2.5 py-2 text-sm font-medium no-underline transition hover:bg-permukaan hover:no-underline',
                                       'bg-permukaan text-utama shadow-xs' => request()->routeIs(str_replace('.index', '.*', $rute)) || request()->routeIs($rute),
                                       'text-redup' => ! (request()->routeIs(str_replace('.index', '.*', $rute)) || request()->routeIs($rute)),
                                   ])>
                                    <x-ikon :nama="$ikon" class="size-[18px]" />
                                    <span class="hidden md:inline">{{ $label }}</span>
                                </a>
                            @endif
                        @endforeach
                        <form method="POST" action="{{ route('keluar') }}" class="m-0">
                            @csrf
                            <button type="submit" title="Keluar" class="flex cursor-pointer items-center gap-2 rounded-lg px-2.5 py-2 text-sm font-medium text-redup transition hover:bg-permukaan hover:text-teks">
                                <x-ikon nama="keluar" class="size-[18px]" />
                                <span class="hidden md:inline">Keluar</span>
                            </button>
                        </form>
                    </nav>
                @endauth
            </div>
        </header>

        <main @class(['mx-auto w-full flex-1 px-4 py-8 sm:px-6 sm:py-10', 'max-w-3xl' => $lebar, 'max-w-xl' => ! $lebar])>
            {{ $slot }}
        </main>

        <footer class="border-t border-garis/80">
            <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-5 text-xs text-redup sm:px-6">
                <span class="flex items-center gap-1.5"><x-ikon nama="gembok" class="size-4" /> Isian dan percakapan Anda disimpan terenkripsi.</span>
                <span class="flex items-center gap-1.5"><x-ikon nama="hati" class="size-4 text-aksen" /> Dalam bahaya? Segera hubungi layanan darurat 112.</span>
            </div>
        </footer>
    </div>
</x-dokumen>
