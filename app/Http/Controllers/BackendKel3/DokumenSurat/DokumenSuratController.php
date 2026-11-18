<?php

namespace App\Http\Controllers\BackendKel3\DokumenSurat;

use App\Http\Controllers\Controller;
use App\Models\DokumenTerlampir;
use App\Models\Models\BackendKel3\DokumenSurat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DokumenSuratController extends Controller
{
    public function index($id): JsonResponse
    {
        try {
            $surat = DokumenSurat::where('id', $id)
                ->orWhere('user_id', $id)
                ->first();

            $dokumen = $surat
                ? DokumenTerlampir::where('dokumen_surat_id', $surat->id)->get()
                : [];

            return response()->json([
                'success' => true,
                'message' => 'List dokumen berhasil dimuat.',
                'data' => $dokumen
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => []
            ], 200);
        }
    }

    public function store(Request $request, $id): JsonResponse
    {
        $request->validate([
            'file'          => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'dokumen'       => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'files.*'       => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'nama_file'     => 'nullable|string',
            'jenis_dokumen' => 'nullable|string',
            'file_path'     => 'nullable|string',
        ]);

        $surat = DokumenSurat::where('id', $id)
            ->orWhere('user_id', $id)
            ->first();

        if (!$surat) {
            $surat = DokumenSurat::create([
                'user_id'         => $id,
                'nama_kegiatan'   => 'Pengajuan Kegiatan #' . $id,
                'jenis_kegiatan'  => 'Kegiatan Dosen',
                'kategori'        => 'Pengabdian',
                'penyelenggara'   => '-',
                'tanggal_mulai'   => now()->toDateString(),
                'tanggal_selesai' => now()->toDateString(),
                'status'          => 'Diajukan',
            ]);
        }

        $uploadedFiles = [];
        if ($request->hasFile('files')) {
            $uploadedFiles = $request->file('files');
        } elseif ($request->hasFile('file')) {
            $uploadedFiles = [$request->file('file')];
        } elseif ($request->hasFile('dokumen')) {
            $uploadedFiles = [$request->file('dokumen')];
        }

        if (!empty($uploadedFiles)) {
            $saved = [];
            $jenis = $request->input('jenis_dokumen', 'Dokumen Perbaikan');

            foreach ($uploadedFiles as $file) {
                $path = $file->store('dokumen_perbaikan', 'public');

                $saved[] = DokumenTerlampir::create([
                    'dokumen_surat_id' => $surat->id,
                    'nama_file'        => $file->getClientOriginalName(),
                    'jenis_dokumen'    => $jenis,
                    'file_path'        => Storage::url($path),
                ]);
            }

            if ($request->boolean('ajukan_kembali', true)) {
                $surat->update(['status' => 'Diajukan']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Dokumen perbaikan berhasil diunggah.',
                'data' => count($saved) === 1 ? $saved[0] : $saved
            ], 201);
        }

        $validated = $request->validate([
            'nama_file' => 'required|string',
            'file_path' => 'required|string',
        ]);

        $dokumen = DokumenTerlampir::create([
            'dokumen_surat_id' => $surat->id,
            'nama_file'        => $validated['nama_file'],
            'jenis_dokumen'    => $request->input('jenis_dokumen', 'Dokumen'),
            'file_path'        => $validated['file_path'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen berhasil diunggah.',
            'data' => $dokumen
        ], 201);
    }

    public function destroy($id, $dokumenId): JsonResponse
    {
        $surat = DokumenSurat::where('id', $id)
            ->orWhere('user_id', $id)
            ->first();

        if (!$surat) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengajuan surat tidak ditemukan.'
            ], 404);
        }

        $dokumen = DokumenTerlampir::where('dokumen_surat_id', $surat->id)
            ->where('id', $dokumenId)
            ->first();

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
            $surat = DokumenSurat::with('dokumenTerlampir')
                ->where('id', $id)
                ->orWhere('user_id', $id)
                ->first();

            return response()->json([
                'success' => true,
                'message' => 'Detail surat berhasil dimuat.',
                'data' => $surat
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'data' => null
            ], 200);
        }
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