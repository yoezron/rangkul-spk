@php use App\Enums\StatusPermintaan; @endphp
<x-kelola judul="Permintaan layanan" aktif="permintaan">
    <x-slot:keterangan>Permintaan yang masuk dari anggota. Yang ditandai darurat selalu di atas.</x-slot:keterangan>

    <form method="GET" action="{{ route('kelola.permintaan.index') }}" class="baris-aksi mb-4">
        <div class="grow basis-[220px]">
            <label for="status" class="kecil">Tampilkan</label>
            <select id="status" name="status">
                <option value="terbuka" @selected($status === 'terbuka')>Semua yang masih berjalan</option>
                <option value="belum_ditugaskan" @selected($status === 'belum_ditugaskan')>Belum ditugaskan konselor</option>
                <option value="darurat" @selected($status === 'darurat')>Darurat</option>
                <option value="menggantung" @selected($status === 'menggantung')>Tanpa aktivitas {{ \App\Models\Permintaan::JAM_MENGGANTUNG }} jam (menggantung)</option>
                @foreach (StatusPermintaan::terlihatKomite() as $satu)
                    <option value="{{ $satu->value }}" @selected($status === $satu->value)>{{ $satu->label() }}</option>
                @endforeach
                <option value="semua" @selected($status === 'semua')>Semua</option>
            </select>
        </div>
        <div class="grow-[2] basis-[240px]">
            <label for="cari" class="kecil">Cari</label>
            <input type="search" id="cari" name="cari" value="{{ $cari }}" placeholder="Kode, nama anggota, atau kampus">
        </div>
        <button type="submit" class="tombol sekunder self-end">Tampilkan</button>
    </form>

    @if ($daftar->isEmpty())
        <p class="redup">Tidak ada permintaan yang cocok.</p>
    @else
        <div class="gulir kartu p-2!">
            <table>
                <thead><tr><th>Kode</th><th>Anggota</th><th>Diajukan</th><th>Status</th><th>Konselor</th></tr></thead>
                <tbody>
                    @foreach ($daftar as $permintaan)
                        <tr>
                            <td>
                                <a href="{{ route('kelola.permintaan.tampil', $permintaan) }}"><strong>{{ $permintaan->kode }}</strong></a>
                                @if ($permintaan->darurat && $permintaan->status->terbuka())
                                    <br><span class="lencana bahaya font-semibold">DARURAT</span>
                                @endif
                            </td>
                            <td>{{ $permintaan->anggota->nama }}<br><span class="redup kecil">{{ $permintaan->anggota->kampus }}</span></td>
                            <td class="whitespace-nowrap">{{ $permintaan->diajukan_pada?->format('d/m/Y H.i') }}</td>
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
