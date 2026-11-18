<x-kelola judul="Ringkasan" aktif="dasbor">
    @if ($layanan)
        <section class="mb-8">
            <h2 class="flex items-center gap-2"><x-ikon nama="hati" class="size-5 text-aksen" /> Layanan konsultasi</h2>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-5">
                <x-angka label="Darurat terbuka" :nilai="$layanan['darurat']" ikon="peringatan" :warna="$layanan['darurat'] > 0 ? 'bahaya' : 'netral'" :tautan="route('kelola.permintaan.index', ['status' => 'darurat'])" />
                <x-angka label="Belum ditugaskan" :nilai="$layanan['belumDitugaskan']" ikon="kotak-masuk" warna="waspada" :tautan="route('kelola.permintaan.index', ['status' => 'belum_ditugaskan'])" />
                <x-angka label="Pendampingan berlangsung" :nilai="$layanan['berlangsung']" ikon="chat" :tautan="route('kelola.permintaan.index', ['status' => 'berlangsung'])" />
                <x-angka label="Menunggu persetujuan komite" :nilai="$layanan['menungguPersetujuan']" ikon="jam" warna="aksen" :tautan="route('kelola.permintaan.index')" />
                <x-angka label="Selesai bulan ini" :nilai="$layanan['selesaiBulanIni']" ikon="centang" warna="sukses" />
            </div>
        </section>
    @endif

    <section class="mb-8">
        <h2 class="flex items-center gap-2"><x-ikon nama="susun" class="size-5 text-utama" /> Sistem</h2>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-5">
            <x-angka label="Status layanan" :nilai="$layananBuka ? 'Dibuka' : 'Ditutup'" ikon="perisai" :warna="$layananBuka ? 'sukses' : 'waspada'">
                @can('pengaturan.umum')<a href="{{ route('kelola.pengaturan.layanan') }}">Ubah</a>@endcan
            </x-angka>
            <x-angka label="Anggota terdaftar" :nilai="number_format($jumlahAnggota, 0, ',', '.')" ikon="kelompok" />
            <x-angka label="Aktif 30 hari terakhir" :nilai="number_format($aktif30Hari, 0, ',', '.')" ikon="grafik" warna="aksen" />
            <x-angka label="Pengiriman surel" :nilai="$surelSiap ? 'Aktif' : 'Belum'" ikon="surel" :warna="$surelSiap ? 'sukses' : 'netral'">
                @can('pengaturan.surel')<a href="{{ route('kelola.pengaturan.surel') }}">Atur</a>@endcan
            </x-angka>
            <x-angka label="Notifikasi WhatsApp" :nilai="$whatsappSiap ? 'Aktif' : 'Belum'" ikon="telepon" :warna="$whatsappSiap ? 'sukses' : 'netral'">
                @can('pengaturan.whatsapp')<a href="{{ route('kelola.pengaturan.whatsapp') }}">Atur</a>@endcan
            </x-angka>
        </div>
    </section>

    <div class="dua-kolom">
        <section class="kartu">
            <h2>Peran di Rangkul</h2>
            <table>
                <thead><tr><th>Peran</th><th>Pemegang</th></tr></thead>
                <tbody>
                    <tr>
                        <td>Superadmin <span class="redup kecil">(Administrator di spk.or.id)</span></td>
                        <td>{{ $jumlahSuperadmin }}</td>
                    </tr>
                    @foreach ($daftarPeran as $peran)
                        <tr>
                            <td>
                                @can('pengguna.lihat')
                                    <a href="{{ route('kelola.pengguna.index', ['peran' => $peran->id]) }}">{{ $peran->nama }}</a>
                                @else
                                    {{ $peran->nama }}
                                @endcan
                            </td>
                            <td>{{ $peran->pengguna_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="kartu">
            <h2>Anggota per wilayah</h2>
            @if ($perWilayah->isEmpty())
                <p class="redup">Belum ada anggota yang masuk.</p>
            @else
                <table>
                    <thead><tr><th>Wilayah</th><th>Anggota</th></tr></thead>
                    <tbody>
                        @foreach ($perWilayah as $baris)
                            <tr><td>{{ $baris->wilayah ?: '(tidak diisi)' }}</td><td>{{ $baris->jumlah }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    @can('audit.lihat')
        <section class="kartu">
            <h2>Tindakan pengelola terbaru</h2>
            @if ($auditTerbaru->isEmpty())
                <p class="redup">Belum ada catatan.</p>
            @else
                <div class="gulir">
                    <table>
                        <thead><tr><th>Waktu</th><th>Pengelola</th><th>Tindakan</th><th>Sasaran</th></tr></thead>
                        <tbody>
                            @foreach ($auditTerbaru as $catatan)
                                <tr>
                                    <td>{{ $catatan->dibuat_pada->format('d/m/Y H:i') }}</td>
                                    <td>{{ $catatan->aktor }}</td>
                                    <td>{{ $catatan->labelAksi() }}</td>
                                    <td>{{ $catatan->sasaran }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="kecil"><a href="{{ route('kelola.audit.index') }}">Lihat semua</a></p>
            @endif
        </section>
    @endcan
</x-kelola>
