<?php

namespace App\Services;

use App\Exceptions\GagalKirimWhatsapp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Pengirim notifikasi WhatsApp lewat Fonnte (https://docs.fonnte.com).
 *
 *   POST {url}/send    Authorization: <token perangkat>   target, message, countryCode
 *   POST {url}/device  Authorization: <token perangkat>   → status & kuota perangkat
 *
 * ATURAN ISI PESAN: pesan melewati server Fonnte dan nomor WhatsApp pengirim,
 * lalu muncul di layar kunci ponsel penerima. Jangan pernah mengirim isi
 * cerita, topik konseling, atau apa pun yang membuka bahwa seseorang mencari
 * bantuan psikologis. Cukup ajakan umum, mis. "Ada pesan baru untuk Anda di
 * SPK Rangkul", disertai tautan.
 *
 * Pengiriman tidak diulang otomatis: Fonnte tidak menyediakan kunci
 * idempotensi, sehingga pengulangan bisa mengirim pesan dua kali.
 */
class KlienFonnte
{
    public function __construct(private readonly PengaturanRangkul $pengaturan) {}

    /**
     * Notifikasi WhatsApp diaktifkan dan token sudah diisi.
     */
    public function siap(): bool
    {
        return $this->pengaturan->get('whatsapp.aktif') === true && $this->pengaturan->adaRahasia('whatsapp.token');
    }

    /**
     * Kirim satu pesan teks.
     *
     * @param  string  $tujuan  nomor tujuan; awalan 0 diganti kode negara oleh Fonnte
     *
     * @throws GagalKirimWhatsapp
     */
    public function kirim(string $tujuan, string $pesan): void
    {
        if (! $this->siap()) {
            throw new GagalKirimWhatsapp('belum diaktifkan');
        }

        $nomor = preg_replace('/\D+/', '', $tujuan) ?? '';

        if ($nomor === '') {
            throw new GagalKirimWhatsapp('nomor tujuan kosong');
        }

        $this->minta('send', [
            'target' => $nomor,
            'message' => $pesan,
            'countryCode' => (string) $this->pengaturan->get('whatsapp.kode_negara'),
        ]);
    }

    /**
     * Status perangkat pengirim menurut Fonnte.
     *
     * @return array{nomor: string, nama: string, status: string, kuota: string, paket: string, kedaluwarsa: string}
     *
     * @throws GagalKirimWhatsapp
     */
    public function perangkat(): array
    {
        if (! $this->pengaturan->adaRahasia('whatsapp.token')) {
            throw new GagalKirimWhatsapp('token belum diisi');
        }

        $badan = $this->minta('device');
        $teks = fn (string $kunci): string => is_scalar($badan[$kunci] ?? null) ? (string) $badan[$kunci] : '';

        return [
            'nomor' => $teks('device'),
            'nama' => $teks('name'),
            'status' => $teks('device_status'),
            'kuota' => $teks('quota'),
            'paket' => $teks('package'),
            'kedaluwarsa' => $teks('expired'),
        ];
    }

    /**
     * @param  array<string, string>  $isian
     * @return array<string, mixed>
     *
     * @throws GagalKirimWhatsapp
     */
    private function minta(string $jalur, array $isian = []): array
    {
        try {
            $jawaban = $this->http()->post($jalur, $isian);
        } catch (ConnectionException) {
            throw $this->gagal('Fonnte tidak dapat dihubungi');
        }

        return $this->periksa($jawaban);
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.fonnte.url'), '/'))
            ->connectTimeout(3)
            ->timeout(10)
            ->asForm()
            ->acceptJson()
            ->withHeaders(['Authorization' => (string) $this->pengaturan->get('whatsapp.token')]);
    }

    /**
     * Fonnte menjawab HTTP 200 juga untuk penolakan; yang menentukan adalah
     * kolom `status` (dokumentasinya sesekali menulis `Status`).
     *
     * @return array<string, mixed>
     *
     * @throws GagalKirimWhatsapp
     */
    private function periksa(Response $jawaban): array
    {
        if (! $jawaban->successful()) {
            throw $this->gagal('HTTP '.$jawaban->status());
        }

        $badan = $jawaban->json();

        if (! is_array($badan)) {
            throw $this->gagal('jawaban tidak dikenali');
        }

        if (($badan['status'] ?? $badan['Status'] ?? null) !== true) {
            $alasan = is_string($badan['reason'] ?? null) ? $badan['reason'] : 'ditolak tanpa alasan';

            throw $this->gagal(Str::limit($alasan, 200));
        }

        return $badan;
    }

    private function gagal(string $sebab): GagalKirimWhatsapp
    {
        Log::warning('Fonnte: permintaan gagal.', ['sebab' => $sebab]);

        return new GagalKirimWhatsapp($sebab);
    }
}
