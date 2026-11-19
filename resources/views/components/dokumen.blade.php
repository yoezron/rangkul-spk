@props(['judul' => null, 'kelasBody' => '', 'indeks' => false, 'deskripsi' => null])
{{--
    Kerangka dokumen HTML. Semua gaya berasal dari Tailwind yang dibangun Vite
    dan disajikan dari server sendiri — tidak ada fonta, skrip, atau gambar
    dari pihak ketiga, supaya membuka Rangkul tidak meninggalkan jejak di
    server lain.
--}}
<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="{{ $indeks ? 'index, follow' : 'noindex, nofollow' }}">
    @if ($deskripsi)
        <meta name="description" content="{{ $deskripsi }}">
    @endif
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#1f6f6b">
    <title>{{ $judul ? $judul.' — ' : '' }}SPK Rangkul</title>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 40 40'%3E%3Crect width='40' height='40' rx='12' fill='%231f6f6b'/%3E%3Ccircle cx='20' cy='14.5' r='4.5' fill='%23fff'/%3E%3Cpath d='M9 20.5c2.6 5.8 6.6 9 11 9s8.4-3.2 11-9' stroke='%23fff' stroke-width='3.2' fill='none' stroke-linecap='round'/%3E%3C/svg%3E">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body {{ $attributes->merge(['class' => 'min-h-full '.$kelasBody]) }}>
    {{ $slot }}
</body>
</html>
