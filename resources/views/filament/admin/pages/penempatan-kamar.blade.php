<x-filament-panels::page>
    {{ $this->form }}

    @if (! $this->pelatihanTerpilih)
        <x-filament::section>
            <div class="flex flex-col items-center gap-2 py-6 text-center">
                <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedHomeModern" class="h-8 w-8 text-gray-400" />
                <p class="text-sm font-semibold text-gray-950 dark:text-white">Pilih pelatihan</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">Penempatan kamar hanya untuk pelatihan di BBPD Malang.</p>
            </div>
        </x-filament::section>
    @else
        @php($ringkasan = $this->ringkasan())

        <x-filament::section
            heading="Ringkasan tempat tidur"
            :description="$this->bisaDiubah()
                ? 'Tempat tidur di kamar kosong dapat dipakai laki-laki maupun perempuan.'
                : 'Pelatihan sudah selesai. Penempatan hanya dapat dilihat.'"
        >
            <dl class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Peserta terverifikasi</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $ringkasan['peserta']['L'] }} laki-laki · {{ $ringkasan['peserta']['P'] }} perempuan
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Belum dapat kamar</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $ringkasan['belum']['L'] }} laki-laki · {{ $ringkasan['belum']['P'] }} perempuan
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Sisa tempat tidur</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $ringkasan['sisa']['L'] }} di kamar laki-laki · {{ $ringkasan['sisa']['P'] }} di kamar perempuan · {{ $ringkasan['kosong'] }} di kamar kosong
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Kecukupan</dt>
                    <dd class="mt-1">
                        @if ($ringkasan['kurang'] > 0)
                            <x-filament::badge color="danger" :icon="\Filament\Support\Icons\Heroicon::OutlinedExclamationTriangle">
                                Kurang ± {{ $ringkasan['kurang'] }} tempat tidur
                            </x-filament::badge>
                        @else
                            <x-filament::badge color="success" :icon="\Filament\Support\Icons\Heroicon::OutlinedCheckCircle">
                                Cukup
                            </x-filament::badge>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-filament::section>

        @forelse ($this->denah as $asrama)
            <x-filament::section :heading="'Asrama '.$asrama['nama']">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($asrama['kamar'] as $kamar)
                        @php($lencana = $this->lencana($kamar))

                        <div
                            wire:key="kamar-{{ $kamar['id'] }}"
                            @class([
                                'flex flex-col gap-3 rounded-xl p-4',
                                'border border-gray-200 bg-white dark:border-white/10 dark:bg-white/5' => $kamar['tersedia'] && ! $kamar['penuh'],
                                'border-2 border-gray-400 bg-white dark:border-white/40 dark:bg-white/5' => $kamar['tersedia'] && $kamar['penuh'],
                                'border border-dashed border-gray-300 bg-gray-50 dark:border-white/10 dark:bg-transparent' => ! $kamar['tersedia'],
                            ])
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-base font-semibold text-gray-950 dark:text-white">{{ $kamar['no_kamar'] }}</p>
                                <x-filament::badge :color="$lencana['color']" :icon="$lencana['icon']">
                                    {{ $lencana['label'] }}
                                </x-filament::badge>
                            </div>

                            @if ($kamar['keterangan'])
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $kamar['keterangan'] }}</p>
                            @endif

                            @if ($kamar['penghuni'] !== [])
                                <ul class="flex flex-col gap-1">
                                    @foreach ($kamar['penghuni'] as $penghuni)
                                        <li wire:key="penghuni-{{ $penghuni['id'] }}" class="flex items-center justify-between gap-2 text-sm">
                                            <span class="min-w-0 text-gray-700 dark:text-gray-200">
                                                {{ $penghuni['nama'] }}
                                                @if ($kamar['tipe'] === \App\Enums\TipeKamar::Pasutri)
                                                    <span class="text-gray-500 dark:text-gray-400">({{ $penghuni['jenis_kelamin']->value }})</span>
                                                @endif
                                                @if ($penghuni['kelas'])
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">· Kelas {{ $penghuni['kelas'] }}</span>
                                                @endif
                                            </span>
                                            {{ ($this->keluarkanAction)(['penempatan' => $penghuni['id']]) }}
                                        </li>
                                    @endforeach
                                </ul>
                            @elseif ($kamar['tersedia'])
                                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada penghuni.</p>
                            @endif

                            @if ($kamar['tersedia'] && ! $kamar['penuh'])
                                <div class="mt-auto">
                                    {{ ($this->tempatkanAction)(['kamar' => $kamar['id']]) }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <div class="flex flex-col items-center gap-2 py-6 text-center">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">Belum ada kamar aktif</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Superadmin dapat menambahkan asrama dan kamar di menu Data Master → Asrama &amp; Kamar.</p>
                </div>
            </x-filament::section>
        @endforelse
    @endif
</x-filament-panels::page>
