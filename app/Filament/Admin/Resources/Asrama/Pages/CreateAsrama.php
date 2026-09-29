<?php

namespace App\Filament\Admin\Resources\Asrama\Pages;

use App\Filament\Admin\Resources\Asrama\AsramaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAsrama extends CreateRecord
{
    protected static string $resource = AsramaResource::class;

    /**
     * Setelah membuat asrama, langsung ke halaman ubah untuk menambah kamar.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
