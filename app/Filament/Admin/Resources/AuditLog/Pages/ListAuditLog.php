<?php

namespace App\Filament\Admin\Resources\AuditLog\Pages;

use App\Filament\Admin\Resources\AuditLog\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLog extends ListRecords
{
    protected static string $resource = AuditLogResource::class;
}
