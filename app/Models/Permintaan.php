<?php

namespace App\Models;

use App\Enums\StatusPermintaan;
use App\Enums\StatusPertemuan;
use Database\Factories\PermintaanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Satu permintaan layanan bantuan psikologis dari seorang anggota.
 *
 * Isian anggota, catatan komite, dan alasan-alasan disimpan terenkripsi.
 *
 * @property int $id
 * @property string|null $kode
 * @property int $user_id
 * @property StatusPermintaan $status
 * @property bool $darurat
 * @property list<string>|null $alasan_darurat
 * @property int $langkah
 * @property Carbon|null $persetujuan_pada
 * @property array<string, string>|null $keselamatan
 * @property list<string>|null $topik
 * @property string|null $topik_lainnya
 * @property string|null $uraian
 * @property string|null $harapan
 * @property string|null $bentuk_layanan
 * @property string|null $waktu_luang
 * @property bool $hindari_kampus_sama
 * @property string $gender_konselor
 * @property int|null $konselor_id
 * @property Carbon|null $ditugaskan_pada
 * @property Carbon|null $daring_disetujui_pada
 * @property Carbon|null $penutupan_diusulkan_pada
 * @property string|null $alasan_penutupan
 * @property Carbon|null $penutupan_disetujui_anggota_pada
 * @property Carbon|null $penutupan_ditinjau_pada
 * @property string|null $catatan_tinjauan_penutupan
 * @property string|null $alasan_penolakan
 * @property string|null $catatan_komite
 * @property Carbon|null $diajukan_pada
 * @property Carbon|null $selesai_pada
 * @property Carbon $created_at
 */
#[Fillable([
    'kode', 'user_id', 'status', 'darurat', 'alasan_darurat', 'langkah', 'persetujuan_pada', 'keselamatan',
    'topik', 'topik_lainnya', 'uraian', 'harapan', 'bentuk_layanan', 'waktu_luang', 'hindari_kampus_sama',
    'gender_konselor', 'konselor_id', 'ditugaskan_pada', 'ditugaskan_oleh', 'daring_disetujui_pada',
    'daring_disetujui_oleh', 'penutupan_diusulkan_pada', 'alasan_penutupan', 'penutupan_disetujui_anggota_pada',
    'penutupan_ditinjau_pada', 'penutupan_ditinjau_oleh', 'catatan_tinjauan_penutupan', 'alasan_penolakan',
    'catatan_komite', 'diajukan_pada', 'selesai_pada',
])]
class Permintaan extends Model
{
    /** @use HasFactory<PermintaanFactory> */
    use HasFactory;

    /** Pilihan "apa yang ingin didiskusikan". */
    public const TOPIK = [
        'stres_kerja' => 'Stres dan tekanan pekerjaan',
        'konflik_kerja' => 'Konflik dengan atasan atau rekan kerja',
        'burnout' => 'Beban kerja, burnout, dan kelelahan',
        'keluarga' => 'Persoalan keluarga atau pasangan',
        'emosional' => 'Kecemasan, kesedihan, atau masalah emosional',
        'perundungan' => 'Perundungan, diskriminasi, atau pelecehan',
        'karier' => 'Permasalahan karier atau kesejahteraan kerja',
        'lainnya' => 'Lainnya / belum dapat menentukan',
    ];

    public const BENTUK_LAYANAN = [
        'chat' => 'Percakapan tertulis (chat) saja',
        'daring' => 'Pertemuan daring (Zoom/Google Meet) bila memungkinkan',
        'belum_tahu' => 'Belum tahu, ikut saran konselor',
    ];

    public const GENDER_KONSELOR = [
        'bebas' => 'Tidak ada preferensi',
        'perempuan' => 'Perempuan',
        'laki-laki' => 'Laki-laki',
    ];

    /**
     * Pertanyaan keselamatan di langkah pertama. Jawaban selain `tidak`
     * menandai permintaan sebagai DARURAT.
     *
     * @var array<string, array{teks: string, pilihan: array<string, string>}>
     */
    public const KESELAMATAN = [
        'pikiran_menyakiti_diri' => [
            'teks' => 'Dalam dua minggu terakhir, apakah Anda punya pikiran untuk menyakiti diri sendiri atau mengakhiri hidup?',
            'pilihan' => ['tidak' => 'Tidak', 'kadang' => 'Kadang-kadang', 'sering' => 'Sering'],
        ],
        'terancam' => [
            'teks' => 'Apakah saat ini Anda merasa tidak aman atau terancam oleh orang lain?',
            'pilihan' => ['tidak' => 'Tidak', 'ya' => 'Ya'],
        ],
        'butuh_segera' => [
            'teks' => 'Apakah Anda membutuhkan bantuan segera, hari ini juga?',
            'pilihan' => ['tidak' => 'Tidak', 'ya' => 'Ya'],
        ],
    ];

