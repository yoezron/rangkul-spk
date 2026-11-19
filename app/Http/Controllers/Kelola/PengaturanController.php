<?php

namespace App\Http\Controllers\Kelola;

use App\Exceptions\GagalKirimWhatsapp;
use App\Http\Controllers\Controller;
use App\Mail\SurelUji;
use App\Services\KlienFonnte;
use App\Services\PencatatAudit;
use App\Services\PengaturanRangkul;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Empat halaman pengaturan, masing-masing dengan hak aksesnya sendiri:
 * layanan & audit log, halaman depan & kontak, surel (SMTP), dan WhatsApp (Fonnte).
 *
 * Nama bidang formulir memakai garis bawah (`layanan_buka`) karena titik di
 * nama bidang dibaca Laravel sebagai larik bersarang; garis bawah pertama
 * diubah kembali menjadi titik (`layanan.buka`) saat disimpan.
 */
class PengaturanController extends Controller
{
    /** @var array<string, string> */
    private const NAMA_DAFTAR = [
        'halaman_keunggulan' => 'Keunggulan',
        'halaman_langkah' => 'Cara kerja',
        'halaman_faq' => 'Tanya jawab',
    ];

    public function __construct(
        private readonly PengaturanRangkul $pengaturan,
        private readonly PencatatAudit $audit,
    ) {}

    public function layanan(): View
    {
        return view('kelola.pengaturan.layanan', ['nilai' => $this->pengaturan->untukFormulir()]);
    }

