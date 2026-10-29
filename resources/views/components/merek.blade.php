{{-- Merek instansi: lambang (gambar) + nama (teks HTML, ikut mode gelap). PF-20, PF-21. --}}
@props(['besar' => false])

@php
    use App\Support\Instansi;
    use App\Support\Logo;

    $lambang = Logo::lambang();
@endphp

<span {{ $attributes->class(['fi-merek flex h-full items-center', $besar ? 'gap-x-4' : 'gap-x-3']) }}>
    @if ($lambang)
        <img src="{{ $lambang }}" alt="Lambang {{ Instansi::KEMENTERIAN }}" @class(['w-auto shrink-0', $besar ? 'h-16' : 'h-full'])>
    @endif

    <span class="flex min-w-0 flex-col justify-center text-start leading-tight">
        <span @class(['fi-merek-kementerian font-medium text-gray-500 dark:text-gray-400', $besar ? 'text-sm' : 'hidden text-xs sm:block'])>
            {{ Instansi::KEMENTERIAN }}
        </span>
        <span @class(['fi-merek-nama font-semibold text-primary-900 dark:text-white', $besar ? 'text-lg' : 'text-sm sm:text-base'])>
            {{ Instansi::NAMA }}
        </span>
    </span>
</span>
