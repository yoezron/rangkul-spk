@php
    $baru = ! $peran->exists;
    $hakDipilih = old('hak', $peran->hakBerlaku());
    $hakAktor = $aktor->daftarHak();
@endphp
<x-kelola :judul="$baru ? 'Buat peran' : 'Ubah peran '.$peran->nama" aktif="peran">
    <p class="kecil"><a href="{{ route('kelola.peran.index') }}">← Semua peran</a></p>

    <form method="POST" action="{{ $baru ? route('kelola.peran.store') : route('kelola.peran.update', $peran) }}">
        @csrf
        @unless ($baru)
            @method('PUT')
        @endunless

        <fieldset>
            <legend>Peran</legend>
            <div class="bidang">
                <label for="nama">Nama</label>
                <input type="text" id="nama" name="nama" maxlength="100" required value="{{ old('nama', $peran->nama) }}">
            </div>
            <div class="bidang">
                <label for="deskripsi">Deskripsi</label>
                <textarea id="deskripsi" name="deskripsi" rows="2" maxlength="500">{{ old('deskripsi', $peran->deskripsi) }}</textarea>
            </div>
        </fieldset>

        <fieldset>
            <legend>Hak akses</legend>
            <p class="bantuan mb-3">
                Hak pengelolaan otomatis disertai hak membuka dashboard. Anda hanya dapat memberikan hak yang Anda miliki sendiri.
            </p>
            @foreach (\App\Enums\Hak::perKelompok() as $kelompok => $daftarHak)
                <p class="mt-0 mb-1.5 font-semibold">{{ $kelompok }}</p>
                @foreach ($daftarHak as $hak)
                    @php $dimiliki = in_array($hak->value, $hakAktor, true); @endphp
                    <div class="bidang mb-2">
                        <label class="pilihan {{ $dimiliki ? '' : 'mati' }}">
                            <input type="checkbox" name="hak[]" value="{{ $hak->value }}"
                                   @checked(in_array($hak->value, $hakDipilih, true)) @disabled(! $dimiliki)>
                            <span>{{ $hak->label() }} <span class="redup kecil">({{ $hak->value }})</span></span>
                        </label>
                    </div>
                @endforeach
            @endforeach
        </fieldset>

        <button type="submit" class="tombol">{{ $baru ? 'Buat peran' : 'Simpan' }}</button>
    </form>
</x-kelola>
