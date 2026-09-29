<x-filament-widgets::widget>
    <x-filament::section
        heading="Peta desa terlatih"
        description="Klik provinsi untuk melihat jumlah kab/kota dan desa terlatih. Data yang sama tersedia di tabel di bawah peta."
    >
        @include('filament.admin.partials.peta-wilayah', [
            'data' => $data,
            'geo' => 'provinsi',
            'klik' => 'panel',
            'labelKosong' => 'Di luar wilayah kerja',
            'kunci' => 'dashboard-'.($tahun ?? 'semua'),
            'label' => 'Peta Indonesia per provinsi, diwarnai menurut jumlah desa terlatih',
        ])
    </x-filament::section>
</x-filament-widgets::widget>
