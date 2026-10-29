{{-- Layout login dua kolom (PF-20): panel instansi di kiri (layar lebar), form di kanan. --}}
@php
    use App\Support\Instansi;
    use App\Support\Logo;
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $livewire ??= null;
    $renderHookScopes = $livewire?->getRenderHookScopes();
    $lambang = Logo::lambang();
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_START, scopes: $renderHookScopes) }}

    <div class="fi-masuk grid min-h-dvh lg:grid-cols-2">
        <aside class="hidden bg-primary-900 p-12 text-white lg:flex lg:flex-col lg:justify-between dark:bg-primary-950">
            <div class="flex items-center gap-x-4">
                @if ($lambang)
                    <img src="{{ $lambang }}" alt="Lambang {{ Instansi::KEMENTERIAN }}" class="h-14 w-auto">
                @endif

                <div class="text-sm leading-snug text-white/75">
                    <p>{{ Instansi::KEMENTERIAN }}</p>
                    <p>{{ Instansi::DIREKTORAT }}</p>
                </div>
            </div>

            <div class="max-w-xl">
                <p class="text-xs font-semibold tracking-widest text-(--emas) uppercase">{{ Instansi::APLIKASI }}</p>
                <p class="mt-4 text-4xl leading-tight font-bold text-balance">{{ Instansi::NAMA }}</p>
            </div>

            <p class="text-xs text-white/50">&copy; {{ now()->year }} {{ Instansi::NAMA }}</p>
        </aside>

        <div class="flex items-center justify-center bg-white px-6 py-12 sm:px-12 dark:bg-gray-950">
            <main id="fi-main-content" class="w-full max-w-sm">
                {{-- Layar sempit: panel kiri tersembunyi, merek tampil di atas form. --}}
                <div class="mb-10 h-12 lg:hidden">
                    <x-merek />
                </div>

                {{ $slot }}
            </main>
        </div>
    </div>

    {{ FilamentView::renderHook(PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}

    {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
</x-filament-panels::layout.base>
