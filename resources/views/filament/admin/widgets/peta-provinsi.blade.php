<x-filament-widgets::widget>
    <x-filament::section
        heading="Peta desa terlatih"
        description="Klik provinsi untuk melihat jumlah kab/kota dan desa terlatih. Data yang sama tersedia di tabel di bawah peta."
    >
        {{-- Kunci berganti saat filter tahun berubah, sehingga peta digambar ulang dengan data baru. --}}
        <div wire:key="peta-provinsi-{{ $tahun ?? 'semua' }}">
        <div
            wire:ignore
            x-data="petaProvinsi({ data: @js($data) })"
            class="grid gap-4 lg:grid-cols-3"
        >
            <div class="lg:col-span-2">
                <div
                    x-ref="peta"
                    class="h-80 w-full overflow-hidden rounded-lg sm:h-96"
                    role="region"
                    aria-label="Peta Indonesia per provinsi, diwarnai menurut jumlah desa terlatih"
                ></div>

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
                        <span>Di luar wilayah kerja</span>
                    </span>
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10" aria-live="polite">
                <template x-if="! terpilih">
                    <p class="text-sm text-gray-600 dark:text-gray-300">Pilih provinsi di peta untuk melihat rinciannya.</p>
                </template>

                <template x-if="terpilih && terpilih.kosong">
                    <div>
                        <p class="text-base font-semibold text-gray-950 dark:text-white" x-text="terpilih.nama"></p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Di luar wilayah kerja BBPD Malang. Tidak ada data.</p>
                    </div>
                </template>

                <template x-if="terpilih && ! terpilih.kosong">
                    <div>
                        <p class="text-base font-semibold text-gray-950 dark:text-white" x-text="terpilih.nama"></p>
                        <dl class="mt-3 space-y-3 text-sm">
                            <div>
                                <dt class="text-gray-600 dark:text-gray-300">Kab/kota terlatih</dt>
                                <dd class="text-lg font-semibold text-gray-950 dark:text-white">
                                    <span x-text="angka(terpilih.kab_kota_terlatih)"></span>
                                    <span class="text-sm font-normal text-gray-600 dark:text-gray-300">dari <span x-text="angka(terpilih.total_kab_kota)"></span></span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-600 dark:text-gray-300">Desa terlatih</dt>
                                <dd class="text-lg font-semibold text-gray-950 dark:text-white" x-text="angka(terpilih.desa_terlatih)"></dd>
                            </div>
                            <div>
                                <dt class="text-gray-600 dark:text-gray-300">Desa belum terlatih</dt>
                                <dd class="text-lg font-semibold text-gray-950 dark:text-white" x-text="angka(terpilih.desa_belum_terlatih)"></dd>
                            </div>
                        </dl>
                        <a
                            :href="terpilih.url"
                            class="mt-4 inline-block text-sm font-medium text-primary-600 underline hover:text-primary-500 dark:text-primary-400"
                        >Lihat rincian</a>
                    </div>
                </template>
            </div>
        </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
