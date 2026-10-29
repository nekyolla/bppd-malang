{{--
    Peta wilayah (resources/js/peta-wilayah.js).
    Parameter: $data (statistik per kode), $geo ('provinsi' | 'kab-kota/{kode}'), $labelKosong,
    $kunci (berganti saat data berubah agar peta digambar ulang), $label (aria).
    Klik wilayah berdata membuka rinciannya; angka singkat tampil di tooltip.
--}}

<div wire:key="peta-{{ $kunci }}">
    <div
        wire:ignore
        x-data="petaWilayah({ data: @js((object) $data), geo: @js($geo), labelKosong: @js($labelKosong) })"
    >
        <div>
            {{-- `isolate`: z-index panel Leaflet (400+) tidak menimpa sidebar & topbar Filament di layar sempit. --}}
            <div class="relative isolate">
                <div
                    x-ref="peta"
                    class="h-80 w-full overflow-hidden rounded-lg sm:h-96"
                    role="region"
                    aria-label="{{ $label }}"
                ></div>
                <p x-show="memuat" class="absolute inset-0 flex items-center justify-center text-sm text-gray-500 dark:text-gray-400">Memuat peta…</p>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-gray-600 dark:text-gray-300">
                <span class="font-medium">Desa terlatih:</span>
                <template x-for="item in legenda" :key="item.label">
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block size-3.5 rounded-sm" :style="{ background: item.warna }"></span>
                        <span x-text="item.label"></span>
                    </span>
                </template>
                <span class="flex items-center gap-1.5">
                    <span
                        class="inline-block size-3.5 rounded-sm border border-dashed"
                        :style="{ background: warnaKosong.latar, borderColor: warnaKosong.garisKosong }"
                    ></span>
                    <span x-text="labelKosong"></span>
                </span>
            </div>
        </div>

    </div>
</div>
