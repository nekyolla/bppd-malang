<?php

namespace App\Models;

use App\Enums\Peran;
use App\Support\Masking;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['nik', 'email', 'password', 'is_aktif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $table = 'users';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_aktif' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->is_aktif) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => $this->hasAnyRole(Peran::panelAdmin()),
            'peserta' => $this->hasRole(Peran::Peserta),
            default => false,
        };
    }

    public function getFilamentName(): string
    {
        // TODO: pakai nama_lengkap dari user_profiles setelah tabel profil dibuat.
        return Masking::nik($this->nik);
    }
}
