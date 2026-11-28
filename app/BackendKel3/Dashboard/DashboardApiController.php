<?php

namespace App\BackendKel3\Dashboard;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardApiController extends Controller
{
    public function __construct(private RingkasanDosen $ringkasan) {}

    /** GET /api/v1/dosen/dashboard[?tahun_akademik=ID] */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $dosen = $user?->dosen;

        $tahun = $this->ringkasan->tahunAkademik($request->integer('tahun_akademik') ?: null);
        $semua = $this->ringkasan->kegiatan($user);
        $menunggu = $this->ringkasan->menunggu($semua);

        $daftarMenunggu = $menunggu->map(fn ($k) => [
            'id_kegiatan' => $k->id_kegiatan,
            'judul' => $this->ringkasan->judul($k),
            'kategori' => $k->kategori?->nama_kategori,
            'status' => $k->status_terakhir,
            'tanggal_update' => $k->tanggal_status_terakhir
                ? Carbon::parse($k->tanggal_status_terakhir)->toIso8601String()
                : null,
            'detail_url' => route('dosen.kegiatan.show', $k->id_kegiatan),
        ])->all();

        return ApiResponse::ok([
            'tahun_akademik' => $tahun?->tahun_akademik,
            'id_tahun_akademik' => $tahun?->id_tahun_akademik,
            'ringkasan' => [
                'penugasan_mengajar' => $this->ringkasan->kelasDiampu($dosen, $tahun)->count(),
                'penelitian' => $this->ringkasan->hitungJenis($semua, 'penelitian'),
                'pengabdian' => $this->ringkasan->hitungJenis($semua, 'pengabdian'),
                'pengajuan_menunggu' => $menunggu->count(),
                'perlu_revisi' => $this->ringkasan->perluRevisi($semua),
                'total_kegiatan' => $semua->count(),
            ],
            'daftar_menunggu' => $daftarMenunggu,
            'grafik_per_tahun' => $this->ringkasan->grafikPerTahun($semua),
        ], 'Data dashboard dosen berhasil dimuat.');
    }
}
