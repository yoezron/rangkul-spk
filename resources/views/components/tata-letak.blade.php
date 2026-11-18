@props(['judul' => null, 'lebar' => false])
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
        main.lebar { max-width: 780px; }
        body:has(main.lebar) { align-items: flex-start; }
        .isi-halaman { margin: 0 0 20px; }
        .kartu { border: 1px solid var(--garis); border-radius: 12px; padding: 16px 18px; margin-bottom: 20px; }
        .langkah-nav { display: flex; flex-wrap: wrap; gap: 6px; list-style: none; padding: 0; margin: 0 0 20px; }
        .langkah-nav li { font-size: .8125rem; padding: 3px 10px; border-radius: 999px; border: 1px solid var(--garis); color: var(--redup); }
        .langkah-nav li[aria-current] { background: var(--info-latar); border-color: var(--info-garis); color: var(--utama); font-weight: 600; }
        .langkah-nav a { color: inherit; text-decoration: none; }
        .butir { padding: 12px 0; border-top: 1px solid var(--garis); }
        .butir p { margin: 0 0 8px; }
        .skala { display: flex; flex-wrap: wrap; gap: 6px 14px; }
        .skala label { font-weight: 400; display: flex; gap: 6px; align-items: center; margin: 0; }
        @media (max-width: 560px) { main { padding: 24px 18px; } .skala { flex-direction: column; } }
    </style>
</head>
<body>
    <main @class(['lebar' => $lebar])>
        {{ $slot }}
    </main>
</body>
</html>
