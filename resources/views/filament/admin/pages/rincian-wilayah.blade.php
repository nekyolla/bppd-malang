<x-filament-panels::page>
    @if ($peta = $this->peta())
        <x-filament::section
            :heading="$peta['judul']"
            description="Klik wilayah di peta untuk membuka rinciannya. Data yang sama tersedia di tabel di bawah."
        >
            @include('filament.admin.partials.peta-wilayah', [...$peta, 'klik' => 'buka'])
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
