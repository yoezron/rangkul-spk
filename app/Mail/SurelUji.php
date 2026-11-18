<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Surel uji dari halaman pengaturan SMTP, dikirim ke alamat pengelola sendiri.
 * Memakai templat yang sama dengan notifikasi layanan, supaya tampilannya
 * sekaligus teruji.
 */
class SurelUji extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Surel uji — SPK Rangkul');
    }

    public function content(): Content
    {
        return new Content(
            view: 'surel.notifikasi',
            text: 'surel.notifikasi-teks',
            with: [
                'sapaan' => 'Halo.',
                'judul' => 'Pengaturan surel SPK Rangkul berfungsi',
                'paragraf' => [
                    'Surel ini dikirim dari halaman pengaturan SPK Rangkul untuk memastikan pengaturan SMTP Brevo sudah benar.',
                    'Bila Anda menerimanya, SPK Rangkul sudah dapat mengirim notifikasi kepada anggota, konselor, dan komite.',
                ],
                'teksTombol' => 'Buka SPK Rangkul',
                'tautan' => route('beranda'),
                'mendesak' => false,
            ],
        );
    }
}
