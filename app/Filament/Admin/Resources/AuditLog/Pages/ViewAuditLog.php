<?php

namespace App\Filament\Admin\Resources\AuditLog\Pages;

use App\Filament\Admin\Actions\KembaliAction;
use App\Filament\Admin\Resources\AuditLog\AuditLogResource;
use App\Filament\Concerns\RemahTanpaLihat;
use Filament\Resources\Pages\ViewRecord;

class ViewAuditLog extends ViewRecord
{
    use RemahTanpaLihat;

    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            KembaliAction::ke(AuditLogResource::getUrl('index'), 'Kembali ke audit log'),
        ];
    }
}
