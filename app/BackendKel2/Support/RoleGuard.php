<?php

namespace App\BackendKel2\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class RoleGuard
{
    public static function ensure(User $user, array $allowedRoles): void
    {
        $role = mb_strtolower((string) $user->role?->nama_role);
        $allowedRoles = array_map(mb_strtolower(...), $allowedRoles);

        if (! in_array($role, $allowedRoles, true)) {
            throw new AuthorizationException('Anda tidak memiliki akses ke fitur ini.');
        }
    }
}
