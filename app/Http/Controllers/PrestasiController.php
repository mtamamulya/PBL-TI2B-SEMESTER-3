<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Prestasi;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PrestasiController extends Controller
{
    public function index()
    {
        $prestasi = Prestasi::latest()->paginate(10);

        return response()->json([
            'success' => true,
            'message' => 'Daftar prestasi berhasil diambil',
            'data' => $prestasi->items(),
            'meta' => [
                'current_page' => $prestasi->currentPage(),
                'per_page' => $prestasi->perPage(),
                'total' => $prestasi->total(),
                'last_page' => $prestasi->lastPage(),
            ],
        ], 200);
    }

    public function show(int $id): JsonResponse
    {
        $m = Prestasi::query()->find($id);

        return $m ? ApiResponse::ok(self::bentuk($m)) : ApiResponse::gagal('Prestasi tidak ditemukan');
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sumber' => 'required|string',
            'nama_prestasi' => 'required|string|max:255',
            'penyelenggara' => 'required|string|max:255',
            'tingkat' => 'required|string',
            'jenis' => 'required|string',
            'tanggal_mulai' => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'capaian' => 'required|string|max:255',
            'kategori' => 'nullable|string|max:255',
            'bentuk' => 'required|string',
            'lokasi' => 'nullable|string|max:255',
            'deskripsi' => 'nullable|string|max:1000',
            'anggota' => 'nullable|array',
            'dokumen' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $prestasi = Prestasi::create([
                'id_user' => auth()->id() ?? 1,
                'created_by' => auth()->id() ?? 1,
                'nama_lomba' => $request->nama_prestasi,
                'penyelenggara' => $request->penyelenggara,
                'tingkat_kejuaraan' => $request->tingkat,
                'jenis_prestasi' => $request->jenis,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'capaian' => $request->capaian,
                'bidang_lomba' => $request->kategori,
                'bentuk_partisipasi' => strtolower($request->bentuk),
                'lokasi' => $request->lokasi ?? 'Semarang',
                'deskripsi' => $request->deskripsi,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Prestasi berhasil disimpan ke database',
                'data' => self::bentuk($prestasi),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan pada server: '.$e->getMessage(),
            ], 500);
        }
    }

    public static function bentuk(Prestasi $k): array
    {
        return [
            'id_prestasi' => $k->id_prestasi ?? $k->id,
            'id_prodi' => $k->id_prodi ?? null,
            'nama_prestasi' => $k->nama_lomba ?? null,
            'penyelenggara' => $k->penyelenggara ?? null,
            'tingkat' => $k->tingkat_kejuaraan ?? null,
            'jenis_prestasi' => $k->jenis_prestasi,
            'bidang_lomba' => $k->bidang_lomba,
            'capaian' => $k->capaian,
            'lokasi' => $k->lokasi ?? null,
            'deskripsi' => $k->deskripsi ?? null,
            'catatan' => $k->getAttribute('catatan'),
            'created_at' => $k->getAttribute('created_at'),
            'updated_at' => $k->getAttribute('updated_at'),
        ];
    }
}
