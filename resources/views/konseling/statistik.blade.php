@php use App\Http\Controllers\Kelola\WaktuLayananController; @endphp
<x-kelola judul="Statistik layanan saya" aktif="statistik-saya">
    <x-slot:keterangan>Waktu layanan dan riwayat pendampingan yang telah Anda lakukan sebagai peer konselor.</x-slot:keterangan>

    <div class="mb-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Waktu layanan '.$bulanIni['bulan']->translatedFormat('F Y'), WaktuLayananController::jamMenit($bulanIni['menit_total']), $bulanIni['menit_total'].' menit · '.$bulanIni['kasus'].' kasus'],
            ['Waktu layanan sepanjang waktu', WaktuLayananController::jamMenit($menitSepanjangWaktu), $menitSepanjangWaktu.' menit'],
            ['Kasus sedang didampingi', $riwayat->filter(fn ($kasus) => $kasus->status->bolehBercakap() && $kasus->konselor_id === auth()->id())->count(), 'berlangsung atau menunggu penutupan'],
            ['Kasus selesai', $riwayat->filter(fn ($kasus) => $kasus->status === \App\Enums\StatusPermintaan::Selesai)->count(), 'dari '.$riwayat->count().' kasus yang pernah Anda dampingi'],
        ] as [$label, $nilai, $keterangan])
            <div class="rounded-2xl border border-garis bg-permukaan p-4 shadow-lembut">
                <p class="m-0 text-xs text-redup">{{ $label }}</p>
                <p class="my-1 text-xl font-semibold tabular-nums">{{ $nilai }}</p>
                <p class="m-0 text-xs text-redup">{{ $keterangan }}</p>
            </div>
        @endforeach
    </div>

    <section class="kartu">
        <h2>Waktu layanan {{ count($perBulan) }} bulan terakhir</h2>
        <x-grafik.batang
            :baris="$perBulan->reverse()->map(fn ($bulan) => ['label' => $bulan['bulan']->translatedFormat('F Y'), 'nilai' => $bulan['menit_total']])->values()->all()"
            satuan="menit"
            kosong="Belum ada waktu layanan yang tercatat." />

        <div class="gulir mt-5">
            <table>
                <thead><tr><th>Bulan</th><th class="text-right">Kasus</th><th class="text-right">Percakapan</th><th class="text-right">Pertemuan</th><th class="text-right">Total (menit)</th><th>Setara</th></tr></thead>
                <tbody>
                    @foreach ($perBulan->reverse() as $bulan)
                        <tr>
                            <td>{{ $bulan['bulan']->translatedFormat('F Y') }}</td>
                            <td class="text-right">{{ $bulan['kasus'] }}</td>
                            <td class="text-right">{{ $bulan['menit_percakapan'] }}</td>
                            <td class="text-right">{{ $bulan['menit_pertemuan'] }}</td>
                            <td class="text-right"><strong>{{ $bulan['menit_total'] }}</strong></td>
                            <td class="kecil whitespace-nowrap">{{ WaktuLayananController::jamMenit($bulan['menit_total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <details class="mt-3">
            <summary class="kecil">Bagaimana waktu layanan dihitung?</summary>
            <ul class="kecil mt-1.5 mb-0">
                <li>Percakapan dihitung hanya selama Anda <em>dan</em> anggota sama-sama membuka halaman percakapan, di antara dua pesan yang berjarak paling lama {{ $jedaMenit }} menit.</li>
                <li>Jeda tanpa balasan lebih dari {{ $jedaMenit }} menit tidak dihitung. Hitungan berhenti saat salah satu pihak menutup halaman atau keluar.</li>
                <li>Pertemuan daring dihitung sesuai durasinya setelah waktunya lewat. Batalkan pertemuan yang tidak jadi.</li>
                <li>Angka ini sama dengan yang dilaporkan komite kepada bendahara.</li>
            </ul>
        </details>
    </section>

    <section class="kartu">
        <h2>Riwayat pendampingan</h2>
        @if ($riwayat->isEmpty())
            <p class="redup m-0">Belum ada kasus yang pernah Anda dampingi.</p>
        @else
            <div class="gulir">
                <table>
                    <thead><tr><th>Kode</th><th>Status</th><th>Ditugaskan</th><th>Selesai</th><th class="text-right">Pesan Anda</th><th class="text-right">Pertemuan</th><th class="text-right">Waktu layanan</th></tr></thead>
                    <tbody>
                        @foreach ($riwayat as $kasus)
                            <tr>
                                <td>
                                    @can('dampingi', $kasus)
                                        <a href="{{ route('konseling.tampil', $kasus) }}">{{ $kasus->kode }}</a>
                                    @else
                                        {{ $kasus->kode }} <span class="lencana netral">Dialihkan</span>
                                    @endcan
                                </td>
                                <td>{{ $kasus->status->label() }}</td>
                                <td class="whitespace-nowrap">{{ $kasus->ditugaskan_pada?->format('d/m/Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap">{{ $kasus->selesai_pada?->format('d/m/Y') ?? '—' }}</td>
                                <td class="text-right">{{ $kasus->pesan_saya_count }}</td>
                                <td class="text-right">{{ $kasus->pertemuan_terlaksana_count }}</td>
                                <td class="text-right whitespace-nowrap">{{ $menitPerKasus->get($kasus->id, 0) }} menit</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-kelola>
