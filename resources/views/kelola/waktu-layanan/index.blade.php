@php use App\Http\Controllers\Kelola\WaktuLayananController; @endphp
<x-kelola judul="Waktu layanan konselor" aktif="waktu-layanan">
    <x-slot:keterangan>Menit pelayanan tiap konselor per bulan, untuk dilaporkan kepada bendahara sebagai dasar pembayaran.</x-slot:keterangan>

    <form method="GET" action="{{ route('kelola.waktu-layanan.index') }}" class="baris-aksi mb-4">
        <div class="basis-[220px]">
            <label for="bulan" class="kecil">Bulan</label>
            <input type="month" id="bulan" name="bulan" value="{{ $bulan->format('Y-m') }}" max="{{ now()->format('Y-m') }}">
        </div>
        <button type="submit" class="tombol sekunder self-end">Tampilkan</button>
    </form>

    <details class="catatan mb-4">
        <summary><strong>Cara menghitung</strong></summary>
        <ul class="mt-1.5 mb-0">
            <li><strong>Percakapan</strong> dihitung hanya selama konselor <em>dan</em> anggota sama-sama membuka halaman percakapan kasus itu, dan hanya di antara dua pesan yang berjarak paling lama {{ $jedaMenit }} menit.</li>
            <li>Jeda tanpa balasan lebih dari {{ $jedaMenit }} menit tidak dihitung sama sekali. Hitungan juga berhenti begitu salah satu pihak menutup halaman, pindah halaman, atau keluar dari aplikasi.</li>
            <li><strong>Pertemuan daring</strong> dihitung sesuai durasi pertemuan terjadwal yang waktunya sudah lewat. Batalkan pertemuan yang tidak jadi supaya tidak terhitung.</li>
            <li>Waktu dibagi per bulan (WIB); rentang yang melewati pergantian bulan dipotong pada batasnya. Menit dibulatkan per kasus.</li>
        </ul>
    </details>

    @if ($rekap === [])
        <p class="redup">Belum ada waktu layanan yang tercatat untuk {{ $bulan->translatedFormat('F Y') }}.</p>
    @else
        <div class="gulir kartu p-2!">
            <table>
                <thead>
                    <tr><th>Konselor</th><th>Kasus</th><th class="text-right">Percakapan</th><th class="text-right">Pertemuan</th><th class="text-right">Total (menit)</th><th>Setara</th></tr>
                </thead>
                <tbody>
                    @foreach ($rekap as $baris)
                        <tr>
                            <td>
                                <strong>{{ $baris['konselor']->nama }}</strong><br>
                                <span class="redup kecil">{{ $baris['konselor']->kampus ?: '—' }}</span>
                            </td>
                            <td>
                                <details>
                                    <summary class="kecil">{{ count($baris['kasus']) }} kasus</summary>
                                    <ul class="kecil mt-1 mb-0 pl-4">
                                        @foreach ($baris['kasus'] as $kasus)
                                            <li class="whitespace-nowrap">{{ $kasus['permintaan']?->kode }}: {{ $kasus['menit_total'] }} mnt</li>
                                        @endforeach
                                    </ul>
                                </details>
                            </td>
                            <td class="text-right">{{ number_format($baris['menit_percakapan'], 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format($baris['menit_pertemuan'], 0, ',', '.') }}</td>
                            <td class="text-right"><strong>{{ number_format($baris['menit_total'], 0, ',', '.') }}</strong></td>
                            <td class="kecil whitespace-nowrap">{{ WaktuLayananController::jamMenit($baris['menit_total']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th>Total</th>
                        <th>{{ array_sum(array_map(fn ($baris) => count($baris['kasus']), $rekap)) }} kasus</th>
                        <th class="text-right">{{ number_format(array_sum(array_column($rekap, 'menit_percakapan')), 0, ',', '.') }}</th>
                        <th class="text-right">{{ number_format(array_sum(array_column($rekap, 'menit_pertemuan')), 0, ',', '.') }}</th>
                        <th class="text-right">{{ number_format(array_sum(array_column($rekap, 'menit_total')), 0, ',', '.') }}</th>
                        <th class="kecil whitespace-nowrap">{{ WaktuLayananController::jamMenit(array_sum(array_column($rekap, 'menit_total'))) }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    <section class="kartu mt-4">
        <h2>Unduh untuk bendahara</h2>
        <p class="redup kecil">Berkas CSV untuk {{ $bulan->translatedFormat('F Y') }}. Rincian per kasus hanya memuat kode kasus, tanpa identitas anggota. Setiap unduhan tercatat di audit log.</p>
        <form method="GET" action="{{ route('kelola.waktu-layanan.unduh') }}" class="baris-aksi">
            <input type="hidden" name="bulan" value="{{ $bulan->format('Y-m') }}">
            <div class="basis-[220px]">
                <label for="isi" class="kecil">Isi berkas</label>
                <select id="isi" name="isi">
                    <option value="ringkasan">Ringkasan per konselor</option>
                    <option value="rincian">Rincian per konselor dan kasus</option>
                </select>
            </div>
            <div class="basis-[260px]">
                <label for="pemisah" class="kecil">Pemisah kolom</label>
                <select id="pemisah" name="pemisah">
                    <option value="koma">Koma (standar)</option>
                    <option value="titik_koma">Titik koma (Excel berbahasa Indonesia)</option>
                </select>
            </div>
            <button type="submit" class="tombol self-end">Unduh CSV</button>
        </form>
    </section>
</x-kelola>
