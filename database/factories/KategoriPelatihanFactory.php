<?php

namespace Database\Factories;

use App\Models\KategoriPelatihan;
use Database\Factories\Concerns\BisaNonaktif;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KategoriPelatihan>
 */
class KategoriPelatihanFactory extends Factory
{
    use BisaNonaktif;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_kategori' => fake()->randomElement(['Teknis', 'Manajerial', 'Kepatuhan Administrasi']),
        ];
    }
}
