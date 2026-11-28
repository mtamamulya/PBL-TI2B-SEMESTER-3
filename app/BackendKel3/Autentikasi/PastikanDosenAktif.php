<?php

namespace App\BackendKel3\Autentikasi;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PastikanDosenAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->status) {
            return ApiResponse::gagal('Akun tidak aktif. Hubungi admin.', 403);
        }

        if (strtolower($user->role?->nama_role ?? '') !== 'dosen') {
            if ($request->isMethod('GET') && ! $request->is('api/*') && ! $request->expectsJson()) {
                return redirect()->route('dashboard');
            }

            return ApiResponse::gagal('Akses ini khusus dosen.', 403);
        }

        return $next($request);
    }
}
