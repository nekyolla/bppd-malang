<?php

namespace Database\Factories;

use App\Enums\JenisKelamin;
use App\Models\Desa;
use App\Models\Jabatan;
use App\Models\Peserta;
use App\Models\StatusPtkp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Peserta>
 */
class PesertaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nik' => fake()->unique()->numerify('35##############'),
            'nama_lengkap' => fake()->name(),
            'jenis_kelamin' => fake()->randomElement(JenisKelamin::cases()),
            'tempat_lahir' => fake()->city(),
            'tanggal_lahir' => fake()->dateTimeBetween('-55 years', '-22 years')->format('Y-m-d'),
            'agama' => fake()->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu']),
            'alamat_domisili' => fake()->streetAddress(),
            'no_hp' => fake()->numerify('08##########'),
            'email' => null,
            'jenjang_pendidikan' => fake()->randomElement(['SMA/Sederajat', 'D3', 'S1', 'S2']),
            'jurusan_pendidikan' => null,
            'jabatan_id' => Jabatan::factory(),
            'waktu_pelantikan' => fake()->dateTimeBetween('-10 years', '-1 year')->format('Y-m-d'),
            'desa_id' => Desa::factory(),
            'alamat_kantor_desa' => fake()->streetAddress(),
            'npwp' => null,
            'status_ptkp_id' => StatusPtkp::factory(),
        ];
    }

    public function lakiLaki(): static
    {
        return $this->state(['jenis_kelamin' => JenisKelamin::LakiLaki]);
    }

    public function perempuan(): static
    {
        return $this->state(['jenis_kelamin' => JenisKelamin::Perempuan]);
    }
}
