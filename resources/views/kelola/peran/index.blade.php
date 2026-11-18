<x-kelola judul="Peran & hak akses" aktif="peran">
    <x-slot:keterangan>Peran menentukan apa yang boleh dilakukan seseorang di Rangkul. Superadmin bukan peran di sini: ia mengikuti peran Administrator di spk.or.id dan selalu memiliki semua hak.</x-slot:keterangan>

    <p><a class="tombol" href="{{ route('kelola.peran.create') }}">Buat peran</a></p>

    <div class="gulir kartu p-2!">
        <table>
            <thead><tr><th>Peran</th><th>Hak akses</th><th>Pemegang</th><th></th></tr></thead>
            <tbody>
                @foreach ($daftarPeran as $peran)
                    <tr>
                        <td>
                            <strong>{{ $peran->nama }}</strong>
                            @if ($peran->bawaan)
                                <span class="lencana">Bawaan</span>
                            @endif
                            @if ($peran->deskripsi)
                                <br><span class="redup kecil">{{ $peran->deskripsi }}</span>
                            @endif
                        </td>
                        <td class="kecil">
                            @forelse ($peran->hakBerlaku() as $hak)
                                <span class="lencana">{{ \App\Enums\Hak::from($hak)->label() }}</span>
                            @empty
                                <span class="redup">Tanpa hak akses</span>
                            @endforelse
                        </td>
                        <td>{{ $peran->pengguna_count }}</td>
                        <td>
                            @if ($peran->bolehDikelolaOleh($aktor))
                                <div class="baris-aksi">
                                    <a class="tombol sekunder ringkas" href="{{ route('kelola.peran.edit', $peran) }}">Ubah</a>
                                    @unless ($peran->bawaan)
                                        <form method="POST" action="{{ route('kelola.peran.destroy', $peran) }}"
                                              onsubmit="return confirm(@js('Hapus peran '.$peran->nama.'? Peran ini akan dicabut dari '.$peran->pengguna_count.' pemegangnya.'))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="tombol bahaya ringkas">Hapus</button>
                                        </form>
                                    @endunless
                                </div>
                            @else
                                <span class="redup kecil">Memuat hak yang tidak Anda miliki</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-kelola>
