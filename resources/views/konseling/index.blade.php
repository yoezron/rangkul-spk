<x-kelola judul="Kasus pendampingan" aktif="kasus">
    <x-slot:keterangan>Permintaan yang ditugaskan kepada Anda sebagai peer konselor.</x-slot:keterangan>

    @if ($daftar->isEmpty())
        <p class="redup">Belum ada kasus yang ditugaskan kepada Anda.</p>
    @else
        <div class="gulir kartu" style="padding: 4px 8px">
            <table>
                <thead><tr><th>Kode</th><th>Anggota</th><th>Status</th><th>Pesan baru</th><th>Ditugaskan</th></tr></thead>
                <tbody>
                    @foreach ($daftar as $permintaan)
                        <tr>
                            <td>
                                <a href="{{ route('konseling.tampil', $permintaan) }}">{{ $permintaan->kode }}</a>
                                @if ($permintaan->darurat && $permintaan->status->terbuka())
                                    <span class="lencana peringatan">Darurat</span>
                                @endif
                            </td>
                            <td>{{ $permintaan->anggota->nama }}<br><span class="redup kecil">{{ $permintaan->anggota->kampus }}</span></td>
                            <td>{{ $permintaan->status->label() }}</td>
                            <td>
                                @if ($permintaan->belum_dibaca_count > 0)
                                    <span class="lencana peringatan">{{ $permintaan->belum_dibaca_count }}</span>
                                @else
                                    <span class="redup">—</span>
                                @endif
                            </td>
                            <td>{{ $permintaan->ditugaskan_pada?->format('d/m/Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-kelola>
