<?php

namespace App\BackendKel1\Autentikasi;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga peran untuk route API (dipasang setelah auth:sanctum).
 * Pemakaian: ->middleware(['auth:sanctum', PeranApi::class.':1,2'])  (1 admin, 2 kaprodi, 3 dosen, 4 mahasiswa).
 */
class PeranApi
{
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::gagal('Belum login atau token tidak valid.', 401);
        }
        if (! $user->status) {
            return ApiResponse::gagal('Akun Anda dinonaktifkan. Hubungi admin.', 403);
        }
        if (! in_array((int) $user->id_role, array_map('intval', $peran), true)) {
            return ApiResponse::gagal('Peran Anda tidak diizinkan melakukan aksi ini.', 403);
        }

        return $next($request);
    }
}
