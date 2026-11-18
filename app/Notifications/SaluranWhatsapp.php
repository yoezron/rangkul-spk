<?php

namespace App\Notifications;

use App\Exceptions\GagalKirimWhatsapp;
use App\Services\KlienFonnte;
use Illuminate\Notifications\Notification;

/**
 * Saluran notifikasi WhatsApp lewat Fonnte.
 *
 * Diam bila Fonnte belum diaktifkan atau penerima tidak punya nomor / tidak
 * ingin dihubungi lewat WhatsApp. Kegagalan kirim tidak diulang, supaya
 * pesan tidak terkirim dua kali (lihat KlienFonnte).
 */
class SaluranWhatsapp
{
    public function __construct(private readonly KlienFonnte $fonnte) {}

    public function send(object $penerima, Notification $notifikasi): void
    {
        $nomor = $penerima->routeNotificationFor('whatsapp', $notifikasi);

        if (! is_string($nomor) || $nomor === '' || ! $this->fonnte->siap() || ! method_exists($notifikasi, 'toWhatsapp')) {
            return;
        }

        try {
            $this->fonnte->kirim($nomor, $notifikasi->toWhatsapp($penerima));
        } catch (GagalKirimWhatsapp) {
            // Sudah dicatat KlienFonnte tanpa nomor maupun isi pesan.
        }
    }
}
