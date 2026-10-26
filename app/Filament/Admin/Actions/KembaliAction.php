<?php

namespace App\Filament\Admin\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * Tombol kembali ke halaman induk yang tetap, bukan riwayat browser (PF-17).
 */
class KembaliAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'kembali';
    }

    public static function ke(string|Closure $url, string|Closure $label = 'Kembali'): static
    {
        return static::make()->url($url)->label($label);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Kembali')
            ->icon(Heroicon::OutlinedArrowLeft)
            ->color('gray');
    }
}
