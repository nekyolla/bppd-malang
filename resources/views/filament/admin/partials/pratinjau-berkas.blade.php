{{-- Pratinjau berkas privat (App\Filament\Admin\Support\PratinjauBerkas). --}}
<div class="flex flex-col gap-4 lg:sticky lg:top-0">
    @foreach ($berkas as $item)
        <section class="flex flex-col gap-2">
            <div class="flex items-center justify-between gap-2 text-sm">
                <span class="font-medium text-gray-950 dark:text-white">{{ $item['label'] }}</span>
                @if ($item['url'])
                    <x-filament::link :href="$item['url']" target="_blank" size="sm" :icon="\Filament\Support\Icons\Heroicon::OutlinedArrowTopRightOnSquare">
                        Buka di tab baru
                    </x-filament::link>
                @endif
            </div>

            @if (! $item['url'])
                <p class="text-sm text-gray-500 dark:text-gray-400">Berkas tidak tersedia.</p>
            @elseif ($item['tautanSaja'])
                {{-- Surat tugas cukup tautan; pratinjau difokuskan ke KTP & foto. --}}
            @elseif ($item['pdf'])
                <iframe
                    src="{{ $item['url'] }}#view=FitH"
                    title="{{ $item['label'] }}"
                    class="h-80 w-full rounded-lg border border-gray-200 bg-white dark:border-white/10"
                ></iframe>
            @else
                <img
                    src="{{ $item['url'] }}"
                    alt="{{ $item['label'] }}"
                    loading="lazy"
                    class="max-h-80 w-full rounded-lg border border-gray-200 bg-gray-50 object-contain dark:border-white/10 dark:bg-white/5"
                >
            @endif
        </section>
    @endforeach
</div>
