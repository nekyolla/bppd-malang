{{--
    Merek instansi: lambang (gambar) + nama (teks HTML, ikut mode gelap). PF-20, PF-21.
    `topbar`: kementerian + nama satu baris. Filament hanya menampilkannya di topbar saat
    sidebar tetap terbuka (≥ lg); di bawah itu merek ada di kepala sidebar (lihat theme.css).
--}}
@props(['topbar' => false])

@php
    use App\Support\Instansi;
    use App\Support\Logo;

    $lambang = Logo::lambang();
@endphp

<span {{ $attributes->class(['fi-merek flex h-full min-w-0 items-center gap-x-3']) }}>
    @if ($lambang)
        <img src="{{ $lambang }}" alt="Lambang {{ Instansi::KEMENTERIAN }}" class="h-full w-auto shrink-0">
    @endif

    <span class="flex min-w-0 flex-col justify-center text-start leading-tight">
        <span @class([
            'fi-merek-kementerian text-xs text-gray-500 dark:text-gray-400',
            'hidden sm:block' => ! $topbar,
        ])>
            {{ Instansi::KEMENTERIAN }}
        </span>

        <span @class([
            'fi-merek-nama font-semibold text-primary-900 dark:text-white',
            'text-base whitespace-nowrap' => $topbar,
            'text-sm sm:text-base' => ! $topbar,
        ])>
            {{ Instansi::NAMA }}
        </span>
    </span>
</span>
