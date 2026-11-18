<x-kelola judul="Audit log" aktif="audit">
    <x-slot:keterangan>Catatan tindakan pengelola. Tidak bisa diubah atau dihapus dari dashboard; catatan lama dibuang otomatis sesuai masa simpan.</x-slot:keterangan>

    <form method="GET" action="{{ route('kelola.audit.index') }}" class="baris-aksi" style="margin-bottom: 16px">
        <div style="flex: 0 1 320px">
            <label for="aksi" class="kecil">Tindakan</label>
            <select id="aksi" name="aksi">
                <option value="">Semua</option>
                @foreach (\App\Models\AuditLog::AKSI as $kunci => $label)
                    <option value="{{ $kunci }}" @selected($aksi === $kunci)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="tombol sekunder" style="align-self: flex-end">Tampilkan</button>
    </form>

    @if ($catatan->isEmpty())
        <p class="redup">Belum ada catatan.</p>
    @else
        <div class="gulir kartu" style="padding: 4px 8px">
            <table>
                <thead><tr><th>Waktu</th><th>Pengelola</th><th>Tindakan</th><th>Sasaran</th><th>Rincian</th><th>IP</th></tr></thead>
                <tbody>
                    @foreach ($catatan as $satu)
                        <tr>
                            <td style="white-space: nowrap">{{ $satu->dibuat_pada->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $satu->aktor }}</td>
                            <td>{{ $satu->labelAksi() }}</td>
                            <td>{{ $satu->sasaran }}</td>
                            <td class="kecil">{{ $satu->ringkasanRincian() }}</td>
                            <td class="kecil">{{ $satu->alamat_ip }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <nav class="halaman-nav" aria-label="Halaman">
            @if ($catatan->previousPageUrl())
                <a class="tombol sekunder ringkas" href="{{ $catatan->previousPageUrl() }}">← Lebih baru</a>
            @endif
            @if ($catatan->nextPageUrl())
                <a class="tombol sekunder ringkas" href="{{ $catatan->nextPageUrl() }}">Lebih lama →</a>
            @endif
        </nav>
    @endif
</x-kelola>
