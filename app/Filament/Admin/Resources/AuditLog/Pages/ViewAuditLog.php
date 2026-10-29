<?php

namespace App\Filament\Admin\Resources\AuditLog\Pages;

use App\Filament\Admin\Resources\AuditLog\AuditLogResource;
use App\Filament\Concerns\RemahTanpaLihat;
use Filament\Resources\Pages\ViewRecord;

/**
 * Tanpa tombol Kembali: breadcrumb "Audit Log" sudah menuju daftar yang sama.
 */
class ViewAuditLog extends ViewRecord
{
    use RemahTanpaLihat;

    protected static string $resource = AuditLogResource::class;
}
