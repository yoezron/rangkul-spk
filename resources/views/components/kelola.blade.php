@props(['judul', 'aktif' => null])
@php
    $menu = [
        ['dasbor', 'kelola.dasbor', 'Ringkasan', 'dasbor.lihat'],
        ['permintaan', 'kelola.permintaan.index', 'Permintaan layanan', 'permintaan.kelola'],
        ['kasus', 'konseling.index', 'Kasus pendampingan saya', 'konseling.tangani'],
        ['instrumen', 'kelola.instrumen.index', 'Asesmen dasar', 'asesmen.kelola'],
        ['layanan', 'kelola.pengaturan.layanan', 'Layanan & audit log', 'pengaturan.umum'],
        ['halaman', 'kelola.pengaturan.halaman', 'Halaman depan & kontak', 'pengaturan.halaman'],
        ['surel', 'kelola.pengaturan.surel', 'Surel (Brevo)', 'pengaturan.surel'],
        ['whatsapp', 'kelola.pengaturan.whatsapp', 'WhatsApp (Fonnte)', 'pengaturan.whatsapp'],
        ['pengguna', 'kelola.pengguna.index', 'Pengguna & penugasan', 'pengguna.lihat'],
        ['peran', 'kelola.peran.index', 'Peran & hak akses', 'peran.kelola'],
        ['audit', 'kelola.audit.index', 'Audit log', 'audit.lihat'],
        ['rekap', 'kelola.rekap.index', 'Rekap & unduhan', ['rekap.unduh', 'permintaan.rekap']],
    ];
    $pengguna = auth()->user();
    $sebutan = match (true) {
        $pengguna->adalahSuperadmin() => 'Superadmin',
        $pengguna->can('dasbor.lihat') => 'Pengelola',
        default => 'Peer konselor',
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $judul }} — Pengelola SPK Rangkul</title>
    <style>
        @include('partials.gaya')
        .kerangka { display: grid; grid-template-columns: 240px minmax(0, 1fr); min-height: 100vh; }
        .sisi { background: var(--kartu); border-right: 1px solid var(--garis); padding: 24px 14px; }
        .merek { font-weight: 700; margin: 0 10px 18px; line-height: 1.3; }
        .sisi ul { list-style: none; padding: 0; margin: 0 0 20px; }
        .sisi li a { display: block; padding: 7px 10px; border-radius: 8px; color: var(--teks); text-decoration: none; font-size: .9375rem; }
        .sisi li a:hover { background: var(--latar); }
        .sisi li a[aria-current] { background: var(--info-latar); color: var(--utama); font-weight: 600; }
        .sisi .bawah { margin: 0 10px; }
        .isi { padding: 28px 32px 48px; max-width: 1100px; width: 100%; }
        .kepala { margin-bottom: 20px; }
        .kartu { background: var(--kartu); border: 1px solid var(--garis); border-radius: 12px; padding: 18px 20px; margin-bottom: 20px; }
        .grid-angka { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .grid-angka .kartu { margin: 0; }
        .angka { font-size: 1.75rem; font-weight: 700; line-height: 1.2; }
        .dua-kolom { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        .halaman-nav { display: flex; gap: 8px; margin-top: 16px; }
        @media (max-width: 760px) {
            .kerangka { grid-template-columns: minmax(0, 1fr); }
            .sisi { border-right: 0; border-bottom: 1px solid var(--garis); padding: 16px; }
            .sisi ul { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 12px; }
            .merek, .sisi .bawah { margin-left: 0; }
            .isi { padding: 20px 16px 40px; }
        }
    </style>
</head>
<body>
<div class="kerangka">
    <nav class="sisi" aria-label="Menu pengelola">
        <p class="merek">SPK Rangkul<br><span class="redup kecil">
            {{ $sebutan }} · {{ $pengguna->nama }}
        </span></p>
        <ul>
            @foreach ($menu as [$kunci, $rute, $label, $hak])
                @canany((array) $hak)
                    <li><a href="{{ route($rute) }}" @if ($aktif === $kunci) aria-current="page" @endif>{{ $label }}</a></li>
                @endcanany
            @endforeach
        </ul>
        <div class="bawah baris-aksi">
            <a class="kecil" href="{{ route('beranda') }}">← Beranda</a>
            <form method="POST" action="{{ route('keluar') }}">
                @csrf
                <button type="submit" class="tombol sekunder ringkas">Keluar</button>
            </form>
        </div>
    </nav>

    <main class="isi">
        <div class="kepala">
            <h1>{{ $judul }}</h1>
            @isset($keterangan)
                <p class="redup">{{ $keterangan }}</p>
            @endisset
        </div>

        @if (session('status'))
            <div class="catatan info" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="catatan galat" role="alert">
                <ul>
                    @foreach ($errors->all() as $pesan)
                        <li>{{ $pesan }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{ $slot }}
    </main>
</div>
</body>
</html>
