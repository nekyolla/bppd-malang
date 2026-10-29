<x-filament-widgets::widget>
    <x-filament::section
        heading="Peta desa terlatih"
        description="Klik provinsi untuk melihat rinciannya."
    >
        @include('filament.admin.partials.peta-wilayah', [
            'data' => $data,
            'geo' => 'provinsi',
            'labelKosong' => 'Di luar wilayah kerja',
            'kunci' => 'dashboard-'.($tahun ?? 'semua'),
            'label' => 'Peta Indonesia per provinsi, diwarnai menurut jumlah desa terlatih',
        ])
    </x-filament::section>
</x-filament-widgets::widget>
