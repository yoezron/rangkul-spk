<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Satu notifikasi layanan, dikirim lewat surel dan WhatsApp sesuai pilihan penerima.
 *
 * ATURAN ISI: notifikasi hanya memberi tahu ADA sesuatu yang perlu dibuka
 * (kode permintaan, jenis kejadian, waktu pertemuan). Isi cerita, topik,
 * jawaban asesmen, isi pesan, dan tautan Zoom/Meet tidak pernah ikut —
 * surel dan WhatsApp bisa terbaca orang lain di layar kunci atau kotak masuk
 * kantor.
 *
 * Diantrekan supaya halaman tidak menunggu SMTP dan Fonnte.
 */
class NotifikasiRangkul extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $jenis  kode kejadian, mis. `permintaan.masuk` (untuk pengujian dan pelacakan)
     * @param  list<string>  $paragraf
     */
    public function __construct(
        public readonly string $jenis,
        public readonly string $subjek,
        public readonly string $judul,
        public readonly array $paragraf,
        public readonly string $teksTombol,
        public readonly string $tautan,
        public readonly bool $mendesak = false,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $penerima): array
    {
        $saluran = [];

        if ($penerima instanceof User && $penerima->routeNotificationForMail() !== null) {
            $saluran[] = 'mail';
        }

        if ($penerima instanceof User && $penerima->routeNotificationForWhatsapp() !== null) {
            $saluran[] = SaluranWhatsapp::class;
        }

        return $saluran;
    }

    public function toMail(object $penerima): MailMessage
    {
        return (new MailMessage)
            ->subject(($this->mendesak ? '[MENDESAK] ' : '').$this->subjek.' — SPK Rangkul')
            ->view(['surel.notifikasi', 'surel.notifikasi-teks'], [
                'sapaan' => $penerima instanceof User ? 'Halo, '.$penerima->namaPanggilan().'.' : 'Halo.',
                'judul' => $this->judul,
                'paragraf' => $this->paragraf,
                'teksTombol' => $this->teksTombol,
                'tautan' => $this->tautan,
                'mendesak' => $this->mendesak,
            ]);
    }

    public function toWhatsapp(object $penerima): string
    {
        return implode("\n\n", array_filter([
            '*SPK Rangkul*'.($this->mendesak ? ' — *MENDESAK*' : ''),
            '*'.$this->judul.'*',
            ...$this->paragraf,
            $this->teksTombol.': '.$this->tautan,
        ]));
    }
}
