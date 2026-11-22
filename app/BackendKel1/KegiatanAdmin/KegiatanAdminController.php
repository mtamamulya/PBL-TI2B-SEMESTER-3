<?php

namespace App\BackendKel1\KegiatanAdmin;

use App\Http\Controllers\Controller;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Data Kegiatan (disetujui) untuk admin (lihat+hapus) dan kaprodi (lihat). Rute: PeranApi 1,2. */
class KegiatanAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'jenis' => ['nullable', 'in:dosen,mahasiswa,prodi'],
            'id_kategori' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $hal = KegiatanAdminQuery::dasar($data['jenis'] ?? null, $data['id_kategori'] ?? null, $data['q'] ?? null)
            ->orderByDesc('id_kegiatan')
            ->paginate((int) ($data['per_page'] ?? 100));

        return response()->json([
            'success' => true,
            'message' => 'Daftar kegiatan berhasil diambil',
            'data' => $hal->getCollection()->map(fn (Kegiatan $k) => KegiatanAdminQuery::baris($k))->all(),
            'meta' => [
                'current_page' => $hal->currentPage(),
                'per_page' => $hal->perPage(),
                'total' => $hal->total(),
                'last_page' => $hal->lastPage(),
            ],
        ]);
    }

    /** Angka tab, tahun yang ada, dan daftar kategori (untuk filter). */
    public function ringkasan(): JsonResponse
    {
        $semua = KegiatanAdminQuery::dasar()->get()->map(fn (Kegiatan $k) => KegiatanAdminQuery::baris($k));

        $hitung = ['semua' => $semua->count()];
        foreach (array_keys(KegiatanAdminQuery::ROLE_JENIS) as $j) {
            $hitung[$j] = $semua->where('jenis', $j)->count();
        }

        return ApiResponse::ok([
            'jumlah' => $hitung,
            'tahun' => $semua->pluck('tahun')->filter()->unique()->sort()->values()->all(),
            'kategori' => Kategori::orderBy('nama_kategori')->get(['id_kategori', 'nama_kategori'])->all(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $k = KegiatanAdminQuery::dasar()->find($id);

        return $k
            ? ApiResponse::ok(KegiatanAdminQuery::detail($k))
            : ApiResponse::gagal('Kegiatan tidak ditemukan.', 404);
    }

    /** Tambah kegiatan (admin & kaprodi): langsung berstatus diterima. Lihat KegiatanAdminSimpan untuk aturan pemilik/tab. */
    public function store(Request $request, KegiatanAdminSimpan $simpan): JsonResponse
    {
        $request->merge(KegiatanAdminSimpan::normalisasi($request->input()));
        $data = $request->validate(KegiatanAdminSimpan::aturan());

        $hasil = $simpan->simpan($data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Kegiatan berhasil ditambahkan.',
            'data' => KegiatanAdminQuery::detail(KegiatanAdminQuery::dasar()->findOrFail($hasil['kegiatan']->id_kegiatan)),
            'peringatan' => $hasil['peringatan'],
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ((int) $request->user()?->id_role !== 1) {
            return ApiResponse::gagal('Hanya admin yang dapat menghapus kegiatan.', 403);
        }

        $k = KegiatanAdminQuery::dasar()->find($id);
        if (! $k) {
            return ApiResponse::gagal('Kegiatan tidak ditemukan.', 404);
        }

        try {
            $k->delete();
        } catch (QueryException) {
            return ApiResponse::gagal('Kegiatan masih dipakai data lain (misalnya prestasi), hapus data tersebut dulu.', 409);
        }

        return ApiResponse::ok(null, 'Kegiatan berhasil dihapus.');
    }
}
