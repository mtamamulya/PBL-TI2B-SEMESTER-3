<?php

namespace App\BackendKel1\TahunAkademik;

use App\Http\Controllers\Controller;
use App\Models\TahunAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TahunAkademikController extends Controller
{
    /**
     * GET /api/v1/tahun-akademik
     * Publik / Read-Only (bisa dibaca Admin, Kaprodi, dan modul Mahasiswa tanpa token).
     */
    public function index(): JsonResponse
    {
        $items = TahunAkademik::query()
            ->orderByDesc('status')
            ->orderByDesc('id_tahun_akademik')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar tahun akademik berhasil diambil.',
            'data' => $items,
        ]);
    }

    /**
     * GET /api/v1/tahun-akademik/aktif
     * Publik / Read-Only (mengambil 1 tahun akademik yang berstatus aktif saat ini).
     */
    public function aktif(): JsonResponse
    {
        $aktif = TahunAkademik::where('status', true)->first();

        if (! $aktif) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada tahun akademik yang aktif.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tahun akademik aktif berhasil diambil.',
            'data' => $aktif,
        ]);
    }

    /**
     * POST /api/v1/tahun-akademik
     * Khusus Admin (Kaprodi dan role lain hanya read-only).
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->isAdmin($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin. Hanya Admin yang dapat menambahkan tahun akademik.',
            ], 403);
        }

        $data = $request->validate([
            'semester' => ['required', Rule::in(['Ganjil', 'Genap'])],
            'tahun_ajaran' => ['required', 'regex:/^\d{4}\/\d{4}$/'],
        ], [
            'semester.required' => 'Semester wajib dipilih (Ganjil atau Genap).',
            'semester.in' => 'Semester hanya boleh Ganjil atau Genap.',
            'tahun_ajaran.required' => 'Tahun ajaran wajib diisi.',
            'tahun_ajaran.regex' => 'Format tahun ajaran harus YYYY/YYYY (contoh: 2026/2027).',
        ]);

        [$tahunAwal, $tahunAkhir] = array_map('intval', explode('/', $data['tahun_ajaran']));
        if ($tahunAkhir !== $tahunAwal + 1) {
            return response()->json([
                'success' => false,
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => [
                    'tahun_ajaran' => ['Tahun kedua harus selisih 1 tahun dari tahun pertama (mis. 2026/2027).'],
                ],
            ], 422);
        }

        $label = "{$data['semester']} {$data['tahun_ajaran']}";

        if (TahunAkademik::where('tahun_akademik', $label)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Data yang dikirim tidak valid.',
                'errors' => [
                    'tahun_ajaran' => ["Tahun akademik {$label} sudah terdaftar."],
                ],
            ], 422);
        }

        $record = TahunAkademik::buatDanAktifkan($label);

        return response()->json([
            'success' => true,
            'message' => "Tahun akademik {$label} berhasil ditambahkan dan menjadi satu-satunya yang aktif.",
            'data' => $record,
        ], 201);
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        $roleName = strtolower((string) ($user->role->nama_role ?? $user->role ?? ''));
        $roleId = (int) ($user->id_role ?? 0);

        return $roleName === 'admin' || $roleId === 1;
    }
}
