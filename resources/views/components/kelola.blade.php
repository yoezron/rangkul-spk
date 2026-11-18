@props(['judul', 'aktif' => null])
{{-- Tata letak panel pengelola dan konselor: menu samping (menu lipat di layar kecil). --}}
@php
    $pengguna = auth()->user();
    $sebutan = match (true) {
        $pengguna->adalahSuperadmin() => 'Superadmin',
        $pengguna->can('dasbor.lihat') => 'Pengelola',
        default => 'Peer konselor',
    };
    $inisial = mb_strtoupper(mb_substr(trim($pengguna->nama), 0, 1));
@endphp
<x-dokumen :judul="$judul">
    <div class="min-h-screen lg:grid lg:grid-cols-[17rem_minmax(0,1fr)]">
        {{-- Menu samping (layar lebar) --}}
        <aside class="sticky top-0 hidden h-screen flex-col border-r border-garis bg-permukaan lg:flex">
            <a href="{{ route('beranda') }}" class="flex items-center gap-2.5 px-5 pt-6 pb-5 text-teks no-underline hover:no-underline">
                <x-logo class="size-9" />
                <span class="leading-tight">
                    <span class="block font-bold">SPK Rangkul</span>
                    <span class="block text-xs text-redup">Panel {{ strtolower($sebutan) }}</span>
                </span>
            </a>
            <div class="flex-1 overflow-y-auto px-3 pb-4">
                @include('partials.menu-kelola', ['aktif' => $aktif])
            </div>
            <div class="border-t border-garis p-3">
                <div class="flex items-center gap-3 rounded-xl px-2 py-2">
                    <span class="grid size-9 place-items-center rounded-full bg-aksen-lembut text-sm font-bold text-aksen">{{ $inisial }}</span>
                    <span class="min-w-0 flex-1 leading-tight">
                        <span class="block truncate text-sm font-semibold">{{ $pengguna->nama }}</span>
                        <span class="block text-xs text-redup">{{ $sebutan }}</span>
                    </span>
                </div>
                <div class="mt-1 grid grid-cols-2 gap-1">
                    <a href="{{ route('beranda') }}" class="flex items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium text-redup no-underline hover:bg-permukaan-2 hover:text-teks hover:no-underline">
                        <x-ikon nama="rumah" class="size-4" /> Beranda
                    </a>
                    <form method="POST" action="{{ route('keluar') }}" class="m-0">
                        @csrf
                        <button type="submit" class="flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium text-redup hover:bg-permukaan-2 hover:text-teks">
                            <x-ikon nama="keluar" class="size-4" /> Keluar
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <div class="min-w-0">
            {{-- Kepala + menu lipat (layar kecil) --}}
            <details class="group sticky top-0 z-30 border-b border-garis bg-permukaan/95 backdrop-blur-md lg:hidden">
                <summary class="flex list-none items-center justify-between gap-3 px-4 py-3 [&::-webkit-details-marker]:hidden">
                    <span class="flex items-center gap-2.5">
                        <x-logo class="size-8" />
                        <span class="text-[15px] font-bold">SPK Rangkul</span>
                    </span>
                    <span class="flex items-center gap-2 rounded-lg border border-garis px-2.5 py-1.5 text-sm font-medium">
                        <x-ikon nama="menu" class="size-5" /> Menu
                    </span>
                </summary>
                <div class="max-h-[75vh] overflow-y-auto border-t border-garis px-3 py-4">
                    @include('partials.menu-kelola', ['aktif' => $aktif])
                    <div class="mt-4 flex gap-2 border-t border-garis pt-4">
                        <a class="tombol sekunder ringkas" href="{{ route('beranda') }}"><x-ikon nama="rumah" class="size-4" /> Beranda</a>
                        <form method="POST" action="{{ route('keluar') }}" class="m-0">
                            @csrf
                            <button type="submit" class="tombol sekunder ringkas"><x-ikon nama="keluar" class="size-4" /> Keluar</button>
                        </form>
                    </div>
                </div>
            </details>

            <main class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-8 sm:py-9">
                <header class="mb-7">
                    <h1>{{ $judul }}</h1>
                    @isset($keterangan)
                        <p class="mt-1.5 max-w-3xl text-[15px] text-redup">{{ $keterangan }}</p>
                    @endisset
                </header>

                @if (session('status'))
                    <div class="catatan info flex items-start gap-2.5" role="status">
                        <x-ikon nama="centang" class="mt-0.5 size-5 text-utama" />
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if ($errors->any())
                    <div class="catatan galat flex items-start gap-2.5" role="alert">
                        <x-ikon nama="peringatan" class="mt-0.5 size-5" />
                        <ul class="list-none! pl-0!">
                            @foreach ($errors->all() as $pesan)
                                <li>{{ $pesan }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</x-dokumen>
