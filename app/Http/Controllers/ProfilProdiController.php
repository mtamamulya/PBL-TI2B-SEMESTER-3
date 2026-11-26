<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use App\Support\ApiException;
use App\Support\ProfilProdi;
use App\Support\ProfilProdiLanding;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

/**
 * Halaman Profil Prodi (admin + kaprodi, keduanya boleh mengubah).
 * Data lewat REST API /api/v1/prodi/profil (app/BackendKel1/ProfilProdi).
 *
 * MODE DUMMY (hanya untuk pengembangan tanpa API): PROFIL_DUMMY=true di .env.
 * Data dummy disimpan di session. Bawaannya mati; tidak pernah aktif saat tes.
 */
class ProfilProdiController extends Controller
{
    private const SESSION = 'profil_prodi_dummy';

    public function index(Request $request)
    {
        [$prefix, $layout] = $this->peran($request);
        $profil = $this->ambil($request);
        $dummy = ProfilProdi::modeDummy();

        return view('admin.profil-prodi', compact('profil', 'prefix', 'layout', 'dummy'));
    }

    public function update(Request $request): JsonResponse
    {
        $input = ProfilProdi::bersihkan($request->all());
        $data = Validator::make($input, ProfilProdi::aturan(), ProfilProdi::pesan())->validate();

        if (ProfilProdi::modeDummy()) {
            $baru = array_merge($this->ambil($request), $data);
            $request->session()->put(self::SESSION, $baru);

            return response()->json(['ok' => true, 'data' => $baru]);
        }

        try {
            $hasil = ApiClient::pengguna()->put('/prodi/profil', $data);
        } catch (ApiException $e) {
            return $this->responGagalApi($e);
        }

        Cache::forget(ProfilProdiLanding::CACHE_KEY);
        Cache::forget(ProfilProdiLanding::CACHE_GAGAL);

        return response()->json(['ok' => true, 'data' => $this->lengkapi($hasil['data'] ?? [])]);
    }

    /** @return array<string, mixed> data sesuai kontrak (semua kunci selalu ada) */
    private function ambil(Request $request): array
    {
        if (ProfilProdi::modeDummy()) {
            return array_merge(ProfilProdi::bawaan(), (array) $request->session()->get(self::SESSION, []));
        }

        $hasil = ApiClient::publik()->get('/prodi/profil');

        return $this->lengkapi($hasil['data'] ?? []);
    }

    /** Pastikan semua kunci kontrak ada (kosong, bukan data contoh). */
    private function lengkapi(array $data): array
    {
        $kosong = array_fill_keys(ProfilProdi::KUNCI, null);
        $kosong['misi'] = $kosong['tujuan'] = $kosong['profil_lulusan'] = [];

        return array_merge($kosong, $data);
    }

    private function responGagalApi(ApiException $e): JsonResponse
    {
        $status = method_exists($e, 'getStatusCode')
            ? $e->getStatusCode()
            : ($e->status ?? ($e->getCode() ?: 422));

        if ($status < 400 || $status > 599) {
            $status = 422;
        }

        return response()->json(['message' => $e->getMessage()], $status);
    }

    /** @return array{0:string,1:string} */
    private function peran(Request $request): array
    {
        $kaprodi = $request->routeIs('kaprodi.*');

        return [$kaprodi ? 'kaprodi' : 'admin', $kaprodi ? 'layouts.kaprodi' : 'layouts.admin'];
    }
}
