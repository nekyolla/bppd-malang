<?php

namespace Database\Factories;

use App\Models\KabKota;
use App\Models\Provinsi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KabKota>
 */
class KabKotaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provinsi_id' => Provinsi::factory(),
            'kode' => fn (array $attributes) => fake()->unique()->numerify(Provinsi::find($attributes['provinsi_id'])->kode.'.##'),
            'nama' => 'Kab. '.fake()->city(),
        ];
    }
}
