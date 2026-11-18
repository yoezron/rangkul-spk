<x-kelola judul="Ringkasan" aktif="dasbor">
    <div class="grid-angka">
        <div class="kartu">
            <div class="redup kecil">Status layanan</div>
            <div class="angka">{{ $layananBuka ? 'Dibuka' : 'Ditutup' }}</div>
            @can('pengaturan.umum')
                <a class="kecil" href="{{ route('kelola.pengaturan.layanan') }}">Ubah</a>
            @endcan
        </div>
        <div class="kartu">
            <div class="redup kecil">Anggota terdaftar</div>
            <div class="angka">{{ number_format($jumlahAnggota, 0, ',', '.') }}</div>
        </div>
        <div class="kartu">
            <div class="redup kecil">Aktif 30 hari terakhir</div>
            <div class="angka">{{ number_format($aktif30Hari, 0, ',', '.') }}</div>
        </div>
        <div class="kartu">
            <div class="redup kecil">Pengiriman surel</div>
            <div class="angka">{{ $surelSiap ? 'Aktif' : 'Belum' }}</div>
            @can('pengaturan.surel')
                <a class="kecil" href="{{ route('kelola.pengaturan.surel') }}">Atur</a>
            @endcan
        </div>
        <div class="kartu">
            <div class="redup kecil">Notifikasi WhatsApp</div>
            <div class="angka">{{ $whatsappSiap ? 'Aktif' : 'Belum' }}</div>
            @can('pengaturan.whatsapp')
                <a class="kecil" href="{{ route('kelola.pengaturan.whatsapp') }}">Atur</a>
            @endcan
        </div>
    </div>

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
