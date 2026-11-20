<x-kelola judul="Tambah & tugaskan anggota" aktif="pengguna">
    <x-slot:keterangan>Cari anggota terkonfirmasi di spk.or.id menurut nama, lalu tambahkan ke Rangkul dan tugaskan perannya sekaligus.</x-slot:keterangan>

    <a href="{{ route('kelola.pengguna.index') }}" class="mb-5 inline-flex items-center gap-1.5 text-sm text-redup no-underline hover:text-teks hover:no-underline">
        <x-ikon nama="panah-kiri" class="size-4" /> Pengguna &amp; penugasan
    </a>

    <section class="kartu">
        @include('kelola.pengguna.formulir-cari-spk', ['nama' => $nama])
        <p class="bantuan mt-3">Yang tampil hanya nama, kampus, wilayah, dan peran SPK. Email dan nomor telepon baru disalin ke Rangkul setelah anggota ditambahkan.</p>
    </section>

    @if ($galat)
        <div class="catatan galat flex items-start gap-2.5" role="alert"><x-ikon nama="peringatan" class="mt-0.5 size-5" /><span>{{ $galat }}</span></div>
    @endif

    @if ($hasil !== null)
        @if ($hasil === [])
            <div class="rounded-2xl border border-dashed border-garis p-8 text-center">
                <x-ikon nama="kelompok" class="mx-auto mb-2 size-8 text-redup" />
                <p class="m-0 font-semibold">Tidak ada anggota terkonfirmasi dengan nama "{{ $nama }}".</p>
                <p class="m-0 text-sm text-redup">Periksa ejaannya, atau coba sebagian nama saja. Calon Anggota, akun nonaktif, dan akun yang masih diwajibkan mengganti password di spk.or.id memang tidak ditampilkan — anggota itu perlu login ke spk.or.id dan mengganti passwordnya lebih dulu.</p>
            </div>
        @else
            <p class="mb-3 text-sm text-redup">{{ count($hasil) }} anggota ditemukan{{ count($hasil) >= 20 ? ' (20 teratas — perjelas nama bila belum ketemu)' : '' }}.</p>
            <ul class="space-y-3">
                @foreach ($hasil as $anggota)
                    @php
                        $lokal = $terdaftar->get($anggota['id']);
                        $pilihan = $bisaDitugaskan->reject(fn ($peran) => $lokal?->daftarPeran->contains($peran));
                    @endphp
                    <li class="flex flex-col gap-4 rounded-2xl border border-garis bg-permukaan p-4 shadow-lembut sm:flex-row sm:items-center">
                        <span class="grid size-11 shrink-0 place-items-center rounded-full bg-utama-lembut font-bold text-utama">{{ mb_strtoupper(mb_substr($anggota['nama'], 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <strong class="block">{{ $anggota['nama'] }}</strong>
                            <span class="block text-sm text-redup">
                                {{ collect([$anggota['kampus'], $anggota['wilayah'], $anggota['peran']])->filter()->implode(' · ') ?: '—' }}
                            </span>
                            @if ($lokal)
                                <span class="mt-1.5 flex flex-wrap items-center gap-1">
                                    <span class="lencana netral">Sudah di Rangkul</span>
                                    @foreach ($lokal->daftarPeran as $peran)
                                        <span class="lencana">{{ $peran->nama }}</span>
                                    @endforeach
                                </span>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('kelola.pengguna.tambah') }}" class="flex flex-col gap-2 sm:w-72">
                            @csrf
                            <input type="hidden" name="spk_id" value="{{ $anggota['id'] }}">
                            @if ($pilihan->isNotEmpty())
                                <label for="peran-{{ $anggota['id'] }}" class="sr-only">Peran untuk {{ $anggota['nama'] }}</label>
                                <select id="peran-{{ $anggota['id'] }}" name="peran_id" class="py-2!">
                                    <option value="">{{ $lokal ? 'Pilih peran…' : 'Tanpa peran dulu' }}</option>
                                    @foreach ($pilihan as $peran)
                                        <option value="{{ $peran->id }}">{{ $peran->nama }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <div class="flex gap-2">
                                @if (! $lokal || $pilihan->isNotEmpty())
                                    <button type="submit" class="tombol ringkas grow">{{ $lokal ? 'Tugaskan' : 'Tambahkan' }}</button>
                                @endif
                                @if ($lokal)
                                    <a href="{{ route('kelola.pengguna.tampil', $lokal) }}" class="tombol sekunder ringkas">Buka</a>
                                @endif
                            </div>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</x-kelola>
