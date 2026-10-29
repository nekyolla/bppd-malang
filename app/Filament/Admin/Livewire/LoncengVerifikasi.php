<?php

namespace App\Filament\Admin\Livewire;

use App\Models\PelatihanPeserta;
use App\Services\PelatihanService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Lonceng di topbar: jumlah pendaftar menunggu verifikasi, dirinci per pelatihan.
 * Dihitung langsung dari pendaftaran (tanpa tabel notifikasi) dan diperbarui berkala.
 */
class LoncengVerifikasi extends Component
{
    public const BATAS = 8;

    public function render(): View
    {
        $pelatihan = Gate::allows('viewAny', PelatihanPeserta::class)
            ? app(PelatihanService::class)->daftarMenunggu()
            : collect();

        return view('filament.admin.livewire.lonceng-verifikasi', [
            'pelatihan' => $pelatihan->take(self::BATAS),
            'total' => (int) $pelatihan->sum('jumlah_menunggu'),
            'lainnya' => max(0, $pelatihan->count() - self::BATAS),
        ]);
    }
}
