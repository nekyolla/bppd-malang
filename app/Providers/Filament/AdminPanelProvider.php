<?php

namespace App\Providers\Filament;

use App\Filament\Admin\Livewire\LoncengVerifikasi;
use App\Filament\Admin\Pages\Dashboard;
use App\Filament\Admin\Pages\RincianWilayah;
use App\Filament\Auth\Login;
use App\Http\Middleware\BatasSesiAdmin;
use App\Support\Instansi;
use App\Support\Logo;
use App\Support\Warna;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;

class AdminPanelProvider extends PanelProvider
{
    /**
     * Komponen Livewire di luar App\Livewire harus didaftarkan: permintaan update (polling,
     * event) memetakan nama komponen kembali ke class, dan bila gagal Livewire membalas 419.
     */
    public function boot(): void
    {
        Livewire::component(LoncengVerifikasi::NAMA, LoncengVerifikasi::class);
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->brandName(Instansi::NAMA)
            // Lambang + nama instansi sebagai teks HTML agar terbaca di mode gelap (PF-20, PF-21).
            ->brandLogo(fn (): HtmlString => new HtmlString(Blade::render('<x-merek topbar />')))
            ->brandLogoHeight('3rem')
            ->favicon(fn (): ?string => Logo::favicon())
            // Poppins dimuat lokal dari theme.css (@fontsource), tanpa CDN.
            ->font('Poppins', provider: LocalFontProvider::class)
            ->colors(Warna::semua())
            // Urutan grup menu mengikuti DESIGN_SYSTEM §5.1.
            // Pencarian topbar hanya untuk resource yang ikut serta (Peserta, Pelatihan).
            ->globalSearchResourceOptIn()
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            ->globalSearchFieldKeyBindingSuffix()
            // Data Master & Pengguna jarang dipakai harian: tertutup bawaan. Filament hanya memakai
            // bawaan ini bila browser belum menyimpan buka/tutup grup (localStorage collapsedGroups).
            // Semua grup harus objek: campuran string & objek membuat urutan grup diabaikan.
            ->navigationGroups([
                NavigationGroup::make('Pelatihan'),
                NavigationGroup::make('Asrama'),
                NavigationGroup::make('Laporan'),
                NavigationGroup::make('Data Master')->collapsed(),
                NavigationGroup::make('Pengguna')->collapsed(),
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->pages([
                Dashboard::class,
            ])
            // Leaflet + GeoJSON hanya dimuat di halaman yang menampilkan peta.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render("@vite('resources/js/peta-wilayah.js')"),
                scopes: [Dashboard::class, RincianWilayah::class],
            )
            // Lonceng pendaftar menunggu verifikasi, di kiri menu akun.
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => Blade::render('@livewire(\''.LoncengVerifikasi::NAMA.'\')'),
            )
            // Sesi berakhir (BatasSesiAdmin membalas 401) atau token kedaluwarsa (419): muat ulang
            // halaman sehingga diarahkan ke login, alih-alih dialog "This page has expired".
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => <<<'HTML'
                    <script>
                        document.addEventListener('livewire:init', () => {
                            Livewire.hook('request', ({ fail }) => {
                                fail(({ status, preventDefault }) => {
                                    if (status === 401 || status === 419) {
                                        preventDefault();
                                        window.location.reload();
                                    }
                                });
                            });
                        });
                    </script>
                    HTML,
            )
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // Persisten: ikut diperiksa pada update Livewire (polling, aksi), bukan hanya saat membuka halaman.
            ->authMiddleware([
                Authenticate::class,
                BatasSesiAdmin::class,
            ], isPersistent: true);
    }
}
