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
        $idDosen = (int) $request->user()->id;
        $filterStatus = $request->query('status'); // diajukan, direview, direvisi, ditolak, diterima, selesai
        $search = $request->query('search');

        $query = Kegiatan::with(['statusTerkini', 'timeline'])
            ->where('id_dosen', $idDosen)
            ->whereHas('timeline'); // Hanya tampilkan kegiatan yang sudah masuk alur pengajuan

        // Filter berdasarkan status terkini
        if (!empty($filterStatus) && $filterStatus !== 'semua') {
            $query->whereHas('statusTerkini', function ($q) use ($filterStatus) {
                $q->where('status', strtolower($filterStatus));
            });
        }

        // Filter pencarian judul kegiatan (sesuai UI search bar)
        if (!empty($search)) {
            $query->where('judul', 'like', "%{$search}%");
        }

        $daftarPengajuan = $query->latest('updated_at')->get();

        return response()->json([
            'status' => 'success',
            'data'   => $daftarPengajuan,
        ]);
    }

    /**
     * GET /dosen/pengajuan/{id}/timeline
     * Menampilkan semua tahap beserta tanggal dan catatan revisi.
     */
    public function timeline(Request $request, int $id): JsonResponse
    {
        $idDosen = (int) $request->user()->id;

        $kegiatan = Kegiatan::with(['timeline' => function ($query) {
            $query->orderBy('created_at', 'asc')->orderBy('id', 'asc');
        }, 'statusTerkini'])
            ->where('id_kegiatan', $id)
            ->where('id_dosen', $idDosen)
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'id_kegiatan'     => $kegiatan->id_kegiatan,
                'judul'           => $kegiatan->judul,
                'kategori'        => $kegiatan->kategori,
                'status_terkini'  => $kegiatan->statusTerkini?->status,
                'riwayat_tahapan' => $kegiatan->timeline->map(function ($item) {
                    return [
                        'id'         => $item->id,
                        'status'     => $item->status,
                        'catatan'    => $item->catatan,
                        'tanggal'    => $item->created_at?->toIso8601String(),
                    ];
                }),
            ],
        ]);
    }

    /**
     * POST /dosen/pengajuan/{id}/ajukan
     * Pemilik mengajukan kegiatan yang belum memiliki tahap pengajuan.
     */
    public function ajukan(Request $request, int $id): JsonResponse
    {
        $idDosen = (int) $request->user()->id;

        $timeline = $this->alurPengajuan->ajukanBaru($id, $idDosen);

        return response()->json([
            'status'  => 'success',
            'message' => 'Kegiatan berhasil diajukan.',
            'data'    => $timeline,
        ], 201);
    }

    /**
     * POST /dosen/pengajuan/{id}/ajukan-ulang
     * Mengajukan ulang setelah kegiatan direvisi dan dilengkapi.
     */
    public function ajukanUlang(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'catatan' => 'nullable|string|max:1000',
        ]);

        $idDosen = (int) $request->user()->id;

        $timeline = $this->alurPengajuan->ajukanUlang(
            $id,
            $idDosen,
            $request->input('catatan')
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Pengajuan ulang berhasil dikirim.',
            'data'    => $timeline,
        ], 201);
    }
}