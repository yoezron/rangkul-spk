{{--
    Menu panel pengelola. Tiap butir tampil hanya bagi pemegang hak yang sesuai.

    @param string|null $aktif
--}}
@php
    $kelompok = [
        'Layanan' => [
            ['dasbor', 'kelola.dasbor', 'Ringkasan', 'dasbor.lihat', 'grafik'],
            ['permintaan', 'kelola.permintaan.index', 'Permintaan layanan', 'permintaan.kelola', 'kotak-masuk'],
            ['kasus', 'konseling.index', 'Kasus pendampingan saya', 'konseling.tangani', 'chat'],
            ['instrumen', 'kelola.instrumen.index', 'Asesmen dasar', 'asesmen.kelola', 'klipboard'],
        ],
        'Pengaturan' => [
            ['layanan', 'kelola.pengaturan.layanan', 'Layanan & audit log', 'pengaturan.umum', 'susun'],
            ['halaman', 'kelola.pengaturan.halaman', 'Halaman depan & kontak', 'pengaturan.halaman', 'dokumen'],
            ['surel', 'kelola.pengaturan.surel', 'Surel (Brevo)', 'pengaturan.surel', 'surel'],
            ['whatsapp', 'kelola.pengaturan.whatsapp', 'WhatsApp (Fonnte)', 'pengaturan.whatsapp', 'telepon'],
        ],
        'Akses & catatan' => [
            ['pengguna', 'kelola.pengguna.index', 'Pengguna & penugasan', 'pengguna.lihat', 'kelompok'],
            ['peran', 'kelola.peran.index', 'Peran & hak akses', 'peran.kelola', 'kunci'],
            ['audit', 'kelola.audit.index', 'Audit log', 'audit.lihat', 'daftar'],
            ['rekap', 'kelola.rekap.index', 'Rekap & unduhan', ['rekap.unduh', 'permintaan.rekap'], 'unduh'],
        ],
    ];
@endphp
<nav aria-label="Menu pengelola" class="space-y-5">
    @foreach ($kelompok as $judulKelompok => $daftar)
        @php $terlihat = array_filter($daftar, fn ($butir) => auth()->user()->canAny((array) $butir[3])); @endphp
        @if ($terlihat !== [])
            <div>
                <p class="mb-1.5 px-3 text-[11px] font-semibold tracking-wider text-redup/80 uppercase">{{ $judulKelompok }}</p>
                <ul class="space-y-0.5">
                    @foreach ($terlihat as [$kunci, $rute, $label, $hak, $ikon])
                        <li>
                            <a href="{{ route($rute) }}" @if ($aktif === $kunci) aria-current="page" @endif
                               @class([
                                   'group flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium no-underline transition hover:no-underline',
                                   'bg-utama-lembut text-utama' => $aktif === $kunci,
                                   'text-teks/80 hover:bg-permukaan-2 hover:text-teks' => $aktif !== $kunci,
                               ])>
                                <x-ikon :nama="$ikon" @class(['size-[18px]', 'text-utama' => $aktif === $kunci, 'text-redup group-hover:text-teks' => $aktif !== $kunci]) />
                                {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
</nav>
