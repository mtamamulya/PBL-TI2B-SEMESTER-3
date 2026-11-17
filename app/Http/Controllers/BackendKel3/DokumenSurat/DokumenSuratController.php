<?php

namespace App\Http\Controllers\BackendKel3\DokumenSurat;

use App\Http\Controllers\Controller;
use App\Models\Models\BackendKel3\DokumenSurat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DokumenSuratController extends Controller
{
    public function index($id): JsonResponse
    {
        try {
            $dokumen = DokumenSurat::where('user_id', $id)->get();
        } catch (\Throwable $e) {
            $dokumen = [];
        }

        return response()->json([
            'success' => true,
            'message' => 'List dokumen berhasil dimuat.',
            'data' => $dokumen
        ], 200);
    }

    public function store(Request $request, $id): JsonResponse
    {
        $validated = $request->validate([
            'nama_file' => 'required|string',
            'file_path' => 'required|string',
        ]);

        $validated['user_id'] = $id;
        $dokumen = DokumenSurat::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil diunggah.',
            'data' => $dokumen
        ], 201);
    }

    public function destroy($id, $dokumenId): JsonResponse
    {
        $dokumen = DokumenSurat::where('user_id', $id)->where('id', $dokumenId)->first();

        if (!$dokumen) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak ditemukan.'
            ], 404);
        }

        $dokumen->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil dihapus.'
        ], 200);
    }

    public function showSurat($id): JsonResponse
    {
        try {
            $surat = DokumenSurat::where('user_id', $id)->first();
        } catch (\Throwable $e) {
            $surat = null;
        }

        return response()->json([
            'success' => true,
            'message' => 'Detail surat berhasil dimuat.',
            'data' => $surat
        ], 200);
    }

    public function opsi(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Opsi pengajaran berhasil dimuat.',
            'data' => [
                ['id' => 1, 'nama' => 'Proposal Kegiatan'],
                ['id' => 2, 'nama' => 'Surat Tugas'],
                ['id' => 3, 'nama' => 'Laporan Kemajuan'],
            ]
        ], 200);
    }
}