{{--
    Kuesioner (instrumen) yang dikirim konselor di tengah percakapan.

    Anggota hanya melihat apa yang perlu diisi dan apa yang sudah diisi; skor
    dan tafsiran hanya untuk konselor dan komite.

    @param \App\Models\Permintaan $permintaan  dengan relasi kirimanInstrumen
    @param string $peran  anggota | konselor | komite
    @param \Illuminate\Support\Collection|null $daftarInstrumen  untuk konselor
--}}
@php
    $kiriman = $permintaan->kirimanInstrumen;
    $menunggu = $kiriman->reject->sudahDiisi();
    $terisi = $kiriman->filter->sudahDiisi();
    $bolehKirim = $peran === 'konselor' && auth()->user()->can('tangani', $permintaan);
@endphp

@if ($bolehKirim || $kiriman->isNotEmpty())
    <section class="kartu" id="instrumen">
        <h2 class="flex items-center gap-2"><x-ikon nama="dokumen" class="size-5 text-utama" /> Kuesioner</h2>

        @if ($peran === 'anggota')
            @if ($menunggu->isNotEmpty() && $permintaan->status->bolehBercakap())
                <p class="redup kecil">Konselor meminta Anda mengisi kuesioner berikut. Ini bukan tes dan bukan diagnosis; hasilnya hanya dibaca konselor dan komite.</p>
                @foreach ($menunggu as $satu)
                    <div class="mb-2.5 flex flex-col gap-2 rounded-2xl border border-utama-garis bg-utama-lembut p-4 sm:flex-row sm:items-center">
                        <div class="flex-1 text-sm">
                            <strong class="block text-base">{{ $satu->nama_instrumen }}</strong>
                            {{ count($satu->salinan['butir'] ?? []) }} butir · dikirim {{ $satu->created_at->format('d/m H.i') }}
                            @if ($satu->pengantar)
                                <span class="mt-1 block whitespace-pre-line">{{ $satu->pengantar }}</span>
                            @endif
                        </div>
                        <a class="tombol" href="{{ route('instrumen-kasus.isi', [$permintaan, $satu]) }}">Isi sekarang</a>
                    </div>
                @endforeach
            @endif
            @foreach ($terisi as $satu)
                <p class="kecil m-0 flex items-center gap-1.5"><x-ikon nama="centang" class="size-4 text-utama" /> {{ $satu->nama_instrumen }} — sudah Anda isi {{ $satu->diisi_pada->format('d/m/Y H.i') }}</p>
            @endforeach
        @else
            <p class="redup kecil">Hasil skrining, bukan diagnosis. Bahas bersama anggota di percakapan.</p>

            @if ($bolehKirim)
                <details @if ($errors->has('instrumen_id') || $errors->has('pengantar')) open @endif class="mb-3">
                    <summary><strong>Kirim kuesioner ke anggota</strong></summary>
                    <form method="POST" action="{{ route('instrumen-kasus.kirim', $permintaan) }}" class="mt-2">
                        @csrf
                        <div class="bidang">
                            <label for="instrumen_id">Instrumen</label>
                            <select id="instrumen_id" name="instrumen_id" required>
                                <option value="">Pilih instrumen…</option>
                                @foreach ($daftarInstrumen ?? [] as $instrumen)
                                    @continue(($instrumen->butir ?? []) === [])
                                    <option value="{{ $instrumen->id }}" @selected((string) old('instrumen_id') === (string) $instrumen->id)>
                                        {{ $instrumen->nama }} ({{ count($instrumen->butir) }} butir){{ $instrumen->aktif ? ' · dipakai di asesmen dasar' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('instrumen_id')<p class="bantuan text-bahaya">{{ $message }}</p>@enderror
                        </div>
                        <div class="bidang">
                            <label for="pengantar">Pengantar untuk anggota <span class="font-normal text-redup">(opsional)</span></label>
                            <textarea id="pengantar" name="pengantar" rows="2" maxlength="1000" placeholder="Mis. Supaya saya lebih memahami yang Anda rasakan dua minggu terakhir.">{{ old('pengantar') }}</textarea>
                        </div>
                        <button type="submit" class="tombol sekunder">Kirim kuesioner</button>
                    </form>
                </details>
            @endif

            @foreach ($menunggu as $satu)
                <div class="baris-aksi justify-between border-t border-garis py-2.5 first:border-t-0">
                    <span><strong>{{ $satu->nama_instrumen }}</strong> <span class="lencana netral">Menunggu diisi</span> <span class="redup kecil">dikirim {{ $satu->created_at->format('d/m H.i') }}</span></span>
                    @if ($bolehKirim)
                        <form method="POST" action="{{ route('instrumen-kasus.batal', [$permintaan, $satu]) }}" onsubmit="return confirm('Batalkan kiriman kuesioner ini?')">
                            @csrf
                            <button type="submit" class="tombol sekunder ringkas">Batalkan</button>
                        </form>
                    @endif
                </div>
            @endforeach

            @foreach ($terisi as $satu)
                @include('partials.kasus.hasil-instrumen', [
                    'nama' => $satu->nama_instrumen,
                    'isian' => $satu->isian,
                    'kritis' => $satu->kritis,
                    'keterangan' => 'diisi '.$satu->diisi_pada->format('d/m/Y H.i'),
                ])
            @endforeach

            @if ($kiriman->isEmpty())
                <p class="redup kecil m-0">Belum ada kuesioner yang dikirim.</p>
            @endif
        @endif
    </section>
@endif
