<?php

namespace App\Models;

use App\Enums\Peran;
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

#[Fillable(['username', 'email', 'password', 'is_aktif'])]
#[Hidden(['password', 'remember_token'])]
/**
 * Akun internal BBPD (superadmin, admin). Peserta tidak memiliki akun (PF-01).
 */
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

        return $panel->getId() === 'admin' && $this->hasAnyRole(Peran::panelAdmin());
    }

    public function getFilamentName(): string
    {
        return $this->username;
    }
}
