<?php

namespace App\Enums;

enum Peran: string
{
    case Superadmin = 'superadmin';
    case Admin = 'admin';

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
