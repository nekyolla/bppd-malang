<div>
    <h1 class="text-2xl font-semibold">Registrasi Peserta Pelatihan</h1>

    @if ($terkirim)
        <div class="mt-6 rounded-xl border border-success-200 bg-white p-6" role="status">
            <p class="text-lg font-semibold text-success-700">Terima kasih, pendaftaran Anda sudah kami terima.</p>
            <p class="mt-2 text-gray-700">
                Panitia akan memeriksa data Anda berdasarkan KTP dan surat tugas.
            </p>
        </div>
    @elseif (! $adaPelatihan)
        <div class="mt-6 rounded-xl border border-gray-200 bg-white p-6">
            <p class="text-gray-700">Saat ini belum ada pelatihan yang membuka pendaftaran.</p>
        </div>
    @else
        <p class="mt-2 text-gray-700">
            Isi data Anda sesuai KTP dan surat tugas dari desa. Siapkan scan KTP, pas foto, dan surat tugas.
        </p>

        @if ($galatUmum)
            <div class="mt-6 rounded-xl border border-danger-200 bg-danger-50 p-4 text-danger-700" role="alert">
                {{ $galatUmum }}
            </div>
        @endif

        <form wire:submit="daftar" class="mt-6">
            {{-- Honeypot: disembunyikan dari manusia dan pembaca layar. --}}
            <div aria-hidden="true" style="position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; overflow: hidden;">
                <label for="website">Website</label>
                <input id="website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            {{ $this->form }}
        </form>

        <x-filament-actions::modals />
    @endif
</div>
