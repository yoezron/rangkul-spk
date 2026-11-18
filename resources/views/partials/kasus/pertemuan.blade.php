{{--
    Pertemuan daring.

    @param \App\Models\Permintaan $permintaan
    @param string $peran  anggota | konselor | komite
--}}
@php
    use App\Enums\StatusPertemuan;
    $bolehUsul = $peran === 'konselor' && auth()->user()->can('tangani', $permintaan);
@endphp
<section class="kartu" id="pertemuan">
    <h2>Pertemuan daring</h2>

    @if ($permintaan->pertemuan->isEmpty())
        <p class="redup">Belum ada pertemuan daring. {{ $peran === 'anggota' ? 'Konselor dapat mengusulkan pertemuan bila diperlukan.' : '' }}</p>
    @endif

    @foreach ($permintaan->pertemuan as $pertemuan)
        <div class="border-t border-garis py-3 first:border-t-0">
            <strong>{{ $pertemuan->waktu() }}</strong>
            <span class="lencana {{ $pertemuan->status === StatusPertemuan::Terjadwal ? '' : 'peringatan' }}">{{ $pertemuan->status->label() }}</span>
            @if ($pertemuan->status === StatusPertemuan::Terjadwal && $pertemuan->mulai_pada->copy()->addMinutes($pertemuan->durasi_menit)->isPast())
                <span class="lencana">Sudah lewat</span>
            @endif
            @if ($pertemuan->catatan)
                <p class="kecil mt-1 mb-0">{{ $pertemuan->catatan }}</p>
            @endif
            @if ($pertemuan->alasan_penolakan && $peran !== 'anggota')
                <p class="kecil redup mt-1 mb-0">Alasan ditolak: {{ $pertemuan->alasan_penolakan }}</p>
            @endif

            @if ($pertemuan->tautan && ($peran !== 'anggota' || $pertemuan->status === StatusPertemuan::Terjadwal))
                <p class="mt-2 mb-0">
                    <a class="tombol ringkas" href="{{ $pertemuan->tautan }}" target="_blank" rel="noreferrer noopener">Buka tautan pertemuan</a>
                </p>
            @elseif ($pertemuan->status === StatusPertemuan::Terjadwal)
                <p class="kecil redup mt-1.5 mb-0">Tautan pertemuan belum diisi konselor.</p>
            @endif

            @if ($peran === 'anggota' && $pertemuan->status === StatusPertemuan::Diusulkan)
                <form class="mt-2.5" method="POST" action="{{ route('pertemuan.tanggapi', [$permintaan, $pertemuan]) }}">
                    @csrf
                    <div class="baris-aksi">
                        <button type="submit" name="keputusan" value="setuju" class="tombol ringkas">Setuju</button>
                        <button type="submit" name="keputusan" value="tolak" class="tombol sekunder ringkas">Tidak bisa</button>
                    </div>
                    <label for="alasan-{{ $pertemuan->id }}" class="kecil mt-2">Alasan bila tidak bisa (opsional)</label>
                    <input type="text" id="alasan-{{ $pertemuan->id }}" name="alasan" maxlength="500">
                </form>
            @endif

            @if ($peran === 'komite' && $pertemuan->status === StatusPertemuan::MenungguKomite)
                <form class="mt-2.5" method="POST" action="{{ route('pertemuan.putuskan', [$permintaan, $pertemuan]) }}">
                    @csrf
                    <label for="alasan-komite-{{ $pertemuan->id }}" class="kecil">Alasan bila ditolak</label>
                    <input type="text" id="alasan-komite-{{ $pertemuan->id }}" name="alasan" maxlength="1000">
                    <div class="baris-aksi mt-2">
                        <button type="submit" name="keputusan" value="setuju" class="tombol ringkas">Setujui pertemuan</button>
                        <button type="submit" name="keputusan" value="tolak" class="tombol bahaya ringkas">Tolak</button>
                    </div>
                </form>
            @endif

            @if ($peran === 'konselor' && $pertemuan->status->aktif() && auth()->user()->can('tangani', $permintaan))
                <form method="POST" action="{{ route('pertemuan.tautan', [$permintaan, $pertemuan]) }}" class="baris-aksi mt-2.5">
                    @csrf
                    @method('PUT')
                    <div class="grow basis-[260px]">
                        <label for="tautan-{{ $pertemuan->id }}" class="kecil">Tautan Zoom / Google Meet</label>
                        <input type="url" id="tautan-{{ $pertemuan->id }}" name="tautan" maxlength="500" placeholder="https://" value="{{ $pertemuan->tautan }}">
                    </div>
                    <button type="submit" class="tombol sekunder ringkas self-end">Simpan tautan</button>
                </form>
                <form class="mt-1.5" method="POST" action="{{ route('pertemuan.batal', [$permintaan, $pertemuan]) }}" onsubmit="return confirm('Batalkan pertemuan ini?')">
                    @csrf
                    <button type="submit" class="tombol bahaya ringkas">Batalkan pertemuan</button>
                </form>
            @endif
        </div>
    @endforeach

    @if ($bolehUsul && ! $permintaan->adaPertemuanAktif())
        <details class="mt-3" @if ($errors->has('mulai_pada') || $errors->has('pertemuan')) open @endif>
            <summary><strong>Usulkan pertemuan daring</strong></summary>
            <p class="redup kecil mt-1.5">
                Sepakati waktunya dulu dengan anggota lewat percakapan.
                @if ($permintaan->pertemuanPerluKomite())
                    Ini akan menjadi pertemuan daring pertama, sehingga perlu persetujuan komite setelah anggota setuju.
                @endif
            </p>
            <form method="POST" action="{{ route('pertemuan.usul', $permintaan) }}">
                @csrf
                <div class="dua-kolom">
                    <div class="bidang">
                        <label for="mulai_pada">Waktu (WIB)</label>
                        <input type="datetime-local" id="mulai_pada" name="mulai_pada" required value="{{ old('mulai_pada') }}">
                    </div>
                    <div class="bidang">
                        <label for="durasi_menit">Durasi (menit)</label>
                        <input type="number" id="durasi_menit" name="durasi_menit" min="15" max="180" step="15" required value="{{ old('durasi_menit', 60) }}">
                    </div>
                </div>
                <div class="bidang">
                    <label for="tautan">Tautan Zoom / Google Meet (boleh diisi nanti)</label>
                    <input type="url" id="tautan" name="tautan" maxlength="500" placeholder="https://" value="{{ old('tautan') }}">
                    <p class="bantuan">Anggota baru melihat tautan setelah pertemuan terjadwal.</p>
                </div>
                <div class="bidang">
                    <label for="catatan-pertemuan">Catatan untuk anggota (opsional)</label>
                    <input type="text" id="catatan-pertemuan" name="catatan" maxlength="500" value="{{ old('catatan') }}">
                </div>
                <button type="submit" class="tombol">Kirim usulan</button>
            </form>
        </details>
    @endif
</section>
