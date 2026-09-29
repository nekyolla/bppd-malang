<?php

namespace Database\Factories;

use App\Models\Desa;
use App\Models\Kecamatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Desa>
 */
class DesaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kecamatan_id' => Kecamatan::factory(),
            'kode' => fn (array $attributes) => fake()->unique()->numerify(Kecamatan::find($attributes['kecamatan_id'])->kode.'.2###'),
            'nama' => fake()->city(),
        ];
    }
}
