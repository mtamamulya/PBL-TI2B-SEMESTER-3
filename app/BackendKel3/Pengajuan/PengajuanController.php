<?php

namespace App\BackendKel3\Pengajuan;

use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PengajuanController extends Controller
{
    public function __construct(
        private AlurPengajuan $alurPengajuan
    ) {}

    /**
     * GET /dosen/pengajuan
     * Daftar pengajuan dosen login dengan filter status & pencarian.
     */
    public function index(Request $request): JsonResponse
    {
        // Mengambil id_user dari tabel users
        $idUser = (int) $request->user()->getKey(); 
        $filterStatus = $request->query('status');
        $search = $request->query('search');

        // HAPUS ->whereHas('timeline') agar kegiatan yang belum diajukan (draft) tetap tampil di front end
        $query = Kegiatan::with(['kategori', 'statusTerkini', 'timeline'])
            ->where('id_user', $idUser); // Sesuai dengan kolom id_user di tabel kegiatan

        if (! empty($filterStatus) && strtolower($filterStatus) !== 'semua') {
            $query->whereHas('statusTerkini', function ($q) use ($filterStatus) {
                $q->where('status', strtolower($filterStatus));
            });
        }

        if (! empty($search)) {
            $query->where('nama_kegiatan', 'like', "%{$search}%"); // Sesuai dengan nama_kegiatan[cite: 7]
        }

        // Menggunakan updated_at karena kolom tersebut tersedia di tabel kegiatan[cite: 7]
        $daftarPengajuan = $query->latest('updated_at')->get();

        return response()->json([
            'status' => 'success',
            'data' => $daftarPengajuan,
        ]);
    }

    /**
     * GET /dosen/pengajuan/{id}/timeline
     * Menampilkan semua tahap beserta tanggal dan catatan revisi.
     */
    public function timeline(Request $request, int $id): JsonResponse
    {
        $idUser = (int) $request->user()->getKey();

        $kegiatan = Kegiatan::with(['kategori', 'timeline', 'statusTerkini'])
            ->where('id_kegiatan', $id) // Sesuai kolom id_kegiatan[cite: 7]
            ->where('id_user', $idUser)
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => [
                'id_kegiatan' => $kegiatan->id_kegiatan,
                'nama_kegiatan' => $kegiatan->nama_kegiatan,
                'kategori' => $kegiatan->kategori,
                'status_terkini' => $kegiatan->statusTerkini?->status,
                'riwayat_tahapan' => $kegiatan->timeline->map(function ($item) {
                    return [
                        'id_timeline' => $item->id_timeline,
                        'status' => $item->status,
                        'catatan_revisi' => $item->catatan_revisi,
                        'tanggal_status' => $item->tanggal_status?->toIso8601String(),
                    ];
                }),
            ],
        ]);
    }

    /**
     * POST /dosen/pengajuan/{id}/ajukan
     */
    public function ajukan(Request $request, int $id): JsonResponse
    {
        $idUser = (int) $request->user()->getKey();
        $timeline = $this->alurPengajuan->ajukanBaru($id, $idUser);

        return response()->json([
            'status' => 'success',
            'message' => 'Kegiatan berhasil diajukan.',
            'data' => $timeline,
        ], 201);
    }

    /**
     * POST /dosen/pengajuan/{id}/ajukan-ulang
     */
    public function ajukanUlang(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'catatan' => 'nullable|string|max:1000',
        ]);

        $idUser = (int) $request->user()->getKey();
        $timeline = $this->alurPengajuan->ajukanUlang(
            $id,
            $idUser,
            $request->input('catatan')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Pengajuan ulang berhasil dikirim.',
            'data' => $timeline,
        ], 201);
    }
}