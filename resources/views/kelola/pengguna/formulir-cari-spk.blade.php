{{-- Kotak pencarian anggota di spk.or.id menurut nama. @param string $nama --}}
<form method="GET" action="{{ route('kelola.pengguna.cari-spk') }}" class="flex flex-col gap-2 sm:flex-row" role="search">
    <label for="nama-spk" class="sr-only">Nama anggota</label>
    <div class="relative grow">
        <x-ikon nama="kelompok" class="pointer-events-none absolute top-1/2 left-3.5 size-[18px] -translate-y-1/2 text-redup" />
        <input type="search" id="nama-spk" name="nama" minlength="3" maxlength="60" required autocomplete="off"
               value="{{ $nama }}" placeholder="Ketik sedikitnya 3 huruf nama anggota" class="pl-10!">
    </div>
    <button type="submit" class="tombol">Cari di spk.or.id</button>
</form>
