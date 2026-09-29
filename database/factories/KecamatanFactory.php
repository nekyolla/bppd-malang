<?php

namespace Database\Factories;

use App\Models\KabKota;
use App\Models\Kecamatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kecamatan>
 */
class KecamatanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kab_kota_id' => KabKota::factory(),
            'kode' => fn (array $attributes) => fake()->unique()->numerify(KabKota::find($attributes['kab_kota_id'])->kode.'.##'),
            'nama' => fake()->city(),
        ];
    }
}
