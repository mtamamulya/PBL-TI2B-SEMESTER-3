<?php

namespace App\BackendKel1\Kelas;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KelasController extends Controller
{
    /**
     * GET /api/v1/kelas
     * Daftar rombel/kelas dari enrollment_tingkat_kelas (Publik / lintas kelompok).
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->baseQuery();

        if ($request->boolean('aktif')) {
            $query->where('ta.status', true);
        }

        if ($request->filled('id_tahun_akademik')) {
            $query->where('etk.id_tahun_akademik', (int) $request->query('id_tahun_akademik'));
        }

        if ($request->filled('id_prodi')) {
            $query->where('etk.id_prodi', (int) $request->query('id_prodi'));
        }

        if ($request->filled('id_tingkat')) {
            $query->where('etk.id_tingkat', (int) $request->query('id_tingkat'));
        }

        $rows = $query
            ->orderByDesc('ta.status')
            ->orderByDesc('ta.id_tahun_akademik')
            ->orderBy('p.kode_prodi')
            ->orderBy('t.tingkat')
            ->orderBy('k.jenis_kelas')
            ->get()
            ->map(fn ($row) => $this->formatRow($row));

        if ($request->filled('q')) {
            $q = mb_strtolower(trim((string) $request->query('q')));
            $rows = $rows->filter(fn (array $item) => str_contains(mb_strtolower($item['nama_kelas']), $q)
                || str_contains(mb_strtolower($item['nama_prodi']), $q)
                || str_contains(mb_strtolower($item['tahun_akademik']), $q)
            )->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Daftar kelas berhasil diambil.',
            'meta' => [
                'total' => $rows->count(),
            ],
            'data' => $rows->values(),
        ], 200);
    }

    /**
     * GET /api/v1/kelas/{id}
     * Detail satu rombel/kelas berdasarkan id_enrollment_tingkat_kelas.
     */
    public function show(string $id): JsonResponse
    {
        $row = $this->baseQuery()
            ->where('etk.id_enrollment_tingkat_kelas', (int) $id)
            ->first();

        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Data kelas tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail kelas berhasil diambil.',
            'data' => $this->formatRow($row),
        ], 200);
    }

    private function baseQuery()
    {
        return DB::table('enrollment_tingkat_kelas as etk')
            ->join('prodi as p', 'p.id_prodi', '=', 'etk.id_prodi')
            ->join('tingkat as t', 't.id_tingkat', '=', 'etk.id_tingkat')
            ->join('kelas as k', 'k.id_kelas', '=', 'etk.id_kelas')
            ->join('tahun_akademik as ta', 'ta.id_tahun_akademik', '=', 'etk.id_tahun_akademik')
            ->select([
                'etk.id_enrollment_tingkat_kelas',
                'etk.id_prodi',
                'p.kode_prodi',
                'p.nama_prodi',
                'p.jenjang',
                'etk.id_tingkat',
                't.tingkat',
                't.keterangan as keterangan_tingkat',
                'etk.id_kelas',
                'k.jenis_kelas',
                'k.keterangan as keterangan_kelas',
                'etk.id_tahun_akademik',
                'ta.tahun_akademik',
                'ta.status as status_tahun_akademik',
            ]);
    }

    private function formatRow(object $row): array
    {
        $kodeRingkas = str_contains($row->kode_prodi, '-')
            ? substr($row->kode_prodi, strrpos($row->kode_prodi, '-') + 1)
            : $row->kode_prodi;

        return [
            'id_enrollment_tingkat_kelas' => (int) $row->id_enrollment_tingkat_kelas,
            'nama_kelas' => "{$kodeRingkas}-{$row->tingkat}{$row->jenis_kelas}",
            'id_prodi' => (int) $row->id_prodi,
            'kode_prodi' => $row->kode_prodi,
            'nama_prodi' => $row->nama_prodi,
            'jenjang' => $row->jenjang,
            'id_tingkat' => (int) $row->id_tingkat,
            'tingkat' => (int) $row->tingkat,
            'keterangan_tingkat' => $row->keterangan_tingkat,
            'id_kelas' => (int) $row->id_kelas,
            'jenis_kelas' => $row->jenis_kelas,
            'keterangan_kelas' => $row->keterangan_kelas,
            'id_tahun_akademik' => (int) $row->id_tahun_akademik,
            'tahun_akademik' => $row->tahun_akademik,
            'status_tahun_akademik' => (bool) $row->status_tahun_akademik,
        ];
    }
}
