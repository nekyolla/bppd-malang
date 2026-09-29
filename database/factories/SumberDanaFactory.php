<?php

namespace Database\Factories;

use App\Models\SumberDana;
use Database\Factories\Concerns\BisaNonaktif;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SumberDana>
 */
class SumberDanaFactory extends Factory
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
            'nama' => fake()->randomElement(['APBN', 'APBD', 'APBDes', 'Mandiri']),
            'butuh_keterangan' => false,
        ];
    }

    /**
     * Sumber dana "Lainnya": keterangan wajib diisi (FR-REG-04).
     */
    public function butuhKeterangan(): static
    {
        return $this->state(['nama' => 'Lainnya', 'butuh_keterangan' => true]);
    }
}
