<?php

namespace App\Models;

use App\Enums\Hak;
use App\Services\Spk\JawabanAnggota;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * Anggota SPK yang pernah masuk ke Rangkul.
 *
 * Tidak ada password di sini: satu-satunya jalan masuk adalah token SSO dari
 * spk.or.id. Seluruh kolom profil adalah salinan dari endpoint data anggota.
 *
 * @property int $id
 * @property int $spk_id
 * @property string $nama
 * @property string|null $email
 * @property string|null $telp
 * @property string|null $gender
 * @property string|null $kampus
 * @property string|null $wilayah
 * @property string|null $peran
 * @property string|null $nomor_anggota
 * @property string|null $foto
 * @property Carbon|null $disinkronkan_pada
 * @property bool $notif_surel
 * @property bool $notif_whatsapp
 * @property string|null $nomor_whatsapp nomor pilihan anggota; bila kosong dipakai `telp` dari spk.or.id
 */
#[Fillable(['spk_id', 'nama', 'email', 'telp', 'gender', 'kampus', 'wilayah', 'peran', 'nomor_anggota', 'foto', 'disinkronkan_pada', 'notif_surel', 'notif_whatsapp', 'nomor_whatsapp'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Fitur "ingat saya" dimatikan: tabel ini tidak punya kolom remember_token.
     *
     * @var string
     */
    protected $rememberTokenName = '';

    /**
     * Sama dengan bawaan kolomnya, supaya pengguna yang baru dibuat di memori
     * (mis. saat masuk pertama) langsung dapat menerima notifikasi.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'notif_surel' => true,
        'notif_whatsapp' => true,
    ];

    /**
     * Buat atau perbarui salinan lokal seorang anggota yang berhak.
     */
    public static function sinkronkanDariSpk(JawabanAnggota $jawaban): self
    {
        return static::updateOrCreate(
            ['spk_id' => $jawaban->id],
            [...$jawaban->profil, 'disinkronkan_pada' => now()],
        );
    }

    /**
     * Perbarui salinan lokal anggota ini dari jawaban pemeriksaan ulang.
     */
    public function perbaruiDariSpk(JawabanAnggota $jawaban): void
    {
        $this->update([...$jawaban->profil, 'disinkronkan_pada' => now()]);
    }

    /**
     * Peran di dalam Rangkul (bukan kolom `peran`, yang menyalin peran di spk.or.id).
     *
     * @return BelongsToMany<Peran, $this>
     */
    public function daftarPeran(): BelongsToMany
    {
        return $this->belongsToMany(Peran::class, 'peran_user')->withPivot('ditugaskan_oleh')->withTimestamps();
    }

    /**
     * Superadmin Rangkul mengikuti peran di spk.or.id (keputusan pemilik
     * proyek): siapa pun yang di sana berperan Administrator. Karena kolom
     * `peran` disalin ulang tiap masuk dan tiap pemeriksaan 15 menit,
     * pencabutan di spk.or.id berlaku di sini paling lambat 15 menit kemudian.
     */
    public function adalahSuperadmin(): bool
    {
        $peranSuperadmin = mb_strtolower(trim((string) config('services.spk.peran_superadmin')));

        return $peranSuperadmin !== '' && mb_strtolower(trim((string) $this->peran)) === $peranSuperadmin;
    }

    /**
     * Seluruh hak akses pengguna ini: semua hak bila superadmin, selain itu
     * gabungan hak dari peran-peran yang ditugaskan kepadanya.
     *
     * @return list<string>
     */
    public function daftarHak(): array
    {
        if ($this->adalahSuperadmin()) {
            return Hak::semuaNilai();
        }

        return $this->daftarPeran->flatMap(fn (Peran $peran): array => $peran->hakBerlaku())->unique()->values()->all();
    }

    public function punyaHak(Hak $hak): bool
    {
        return in_array($hak->value, $this->daftarHak(), true);
    }

    /**
     * Pengguna yang memegang hak lewat peran Rangkul yang ditugaskan.
     *
     * Superadmin sengaja TIDAK ikut: mereka administrator spk.or.id yang
     * jumlahnya bisa banyak dan bukan bagian tim layanan, sehingga tidak
     * dikirimi notifikasi kasus kecuali juga diberi peran (mis. Komite).
     *
     * @param  Builder<self>  $kueri
     */
    public function scopePemegangPeranDenganHak(Builder $kueri, Hak $hak): void
    {
        $kueri->whereHas('daftarPeran', fn (Builder $peran) => $peran->whereJsonContains('hak', $hak->value));
    }

    /**
     * @return HasMany<Permintaan, $this>
     */
    public function permintaan(): HasMany
    {
        return $this->hasMany(Permintaan::class);
    }

    /**
     * @return HasMany<Permintaan, $this>
     */
    public function kasusDampingan(): HasMany
    {
        return $this->hasMany(Permintaan::class, 'konselor_id');
    }

    public function routeNotificationForMail(): ?string
    {
        return $this->notif_surel && filled($this->email) ? $this->email : null;
    }

    public function routeNotificationForWhatsapp(): ?string
    {
        $nomor = $this->nomor_whatsapp ?: $this->telp;

        return $this->notif_whatsapp && filled($nomor) ? $nomor : null;
    }

    /**
     * Sapaan singkat untuk notifikasi: kata pertama nama.
     */
    public function namaPanggilan(): string
    {
        return strtok(trim($this->nama), ' ') ?: $this->nama;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spk_id' => 'integer',
            'disinkronkan_pada' => 'datetime',
            'notif_surel' => 'boolean',
            'notif_whatsapp' => 'boolean',
        ];
    }
}
