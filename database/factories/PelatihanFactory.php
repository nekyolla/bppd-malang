<?php

namespace Database\Factories;

use App\Enums\StatusPelatihan;
use App\Enums\TipeLokasi;
use App\Models\JudulPelatihan;
use App\Models\Pelatihan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Pelatihan>
 */
class PelatihanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'judul_pelatihan_id' => JudulPelatihan::factory(),
            'tanggal_mulai' => fake()->dateTimeBetween('+1 week', '+2 months')->format('Y-m-d'),
            'tanggal_selesai' => fn (array $attributes) => Carbon::parse($attributes['tanggal_mulai'])->addDays(3)->toDateString(),
            // Tahun anggaran sama dengan tahun pelaksanaan (BR-07).
            'tahun_anggaran' => fn (array $attributes) => Carbon::parse($attributes['tanggal_mulai'])->year,
            'batch_ke' => 1,
            'tipe_lokasi' => TipeLokasi::Bbpd,
            'keterangan_lokasi' => null,
        ];
    }

    /**
     * Rentang tanggal tertentu, tahun anggaran ikut menyesuaikan.
     */
    public function tanggal(string $mulai, string $selesai): static
    {
        return $this->state([
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'tahun_anggaran' => Carbon::parse($mulai)->year,
        ]);
    }

    public function dibuka(): static
    {
        return $this->state(['status' => StatusPelatihan::Dibuka]);
    }

    public function berjalan(): static
    {
        return $this->state(['status' => StatusPelatihan::Berjalan]);
    }

    public function selesai(): static
    {
        return $this->state(['status' => StatusPelatihan::Selesai]);
    }

    public function luar(): static
    {
        return $this->state([
            'tipe_lokasi' => TipeLokasi::Luar,
            'keterangan_lokasi' => 'Hotel '.fake()->lastName().', '.fake()->city(),
        ]);
    }
}
