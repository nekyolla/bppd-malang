@php
    use App\Filament\Admin\Resources\Pelatihan\Pages\ManagePendaftar;
    use App\Filament\Admin\Resources\Pendaftaran\PendaftaranResource;
    use Filament\Support\Icons\Heroicon;
@endphp

<div wire:poll.30s class="fi-lonceng">
    <x-filament::dropdown placement="bottom-end" width="sm" teleport>
        <x-slot name="trigger">
            <x-filament::icon-button
                :icon="Heroicon::OutlinedBell"
                :badge="$total > 0 ? ($total > 99 ? '99+' : $total) : null"
                badge-color="danger"
                color="gray"
                size="lg"
                label="Notifikasi"
                :tooltip="$total > 0 ? $total . ' menunggu verifikasi' : 'Notifikasi'"
            />
        </x-slot>

        <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">Menunggu verifikasi</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                @if ($total > 0)
                    {{ $total }} pendaftar di {{ $pelatihan->count() + $lainnya }} pelatihan
                @else
                    Semua pendaftar sudah diperiksa.
                @endif
            </p>
        </div>

        @if ($pelatihan->isNotEmpty())
            <x-filament::dropdown.list>
                @foreach ($pelatihan as $item)
                    <x-filament::dropdown.list.item
                        tag="a"
                        :href="ManagePendaftar::urlMenunggu($item)"
                        :badge="$item->jumlah_menunggu"
                        badge-color="warning"
                    >
                        {{ $item->nama_tampilan }}
                    </x-filament::dropdown.list.item>
                @endforeach
            </x-filament::dropdown.list>

            <x-filament::dropdown.list>
                <x-filament::dropdown.list.item tag="a" :href="PendaftaranResource::getUrl('index')" color="primary">
                    {{ $lainnya > 0 ? "Lihat semua (+{$lainnya} pelatihan)" : 'Lihat semua pendaftaran' }}
                </x-filament::dropdown.list.item>
            </x-filament::dropdown.list>
        @endif
    </x-filament::dropdown>
</div>
