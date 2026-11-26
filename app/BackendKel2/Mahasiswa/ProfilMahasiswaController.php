<?php

namespace App\BackendKel2\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiClient;
use App\Support\ApiException;
use App\Support\NimMahasiswa;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfilMahasiswaController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $user->loadMissing('mahasiswa');
        $mahasiswa = $user->mahasiswa;
        abort_unless($mahasiswa, 404, 'Data profil mahasiswa belum terhubung dengan akun ini.');

        $programStudi = DB::table('enrollment_mahasiswa_tingkat_kelas as emtk')
            ->join('enrollment_tingkat_kelas as etk', 'etk.id_enrollment_tingkat_kelas', '=', 'emtk.id_enrollment_tingkat_kelas')
            ->join('prodi as p', 'p.id_prodi', '=', 'etk.id_prodi')
            ->join('tahun_akademik as ta', 'ta.id_tahun_akademik', '=', 'etk.id_tahun_akademik')
            ->where('emtk.id_mahasiswa', $mahasiswa->getKey())
            ->orderByDesc('ta.status')
            ->orderByDesc('ta.id_tahun_akademik')
            ->first(['p.nama_prodi', 'p.jenjang']);

        if ($programStudi === null && ($nimData = NimMahasiswa::parse((string) $mahasiswa->nim))) {
            $programStudi = DB::table('prodi')
                ->where('kode_prodi', config('admin.prodi.kode', 'TRK'))
                ->first(['nama_prodi', 'jenjang'])
                ?? (object) [
                    'nama_prodi' => config('admin.prodi.nama'),
                    'jenjang' => $nimData['jenjang'],
                ];
        }

        return view('mahasiswa.mahasiswa.profil', [
            'mahasiswa' => (object) [
                'id_user' => $user->getKey(),
                'nama' => $mahasiswa->nama,
                'nim' => $mahasiswa->nim,
                'angkatan' => $mahasiswa->tahun_masuk,
                'email' => $user->email,
                'prodi' => $programStudi?->nama_prodi ?? 'Belum ditempatkan',
                'jenjang' => $programStudi?->jenjang,
                'status' => $mahasiswa->status_mahasiswa ?? 'Belum diatur',
            ],
        ]);
    }

    public function kirimOtp(): JsonResponse
    {
        return $this->proxy(fn () => ApiClient::pengguna()->post('/profil/ganti-sandi/kirim-otp'));
    }

    public function verifikasi(Request $request): JsonResponse
    {
        $data = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        return $this->proxy(fn () => ApiClient::pengguna()->post('/profil/ganti-sandi/verifikasi', $data));
    }

    public function simpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'otp_id' => ['required', 'integer', 'min:1'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        try {
            $hasil = ApiClient::pengguna()->post('/profil/ganti-sandi/simpan', $data);
        } catch (ApiException $e) {
            return $this->apiError($e);
        }

        $tokenBaru = $hasil['data']['token'] ?? null;
        if (! is_string($tokenBaru) || $tokenBaru === '') {
            return response()->json([
                'success' => false,
                'message' => 'API tidak mengembalikan token sesi baru. Silakan masuk kembali.',
            ], 502);
        }

        $request->session()->put('api_token', $tokenBaru);
        unset($hasil['data']['token']);

        return response()->json($hasil);
    }

    private function proxy(callable $request): JsonResponse
    {
        try {
            return response()->json($request());
        } catch (ApiException $e) {
            return $this->apiError($e);
        }
    }

    private function apiError(ApiException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
            'errors' => $e->errors,
        ], $e->getStatusCode());
    }
}
