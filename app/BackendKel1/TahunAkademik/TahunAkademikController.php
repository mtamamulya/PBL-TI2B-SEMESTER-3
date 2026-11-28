<?php

namespace App\BackendKel1\TahunAkademik;

use App\Http\Controllers\Controller;
use App\Models\TahunAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TahunAkademikController extends Controller
{
    /**
     * GET /api/v1/tahun-akademik
     */
    public function index(): JsonResponse
    {
        $rows = TahunAkademik::query()
            ->orderByDesc('status')
            ->orderByDesc('id_tahun_akademik')
            ->get()
            ->map(fn (TahunAkademik $ta) => $this->formatItem($ta))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'Data tahun akademik berhasil diambil.',
            'meta' => [
                'total' => count($rows),
            ],
            'data' => $rows,
        ], 200);
    }

    /**
     * GET /api/v1/tahun-akademik/aktif
     */
    public function aktif(): JsonResponse
    {
        $aktif = TahunAkademik::query()
            ->where('status', true)
            ->orderByDesc('id_tahun_akademik')
            ->first();

        if (! $aktif) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada tahun akademik yang aktif.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tahun akademik aktif berhasil diambil.',
            'data' => $this->formatItem($aktif),
        ], 200);
    }

    /**
     * POST /api/v1/tahun-akademik (Khusus Admin)
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $isAdmin = ($user?->role?->nama_role ?? $user?->role ?? '') === 'admin'
            || (int) ($user?->id_role ?? 0) === 1;

        if (! $isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Hanya Admin yang dapat menambahkan tahun akademik.',
            ], 403);
        }

        $validated = $request->validate([
            'semester' => ['required', Rule::in(['Ganjil', 'Genap'])],
            'tahun_ajaran' => [
                'bail',
                'required',
                'regex:/^\d{4}\/\d{4}$/',
                function (string $attr, mixed $value, \Closure $fail) {
                    [$awal, $akhir] = array_map('intval', explode('/', (string) $value));
                    if ($akhir !== $awal + 1) {
                        $fail('Tahun ajaran harus berurutan 1 tahun (contoh: 2027/2028).');
                    }
                },
            ],
        ], [
            'semester.required' => 'Semester wajib dipilih.',
            'semester.in' => 'Semester harus Ganjil atau Genap.',
            'tahun_ajaran.required' => 'Tahun ajaran wajib diisi.',
            'tahun_ajaran.regex' => 'Format tahun ajaran harus YYYY/YYYY (contoh: 2027/2028).',
        ]);

        $label = "{$validated['semester']} {$validated['tahun_ajaran']}";

        if (TahunAkademik::where('tahun_akademik', $label)->exists()) {
            return response()->json([
                'success' => false,
                'message' => "Tahun akademik {$label} sudah terdaftar.",
                'errors' => [
                    'tahun_ajaran' => ["Tahun akademik {$label} sudah terdaftar."],
                ],
            ], 422);
        }

        $baru = DB::transaction(function () use ($label) {
            TahunAkademik::query()->update(['status' => false]);

            return TahunAkademik::create([
                'tahun_akademik' => $label,
                'status' => true,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "Tahun akademik {$label} berhasil ditambahkan dan diaktifkan.",
            'data' => $this->formatItem($baru),
        ], 201);
    }

    private function formatItem(TahunAkademik $ta): array
    {
        return [
            'id_tahun_akademik' => (int) $ta->id_tahun_akademik,
            'tahun_akademik' => $ta->tahun_akademik,
            'status' => (bool) $ta->status,
        ];
    }
}
