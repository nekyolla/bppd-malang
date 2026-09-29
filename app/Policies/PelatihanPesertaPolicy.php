<?php

namespace App\Policies;

use App\Enums\Peran;
use App\Models\PelatihanPeserta;
use App\Models\User;

/**
 * Pendaftaran dan berkasnya hanya untuk akun internal yang aktif (PRD §10).
 */
class PelatihanPesertaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_aktif && $user->hasAnyRole(Peran::panelAdmin());
    }

    /**
     * Termasuk membuka berkas KTP, pas foto, dan surat tugas lewat BerkasController.
     */
    public function view(User $user, PelatihanPeserta $pelatihanPeserta): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Pendaftaran hanya dibuat lewat form registrasi publik.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Status dan data pendaftaran berubah lewat aksi khusus (verifikasi, dll.), bukan form ubah.
     */
    public function update(User $user, PelatihanPeserta $pelatihanPeserta): bool
    {
        return false;
    }

    public function delete(User $user, PelatihanPeserta $pelatihanPeserta): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function verifikasi(User $user, PelatihanPeserta $pelatihanPeserta): bool
    {
        return $this->viewAny($user);
    }
}
