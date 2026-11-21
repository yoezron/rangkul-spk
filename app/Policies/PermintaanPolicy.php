<?php

namespace App\Policies;

use App\Enums\Hak;
use App\Enums\StatusPermintaan;
use App\Models\Permintaan;
use App\Models\User;

/**
 * Siapa boleh berbuat apa terhadap satu permintaan layanan.
 *
 * Tiga pihak: anggota pemilik, konselor pendamping (PIC) yang ditugaskan,
 * dan komite (hak permintaan.kelola; superadmin memiliki semua hak).
 * Konselor lain dan pengelola tanpa hak itu tidak dapat membuka kasus.
 */
class PermintaanPolicy
{
    public function milik(User $pengguna, Permintaan $permintaan): bool
    {
        return $permintaan->user_id === $pengguna->id;
    }

    public function dampingi(User $pengguna, Permintaan $permintaan): bool
    {
        return $permintaan->konselor_id !== null
            && $permintaan->konselor_id === $pengguna->id
            && $permintaan->user_id !== $pengguna->id
            && $pengguna->punyaHak(Hak::KonselingTangani);
    }

    public function kelola(User $pengguna, Permintaan $permintaan): bool
    {
        return $permintaan->status !== StatusPermintaan::Draf && $pengguna->punyaHak(Hak::PermintaanKelola);
    }

    public function lihat(User $pengguna, Permintaan $permintaan): bool
    {
        return $this->milik($pengguna, $permintaan) || $this->dampingi($pengguna, $permintaan) || $this->kelola($pengguna, $permintaan);
    }

    /**
     * Skor asesmen, jawaban keselamatan, dan catatan sesi: konselor dan komite.
     */
    public function lihatRahasia(User $pengguna, Permintaan $permintaan): bool
    {
        return $this->dampingi($pengguna, $permintaan) || $this->kelola($pengguna, $permintaan);
    }

    public function isiFormulir(User $pengguna, Permintaan $permintaan): bool
    {
        return $this->milik($pengguna, $permintaan) && $permintaan->status === StatusPermintaan::Draf;
    }

    public function batalkan(User $pengguna, Permintaan $permintaan): bool
    {
        return $this->milik($pengguna, $permintaan) && $permintaan->status->belumDitangani();
    }

    /**
     * Mengirim pesan: hanya anggota dan konselor pendamping, selama pendampingan berjalan.
     */
    public function bercakap(User $pengguna, Permintaan $permintaan): bool
    {
        return $permintaan->status->bolehBercakap()
            && ($this->milik($pengguna, $permintaan) || $this->dampingi($pengguna, $permintaan));
    }

    /**
     * Anggota membuka kembali kasus yang selesai dalam HARI_BUKA_KEMBALI hari.
     */
    public function bukaKembali(User $pengguna, Permintaan $permintaan): bool
    {
        return $this->milik($pengguna, $permintaan) && $permintaan->masihBolehDibukaKembali();
    }

    /**
     * Tindakan konselor: mengusulkan pertemuan dan penutupan, mengirim instrumen.
     */
    public function tangani(User $pengguna, Permintaan $permintaan): bool
    {
        return $permintaan->status->bolehBercakap() && $this->dampingi($pengguna, $permintaan);
    }

    /**
     * Catatan sesi boleh ditulis selama dan sesudah pendampingan.
     */
    public function catat(User $pengguna, Permintaan $permintaan): bool
    {
        return in_array($permintaan->status, [StatusPermintaan::Berlangsung, StatusPermintaan::MenungguPenutupan, StatusPermintaan::Selesai], true)
            && $this->dampingi($pengguna, $permintaan);
    }
}
