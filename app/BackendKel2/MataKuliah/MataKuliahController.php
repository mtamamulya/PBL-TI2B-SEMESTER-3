<?php

namespace App\BackendKel2\MataKuliah;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class MataKuliahController extends Controller
{
    // 1. TAB KATALOG
    public function katalogMataKuliah(Request $request)
    {
        // Panggil tanpa parameter filter dulu agar semua data dari Kelompok 1 tampil
        $response = Http::get("http://127.0.0.1:8001/api/v1/mata-kuliah");

        if ($response->successful()) {
            return response()->json([
                'status' => 'success',
                'data' => $response->json()
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Gagal mengambil data dari API Kelompok 1'
        ], 500);
    }

    // 2. TAB SEMESTER BERJALAN
    public function semesterBerjalan(Request $request)
    {
        try {
            // Panggil tanpa parameter filter dulu
            $response = Http::get("http://127.0.0.1:8001/api/v1/mata-kuliah");

            if (!$response->successful()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengambil data mata kuliah dari API Kelompok 1'
                ], 500);
            }

            $mataKuliahApi = $response->json();

            // Data dosen pengampu dari database lokalmu
            $dosenLokal = DB::table('enrollment_kelas_mata_kuliah')
                ->join('dosen', 'enrollment_kelas_mata_kuliah.id_dosen', '=', 'dosen.id_dosen')
                ->select('enrollment_kelas_mata_kuliah.*', 'dosen.*')
                ->get();

            return response()->json([
                'status' => 'success',
                'data_mata_kuliah' => $mataKuliahApi,
                'dosen_pengampu' => $dosenLokal
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()
            ], 500);
        }
    }

    // 3. TAB RIWAYAT MATA KULIAH (Menampilkan semester-semester sebelumnya)
    public function riwayatAkademik(Request $request)
    {
        try {
            $angkatan = $request->input('angkatan', '2024');
            // Riwayat biasanya mencakup semester 1 sampai semester sebelum semester aktif (misal: < 3)
            
            $response = Http::get("http://127.0.0.1:8001/api/v1/mata-kuliah", [
                'angkatan' => $angkatan
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengambil data riwayat dari API Kelompok 1'
                ], 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Data riwayat akademik berhasil dimuat',
                'data' => $response->json()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()
            ], 500);
        }
    }
}