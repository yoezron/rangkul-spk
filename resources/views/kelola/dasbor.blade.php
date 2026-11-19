<x-kelola judul="Ringkasan" aktif="dasbor">
    @if ($layanan)
        @php
            $jam = $layanan['medianJamTunggu'];
            $tunggu = match (true) {
                $jam === null => '—',
                $jam < 1 => max(1, (int) round($jam * 60)).' menit',
                $jam < 48 => \App\Models\JawabanAsesmen::angka(round($jam, 1)).' jam',
                default => \App\Models\JawabanAsesmen::angka(round($jam / 24, 1)).' hari',
            };
        @endphp
        <section class="mb-8">
            <h2 class="flex items-center gap-2"><x-ikon nama="hati" class="size-5 text-aksen" /> Layanan konsultasi</h2>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-3 2xl:grid-cols-6">
                <x-angka label="Darurat terbuka" :nilai="$layanan['darurat']" ikon="peringatan" :warna="$layanan['darurat'] > 0 ? 'bahaya' : 'netral'" :tautan="route('kelola.permintaan.index', ['status' => 'darurat'])" />
                <x-angka label="Belum ditugaskan" :nilai="$layanan['belumDitugaskan']" ikon="kotak-masuk" warna="waspada" :tautan="route('kelola.permintaan.index', ['status' => 'belum_ditugaskan'])" />
                <x-angka label="Pendampingan berlangsung" :nilai="$layanan['berlangsung']" ikon="chat" :tautan="route('kelola.permintaan.index', ['status' => 'berlangsung'])" />
                <x-angka label="Menunggu persetujuan komite" :nilai="$layanan['menungguPersetujuan']" ikon="jam" warna="aksen" :tautan="route('kelola.permintaan.index')" />
                <x-angka label="Selesai bulan ini" :nilai="$layanan['selesaiBulanIni']" ikon="centang" warna="sukses" />
                <x-angka label="Median waktu tunggu" :nilai="$tunggu" ikon="kelompok" warna="netral">
                    <span class="text-redup">sampai konselor ditugaskan, 90 hari</span>
                </x-angka>
            </div>
        </section>
    @endif

    @if ($grafik)
        <div class="mb-6 grid gap-6 xl:grid-cols-3">
            <section class="kartu mb-0 xl:col-span-2">
                <h2 class="mb-1">Permintaan per bulan</h2>
                <p class="mb-5 text-sm text-redup">12 bulan terakhir. <em>Masuk</em> dihitung saat diajukan, <em>selesai</em> saat penutupan disetujui komite.</p>
                <x-grafik.kolom-ganda :data="$grafik['tren']" kosong="Belum ada permintaan dalam 12 bulan terakhir."
                    :seri="[['kunci' => 'masuk', 'nama' => 'Masuk', 'warna' => 'var(--c-seri-1)'], ['kunci' => 'selesai', 'nama' => 'Selesai', 'warna' => 'var(--c-seri-2)']]" />
            </section>

            <div class="grid content-start gap-6">
                <section class="kartu mb-0">
                    <h2 class="mb-1">Kasus terbuka per tahap</h2>
                    <p class="mb-4 text-sm text-redup">Urut sesuai alur layanan.</p>
                    <x-grafik.batang :baris="$grafik['alur']" satuan="kasus" kosong="Tidak ada kasus terbuka." />
                </section>
                <section class="kartu mb-0">
                    <h2 class="mb-1">Beban konselor</h2>
                    <p class="mb-4 text-sm text-redup">Kasus aktif per peer konselor — bahan pertimbangan saat menugaskan.</p>
                    <x-grafik.batang :baris="$grafik['beban']" satuan="kasus aktif" kosong="Belum ada kasus aktif." />
                </section>
            </div>
        </div>

        <div class="mb-8 grid gap-6 xl:grid-cols-2">
            <section class="kartu mb-0">
                <h2 class="mb-1">Yang ingin didiskusikan</h2>
                <p class="mb-4 text-sm text-redup">Persentase dari {{ $grafik['topik']['total'] }} permintaan dalam 12 bulan terakhir; satu permintaan boleh memilih beberapa topik.</p>
                <x-grafik.batang :baris="$grafik['topik']['baris']" :total="$grafik['topik']['total']" satuan="permintaan" kosong="Belum ada permintaan dalam 12 bulan terakhir." />
            </section>

            <section class="kartu mb-0">
                <h2 class="mb-1">Tingkat gejala saat pengajuan</h2>
                <p class="mb-5 text-sm text-redup">Sebaran hasil asesmen dasar 12 bulan terakhir, dari ringan ke berat — makin berat makin kontras warnanya. Hasil skrining, bukan diagnosis.</p>
                @forelse ($grafik['gejala'] as $instrumen)
                    <div class="mb-5 last:mb-0">
                        <p class="mb-2 flex items-baseline justify-between gap-3 text-sm">
                            <strong class="font-semibold">{{ $instrumen['judul'] }}</strong>
                            <span class="shrink-0 text-redup tabular-nums">{{ $instrumen['total'] }} jawaban</span>
                        </p>
                        <x-grafik.segmen :bagian="$instrumen['bagian']" :total="$instrumen['total']" />
                    </div>
                @empty
                    <p class="m-0 rounded-xl bg-permukaan-2 px-4 py-8 text-center text-sm text-redup">Tidak ada instrumen asesmen yang dipakai.</p>
                @endforelse
            </section>
        </div>
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
            <h2 class="mb-1">Anggota per wilayah</h2>
            <p class="mb-4 text-sm text-redup">Anggota yang pernah masuk atau ditambahkan ke Rangkul.</p>
            <x-grafik.batang :baris="$perWilayah" satuan="anggota" kosong="Belum ada anggota yang masuk." />
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
