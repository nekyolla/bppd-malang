<?php

namespace App\Services;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Akun internal (superadmin, admin) dikelola superadmin (FR-AUTH-04–05).
 * Akun tidak dihapus, hanya dinonaktifkan.
 */
class AkunService
{
    /**
     * Username akun internal: huruf kecil, angka, titik, garis bawah, atau
     * tanda hubung, dan wajib memuat minimal satu huruf.
     */
    public const POLA_USERNAME = '/^(?=.*[a-z])[a-z0-9._-]{3,50}$/';

    public const PESAN_USERNAME = 'Username 3–50 karakter: huruf kecil, angka, titik, garis bawah, atau tanda hubung, dan memuat minimal satu huruf.';

    /**
     * @param  array{username: string, email?: ?string, peran: Peran|string, password: string}  $data
     *
     * @throws ValidationException
     */
    public function buat(#[SensitiveParameter] array $data): User
    {
        $data = $this->normalisasi($data);

        Validator::make($data, [
            ...$this->aturanProfil(),
            'password' => ['required', 'string', Password::min(8)],
        ], $this->pesan())->validate();

        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'is_aktif' => true,
            ]);

            $user->syncRoles([Peran::from($data['peran'])]);

            return $user;
        });
    }

    /**
     * @param  array{username: string, email?: ?string, peran: Peran|string}  $data
     *
     * @throws ValidationException
     */
    public function ubah(User $user, User $oleh, array $data): void
    {
        $data = $this->normalisasi($data);

        Validator::make($data, $this->aturanProfil($user), $this->pesan())->validate();

        $peran = Peran::from($data['peran']);

        if ($peran !== Peran::Superadmin && $user->hasRole(Peran::Superadmin)) {
            $this->pastikanBukanDiriSendiri($user, $oleh, 'Anda tidak dapat menurunkan peran akun Anda sendiri.');
            $this->pastikanMasihAdaSuperadminLain($user);
        }

        DB::transaction(function () use ($user, $data, $peran): void {
            $user->update(['username' => $data['username'], 'email' => $data['email']]);
            $user->syncRoles([$peran]);
        });
    }

    /**
     * Reset kata sandi oleh superadmin setelah memastikan identitas pemilik akun (FR-AUTH-04).
     *
     * @throws ValidationException
     */
    public function aturUlangKataSandi(User $user, #[SensitiveParameter] string $password): void
    {
        Validator::make(['password' => $password], ['password' => ['required', 'string', Password::min(8)]])->validate();

        $user->update(['password' => $password]);
    }

    /**
     * @throws ValidationException
     */
    public function nonaktifkan(User $user, User $oleh): void
    {
        $this->pastikanBukanDiriSendiri($user, $oleh, 'Anda tidak dapat menonaktifkan akun Anda sendiri.');

        if ($user->hasRole(Peran::Superadmin)) {
            $this->pastikanMasihAdaSuperadminLain($user);
        }

        $user->update(['is_aktif' => false]);
    }

    public function aktifkan(User $user): void
    {
        $user->update(['is_aktif' => true]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function aturanProfil(?User $abaikan = null): array
    {
        return [
            'username' => ['required', 'regex:'.self::POLA_USERNAME, Rule::unique('users', 'username')->ignore($abaikan)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($abaikan)],
            'peran' => ['required', Rule::enum(Peran::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function pesan(): array
    {
        return [
            'username.regex' => self::PESAN_USERNAME,
            'username.unique' => 'Username ini sudah dipakai.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalisasi(#[SensitiveParameter] array $data): array
    {
        $data['username'] = strtolower(trim((string) ($data['username'] ?? '')));
        $data['email'] = filled($data['email'] ?? null) ? strtolower(trim($data['email'])) : null;
        $data['peran'] = ($data['peran'] ?? null) instanceof Peran ? $data['peran']->value : ($data['peran'] ?? null);

        return $data;
    }

    /**
     * @throws ValidationException
     */
    private function pastikanBukanDiriSendiri(User $user, User $oleh, string $pesan): void
    {
        if ($user->is($oleh)) {
            throw ValidationException::withMessages(['akun' => $pesan]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function pastikanMasihAdaSuperadminLain(User $user): void
    {
        $lain = User::role(Peran::Superadmin)->where('is_aktif', true)->whereKeyNot($user->getKey())->exists();

        if (! $lain) {
            throw ValidationException::withMessages(['akun' => 'Harus tetap ada minimal satu superadmin yang aktif.']);
        }
    }
}
