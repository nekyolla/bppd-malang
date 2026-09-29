<?php

namespace Database\Factories;

use App\Models\Asrama;
use Database\Factories\Concerns\BisaNonaktif;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asrama>
 */
class AsramaFactory extends Factory
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
            'nama_asrama' => fake()->randomElement(['Anggrek', 'Melati', 'Mawar', 'Kenanga']),
        ];
    }
}
