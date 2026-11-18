{{--
    Catatan sesi konselor. Tidak terlihat oleh anggota.

    @param \App\Models\Permintaan $permintaan
    @param bool $bolehTulis
--}}
<section class="kartu" id="catatan">
    <h2>Catatan sesi</h2>
    <p class="redup kecil">Hanya terbaca oleh konselor pendamping, komite, dan superadmin — tidak oleh anggota.</p>

    @forelse ($permintaan->catatanSesi as $catatan)
        <div style="padding: 10px 0; border-top: 1px solid var(--garis)">
            <p class="kecil redup" style="margin: 0 0 6px">
                {{ $catatan->created_at->format('d/m/Y H.i') }} · {{ $catatan->penulis?->nama ?? 'Konselor' }}
                @if ($catatan->pertemuan) · pertemuan {{ $catatan->pertemuan->mulai_pada->format('d/m/Y') }} @endif
                @if ($catatan->rujukan) <span class="lencana peringatan">Rujukan</span> @endif
            </p>
            <p style="margin: 0 0 6px; white-space: pre-line"><strong>Rangkuman:</strong> {{ $catatan->rangkuman }}</p>
            @if ($catatan->analisis)
                <p style="margin: 0 0 6px; white-space: pre-line"><strong>Analisis & prognosis:</strong> {{ $catatan->analisis }}</p>
            @endif
            @if ($catatan->saran)
                <p style="margin: 0 0 6px; white-space: pre-line"><strong>Saran:</strong> {{ $catatan->saran }}</p>
            @endif
            @if ($catatan->rujukan)
                <p style="margin: 0; white-space: pre-line"><strong>Dirujuk ke:</strong> {{ $catatan->rujukan_ke }}</p>
            @endif
        </div>
    @empty
        <p class="redup">Belum ada catatan sesi.</p>
    @endforelse

    @if ($bolehTulis)
        <details style="margin-top: 12px" @if ($errors->has('rangkuman') || $errors->has('rujukan_ke')) open @endif>
            <summary><strong>Tulis catatan sesi</strong></summary>
            <form method="POST" action="{{ route('catatan.simpan', $permintaan) }}" style="margin-top: 10px">
                @csrf
                @php $daftarPertemuan = $permintaan->pertemuan->where('status', \App\Enums\StatusPertemuan::Terjadwal); @endphp
                @if ($daftarPertemuan->isNotEmpty())
                    <div class="bidang">
                        <label for="pertemuan_id">Terkait pertemuan</label>
                        <select id="pertemuan_id" name="pertemuan_id">
                            <option value="">Percakapan / tidak terkait pertemuan</option>
                            @foreach ($daftarPertemuan as $pertemuan)
                                <option value="{{ $pertemuan->id }}">{{ $pertemuan->waktu() }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="bidang">
                    <label for="rangkuman">Rangkuman isi sesi</label>
                    <textarea id="rangkuman" name="rangkuman" rows="4" maxlength="10000" required>{{ old('rangkuman') }}</textarea>
                </div>
                <div class="bidang">
                    <label for="analisis">Analisis dan prognosis</label>
                    <textarea id="analisis" name="analisis" rows="3" maxlength="10000">{{ old('analisis') }}</textarea>
                </div>
                <div class="bidang">
                    <label for="saran">Saran yang direkomendasikan</label>
                    <textarea id="saran" name="saran" rows="3" maxlength="5000">{{ old('saran') }}</textarea>
                </div>
                <div class="bidang">
                    <input type="hidden" name="rujukan" value="0">
                    <label class="pilihan"><input type="checkbox" name="rujukan" value="1" @checked(old('rujukan'))> Menyarankan rujukan ke psikolog/profesional di luar tim Rangkul</label>
                    <label for="rujukan_ke" class="kecil" style="margin-top: 6px">Dirujuk ke (nama psikolog, lembaga, atau layanan)</label>
                    <input type="text" id="rujukan_ke" name="rujukan_ke" maxlength="1000" value="{{ old('rujukan_ke') }}">
                </div>
                <button type="submit" class="tombol">Simpan catatan</button>
            </form>
        </details>
    @endif
</section>
