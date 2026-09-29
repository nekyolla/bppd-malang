<?php

namespace Database\Factories;

use App\Models\StatusPtkp;
use Database\Factories\Concerns\BisaNonaktif;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusPtkp>
 */
class StatusPtkpFactory extends Factory
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
            'kode' => fake()->unique()->bothify('X?/##'),
            'nama' => fake()->sentence(3),
        ];
    }
}
