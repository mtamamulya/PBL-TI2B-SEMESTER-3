<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $isAdmin = $user && (
            ($user->role->nama_role ?? $user->role ?? '') === 'admin'
            || (int) ($user->id_role ?? 0) === 1
        );

        if (! $isAdmin) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses. Hanya Admin yang diizinkan melakukan aksi ini.',
                ], 403);
            }

            abort(403, 'Anda tidak memiliki akses.');
        }

        return $next($request);
    }
}