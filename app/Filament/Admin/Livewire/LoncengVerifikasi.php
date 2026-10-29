<?php

namespace App\Filament\Admin\Livewire;

use App\Models\PelatihanPeserta;
use App\Services\PelatihanService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Lonceng di topbar: jumlah pendaftar menunggu verifikasi, dirinci per pelatihan.
 * Dihitung langsung dari pendaftaran (tanpa tabel notifikasi). Diperbarui seketika setelah aksi
 * admin yang mengubah status (event PERBARUI), dan berkala untuk pendaftar baru dari form publik.
 */
class LoncengVerifikasi extends Component
{
    public const NAMA = 'lonceng-verifikasi';

    public const BATAS = 8;

    public const PERBARUI = 'lonceng-verifikasi-perbarui';

    /**
     * Dipanggil di after() aksi pendaftaran agar angka lonceng langsung berubah.
     */
    public static function perbarui(Component $livewire): void
    {
        $livewire->dispatch(self::PERBARUI);
    }

    /**
     * Cukup memicu render ulang.
     */
    #[On(self::PERBARUI)]
    public function segarkan(): void {}

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
