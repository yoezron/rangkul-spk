<x-kelola judul="Asesmen dasar" aktif="instrumen">
    <x-slot:keterangan>Instrumen yang diisi anggota saat mengajukan permintaan. Hanya instrumen yang "dipakai" yang muncul di formulir, sesuai urutannya.</x-slot:keterangan>

    <div class="catatan" role="note">
        Asesmen dasar adalah alat skrining untuk membantu konselor memahami keadaan anggota, bukan diagnosis.
        Teks bawaan adalah terjemahan kerja — ganti dengan versi bahasa Indonesia yang sudah divalidasi bila tersedia.
        Jaga formulir tetap ringkas: anggota yang sedang tertekan mudah lelah mengisi puluhan butir.
    </div>

    <p><a class="tombol" href="{{ route('kelola.instrumen.create') }}">Buat instrumen</a></p>

    <div class="gulir kartu" style="padding: 4px 8px">
        <table>
            <thead><tr><th>Urutan</th><th>Instrumen</th><th>Butir</th><th>Sudah dijawab</th><th>Di formulir</th><th></th></tr></thead>
            <tbody>
                @foreach ($daftar as $instrumen)
                    <tr>
                        <td>{{ $instrumen->urutan }}</td>
                        <td>
                            <strong>{{ $instrumen->nama }}</strong>
                            @if ($instrumen->bawaan)
                                <span class="lencana">Bawaan</span>
                            @endif
                            @if ($instrumen->sumber)
                                <br><span class="redup kecil">{{ $instrumen->sumber }}</span>
                            @endif
                        </td>
                        <td>{{ count($instrumen->butir) }}</td>
                        <td>{{ $instrumen->jawaban_count }}</td>
                        <td>
                            <form method="POST" action="{{ route('kelola.instrumen.aktif', $instrumen) }}">
                                @csrf
                                <input type="hidden" name="aktif" value="{{ $instrumen->aktif ? 0 : 1 }}">
                                <button type="submit" class="tombol {{ $instrumen->aktif ? '' : 'sekunder' }} ringkas">
                                    {{ $instrumen->aktif ? 'Dipakai' : 'Tidak dipakai' }}
                                </button>
                            </form>
                        </td>
                        <td>
                            <div class="baris-aksi">
                                <a class="tombol sekunder ringkas" href="{{ route('kelola.instrumen.edit', $instrumen) }}">Ubah</a>
                                @if ($instrumen->jawaban_count === 0)
                                    <form method="POST" action="{{ route('kelola.instrumen.destroy', $instrumen) }}"
                                          onsubmit="return confirm(@js('Hapus instrumen '.$instrumen->nama.'?'))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="tombol bahaya ringkas">Hapus</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-kelola>
