<?php

namespace App\BackendKel1\Skim;

use App\Http\Controllers\Controller;
use App\Models\Skim;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** CRUD /api/v1/skim (master skim penelitian/pengabdian). */
class SkimController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $p = Skim::query()
            ->withCount('kegiatan')
            ->when($request->filled('q'), fn ($q) => $q->where('nama_skim', 'like', '%'.$request->query('q').'%'))
            ->orderBy('nama_skim')
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::paginated($p, $p->getCollection()->map(fn ($item) => self::bentuk($item))->all());
    }

    public function show(int $id): JsonResponse
    {
        $item = Skim::query()->withCount('kegiatan')->find($id);

        return $item ? ApiResponse::ok(self::bentuk($item)) : ApiResponse::gagal('Skim tidak ditemukan.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama_skim' => ['required', 'string', 'max:100', 'unique:skim,nama_skim'],
        ]);

        $item = Skim::query()->create($data);

        return ApiResponse::ok(self::bentuk($item->loadCount('kegiatan')), 'Skim berhasil ditambahkan.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $item = Skim::query()->find($id);

        if (! $item) {
            return ApiResponse::gagal('Skim tidak ditemukan.');
        }

        $data = $request->validate([
            'nama_skim' => [
                'required',
                'string',
                'max:100',
                Rule::unique('skim', 'nama_skim')->ignore($item->id_skim, 'id_skim'),
            ],
        ]);

        $item->update($data);

        return ApiResponse::ok(self::bentuk($item->loadCount('kegiatan')), 'Skim berhasil diperbarui.');
    }

    public function destroy(int $id): JsonResponse
    {
        $item = Skim::query()->find($id);

        if (! $item) {
            return ApiResponse::gagal('Skim tidak ditemukan.');
        }

        if ($item->kegiatan()->exists()) {
            return ApiResponse::gagal('Skim masih dipakai kegiatan dan tidak dapat dihapus.', 409);
        }

        $item->delete();

        return ApiResponse::ok([], 'Skim berhasil dihapus.');
    }

    public static function bentuk(Skim $item): array
    {
        return [
            'id_skim' => $item->id_skim,
            'nama_skim' => $item->nama_skim,
            'total_kegiatan' => (int) ($item->kegiatan_count ?? 0),
        ];
    }
}
