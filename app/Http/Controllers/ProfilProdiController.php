<?php

namespace App\Http\Controllers;

use App\Support\ProfilProdi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Halaman Profil Prodi (admin + kaprodi, keduanya boleh mengubah).
 *
 * FASE 1 (sekarang): MODE DUMMY. Data disimpan di session, bentuknya mengikuti kontrak
 * App\Support\ProfilProdi. Mode dummy aktif secara bawaan; matikan di Fase 5 dengan
 * PROFIL_DUMMY=false di .env (setelah API Fase 4 ada dan cabang API di bawah diisi).
 */
class ProfilProdiController extends Controller
{
    private const SESSION = 'profil_prodi_dummy';

    public function index(Request $request)
    {
        [$prefix, $layout] = $this->peran($request);
        $profil = $this->ambil($request);
        $dummy = $this->dummy();

        return view('admin.profil-prodi', compact('profil', 'prefix', 'layout', 'dummy'));
    }

    public function update(Request $request): JsonResponse
    {
        $input = ProfilProdi::bersihkan($request->all());
        $data = Validator::make($input, ProfilProdi::aturan(), ProfilProdi::pesan())->validate();

        if (! $this->dummy()) {
            // FASE 5: ApiClient::pengguna()->put('/prodi/profil', $data) lalu kembalikan $hasil['data'].
            abort(501, 'API Profil Prodi belum tersedia (Fase 4).');
        }

        $baru = array_merge($this->ambil($request), $data);
        $request->session()->put(self::SESSION, $baru);

        return response()->json(['ok' => true, 'data' => $baru]);
    }

    /** @return array<string, mixed> data sesuai kontrak */
    private function ambil(Request $request): array
    {
        if (! $this->dummy()) {
            // FASE 5: ApiClient::publik()->get('/prodi/profil')['data'] ?? ProfilProdi::bawaan()
            abort(501, 'API Profil Prodi belum tersedia (Fase 4).');
        }

        return array_merge(ProfilProdi::bawaan(), (array) $request->session()->get(self::SESSION, []));
    }

    private function dummy(): bool
    {
        return filter_var(env('PROFIL_DUMMY', true), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array{0:string,1:string} */
    private function peran(Request $request): array
    {
        $kaprodi = $request->routeIs('kaprodi.*');

        return [$kaprodi ? 'kaprodi' : 'admin', $kaprodi ? 'layouts.kaprodi' : 'layouts.admin'];
    }
}
