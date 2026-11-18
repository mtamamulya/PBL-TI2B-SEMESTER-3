<?php

namespace App\BackendKel2\MataKuliah;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\BackendKel2\Akademik\PeriodeAkademik; // <-- Import class helper

class MataKuliahController extends Controller
{
    // 1. TAB KATALOG / SEMUA MATA KULIAH
    public function katalogMataKuliah(Request $request)
    {
        try {
            // Mengirim parameter filter dan pagination ke API Kelompok 1
            $response = Http::acceptJson()->timeout(10)
                ->get('http://127.0.0.1:8001/api/v1/mata-kuliah', [
                    'angkatan'       => $request->input('angkatan', '2024'),
                    'semester'       => $request->input('semester'),
                    'tingkat'        => $request->input('tingkat'),
                    'tahun_akademik' => $request->input('tahun_akademik'),
                    'q'              => $request->input('q'),
                    'per_page'       => $request->input('per_page', 100),
                    'page'           => $request->input('page', 1),
                ]);

            if ($response->successful()) {
                return response()->json([
                    'status'  => 'success',
                    'message' => 'Berhasil mengambil data katalog mata kuliah',
                    'data'    => $response->json('data'),
                    'meta'    => $response->json('meta')
                ], 200);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengambil data dari API Kelompok 1'
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal tersambung ke server Kelompok 1: ' . $e->getMessage()
            ], 500);
        }
    }

    // 2. TAB SEMESTER BERJALAN
    public function semesterBerjalan(Request $request)
    {
        try {
            // Menggunakan PeriodeAkademik sebagai Single Source of Truth
            $periodeAktif = PeriodeAkademik::sekarang();
            
            // Jika frontend tidak mengirim parameter, gunakan nilai otomatis dari helper
            $angkatan = $request->input('angkatan', '2024');
            $semester = $request->input('semester', $periodeAktif->semesterKe($angkatan));
            $tahunAkademik = $request->input('tahun_akademik', $periodeAktif->label());

            $response = Http::acceptJson()->timeout(10)
                ->get('http://127.0.0.1:8001/api/v1/mata-kuliah', [
                    'angkatan'       => $angkatan,
                    'semester'       => $semester,
                    'tahun_akademik' => $tahunAkademik,
                    'per_page'       => $request->input('per_page', 100),
                    'page'           => $request->input('page', 1),
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Gagal mengambil data mata kuliah dari API Kelompok 1'
                ], 500);
            }

            $mataKuliahApi = $response->json('data') ?? [];
            $metaApi = $response->json('meta');

            // Hitung statistik untuk Kartu Ringkasan (Card Summary)
            $jumlahMataKuliah = count($mataKuliahApi);
            $totalSks = 0;

            foreach ($mataKuliahApi as $mk) {
                $sksTeori   = $mk['sks_teori'] ?? 0;
                $sksPraktik = $mk['sks_praktik'] ?? 0;
                $totalSks  += ($sksTeori + $sksPraktik);
            }

            // Ambil data dosen lokal dengan join berjenjang yang benar sesuai migrasi
            $dosenLokal = DB::table('enrollment_kelas_mata_kuliah')
                ->join('dosen', 'enrollment_kelas_mata_kuliah.id_dosen', '=', 'dosen.id_dosen')
                ->join('enrollment_tingkat_kelas', 'enrollment_kelas_mata_kuliah.id_enrollment_tingkat_kelas', '=', 'enrollment_tingkat_kelas.id_enrollment_tingkat_kelas')
                ->join('kelas', 'enrollment_tingkat_kelas.id_kelas', '=', 'kelas.id_kelas')
                ->select(
                    'enrollment_kelas_mata_kuliah.id_enrollment_kelas_mk',
                    'enrollment_kelas_mata_kuliah.id_enrollment_mk',
                    'dosen.id_dosen',
                    'dosen.nama as nama_dosen',
                    'dosen.nip',
                    'kelas.id_kelas',
                    'kelas.jenis_kelas',
                    'kelas.keterangan as nama_kelas'
                )
                ->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Data semester berjalan berhasil dimuat',
                'periode_akademik' => $periodeAktif->label(),
                'summary' => [
                    'jumlah_mata_kuliah' => $jumlahMataKuliah,
                    'total_beban_studi'  => $totalSks . ' SKS',
                    'status_paket'       => 'Sudah ditetapkan'
                ],
                'data_mata_kuliah' => $mataKuliahApi,
                'meta'             => $metaApi,
                'dosen_pengampu'   => $dosenLokal
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()
            ], 500);
        }
    }

    // 3. TAB RIWAYAT MATA KULIAH
    public function riwayatAkademik(Request $request)
    {
        try {
            $angkatan = $request->input('angkatan', '2024');
            $periodeAktif = PeriodeAkademik::sekarang();
            
            $response = Http::acceptJson()->timeout(10)
                ->get('http://127.0.0.1:8001/api/v1/mata-kuliah', [
                    'angkatan'       => $angkatan,
                    'semester'       => $request->input('semester'),
                    'tingkat'        => $request->input('tingkat'),
                    'tahun_akademik' => $request->input('tahun_akademik'),
                    'q'              => $request->input('q'),
                    'per_page'       => $request->input('per_page', 100),
                    'page'           => $request->input('page', 1),
                ]);

            if (!$response->successful()) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Gagal mengambil data riwayat dari API Kelompok 1'
                ], 500);
            }

            // Ambil data riwayat dosen pengampu dari database lokal
            $dosenLokal = DB::table('enrollment_kelas_mata_kuliah')
                ->join('dosen', 'enrollment_kelas_mata_kuliah.id_dosen', '=', 'dosen.id_dosen')
                ->join('enrollment_tingkat_kelas', 'enrollment_kelas_mata_kuliah.id_enrollment_tingkat_kelas', '=', 'enrollment_tingkat_kelas.id_enrollment_tingkat_kelas')
                ->join('kelas', 'enrollment_tingkat_kelas.id_kelas', '=', 'kelas.id_kelas')
                ->select(
                    'enrollment_kelas_mata_kuliah.id_enrollment_kelas_mk',
                    'enrollment_kelas_mata_kuliah.id_enrollment_mk',
                    'dosen.id_dosen',
                    'dosen.nama as nama_dosen',
                    'dosen.nip',
                    'kelas.id_kelas',
                    'kelas.jenis_kelas',
                    'kelas.keterangan as nama_kelas'
                )
                ->get();

            return response()->json([
                'status'         => 'success',
                'message'        => 'Data riwayat akademik berhasil dimuat',
                'periode_aktif'  => $periodeAktif->label(),
                'data'           => $response->json('data'),
                'meta'           => $response->json('meta'),
                'dosen_pengampu' => $dosenLokal
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan pada server: ' . $e->getMessage()
            ], 500);
        }
    }
}