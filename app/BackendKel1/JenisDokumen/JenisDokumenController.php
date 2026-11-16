<?php

namespace App\BackendKel1\JenisDokumen;

use App\Http\Controllers\Controller;
use App\Models\JenisDokumen;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CRUD /api/v1/jenis-dokumen */
class JenisDokumenController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $p = JenisDokumen::query()
            ->when($request->filled('q'), fn ($q) => $q->where('nama_jenis_dokumen', 'like', '%'.$request->query('q').'%'))
            ->orderByDesc('id_jenis_dokumen')
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::paginated($p, $p->getCollection()->map(fn ($item) => self::bentuk($item))->all());
    }

    public function show(int $id): JsonResponse
    {
        $item = JenisDokumen::query()->find($id);

        return $item ? ApiResponse::ok(self::bentuk($item)) : ApiResponse::gagal('Jenis dokumen tidak ditemukan');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama_jenis_dokumen' => ['required', 'string', 'max:100', 'unique:jenis_dokumen,nama_jenis_dokumen'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $item = JenisDokumen::query()->create($data);

        return ApiResponse::ok(self::bentuk($item));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = JenisDokumen::query()->find($id);

        if (! $item) {
            return ApiResponse::gagal('Jenis dokumen tidak ditemukan');
        }

        $data = $request->validate([
            'nama_jenis_dokumen' => [
                'required',
                'string',
                'max:100',
                Rule::unique('jenis_dokumen', 'nama_jenis_dokumen')->ignore($item->id_jenis_dokumen, 'id_jenis_dokumen'),
            ],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $item->update($data);

        return ApiResponse::ok(self::bentuk($item));
    }

    public function destroy(int $id): JsonResponse
    {
        $item = JenisDokumen::query()->find($id);

        if (! $item) {
            return ApiResponse::gagal('Jenis dokumen tidak ditemukan');
        }

        if ($item->dokumen()->exists() || $item->buktiPrestasi()->exists()) {
            return ApiResponse::gagal('Jenis dokumen masih digunakan dan tidak dapat dihapus');
        }

        $item->delete();

        return ApiResponse::ok([]);
    }

    public static function bentuk(JenisDokumen $item): array
    {
        return [
            'id_jenis_dokumen' => $item->id_jenis_dokumen,
            'nama_jenis_dokumen' => $item->nama_jenis_dokumen,
            'keterangan' => $item->keterangan,
        ];
    }
}
