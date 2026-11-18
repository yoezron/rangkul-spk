<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'spk_id' => fake()->unique()->numberBetween(1, 1_000_000),
            'nama' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'telp' => fake()->numerify('08##########'),
            'gender' => fake()->randomElement(['laki-laki', 'perempuan']),
            'kampus' => 'Universitas '.fake()->city(),
            'wilayah' => fake()->city(),
            'peran' => 'Anggota',
            'nomor_anggota' => fake()->numerify('16########'),
            'foto' => null,
            'disinkronkan_pada' => now(),
        ];
    }

    /**
     * Berperan Administrator di spk.or.id, sehingga menjadi superadmin Rangkul.
     */
    public function superadmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'peran' => 'Administrator',
        ]);
    }
}
