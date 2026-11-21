<?php

namespace App\BackendKel1\Prestasi;

use App\Http\Controllers\Controller;
use App\Models\Prestasi;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrestasiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $prestasi = Prestasi::query()
            ->with(['kegiatan.user', 'kegiatan.kategori'])
            ->latest('id_prestasi')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Daftar prestasi berhasil diambil',
            'data' => $prestasi->getCollection()->map(fn (Prestasi $item) => self::bentuk($item))->all(),
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
        $m = Prestasi::query()
            ->with(['kegiatan.user', 'kegiatan.kategori', 'buktiPrestasi.jenisDokumen'])
            ->find($id);

        return $m ? ApiResponse::ok(self::bentuk($m, true)) : ApiResponse::gagal('Prestasi tidak ditemukan');
    }

    /** Nama kunci mengikuti kolom database. */
    public static function bentuk(Prestasi $k, bool $denganDetail = false): array
    {
        $data = [
            'id_prestasi' => $k->id_prestasi,
            'id_kegiatan' => $k->id_kegiatan,
            'id_prodi' => $k->id_prodi,
            'jenis_prestasi' => $k->jenis_prestasi,
            'bidang_lomba' => $k->bidang_lomba,
            'capaian' => $k->capaian,
            'catatan' => $k->getAttribute('catatan'),
            'created_at' => $k->getAttribute('created_at'),
            'updated_at' => $k->getAttribute('updated_at'),
            'kegiatan' => $k->relationLoaded('kegiatan') && $k->kegiatan ? [
                'id_kegiatan' => $k->kegiatan->id_kegiatan,
                'nama_kegiatan' => $k->kegiatan->nama_kegiatan,
                'tanggal_mulai' => $k->kegiatan->tanggal_mulai?->toDateString(),
                'tanggal_selesai' => $k->kegiatan->tanggal_selesai?->toDateString(),
                'tempat' => $k->kegiatan->tempat ?? null,
                'deskripsi' => $k->kegiatan->deskripsi ?? null,
                'user' => $k->kegiatan->relationLoaded('user') && $k->kegiatan->user ? [
                    'id_user' => $k->kegiatan->user->id_user,
                    'nama' => $k->kegiatan->user->nama ?? $k->kegiatan->user->name ?? null,
                    'role' => $k->kegiatan->user->role ?? null,
                    'is_dosen' => $k->kegiatan->user->is_dosen ?? null,
                ] : null,
            ] : null,
        ];

        if ($denganDetail) {
            $data['bukti_prestasi'] = $k->relationLoaded('buktiPrestasi')
                ? $k->buktiPrestasi->map(fn ($b) => [
                    'id_bukti_prestasi' => $b->id_bukti_prestasi ?? null,
                    'nama_file' => $b->nama_file ?? $b->file_path ?? null,
                    'file_path' => $b->file_path ?? $b->nama_file ?? null,
                    'link' => $b->link ?? ($b->file_path ? asset('storage/'.$b->file_path) : null),
                    'keterangan' => $b->keterangan ?? null,
                    'jenis_dokumen' => $b->relationLoaded('jenisDokumen') && $b->jenisDokumen ? [
                        'id_jenis_dokumen' => $b->jenisDokumen->id_jenis_dokumen,
                        'nama_jenis' => $b->jenisDokumen->nama_jenis ?? $b->jenisDokumen->nama_jenis_dokumen ?? null,
                        'nama_jenis_dokumen' => $b->jenisDokumen->nama_jenis_dokumen ?? $b->jenisDokumen->nama_jenis ?? null,
                    ] : null,
                ])->all()
                : [];
        }

        return $data;
    }
}
