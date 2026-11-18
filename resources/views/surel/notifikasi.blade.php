{{--
    Templat surel notifikasi SPK Rangkul.
    Tata letak tabel dan gaya sebaris supaya tampil rapi di Gmail, Outlook,
    dan aplikasi surel ponsel. Tidak ada gambar atau aset dari luar.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ $judul }}</title>
</head>
<body style="margin:0;padding:0;background:#f3efe9;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#2b2a28;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $judul }} — buka SPK Rangkul untuk selengkapnya.</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3efe9;">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">
                    <tr>
                        <td style="background:#1f6f6b;border-radius:16px 16px 0 0;padding:26px 32px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td style="font-size:22px;font-weight:700;color:#ffffff;letter-spacing:.2px;">SPK Rangkul</td>
                                    <td align="right" style="font-size:12px;color:#cfe7e5;">Layanan bantuan psikologis<br>Serikat Pekerja Kampus</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if ($mendesak)
                        <tr>
                            <td style="background:#a8231b;padding:10px 32px;font-size:13px;font-weight:700;color:#ffffff;letter-spacing:.4px;">
                                MENDESAK — MOHON SEGERA DITINDAKLANJUTI
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td style="background:#ffffff;padding:32px;border-left:1px solid #e4e0da;border-right:1px solid #e4e0da;">
                            <p style="margin:0 0 6px;font-size:15px;color:#6b6862;">{{ $sapaan }}</p>
                            <h1 style="margin:0 0 18px;font-size:22px;line-height:1.35;color:#2b2a28;">{{ $judul }}</h1>

                            @foreach ($paragraf as $kalimat)
                                <p style="margin:0 0 14px;font-size:15px;line-height:1.65;color:#2b2a28;">{{ $kalimat }}</p>
                            @endforeach

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0 8px;">
                                <tr>
                                    <td align="center" bgcolor="#1f6f6b" style="border-radius:10px;">
                                        <a href="{{ $tautan }}" target="_blank" rel="noopener"
                                           style="display:inline-block;padding:13px 26px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px;">
                                            {{ $teksTombol }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <p style="margin:14px 0 0;font-size:12px;line-height:1.5;color:#8a867f;">
                                Bila tombol tidak berfungsi, salin alamat ini ke peramban:<br>
                                <span style="color:#1f6f6b;word-break:break-all;">{{ $tautan }}</span>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="background:#e9f4f3;padding:16px 32px;border:1px solid #e4e0da;border-top:0;border-radius:0 0 16px 16px;">
                            <p style="margin:0;font-size:12px;line-height:1.6;color:#4d5b59;">
                                🔒 Demi kerahasiaan, surel ini tidak memuat isi cerita maupun percakapan. Semuanya hanya dapat dibaca setelah masuk ke SPK Rangkul dengan akun SPK Anda.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:18px 32px 0;font-size:11px;line-height:1.6;color:#8a867f;text-align:center;">
                            Bila Anda atau orang di sekitar Anda dalam bahaya, segera hubungi layanan darurat 112.<br>
                            Surel otomatis dari SPK Rangkul · Serikat Pekerja Kampus. Mohon tidak membalas surel ini.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
