<?php

namespace Database\Factories;

use App\Enums\StatusPresensi;
use App\Models\LembarPresensi;
use App\Models\PelatihanPeserta;
use App\Models\Presensi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Presensi>
 */
class PresensiFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lembar_presensi_id' => LembarPresensi::factory(),
            // Peserta di kelas yang sama dengan lembar presensinya.
            'pelatihan_peserta_id' => function (array $attributes) {
                $kelas = LembarPresensi::findOrFail($attributes['lembar_presensi_id'])->kelasPelatihan;

                return PelatihanPeserta::factory()->terverifikasi()->create([
                    'pelatihan_id' => $kelas->pelatihan_id,
                    'kelas_pelatihan_id' => $kelas->id,
                ])->id;
            },
            'status' => StatusPresensi::Hadir,
        ];
    }

    public function status(StatusPresensi $status): static
    {
        return $this->state(['status' => $status]);
    }
}
