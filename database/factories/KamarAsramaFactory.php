<?php

namespace Database\Factories;

use App\Models\Asrama;
use App\Models\KamarAsrama;
use Database\Factories\Concerns\BisaNonaktif;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KamarAsrama>
 */
class KamarAsramaFactory extends Factory
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
            'asrama_id' => Asrama::factory(),
            'no_kamar' => fake()->unique()->numerify('###'),
            'kapasitas' => 4,
        ];
    }
}
