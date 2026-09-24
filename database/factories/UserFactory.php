<?php

namespace Database\Factories;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nik' => fake()->unique()->numerify('35##############'),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'is_aktif' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Akun internal (superadmin, admin, keuangan): login dengan username, tanpa NIK.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'username' => fake()->unique()->lexify('admin????'),
            'nik' => null,
        ]);
    }

    public function nonaktif(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_aktif' => false,
        ]);
    }

    public function peran(Peran $peran): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($peran));
    }
}
