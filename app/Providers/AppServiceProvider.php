<?php

namespace App\Providers;

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Warna untuk komponen Filament di luar panel (form registrasi publik),
        // sama dengan panel admin (DESIGN_SYSTEM §2.1).
        FilamentColor::register([
            'primary' => Color::Blue,
            'gray' => Color::Slate,
            'success' => Color::Emerald,
            'warning' => Color::Amber,
            'danger' => Color::Rose,
            'info' => Color::Sky,
        ]);
    }
}