    /** Urutan langkah formulir pengajuan. */
    public const LANGKAH = [
        1 => 'Persetujuan & keselamatan',
        2 => 'Yang ingin didiskusikan',
        3 => 'Cerita Anda',
        4 => 'Asesmen dasar',
        5 => 'Preferensi layanan',
        6 => 'Tinjau & kirim',
    ];

    protected $table = 'permintaan';

    /**
     * @return BelongsTo<User, $this>
     */
    public function anggota(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function konselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'konselor_id');
    }

    /**
     * @return HasMany<JawabanAsesmen, $this>
     */
    public function jawabanAsesmen(): HasMany
    {
        return $this->hasMany(JawabanAsesmen::class)->orderBy('id');
    }

    /**
     * @return HasMany<Pesan, $this>
     */
    public function pesan(): HasMany
    {
        return $this->hasMany(Pesan::class)->orderBy('id');
    }

    /**
     * @return HasMany<Pertemuan, $this>
     */
    public function pertemuan(): HasMany
    {
        return $this->hasMany(Pertemuan::class)->orderByDesc('mulai_pada');
    }

    /**
     * @return HasMany<CatatanSesi, $this>
     */
    public function catatanSesi(): HasMany
    {
        return $this->hasMany(CatatanSesi::class)->orderByDesc('id');
    }

    /**
     * Tandai DARURAT beserta alasannya (alasan sama tidak dicatat dua kali).
     */
    public function tandaiDarurat(string $alasan): void
    {
        $this->darurat = true;
        $this->alasan_darurat = array_values(array_unique([...($this->alasan_darurat ?? []), $alasan]));
    }

    /**
     * @return list<string>
     */
    public function labelTopik(): array
    {
        return array_values(array_map(fn (string $kunci): string => self::TOPIK[$kunci] ?? $kunci, $this->topik ?? []));
    }

    /**
     * Anggota meminta konselor dari kampus lain, dan calon ini sekampus dengannya.
     */
    public function bentrokKampus(User $calon): bool
    {
        return $this->kampusSama($calon) && $this->hindari_kampus_sama;
    }

    public function kampusSama(User $calon): bool
    {
        $kampus = mb_strtolower(trim((string) $this->anggota?->kampus));

        return $kampus !== '' && $kampus === mb_strtolower(trim((string) $calon->kampus));
    }

    /**
     * Pertemuan daring berikutnya perlu persetujuan komite bila belum pernah ada
     * pertemuan yang disetujui untuk kasus ini.
     */
    public function pertemuanPerluKomite(): bool
    {
        return $this->daring_disetujui_pada === null;
    }

    public function adaPertemuanAktif(): bool
    {
        return $this->pertemuan()->whereIn('status', [StatusPertemuan::Diusulkan->value, StatusPertemuan::MenungguKomite->value])->exists();
    }

    /**
     * Kode rujukan yang dibacakan anggota dan komite, mis. RGK-2611-0007.
     */
    public function buatKode(): string
    {
        return 'RGK-'.now()->format('ym').'-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusPermintaan::class,
            'darurat' => 'boolean',
            'alasan_darurat' => 'encrypted:array',
            'langkah' => 'integer',
            'persetujuan_pada' => 'datetime',
            'keselamatan' => 'encrypted:array',
            'topik' => 'encrypted:array',
            'topik_lainnya' => 'encrypted',
            'uraian' => 'encrypted',
            'harapan' => 'encrypted',
            'waktu_luang' => 'encrypted',
            'hindari_kampus_sama' => 'boolean',
            'ditugaskan_pada' => 'datetime',
            'daring_disetujui_pada' => 'datetime',
            'penutupan_diusulkan_pada' => 'datetime',
            'alasan_penutupan' => 'encrypted',
            'penutupan_disetujui_anggota_pada' => 'datetime',
            'penutupan_ditinjau_pada' => 'datetime',
            'catatan_tinjauan_penutupan' => 'encrypted',
            'alasan_penolakan' => 'encrypted',
            'catatan_komite' => 'encrypted',
            'diajukan_pada' => 'datetime',
            'selesai_pada' => 'datetime',
        ];
    }
}
