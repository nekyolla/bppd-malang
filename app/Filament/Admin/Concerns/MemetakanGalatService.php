<?php

namespace App\Filament\Admin\Concerns;

use Closure;
use Illuminate\Validation\ValidationException;

/**
 * Service melempar ValidationException dengan nama kolom biasa (mis. `batch_ke`)
 * agar dapat dipakai di luar Filament. Trait ini memetakannya ke state path
 * form Filament (`data.batch_ke`) supaya pesan tampil di bawah isian yang benar.
 */
trait MemetakanGalatService
{
    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function jalankanService(Closure $callback, string $statePath = 'data'): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                collect($exception->errors())
                    ->mapWithKeys(fn (array $pesan, string $kolom): array => ["{$statePath}.{$kolom}" => $pesan])
                    ->all(),
            );
        }
    }
}
