<?php

namespace App\Filament\Admin\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Aturan yang ditolak service (ValidationException) di dalam aksi modal: pesannya tampil
 * sebagai notifikasi dan aksi dihentikan sehingga modal tetap terbuka (CLAUDE.md, "Galat
 * service"). Untuk form halaman, pakai App\Filament\Concerns\MemetakanGalatService.
 */
class GalatService
{
    /**
     * @template T
     *
     * @param  string  $judul  Judul notifikasi, mis. "Peserta tidak dapat dibatalkan".
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function jalankan(Action $action, string $judul, Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            self::notifikasi($judul, $exception)->send();

            $action->halt();
        }
    }

    public static function notifikasi(string $judul, ValidationException $exception): Notification
    {
        return Notification::make()
            ->title($judul)
            ->body(Arr::first(Arr::flatten($exception->errors())))
            ->danger();
    }
}
