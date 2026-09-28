<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Fitur asrama hanya aktif untuk pelatihan di BBPD (FR-ASR-01).
 */
enum TipeLokasi: string implements HasLabel
{
    case Bbpd = 'bbpd';
    case Luar = 'luar';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bbpd => 'BBPD Malang',
            self::Luar => 'Luar BBPD',
        };
    }
}
