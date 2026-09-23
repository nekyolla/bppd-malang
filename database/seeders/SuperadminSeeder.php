<?php

namespace Database\Seeders;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperadminSeeder extends Seeder
{
    public function run(): void
    {
        $config = config('bbpd.superadmin');

        if (blank($config['nik']) || blank($config['password'])) {
            $this->command?->warn('SUPERADMIN_NIK / SUPERADMIN_PASSWORD belum diisi di .env. Akun superadmin tidak dibuat.');

            return;
        }

        $user = User::firstOrCreate(
            ['nik' => $config['nik']],
            [
                'email' => $config['email'] ?: null,
                'password' => $config['password'],
                'is_aktif' => true,
            ],
        );

        $user->assignRole(Peran::Superadmin);
    }
}
