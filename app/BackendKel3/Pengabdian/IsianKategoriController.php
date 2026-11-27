<?php

namespace App\BackendKel3\Pengabdian;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class IsianKategoriController extends Controller
{
    /** GET /dosen/kategori/{id}/isian: definisi form satu kategori. */
    public function index(int $id): JsonResponse
    {
        $kategori = DB::table('kategori')->where('id_kategori', $id)->first();

        if (! $kategori) {
            return response()->json([
                'success' => false,
                'message' => 'Kategori tidak ditemukan',
                'errors' => (object) [],
            ], 404);
        }

        $isian = DB::table('isian_kategori')
            ->where('id_kategori', $id)
            ->orderBy('id_isian_kategori')
            ->get()
            ->map(fn ($i) => [
                'id_isian_kategori' => (int) $i->id_isian_kategori,
                'input' => $i->input,
                'jenis_input' => $i->jenis_input,
                'wajib' => ! in_array($i->aturan, [null, '', 'NULL'], true),
                'aturan' => $i->aturan,
                'jumlah_isian' => $i->jumlah_isian === null ? null : (int) $i->jumlah_isian,
            ])
            ->all();

        return ApiResponse::ok([
            'kategori' => [
                'id_kategori' => (int) $kategori->id_kategori,
                'nama_kategori' => $kategori->nama_kategori,
            ],
            'isian' => $isian,
        ]);
    }
}
