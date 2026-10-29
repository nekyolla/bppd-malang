<?php

namespace App\Providers;

use App\Policies\ActivityPolicy;
use App\Support\Warna;
use Filament\Support\Facades\FilamentColor;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Activitylog\Models\Activity;

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
        // Model Activity milik package, jadi policy-nya tidak ditemukan otomatis.
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Warna untuk komponen Filament di luar panel (form registrasi publik),
        // sama dengan panel admin (DESIGN_SYSTEM §2.1).
        FilamentColor::register(Warna::semua());

        // IP asli pengunjung dari header X-Forwarded-For (config bbpd.proxy_tepercaya).
        if (filled($proxy = config('bbpd.proxy_tepercaya'))) {
            TrustProxies::at($proxy === '*' ? '*' : array_map('trim', explode(',', $proxy)));
        }
    }
}
