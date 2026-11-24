<?php

namespace App\Policies;

use App\Models\Dokumen;
use App\Models\Kegiatan;
use App\Models\User;

class DokumenPolicy
{
    public function viewAny(User $user, Kegiatan $kegiatan): bool
    {
        return $this->dapatMelihatKegiatan($user, $kegiatan);
    }

    public function view(User $user, Dokumen $dokumen): bool
    {
        return $this->dapatMelihatKegiatan($user, $dokumen->kegiatan);
    }

    public function create(User $user, Kegiatan $kegiatan): bool
    {
        return $this->dapatMengelolaKegiatan($user, $kegiatan);
    }

    public function update(User $user, Dokumen $dokumen): bool
    {
        return $this->dapatMengelolaKegiatan($user, $dokumen->kegiatan);
    }

    public function delete(User $user, Dokumen $dokumen): bool
    {
        return $this->dapatMengelolaKegiatan($user, $dokumen->kegiatan);
    }

    public function viewHasil(User $user, Kegiatan $kegiatan): bool
    {
        return $this->dapatMengelolaKegiatan($user, $kegiatan);
    }

    public function deleteMahasiswa(User $user, Dokumen $dokumen): bool
    {
        return $this->dapatMengelolaKegiatan($user, $dokumen->kegiatan);
    }

    private function dapatMelihatKegiatan(User $user, ?Kegiatan $kegiatan): bool
    {
        if (! $kegiatan) {
            return false;
        }

        return in_array($this->role($user), ['admin', 'kaprodi'], true) || $this->dapatMengelolaKegiatan($user, $kegiatan);
    }

    private function dapatMengelolaKegiatan(User $user, Kegiatan $kegiatan): bool
    {
        if (! in_array($this->role($user), ['mahasiswa', 'dosen'], true)) {
            return false;
        }

        return Kegiatan::query()->untukUser($user)->whereKey($kegiatan->getKey())->exists();
    }

    private function role(User $user): string
    {
        return mb_strtolower((string) $user->role?->nama_role);
    }
}
