<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Mencatat setiap perubahan ke audit log: siapa, kapan, nilai lama, nilai baru (FR-AUD-01).
 * Semua kolom dicatat, termasuk status dan snapshot yang tidak ada di $fillable.
 */
trait Diaudit
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['created_at', 'updated_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
