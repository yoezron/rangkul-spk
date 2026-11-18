<?php

namespace Database\Factories;

use App\Enums\Hak;
use App\Models\Peran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Peran>
 */
class PeranFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kunci' => fake()->unique()->slug(2),
            'nama' => 'Peran '.fake()->unique()->word(),
            'deskripsi' => null,
            'hak' => [],
            'bawaan' => false,
        ];
    }

    /**
     * Peran dengan hak akses tertentu.
     */
    public function denganHak(Hak ...$hak): static
    {
        return $this->state(fn (array $attributes) => [
            'hak' => array_map(fn (Hak $satu): string => $satu->value, $hak),
        ]);
    }
}
