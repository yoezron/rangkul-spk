<x-kelola judul="Pengguna & penugasan" aktif="pengguna">
    <x-slot:keterangan>Anggota yang pernah masuk ke Rangkul atau ditambahkan pengelola. Buka seorang anggota untuk menugaskannya sebagai peer konselor, administrator, atau peran lain.</x-slot:keterangan>

    @can('pengguna.tugaskan')
        <section class="kartu">
            <h2 class="mb-1 flex items-center gap-2"><x-ikon nama="kelompok" class="size-5 text-utama" /> Tambah &amp; tugaskan anggota dari spk.or.id</h2>
            <p class="redup kecil">Cari anggota menurut namanya di spk.or.id — termasuk yang belum pernah membuka Rangkul — lalu tambahkan dan langsung tugaskan perannya, mis. sebagai peer konselor. Hanya anggota terkonfirmasi yang muncul.</p>
            @include('kelola.pengguna.formulir-cari-spk', ['nama' => ''])
        </section>
    @endcan

    <form method="GET" action="{{ route('kelola.pengguna.index') }}" class="baris-aksi mb-4">
        <div class="grow-[2] basis-[240px]">
            <label for="cari" class="kecil">Cari</label>
            <input type="search" id="cari" name="cari" value="{{ $cari }}" placeholder="Nama, nomor anggota, kampus, wilayah, atau id akun">
        </div>
        <div class="grow basis-[180px]">
            <label for="peran" class="kecil">Peran</label>
            <select id="peran" name="peran">
                <option value="">Semua</option>
                @foreach ($semuaPeran as $peran)
                    <option value="{{ $peran->id }}" @selected($peranId === $peran->id)>{{ $peran->nama }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="tombol sekunder self-end">Tampilkan</button>
    </form>

    @if ($pengguna->isEmpty())
        <p class="redup">Tidak ada pengguna yang cocok.</p>
    @else
        <div class="gulir kartu p-2!">
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
