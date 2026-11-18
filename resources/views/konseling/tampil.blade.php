<x-kelola :judul="'Kasus '.$permintaan->kode" aktif="kasus">
    <x-slot:keterangan>{{ $permintaan->status->label() }} · ditugaskan {{ $permintaan->ditugaskan_pada?->format('d/m/Y') }}</x-slot:keterangan>

    <p class="kecil"><a href="{{ route('konseling.index') }}">← Semua kasus</a></p>

    @include('partials.kasus.penutupan', ['permintaan' => $permintaan, 'peran' => 'konselor'])

    @include('partials.kasus.percakapan', [
        'permintaan' => $permintaan,
        'bolehKirim' => auth()->user()->can('bercakap', $permintaan),
        'keterangan' => 'Mulailah dengan menyapa dan mendengarkan. Tim komite dapat memantau percakapan ini.',
    ])

    @include('partials.kasus.pertemuan', ['permintaan' => $permintaan, 'peran' => 'konselor'])

    @include('partials.kasus.catatan-sesi', ['permintaan' => $permintaan, 'bolehTulis' => auth()->user()->can('catat', $permintaan)])

    <h2 style="margin-top: 28px">Isian dan asesmen anggota</h2>
    @include('partials.kasus.isian', ['permintaan' => $permintaan])
</x-kelola>
