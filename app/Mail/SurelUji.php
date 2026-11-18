<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Surel uji dari halaman pengaturan SMTP, dikirim ke alamat pengelola sendiri.
 */
class SurelUji extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Surel uji SPK Rangkul');
    }

    public function content(): Content
    {
        return new Content(text: 'surel.uji');
    }
}
