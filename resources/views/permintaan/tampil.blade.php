@php
    use App\Enums\StatusPermintaan;
    use App\Models\Permintaan;
    $status = $permintaan->status;
@endphp
<x-tata-letak :judul="'Permintaan '.$permintaan->kode" :lebar="true">
    <p class="kecil"><a href="{{ route('permintaan.index') }}">← Permintaan layanan</a></p>
    <h1>Permintaan {{ $permintaan->kode }}</h1>
    <p class="redup">
        <span class="lencana {{ $status->terbuka() ? '' : 'peringatan' }}">{{ $status->label() }}</span>
        Diajukan {{ $permintaan->diajukan_pada?->translatedFormat('j F Y') }}
    </p>

    @if (session('status'))
        <div class="catatan info" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="catatan galat" role="alert"><ul>@foreach ($errors->all() as $pesan)<li>{{ $pesan }}</li>@endforeach</ul></div>
    @endif

    @if ($baruDiajukan)
        <div class="catatan info" role="status">
            <strong>Terima kasih, permintaan Anda sudah kami terima.</strong>
            Tim komite akan membacanya dan menugaskan peer konselor. Kami akan memberi kabar lewat surel/WhatsApp, dan percakapan dengan konselor akan muncul di halaman ini.
        </div>
    @endif

    @if ($permintaan->darurat && $status->terbuka())
        @include('partials.krisis', ['kontak' => $kontak])
    @endif

    @if ($status === StatusPermintaan::Ditolak)
        <div class="kartu">
            <h2>Penjelasan dari tim komite</h2>
            <p style="white-space: pre-line; margin: 0">{{ $permintaan->alasan_penolakan }}</p>
        </div>
    @elseif (in_array($status, [StatusPermintaan::Diajukan, StatusPermintaan::Ditinjau], true))
        <div class="kartu">
            <h2>Langkah berikutnya</h2>
            <p style="margin: 0">Tim komite sedang membaca permintaan Anda dan akan menugaskan peer konselor pendamping. Biasanya dalam 1–2 hari kerja.</p>
        </div>
    @endif

    @if ($permintaan->konselor)
        <p>Konselor pendamping: <strong>{{ $permintaan->konselor->nama }}</strong></p>
    @endif

    @foreach ($pertemuanTerjadwal->filter(fn ($pertemuan) => $pertemuan->mulai_pada->copy()->addMinutes($pertemuan->durasi_menit)->isFuture()) as $pertemuan)
        <div class="catatan info" role="status">
            <strong>Pertemuan daring terjadwal:</strong> {{ $pertemuan->waktu() }}
            @if ($pertemuan->tautan)
                <br><a class="tombol ringkas" style="margin-top: 8px" href="{{ $pertemuan->tautan }}" target="_blank" rel="noreferrer noopener">Gabung pertemuan</a>
            @endif
        </div>
    @endforeach

    @include('partials.kasus.penutupan', ['permintaan' => $permintaan, 'peran' => 'anggota'])

    @if ($permintaan->konselor_id)
        @include('partials.kasus.percakapan', [
            'permintaan' => $permintaan,
            'bolehKirim' => auth()->user()->can('bercakap', $permintaan),
            'keterangan' => $status->bolehBercakap() ? 'Percakapan ini hanya dibaca Anda, konselor pendamping, dan tim komite yang memantau layanan.' : 'Percakapan sudah ditutup dan hanya dapat dibaca.',
        ])
        @include('partials.kasus.pertemuan', ['permintaan' => $permintaan, 'peran' => 'anggota'])
    @endif

    <details class="kartu">
        <summary><strong>Isian yang Anda kirim</strong></summary>
        <div style="margin-top: 10px">
            @foreach ($permintaan->labelTopik() as $label)
                <span class="lencana">{{ $label }}</span>
            @endforeach
            @if ($permintaan->topik_lainnya)
                <p class="kecil">{{ $permintaan->topik_lainnya }}</p>
            @endif
            <p style="white-space: pre-line; margin-top: 10px">{{ $permintaan->uraian }}</p>
            @if ($permintaan->harapan)
                <p class="redup kecil" style="margin-bottom: 4px"><strong>Harapan</strong></p>
                <p style="white-space: pre-line">{{ $permintaan->harapan }}</p>
            @endif
            <p class="redup kecil" style="margin: 0">
                {{ Permintaan::BENTUK_LAYANAN[$permintaan->bentuk_layanan] ?? '' }} ·
                {{ $permintaan->jawabanAsesmen->count() }} instrumen asesmen diisi
            </p>
        </div>
    </details>

    @can('batalkan', $permintaan)
        <form method="POST" action="{{ route('permintaan.batalkan', $permintaan) }}" onsubmit="return confirm('Batalkan permintaan ini?')">
            @csrf
            <button type="submit" class="tombol bahaya ringkas">Batalkan permintaan</button>
        </form>
    @endcan

    @include('partials.kontak', ['kontak' => $kontak])
</x-tata-letak>
