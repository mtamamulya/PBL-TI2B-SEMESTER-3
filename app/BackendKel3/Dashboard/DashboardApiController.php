<?php

namespace App\BackendKel3\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\EnrollmentKelasMataKuliah;
use App\Models\Kegiatan;
use App\Models\TahunAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $dosen = $user?->dosen;

        $tahunAktif = $request->filled('tahun_akademik')
            ? TahunAkademik::find((int) $request->query('tahun_akademik'))
            : TahunAkademik::where('status', true)->first();

        $queryKegiatan = method_exists(Kegiatan::class, 'scopeUntukUser') && $user
            ? Kegiatan::untukUser($user->id_user)
            : Kegiatan::where('id_user', $user?->id_user ?? 0);

        $semuaKegiatan = $queryKegiatan->with([
            'kategori.jenisKegiatan',
            'timeline' => fn ($q) => $q->orderByDesc('tanggal_status')->orderByDesc('id_timeline'),
        ])->get()->map(function ($item) {
            $latest = $item->timeline->first();
            $item->status_terakhir = $latest?->status ?? 'diajukan';
            $item->tanggal_status_terakhir = $latest?->tanggal_status ?? $item->created_at;

            return $item;
        });

        // Grafik per tahun
        $tahunSekarang = (int) now()->year;
        $grafikPerTahun = [];
        for ($tahun = $tahunSekarang - 4; $tahun <= $tahunSekarang; $tahun++) {
            $grafikPerTahun[(string) $tahun] = 0;
        }

        foreach ($semuaKegiatan as $item) {
            $ref = $item->tanggal_mulai ?? $item->created_at;
            if ($ref) {
                $thn = (string) Carbon::parse($ref)->year;
                if (array_key_exists($thn, $grafikPerTahun)) {
                    $grafikPerTahun[$thn]++;
                }
            }
        }

        // Jumlah penugasan mengajar
        $jumlahPengajaran = $dosen
            ? EnrollmentKelasMataKuliah::where('id_dosen', $dosen->id_dosen)
                ->when($tahunAktif, fn ($q) => $q->whereHas(
                    'enrollmentMataKuliah',
                    fn ($sub) => $sub->where('id_tahun_akademik', $tahunAktif->id_tahun_akademik)
                ))
                ->count()
            : 0;

        $jumlahPenelitian = $semuaKegiatan->filter(
            fn ($k) => str_contains(mb_strtolower($k->kategori?->nama_kategori ?? ''), 'penelitian')
        )->count();

        $jumlahPengabdian = $semuaKegiatan->filter(
            fn ($k) => str_contains(mb_strtolower($k->kategori?->nama_kategori ?? ''), 'pengabdian')
        )->count();

        $pengajuanMenunggu = $semuaKegiatan
            ->whereIn('status_terakhir', ['diajukan', 'direview', 'direvisi'])
            ->values()
            ->map(fn ($item) => [
                'id_kegiatan' => $item->id_kegiatan,
                'judul' => $item->nama_kegiatan ?? $item->judul ?? 'Kegiatan #' . $item->id_kegiatan,
                'kategori' => $item->kategori?->nama_kategori,
                'status' => $item->status_terakhir,
                'tanggal_update' => $item->tanggal_status_terakhir,
                'detail_url' => route('dosen.kegiatan.show', $item->id_kegiatan),
            ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'tahun_akademik' => $tahunAktif?->tahun_akademik,
                'ringkasan' => [
                    'penugasan_mengajar' => $jumlahPengajaran,
                    'penelitian' => $jumlahPenelitian,
                    'pengabdian' => $jumlahPengabdian,
                    'pengajuan_menunggu' => $pengajuanMenunggu->count(),
                    'total_kegiatan' => $semuaKegiatan->count(),
                ],
                'daftar_menunggu' => $pengajuanMenunggu,
                'grafik_per_tahun' => $grafikPerTahun,
            ],
        ]);
    }
}