<?php

namespace App\Http\Controllers;

use App\BackendKel1\KelompokBidang\KelompokBidangController as ApiKelompokBidangController;
use App\Models\KelompokBidang;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class KelompokBidangController extends Controller
{
    public function index(Request $request): View
    {
        $prefix = $request->routeIs('kaprodi.*') ? 'kaprodi' : 'admin';
        $layout = $prefix === 'kaprodi' ? 'layouts.kaprodi' : 'layouts.admin';

        $rows = KelompokBidang::query()
            ->with('rujukan:id_kelompok_bidang,nama_kelompok_bidang')
            ->withCount($this->hitungan())
            ->orderBy('nama_kelompok_bidang')
            ->get()
            ->map(fn ($k) => ApiKelompokBidangController::bentuk($k))
            ->all();

        return view('admin.kelompok-bidang', [
            'prefix' => $prefix,
            'layout' => $layout,
            'rows' => $rows,
            'apiError' => null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        try {
            $k = KelompokBidang::create([
                'nama_kelompok_bidang' => $data['nama_kelompok_bidang'],
                'id_rujukan' => $data['id_rujukan'] ?? null,
            ]);
        } catch (QueryException) {
            return response()->json(['message' => 'Kelompok bidang gagal disimpan. Periksa isian form.'], 409);
        }

        $k = $this->cari($k->getKey());

        return response()->json([
            'data' => ApiKelompokBidangController::bentuk($k),
            'message' => 'Kelompok bidang dibuat.',
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $k = KelompokBidang::query()->find($id);
        if (! $k) {
            return response()->json(['message' => 'Kelompok bidang tidak ditemukan.'], 404);
        }

        $data = $this->validasi($request, $id);

        try {
            $k->update([
                'nama_kelompok_bidang' => $data['nama_kelompok_bidang'],
                'id_rujukan' => $data['id_rujukan'] ?? null,
            ]);
        } catch (QueryException) {
            return response()->json(['message' => 'Kelompok bidang gagal diperbarui. Periksa isian form.'], 409);
        }

        $k = $this->cari($id);

        return response()->json([
            'data' => ApiKelompokBidangController::bentuk($k),
            'message' => 'Kelompok bidang diperbarui.',
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $k = KelompokBidang::query()->find($id);
        if (! $k) {
            return response()->json(['message' => 'Kelompok bidang tidak ditemukan.'], 404);
        }

        if ($this->adaKolomKegiatan() && $k->kegiatan()->exists()) {
            return response()->json(['message' => 'Kelompok bidang ini sudah dipakai oleh kegiatan, jadi tidak bisa dihapus.'], 409);
        }

        if ($k->turunan()->exists()) {
            return response()->json(['message' => 'Kelompok bidang ini masih menjadi rujukan kelompok bidang lain. Pindahkan atau hapus turunannya dulu.'], 409);
        }

        try {
            $k->delete();
        } catch (QueryException) {
            return response()->json(['message' => 'Kelompok bidang ini masih dipakai data lain, jadi tidak bisa dihapus.'], 409);
        }

        return response()->json([
            'data' => null,
            'message' => 'Kelompok bidang dihapus.',
        ]);
    }

    private function hitungan(): array
    {
        return $this->adaKolomKegiatan() ? ['turunan', 'kegiatan'] : ['turunan'];
    }

    private function adaKolomKegiatan(): bool
    {
        static $ada = null;

        return $ada ??= Schema::hasColumn('kegiatan', 'id_kelompok_bidang');
    }

    private function cari(int $id): ?KelompokBidang
    {
        return KelompokBidang::query()
            ->with('rujukan:id_kelompok_bidang,nama_kelompok_bidang')
            ->withCount($this->hitungan())
            ->find($id);
    }

    private function validasi(Request $request, ?int $id = null): array
    {
        if (is_string($request->input('nama_kelompok_bidang'))) {
            $request->merge(['nama_kelompok_bidang' => trim($request->input('nama_kelompok_bidang'))]);
        }

        $data = $request->validate([
            'nama_kelompok_bidang' => [
                'required', 'string', 'max:100',
                Rule::unique('kelompok_bidang', 'nama_kelompok_bidang')->ignore($id, 'id_kelompok_bidang'),
            ],
            'id_rujukan' => ['nullable', 'integer', Rule::exists('kelompok_bidang', 'id_kelompok_bidang')],
        ], [
            'nama_kelompok_bidang.required' => 'Nama kelompok bidang wajib diisi.',
            'nama_kelompok_bidang.max' => 'Nama kelompok bidang maksimal 100 karakter.',
            'nama_kelompok_bidang.unique' => 'Nama kelompok bidang sudah ada.',
            'id_rujukan.exists' => 'Kelompok bidang rujukan tidak ditemukan.',
        ]);

        if ($id !== null && isset($data['id_rujukan']) && $this->membuatSiklus($id, (int) $data['id_rujukan'])) {
            throw ValidationException::withMessages([
                'id_rujukan' => 'Rujukan tidak valid: kelompok bidang tidak boleh merujuk ke dirinya sendiri atau ke turunannya.',
            ]);
        }

        return $data;
    }

    private function membuatSiklus(int $id, int $idRujukan): bool
    {
        $kini = $idRujukan;
        $dilihat = [];

        while ($kini !== null) {
            if ($kini === $id || isset($dilihat[$kini])) {
                return true;
            }

            $dilihat[$kini] = true;
            $induk = KelompokBidang::query()->whereKey($kini)->value('id_rujukan');
            $kini = $induk === null ? null : (int) $induk;
        }

        return false;
    }
}
