<?php

namespace Database\Factories;

use App\Enums\StatusPermintaan;
use App\Models\Permintaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permintaan>
 */
class PermintaanFactory extends Factory
{
    /**
     * Permintaan yang sudah diajukan dan belum ditangani.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => StatusPermintaan::Diajukan,
            'darurat' => false,
            'langkah' => 6,
            'persetujuan_pada' => now(),
            'keselamatan' => ['pikiran_menyakiti_diri' => 'tidak', 'terancam' => 'tidak', 'butuh_segera' => 'tidak'],
            'topik' => ['stres_kerja'],
            'uraian' => 'Beban mengajar dan administrasi semester ini membuat saya sulit tidur.',
            'harapan' => 'Ingin punya teman bicara.',
            'bentuk_layanan' => 'chat',
            'waktu_luang' => 'Malam hari',
            'hindari_kampus_sama' => false,
            'gender_konselor' => 'bebas',
            'diajukan_pada' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Permintaan $permintaan): void {
            if ($permintaan->status !== StatusPermintaan::Draf && $permintaan->kode === null) {
                $permintaan->forceFill(['kode' => $permintaan->buatKode()])->save();
            }
        });
    }

    public function draf(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPermintaan::Draf,
            'langkah' => 1,
            'persetujuan_pada' => null,
            'keselamatan' => null,
            'topik' => null,
            'uraian' => null,
            'harapan' => null,
            'bentuk_layanan' => null,
            'waktu_luang' => null,
            'diajukan_pada' => null,
        ]);
    }

    /**
     * Sudah ditugaskan kepada konselor dan pendampingan berjalan.
     */
    public function berlangsung(?User $konselor = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => StatusPermintaan::Berlangsung,
            'konselor_id' => $konselor?->id ?? User::factory(),
            'ditugaskan_pada' => now(),
        ]);
    }

    public function darurat(): static
    {
        return $this->state(fn (array $attributes) => [
            'darurat' => true,
            'alasan_darurat' => ['Pertanyaan keselamatan: pikiran menyakiti diri (sering)'],
        ]);
    }
}