    public function simpanLayanan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'layanan_buka' => ['required', 'boolean'],
            'layanan_pesan_tutup' => ['required', 'string', 'max:1000'],
            'audit_retensi_hari' => ['required', 'integer', 'min:0', 'max:3650'],
            'audit_catat_ip' => ['required', 'boolean'],
        ]);

        return $this->simpan($data, 'layanan', 'kelola.pengaturan.layanan');
    }

    public function halaman(): View
    {
        return view('kelola.pengaturan.halaman', ['nilai' => $this->pengaturan->untukFormulir()]);
    }

    public function simpanHalaman(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'halaman_judul' => ['required', 'string', 'max:120'],
            'halaman_label' => ['nullable', 'string', 'max:60'],
            'halaman_tajuk' => ['nullable', 'string', 'max:160'],
            'halaman_subjudul' => ['nullable', 'string', 'max:255'],
            'halaman_teks_tombol' => ['nullable', 'string', 'max:40'],
            'halaman_judul_tentang' => ['nullable', 'string', 'max:80'],
            'halaman_isi' => ['nullable', 'string', 'max:5000'],
            'halaman_tampil_keunggulan' => ['nullable', 'boolean'],
            'halaman_keunggulan' => ['nullable', 'array', 'max:12'],
            'halaman_keunggulan.*.judul' => ['nullable', 'string', 'max:80'],
            'halaman_keunggulan.*.teks' => ['nullable', 'string', 'max:300'],
            'halaman_tampil_langkah' => ['nullable', 'boolean'],
            'halaman_langkah' => ['nullable', 'array', 'max:12'],
            'halaman_langkah.*.judul' => ['nullable', 'string', 'max:80'],
            'halaman_langkah.*.teks' => ['nullable', 'string', 'max:300'],
            'halaman_tampil_faq' => ['nullable', 'boolean'],
            'halaman_faq' => ['nullable', 'array', 'max:20'],
            'halaman_faq.*.pertanyaan' => ['nullable', 'string', 'max:200'],
            'halaman_faq.*.jawaban' => ['nullable', 'string', 'max:1000'],
            'halaman_indeks' => ['nullable', 'boolean'],
            'kontak_email' => ['nullable', 'email', 'max:255'],
            'kontak_whatsapp' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{6,30}$/'],
            'kontak_jam_layanan' => ['nullable', 'string', 'max:255'],
            'kontak_darurat' => ['nullable', 'string', 'max:1000'],
        ], [
            'kontak_whatsapp.regex' => 'Nomor WhatsApp hanya boleh berisi angka, spasi, tanda kurung, tanda hubung, dan + di depan.',
        ]);

        // Baris kosong dibuang; yang tersisa harus lengkap supaya tidak tampil setengah jadi.
        foreach (['halaman_keunggulan' => ['judul', 'teks'], 'halaman_langkah' => ['judul', 'teks'], 'halaman_faq' => ['pertanyaan', 'jawaban']] as $bidang => $kolom) {
            if (! array_key_exists($bidang, $data)) {
                continue;
            }

            $data[$bidang] = array_values(array_filter($data[$bidang] ?? [], fn (array $baris): bool => collect($kolom)->contains(fn (string $nama): bool => filled($baris[$nama] ?? null))));

            foreach ($data[$bidang] as $nomor => $baris) {
                foreach ($kolom as $nama) {
                    if (blank($baris[$nama] ?? null)) {
                        return back()->withInput()->withErrors([$bidang => 'Baris '.($nomor + 1).' pada bagian '.self::NAMA_DAFTAR[$bidang].' belum lengkap: isi '.$nama.'-nya atau kosongkan barisnya.']);
                    }
                }
            }
        }

        return $this->simpan($data, 'halaman', 'kelola.pengaturan.halaman');
    }

    public function surel(): View
    {
        return view('kelola.pengaturan.surel', [
            'nilai' => $this->pengaturan->untukFormulir(),
            'adaSandi' => $this->pengaturan->adaRahasia('surel.password'),
            'surelSiap' => $this->pengaturan->surelSiap(),
        ]);
    }

    public function simpanSurel(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'surel_aktif' => ['required', 'boolean'],
            'surel_host' => ['nullable', 'required_if:surel_aktif,1', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/'],
            'surel_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'surel_enkripsi' => ['required', 'in:tls,ssl'],
            'surel_username' => ['nullable', 'string', 'max:255'],
            'surel_password' => ['nullable', 'string', 'max:255'],
            'surel_hapus_password' => ['nullable', 'boolean'],
            'surel_dari_alamat' => ['nullable', 'required_if:surel_aktif,1', 'email', 'max:255'],
            'surel_dari_nama' => ['nullable', 'string', 'max:100'],
        ], [
            'surel_host.regex' => 'Host SMTP ditulis tanpa awalan, mis. smtp-relay.brevo.com.',
            'surel_host.required_if' => 'Host SMTP wajib diisi bila pengiriman surel diaktifkan.',
            'surel_dari_alamat.required_if' => 'Alamat pengirim wajib diisi bila pengiriman surel diaktifkan.',
        ]);

        // Dua kekeliruan yang paling sering saat menyambungkan Brevo.
        if (str_starts_with((string) ($data['surel_password'] ?? ''), 'xkeysib-')) {
            return back()->withInput()->withErrors(['surel_password' => 'Itu API key Brevo. Untuk SMTP dibutuhkan SMTP key dari tab SMTP di halaman "SMTP & API" Brevo.']);
        }

        if (($data['surel_enkripsi'] === 'ssl') !== ((int) $data['surel_port'] === 465)) {
            return back()->withInput()->withErrors(['surel_port' => 'Brevo memakai SSL/TLS hanya di port 465; port 587 dan 2525 memakai STARTTLS. Samakan port dengan jenis enkripsinya.']);
        }

        // Sandi kosong berarti "pertahankan sandi lama"; sandi tidak pernah
        // dikirim kembali ke formulir.
        $sandi = $data['surel_password'] ?? null;
        unset($data['surel_password'], $data['surel_hapus_password']);

        if ($request->boolean('surel_hapus_password')) {
            $data['surel_password'] = '';
        } elseif (filled($sandi)) {
            $data['surel_password'] = $sandi;
        }

        return $this->simpan($data, 'surel', 'kelola.pengaturan.surel');
    }

    /**
     * Kirim surel uji ke alamat pengelola sendiri memakai SMTP yang tersimpan.
     */
    public function ujiSurel(Request $request): RedirectResponse
    {
        $alamat = (string) $request->user()->email;

        if (! $this->pengaturan->surelSiap()) {
            return back()->withErrors(['surel' => 'Aktifkan dan lengkapi pengaturan SMTP, lalu simpan, sebelum mengirim surel uji.']);
        }

        if ($alamat === '') {
            return back()->withErrors(['surel' => 'Akun SPK Anda tidak punya alamat surel untuk menerima surel uji.']);
        }

        // Pengirim SMTP yang sudah dibuat sebelum pengaturan dipasang dibuang,
        // supaya surel uji benar-benar memakai pengaturan terbaru.
        $this->pengaturan->terapkanSurel();
        $pengelolaSurel = app('mail.manager');

        if ($pengelolaSurel instanceof MailManager) {
            $pengelolaSurel->purge('smtp');
        }

        try {
            Mail::mailer('smtp')->to($alamat)->send(new SurelUji);
        } catch (Throwable $galat) {
            return back()->withErrors([
                'surel' => 'Surel uji gagal dikirim: '.Str::limit($galat->getMessage(), 300),
                'surel_saran' => 'Periksa: login dan SMTP key dari tab SMTP di Brevo (bukan API key atau password akun), dan alamat pengirim yang sudah terdaftar serta terverifikasi di Brevo.',
            ]);
        }

        $this->audit->catat('surel.uji_dikirim', $alamat);

        return back()->with('status', "Surel uji dikirim ke {$alamat}. Periksa kotak masuk (dan folder spam).");
    }

    public function whatsapp(Request $request): View
    {
        return view('kelola.pengaturan.whatsapp', [
            'nilai' => $this->pengaturan->untukFormulir(),
            'adaToken' => $this->pengaturan->adaRahasia('whatsapp.token'),
            'siap' => $this->klienFonnte()->siap(),
            'nomorUji' => (string) $request->user()->telp,
            'perangkat' => $request->session()->get('perangkat'),
        ]);
    }

    public function simpanWhatsapp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'whatsapp_aktif' => ['required', 'boolean'],
            'whatsapp_token' => ['nullable', 'string', 'max:255', 'regex:/^\S+$/'],
            'whatsapp_hapus_token' => ['nullable', 'boolean'],
            'whatsapp_kode_negara' => ['required', 'string', 'regex:/^[0-9]{1,4}$/'],
        ], [
            'whatsapp_token.regex' => 'Token Fonnte tidak boleh mengandung spasi.',
            'whatsapp_kode_negara.regex' => 'Kode negara berupa 1–4 angka, mis. 62. Isi 0 bila nomor tujuan selalu ditulis lengkap.',
        ]);

        // Token kosong berarti "pertahankan token lama", seperti sandi SMTP.
        $token = $data['whatsapp_token'] ?? null;
        unset($data['whatsapp_token'], $data['whatsapp_hapus_token']);

        if ($request->boolean('whatsapp_hapus_token')) {
            $data['whatsapp_token'] = '';
        } elseif (filled($token)) {
            $data['whatsapp_token'] = $token;
        }

        $akanAdaToken = array_key_exists('whatsapp_token', $data)
            ? $data['whatsapp_token'] !== ''
            : $this->pengaturan->adaRahasia('whatsapp.token');

        if ($request->boolean('whatsapp_aktif') && ! $akanAdaToken) {
            return back()->withInput()->withErrors(['whatsapp_token' => 'Isi token perangkat Fonnte sebelum mengaktifkan notifikasi WhatsApp.']);
        }

        return $this->simpan($data, 'whatsapp', 'kelola.pengaturan.whatsapp');
    }

    /**
     * Kirim pesan uji ke nomor yang diisi pengelola (bawaannya nomor akun SPK-nya sendiri).
     */
    public function ujiWhatsapp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nomor_uji' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{8,20}$/'],
        ], [
            'nomor_uji.regex' => 'Nomor WhatsApp hanya boleh berisi angka, spasi, tanda kurung, tanda hubung, dan + di depan.',
        ]);

        $klien = $this->klienFonnte();

        if (! $klien->siap()) {
            return back()->withInput()->withErrors(['whatsapp' => 'Aktifkan notifikasi WhatsApp dan simpan tokennya sebelum mengirim pesan uji.']);
        }

        try {
            $klien->kirim($data['nomor_uji'], 'Pesan uji dari SPK Rangkul. Bila Anda menerimanya, notifikasi WhatsApp sudah berfungsi.');
        } catch (GagalKirimWhatsapp $galat) {
            return back()->withInput()->withErrors(['whatsapp' => 'Pesan uji gagal dikirim: '.$galat->sebab]);
        }

        $tersamar = self::samarkanNomor($data['nomor_uji']);
        $this->audit->catat('whatsapp.uji_dikirim', $tersamar);

        return back()->with('status', "Pesan uji diserahkan ke Fonnte untuk {$tersamar}. Periksa WhatsApp di nomor itu.");
    }

    /**
     * Tanyakan status perangkat pengirim (tersambung atau tidak, sisa kuota).
     */
    public function periksaWhatsapp(): RedirectResponse
    {
        try {
            $perangkat = $this->klienFonnte()->perangkat();
        } catch (GagalKirimWhatsapp $galat) {
            return back()->withErrors(['whatsapp' => 'Status perangkat tidak dapat diambil: '.$galat->sebab]);
        }

        return back()->with('perangkat', $perangkat);
    }

    /**
     * Nomor di audit log disamarkan: cukup untuk mengenali, tidak untuk menyalin.
     */
    private static function samarkanNomor(string $nomor): string
    {
        $digit = preg_replace('/\D+/', '', $nomor) ?? '';

        return strlen($digit) <= 7 ? str_repeat('•', strlen($digit)) : substr($digit, 0, 4).str_repeat('•', strlen($digit) - 7).substr($digit, -3);
    }

    private function klienFonnte(): KlienFonnte
    {
        return new KlienFonnte($this->pengaturan);
    }

    /**
     * @param  array<string, mixed>  $data  bidang formulir yang sudah divalidasi
     */
    private function simpan(array $data, string $bagian, string $rute): RedirectResponse
    {
        $nilai = [];

        foreach ($data as $bidang => $isi) {
            $nilai[Str::replaceFirst('_', '.', $bidang)] = $isi;
        }

        $berubah = $this->pengaturan->simpan($nilai);

        if ($berubah === []) {
            return redirect()->route($rute)->with('status', 'Tidak ada yang berubah.');
        }

        $this->audit->catat('pengaturan.diubah', $bagian, ['kunci' => $berubah]);

        return redirect()->route($rute)->with('status', 'Pengaturan disimpan.');
    }
}
