<?php

namespace App\Enums;

use App\Support\Instansi;
use Filament\Support\Contracts\HasLabel;

/**
 * Fitur asrama hanya aktif untuk pelatihan di Balai Besar (FR-ASR-01). Nilai `bbpd` tetap
 * dipakai di database; labelnya mengikuti nomenklatur baru (PF-21).
 */
enum TipeLokasi: string implements HasLabel
{
    case Bbpd = 'bbpd';
    case Luar = 'luar';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bbpd => Instansi::SINGKAT,
            self::Luar => 'Luar '.Instansi::SINGKAT,
        };
    }
}
