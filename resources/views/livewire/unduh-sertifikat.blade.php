{{-- Halaman publik unduh sertifikat (PF-40). Hanya nama tersamar dan daftar sertifikat yang tampil. --}}
<div>
    <h1 class="text-2xl font-bold">Unduh Sertifikat</h1>
    <p class="mt-2 text-gray-700">
        Masukkan NIK Anda untuk mengunduh sertifikat pelatihan yang pernah Anda ikuti.
    </p>

    @if ($galatUmum)
        <div class="mt-6 rounded-xl border border-danger-200 bg-danger-50 p-4 text-danger-700" role="alert">
            {{ $galatUmum }}
        </div>
    @endif

    <form wire:submit="cari" class="mt-6 rounded-xl border border-gray-200 bg-white p-6">
        {{-- Honeypot: disembunyikan dari manusia dan pembaca layar. --}}
        <div aria-hidden="true" style="position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden;">
            <label for="website">Website</label>
            <input id="website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
        </div>

        {{ $this->form }}

        <x-filament::button type="submit" class="mt-4 w-full sm:w-auto" wire:loading.attr="disabled" wire:target="cari">
            Cari sertifikat
        </x-filament::button>
    </form>

    <div class="mt-6" aria-live="polite">
        @if ($tidakDitemukan)
            <div class="rounded-xl border border-gray-200 bg-white p-6" role="status">
                <p class="text-gray-700">Sertifikat untuk NIK ini belum tersedia. Bila Anda sudah menyelesaikan pelatihan, hubungi panitia.</p>
            </div>
        @elseif ($hasil)
            <p class="text-sm text-gray-600">Sertifikat atas nama <span class="font-semibold text-gray-950">{{ $hasil['nama'] }}</span>:</p>

            <ul class="mt-3 space-y-3">
                @foreach ($hasil['daftar'] as $item)
                    <li class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p class="font-semibold">{{ $item['pelatihan'] }}</p>
                            <p class="text-sm text-gray-600">{{ $item['tanggal'] }}</p>
                            @if ($item['nomor'])
                                <p class="text-sm text-gray-600">No. {{ $item['nomor'] }}</p>
                            @endif
                        </div>
                        <x-filament::button tag="a" :href="$item['url']" icon="heroicon-o-arrow-down-tray" color="primary" class="shrink-0">
                            Unduh PDF
                        </x-filament::button>
                    </li>
                @endforeach
            </ul>

            <p class="mt-3 text-xs text-gray-500">Tautan unduh berlaku 15 menit. Bila kedaluwarsa, cari ulang.</p>
        @endif
    </div>

    <div class="mt-8 flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-700">Mendapat surat pemanggilan pelatihan? Daftarkan diri Anda.</p>
        <x-filament::button tag="a" :href="route('registrasi', absolute: false)" icon="heroicon-o-pencil-square" class="shrink-0">
            Form registrasi
        </x-filament::button>
    </div>
</div>
