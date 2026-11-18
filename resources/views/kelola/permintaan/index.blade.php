@php use App\Enums\StatusPermintaan; @endphp
<x-kelola judul="Permintaan layanan" aktif="permintaan">
    <x-slot:keterangan>Permintaan yang masuk dari anggota. Yang ditandai darurat selalu di atas.</x-slot:keterangan>

    <form method="GET" action="{{ route('kelola.permintaan.index') }}" class="baris-aksi" style="margin-bottom: 16px">
        <div style="flex: 1 1 220px">
            <label for="status" class="kecil">Tampilkan</label>
            <select id="status" name="status">
                <option value="terbuka" @selected($status === 'terbuka')>Semua yang masih berjalan</option>
                <option value="belum_ditugaskan" @selected($status === 'belum_ditugaskan')>Belum ditugaskan konselor</option>
                <option value="darurat" @selected($status === 'darurat')>Darurat</option>
                @foreach (StatusPermintaan::terlihatKomite() as $satu)
                    <option value="{{ $satu->value }}" @selected($status === $satu->value)>{{ $satu->label() }}</option>
                @endforeach
                <option value="semua" @selected($status === 'semua')>Semua</option>
            </select>
        </div>
        <div style="flex: 2 1 240px">
            <label for="cari" class="kecil">Cari</label>
            <input type="search" id="cari" name="cari" value="{{ $cari }}" placeholder="Kode, nama anggota, atau kampus">
        </div>
        <button type="submit" class="tombol sekunder" style="align-self: flex-end">Tampilkan</button>
    </form>

    @if ($daftar->isEmpty())
        <p class="redup">Tidak ada permintaan yang cocok.</p>
    @else
        <div class="gulir kartu" style="padding: 4px 8px">
            <table>
                <thead><tr><th>Kode</th><th>Anggota</th><th>Diajukan</th><th>Status</th><th>Konselor</th></tr></thead>
                <tbody>
                    @foreach ($daftar as $permintaan)
                        <tr>
                            <td>
                                <a href="{{ route('kelola.permintaan.tampil', $permintaan) }}"><strong>{{ $permintaan->kode }}</strong></a>
                                @if ($permintaan->darurat && $permintaan->status->terbuka())
                                    <br><span class="lencana" style="background: var(--galat-latar); border-color: var(--galat-garis); color: var(--galat)">DARURAT</span>
                                @endif
                            </td>
                            <td>{{ $permintaan->anggota->nama }}<br><span class="redup kecil">{{ $permintaan->anggota->kampus }}</span></td>
                            <td style="white-space: nowrap">{{ $permintaan->diajukan_pada?->format('d/m/Y H.i') }}</td>
                            <td>{{ $permintaan->status->label() }}</td>
                            <td>{{ $permintaan->konselor?->nama ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <nav class="halaman-nav" aria-label="Halaman">
            @if ($daftar->previousPageUrl())
                <a class="tombol sekunder ringkas" href="{{ $daftar->previousPageUrl() }}">← Sebelumnya</a>
            @endif
            @if ($daftar->nextPageUrl())
                <a class="tombol sekunder ringkas" href="{{ $daftar->nextPageUrl() }}">Berikutnya →</a>
            @endif
        </nav>
    @endif
</x-kelola>
