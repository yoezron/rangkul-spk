<x-kelola judul="Pengguna & penugasan" aktif="pengguna">
    <x-slot:keterangan>Anggota yang pernah masuk ke Rangkul atau ditambahkan pengelola. Buka seorang anggota untuk menugaskannya sebagai peer konselor, administrator, atau peran lain.</x-slot:keterangan>

    @can('pengguna.tugaskan')
        <section class="kartu">
            <h2>Tambahkan anggota dari spk.or.id</h2>
            <p class="redup kecil">Untuk anggota yang belum pernah membuka Rangkul. Masukkan id akun SPK-nya; data diambil dari spk.or.id dan hanya anggota terkonfirmasi yang dapat ditambahkan.</p>
            <form method="POST" action="{{ route('kelola.pengguna.tambah') }}" class="baris-aksi">
                @csrf
                <div style="flex: 1 1 200px">
                    <label for="spk_id" class="kecil">Id akun SPK</label>
                    <input type="number" id="spk_id" name="spk_id" min="1" required value="{{ old('spk_id') }}">
                </div>
                <button type="submit" class="tombol" style="align-self: flex-end">Tambahkan</button>
            </form>
        </section>
    @endcan

    <form method="GET" action="{{ route('kelola.pengguna.index') }}" class="baris-aksi" style="margin-bottom: 16px">
        <div style="flex: 2 1 240px">
            <label for="cari" class="kecil">Cari</label>
            <input type="search" id="cari" name="cari" value="{{ $cari }}" placeholder="Nama, nomor anggota, kampus, wilayah, atau id akun">
        </div>
        <div style="flex: 1 1 180px">
            <label for="peran" class="kecil">Peran</label>
            <select id="peran" name="peran">
                <option value="">Semua</option>
                @foreach ($semuaPeran as $peran)
                    <option value="{{ $peran->id }}" @selected($peranId === $peran->id)>{{ $peran->nama }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="tombol sekunder" style="align-self: flex-end">Tampilkan</button>
    </form>

    @if ($pengguna->isEmpty())
        <p class="redup">Tidak ada pengguna yang cocok.</p>
    @else
        <div class="gulir kartu" style="padding: 4px 8px">
            <table>
                <thead>
                    <tr><th>Nama</th><th>No. anggota</th><th>Kampus</th><th>Wilayah</th><th>Peran SPK</th><th>Peran Rangkul</th></tr>
                </thead>
                <tbody>
                    @foreach ($pengguna as $satu)
                        <tr>
                            <td><a href="{{ route('kelola.pengguna.tampil', $satu) }}">{{ $satu->nama }}</a></td>
                            <td>{{ $satu->nomor_anggota }}</td>
                            <td>{{ $satu->kampus }}</td>
                            <td>{{ $satu->wilayah }}</td>
                            <td>
                                {{ $satu->peran }}
                                @if ($satu->adalahSuperadmin())
                                    <span class="lencana peringatan">Superadmin</span>
                                @endif
                            </td>
                            <td>
                                @foreach ($satu->daftarPeran as $peran)
                                    <span class="lencana">{{ $peran->nama }}</span>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <nav class="halaman-nav" aria-label="Halaman">
            @if ($pengguna->previousPageUrl())
                <a class="tombol sekunder ringkas" href="{{ $pengguna->previousPageUrl() }}">← Sebelumnya</a>
            @endif
            @if ($pengguna->nextPageUrl())
                <a class="tombol sekunder ringkas" href="{{ $pengguna->nextPageUrl() }}">Berikutnya →</a>
            @endif
        </nav>
    @endif
</x-kelola>
