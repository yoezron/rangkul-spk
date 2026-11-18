@php
    use App\Enums\StatusPermintaan;
    $status = $permintaan->status;
@endphp
<x-kelola :judul="'Permintaan '.$permintaan->kode" aktif="permintaan">
    <x-slot:keterangan>{{ $status->label() }} · diajukan {{ $permintaan->diajukan_pada?->format('d/m/Y H.i') }}</x-slot:keterangan>

    <p class="kecil"><a href="{{ route('kelola.permintaan.index') }}">← Semua permintaan</a></p>

    <section class="kartu">
        <h2>Tindakan komite</h2>

        <div class="dua-kolom">
            <div>
                <p class="mb-1.5"><strong>Konselor pendamping (PIC)</strong></p>
                <p>{{ $permintaan->konselor?->nama ?? 'Belum ditugaskan' }}
                    @if ($permintaan->konselor && $permintaan->kampusSama($permintaan->konselor))
                        <span class="lencana peringatan">Sekampus</span>
                    @endif
                </p>

                @if ($status->terbuka())
                    @if ($calonKonselor->isEmpty())
                        <p class="catatan">Belum ada pengguna dengan peran peer konselor. Tugaskan peran <em>Peer Konselor</em> di menu Pengguna &amp; penugasan.</p>
                    @else
                        <form method="POST" action="{{ route('kelola.permintaan.tugaskan', $permintaan) }}">
                            @csrf
                            <label for="konselor_id" class="kecil">{{ $permintaan->konselor ? 'Ganti konselor' : 'Tugaskan konselor' }}</label>
                            <select id="konselor_id" name="konselor_id" required>
                                @foreach ($calonKonselor as $calon)
                                    <option value="{{ $calon->id }}" @selected(old('konselor_id', $permintaan->konselor_id) == $calon->id)>
                                        {{ $calon->nama }} — {{ $calon->kampus ?: 'kampus ?' }}, {{ $calon->gender ?: '?' }} · {{ $calon->kasus_aktif_count }} kasus aktif{{ $permintaan->kampusSama($calon) ? ' · SEKAMPUS' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($permintaan->hindari_kampus_sama)
                                <p class="bantuan">Anggota meminta konselor dari kampus lain ({{ $permintaan->anggota->kampus }}).</p>
                                <label class="pilihan kecil"><input type="checkbox" name="konfirmasi_kampus" value="1"> Tetap tugaskan walau sekampus</label>
                            @endif
                            @if ($permintaan->gender_konselor !== 'bebas')
                                <p class="bantuan">Preferensi anggota: konselor {{ $permintaan->gender_konselor }}.</p>
                            @endif
                            <button type="submit" class="tombol ringkas mt-2">Tugaskan</button>
                        </form>
                    @endif
                @endif
            </div>

            <div>
                <p class="mb-1.5"><strong>Status</strong></p>
                <div class="baris-aksi mb-2.5">
                    @if ($status === StatusPermintaan::Diajukan)
                        <form method="POST" action="{{ route('kelola.permintaan.tinjau', $permintaan) }}">
                            @csrf
                            <button type="submit" class="tombol sekunder ringkas">Tandai sedang ditinjau</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('kelola.permintaan.darurat', $permintaan) }}">
                        @csrf
                        <input type="hidden" name="darurat" value="{{ $permintaan->darurat ? 0 : 1 }}">
                        <button type="submit" class="tombol {{ $permintaan->darurat ? 'sekunder' : 'bahaya' }} ringkas">{{ $permintaan->darurat ? 'Hapus tanda darurat' : 'Tandai darurat' }}</button>
                    </form>
                </div>

                @if (in_array($status, [StatusPermintaan::Diajukan, StatusPermintaan::Ditinjau], true))
                    <details @if ($errors->has('alasan')) open @endif>
                        <summary class="kecil">Tidak dapat dilayani…</summary>
                        <form class="mt-2" method="POST" action="{{ route('kelola.permintaan.tolak', $permintaan) }}" onsubmit="return confirm('Tutup permintaan ini? Anggota akan menerima penjelasan Anda.')">
                            @csrf
                            <label for="alasan-tolak" class="kecil">Penjelasan dan saran untuk anggota</label>
                            <textarea id="alasan-tolak" name="alasan" rows="3" maxlength="3000" required>{{ old('alasan') }}</textarea>
                            <button type="submit" class="tombol bahaya ringkas mt-1.5">Tutup permintaan</button>
                        </form>
                    </details>
                @endif
                @if ($status === StatusPermintaan::Ditolak)
                    <p class="kecil"><strong>Penjelasan kepada anggota:</strong> {{ $permintaan->alasan_penolakan }}</p>
                @endif
            </div>
        </div>

        <form class="mt-3" method="POST" action="{{ route('kelola.permintaan.catatan', $permintaan) }}">
            @csrf
            <label for="catatan_komite" class="kecil">Catatan internal komite (tidak terlihat anggota maupun konselor)</label>
            <textarea id="catatan_komite" name="catatan_komite" rows="2" maxlength="10000">{{ old('catatan_komite', $permintaan->catatan_komite) }}</textarea>
            <button type="submit" class="tombol sekunder ringkas mt-1.5">Simpan catatan</button>
        </form>
    </section>

    @include('partials.kasus.penutupan', ['permintaan' => $permintaan, 'peran' => 'komite'])

    @include('partials.kasus.isian', ['permintaan' => $permintaan])

    @if ($permintaan->konselor_id)
        @include('partials.kasus.percakapan', [
            'permintaan' => $permintaan,
            'bolehKirim' => false,
            'keterangan' => 'Mode pantau: komite membaca tanpa ikut mengirim pesan, dan membaca di sini tidak menandai pesan sebagai terbaca.',
        ])
        @include('partials.kasus.pertemuan', ['permintaan' => $permintaan, 'peran' => 'komite'])
    @endif

    @include('partials.kasus.catatan-sesi', ['permintaan' => $permintaan, 'bolehTulis' => false])
</x-kelola>
