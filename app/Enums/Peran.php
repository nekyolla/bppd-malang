<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Peran: string implements HasLabel
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::Superadmin => 'Superadmin',
            self::Admin => 'Admin',
        };
    }

    /**
     * Role yang boleh masuk panel /admin.
     *
     * @return list<self>
     */
    public static function panelAdmin(): array
    {
        return [self::Superadmin, self::Admin];
    }
}
