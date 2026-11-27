<?php

namespace App\BackendKel2\MataKuliah;

use App\BackendKel2\Akademik\PeriodeAkademik;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class MataKuliahController extends Controller
{
    private function baseApiUrl(): string
    {
        return rtrim((string) config('services.kelompok1.base_url', 'http://127.0.0.1:8001/api/v1'), '/');
    }

    private function resolveAngkatan(Request $request): string
    {
        $inputAngkatan = $request->input('angkatan');
        if ($inputAngkatan !== null && $inputAngkatan !== '') {
            return (string) $inputAngkatan;
        }

        $user = $request->user();
        $tahunMasuk = $user?->mahasiswa?->tahun_masuk;

        if (! filled($tahunMasuk) && $user) {
            $tahunMasuk = DB::table('mahasiswa')
                ->where('id_user', $user->id_user ?? $user->id)
                ->value('tahun_masuk');
        }

        return (string) (filled($tahunMasuk) ? $tahunMasuk : config('mahasiswa.angkatan', '2024'));
    }

    private function ambilDataMataKuliah(array $query): array
    {
        $response = Http::acceptJson()
            ->timeout(10)
            ->get($this->baseApiUrl().'/mata-kuliah', $query);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'data' => $response->successful() ? ($response->json('data') ?? []) : [],
            'meta' => $response->successful() ? $response->json('meta') : null,
        ];
    }

    // 1. TAB KATALOG / SEMUA MATA KULIAH
    public function katalogMataKuliah(Request $request)
    {
        try {
            $angkatan = $this->resolveAngkatan($request);

            $hasil = $this->ambilDataMataKuliah(array_filter([
                'angkatan' => $angkatan,
                'kode_prodi' => $request->input('kode_prodi'),
                'semester' => $request->input('semester'),
                'q' => $request->input('q'),
                'per_page' => min(max((int) $request->input('per_page', 100), 1), 100),
                'page' => max((int) $request->input('page', 1), 1),
            ], fn ($v) => $v !== null && $v !== ''));

            if (! $hasil['ok']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengambil data dari API Kelompok 1',
                ], $hasil['status'] ?: 500);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil mengambil data katalog mata kuliah',
                'angkatan' => $angkatan,
                'data' => $hasil['data'],
                'meta' => $hasil['meta'],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal tersambung ke server Kelompok 1: '.$e->getMessage(),
            ], 500);
        }
    }

    // 2. TAB SEMESTER BERJALAN
    public function semesterBerjalan(Request $request)
    {
        try {
            $periodeAktif = PeriodeAkademik::sekarang();
            $angkatan = $this->resolveAngkatan($request);
            $semester = $request->input('semester', $periodeAktif->semesterKe($angkatan));
            $tahunAkademik = $request->input('tahun_akademik', $periodeAktif->label());

            $hasil = $this->ambilDataMataKuliah(array_filter([
                'angkatan' => $angkatan,
                'kode_prodi' => $request->input('kode_prodi'),
                'semester' => $semester,
                'tahun_akademik' => $tahunAkademik,
                'per_page' => min(max((int) $request->input('per_page', 100), 1), 100),
                'page' => max((int) $request->input('page', 1), 1),
            ], fn ($v) => $v !== null && $v !== ''));

            if (! $hasil['ok']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengambil data mata kuliah dari API Kelompok 1',
                ], $hasil['status'] ?: 500);
            }

            $mataKuliahApi = is_array($hasil['data']) ? $hasil['data'] : [];
            $metaApi = $hasil['meta'];

            $jumlahMataKuliah = is_array($metaApi) && isset($metaApi['total'])
                ? (int) $metaApi['total']
                : count($mataKuliahApi);

            $totalSks = 0;
            foreach ($mataKuliahApi as $mk) {
                $sksTeori = (int) ($mk['sks_teori'] ?? 0);
                $sksPraktik = (int) ($mk['sks_praktik'] ?? 0);
                $totalSks += ($sksTeori + $sksPraktik);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Data semester berjalan berhasil dimuat',
                'angkatan' => $angkatan,
                'semester' => $semester,
                'periode_akademik' => $tahunAkademik,
                'summary' => [
                    'jumlah_mata_kuliah' => $jumlahMataKuliah,
                    'total_beban_studi' => $totalSks.' SKS',
                    'status_paket' => 'Sudah ditetapkan',
                ],
                'data_mata_kuliah' => $mataKuliahApi,
                'meta' => $metaApi,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan pada server: '.$e->getMessage(),
            ], 500);
        }
    }

    // 3. TAB RIWAYAT MATA KULIAH
    public function riwayatAkademik(Request $request)
    {
        try {
            $angkatan = $this->resolveAngkatan($request);
            $periodeAktif = PeriodeAkademik::sekarang();
            $semesterAktif = $periodeAktif->semesterKe($angkatan);

            $hasil = $this->ambilDataMataKuliah(array_filter([
                'angkatan' => $angkatan,
                'kode_prodi' => $request->input('kode_prodi'),
                'semester' => $request->input('semester'),
                'q' => $request->input('q'),
                'per_page' => min(max((int) $request->input('per_page', 100), 1), 100),
                'page' => max((int) $request->input('page', 1), 1),
            ], fn ($v) => $v !== null && $v !== ''));

            if (! $hasil['ok']) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal mengambil data riwayat dari API Kelompok 1',
                ], $hasil['status'] ?: 500);
            }

            $items = is_array($hasil['data']) ? $hasil['data'] : [];

            if (is_numeric($semesterAktif)) {
                $batasSemester = (int) $semesterAktif;
                $items = array_values(array_filter($items, function ($item) use ($batasSemester) {
                    if (! is_array($item) || ! isset($item['semester']) || ! is_numeric($item['semester'])) {
                        return true;
                    }

                    return (int) $item['semester'] < $batasSemester;
                }));
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Data riwayat akademik berhasil dimuat',
                'angkatan' => $angkatan,
                'periode_aktif' => $periodeAktif->label(),
                'data' => $items,
                'meta' => $hasil['meta'],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan pada server: '.$e->getMessage(),
            ], 500);
        }
    }
}
