<x-kelola :judul="$pengguna->nama" aktif="pengguna">
    <p class="kecil"><a href="{{ route('kelola.pengguna.index') }}">← Semua pengguna</a></p>

    <div class="dua-kolom">
        <section class="kartu">
            <h2>Data dari spk.or.id</h2>
            <table>
                <tbody>
                    <tr><th>Id akun SPK</th><td>{{ $pengguna->spk_id }}</td></tr>
                    <tr><th>Nomor anggota</th><td>{{ $pengguna->nomor_anggota }}</td></tr>
                    <tr><th>Peran SPK</th><td>{{ $pengguna->peran }}</td></tr>
                    <tr><th>Kampus</th><td>{{ $pengguna->kampus }}</td></tr>
                    <tr><th>Wilayah</th><td>{{ $pengguna->wilayah }}</td></tr>
                    <tr><th>Surel</th><td>{{ $pengguna->email }}</td></tr>
                    <tr><th>Telepon</th><td>{{ $pengguna->telp }}</td></tr>
                    <tr><th>Disinkronkan</th><td>{{ $pengguna->disinkronkan_pada?->format('d/m/Y H:i') }}</td></tr>
                </tbody>
            </table>
            <p class="bantuan">Data ini disalin dari spk.or.id setiap kali anggota masuk dan diperiksa ulang; ubah datanya di spk.or.id.</p>
        </section>

        <section class="kartu">
            <h2>Peran di Rangkul</h2>

            @if ($pengguna->adalahSuperadmin())
                <div class="catatan">
                    Superadmin — memiliki semua hak akses karena berperan {{ $pengguna->peran }} di spk.or.id.
                    Status ini hanya bisa dicabut dengan mengubah perannya di spk.or.id.
                </div>
            @endif

            @if ($pengguna->daftarPeran->isEmpty())
                <p class="redup">Belum memegang peran apa pun.</p>
            @else
                <table>
                    <thead><tr><th>Peran</th><th>Ditugaskan</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($pengguna->daftarPeran as $peran)
                            <tr>
                                <td>{{ $peran->nama }}</td>
                                <td class="kecil">
                                    {{ $peran->pivot->created_at?->format('d/m/Y') }}
                                    @if ($penugas->has($peran->pivot->ditugaskan_oleh))
                                        <br><span class="redup">oleh {{ $penugas[$peran->pivot->ditugaskan_oleh]->nama }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($peran->bolehDikelolaOleh($aktor) && $aktor->can('pengguna.tugaskan'))
                                        <form method="POST" action="{{ route('kelola.pengguna.cabut', [$pengguna, $peran]) }}"
                                              onsubmit="return confirm(@js('Cabut peran '.$peran->nama.' dari '.$pengguna->nama.'?'))">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="tombol bahaya ringkas">Cabut</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            @can('pengguna.tugaskan')
                @if ($bisaDitugaskan->isNotEmpty())
                    <form method="POST" action="{{ route('kelola.pengguna.tugaskan', $pengguna) }}" class="baris-aksi" style="margin-top: 16px">
                        @csrf
                        <div style="flex: 1 1 200px">
                            <label for="peran_id" class="kecil">Tugaskan peran</label>
                            <select id="peran_id" name="peran_id" required>
                                @foreach ($bisaDitugaskan as $peran)
                                    <option value="{{ $peran->id }}">{{ $peran->nama }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="tombol" style="align-self: flex-end">Tugaskan</button>
                    </form>
                @endif
            @endcan
        </section>
    </div>
</x-kelola>
