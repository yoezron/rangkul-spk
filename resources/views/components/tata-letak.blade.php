@props(['judul' => null])
{{--
    Tata letak halaman anggota. Gaya ditulis langsung di sini dan tidak ada
    aset dari pihak ketiga (fonta, skrip, gambar): membuka Rangkul tidak boleh
    meninggalkan jejak permintaan di server lain.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $judul ? $judul.' — ' : '' }}SPK Rangkul</title>
    <style>
        @include('partials.gaya')
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; }
        main { width: 100%; max-width: 480px; background: var(--kartu); border: 1px solid var(--garis); border-radius: 14px; padding: 32px 28px; }
        .isi-halaman { margin: 0 0 20px; }
    </style>
</head>
<body>
    <main>
        {{ $slot }}
    </main>
</body>
</html>
