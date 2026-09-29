<?php

namespace Database\Factories;

use App\Models\JudulPelatihan;
use App\Models\KategoriPelatihan;
use Database\Factories\Concerns\BisaNonaktif;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JudulPelatihan>
 */
class JudulPelatihanFactory extends Factory
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
            'kategori_pelatihan_id' => KategoriPelatihan::factory(),
            'judul' => 'Pelatihan '.fake()->words(3, true),
        ];
    }
}
