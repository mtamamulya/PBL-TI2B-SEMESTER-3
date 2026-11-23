<?php

namespace App\BackendKel3\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\EnrollmentKelasMataKuliah;
use App\Models\Kegiatan;
use App\Models\TahunAkademik;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DosenDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $dosen = $user?->dosen;

        $daftarTahunAkademik = TahunAkademik::orderByDesc('id_tahun_akademik')->get();
        $tahunAktif = $request->filled('tahun_akademik')
            ? $daftarTahunAkademik->firstWhere('id_tahun_akademik', (int) $request->query('tahun_akademik'))
            : ($daftarTahunAkademik->firstWhere('status', true) ?? $daftarTahunAkademik->first());

        $semuaKegiatan = Kegiatan::untukUser($user)
            ->with([
                'kategori.jenisKegiatan',
                'timeline' => fn ($q) => $q->orderByDesc('tanggal_status')->orderByDesc('id_timeline'),
            ])
            ->latest('id_kegiatan')
            ->get()
            ->map(function ($item) {
                $latestTimeline = $item->timeline->first();
                $item->status_terakhir = strtolower((string) ($latestTimeline?->status ?? 'diajukan'));
                $item->tanggal_status_terakhir = $latestTimeline?->tanggal_status ?? $item->created_at;
                $item->catatan_terakhir = $latestTimeline?->catatan_revisi;

                return $item;
            });

        // Grafik per tahun (7 tahun terakhir)
        $tahunSekarang = (int) now()->year;
        $perTahun = [];
        for ($tahun = $tahunSekarang - 6; $tahun <= $tahunSekarang; $tahun++) {
            $perTahun[(string) $tahun] = 0;
        }

        foreach ($semuaKegiatan as $kegiatan) {
            $tanggalRef = $kegiatan->tanggal_mulai ?? $kegiatan->created_at;
            if ($tanggalRef) {
                $tahunItem = (string) Carbon::parse($tanggalRef)->year;
                if (array_key_exists($tahunItem, $perTahun)) {
                    $perTahun[$tahunItem]++;
                }
            }
        }

        // Penugasan mengajar milik dosen login
        $kelasDiampu = $dosen
            ? EnrollmentKelasMataKuliah::with([
                'enrollmentMataKuliah.mataKuliah',
                'enrollmentMataKuliah.tahunAkademik',
                'enrollmentTingkatKelas.tingkat',
                'enrollmentTingkatKelas.kelas',
                'enrollmentTingkatKelas.prodi',
            ])
                ->where('id_dosen', $dosen->id_dosen)
                ->when($tahunAktif, fn ($q) => $q->whereHas(
                    'enrollmentMataKuliah',
                    fn ($sub) => $sub->where('id_tahun_akademik', $tahunAktif->id_tahun_akademik)
                ))
                ->get()
            : collect();

        $totalPenelitian = $semuaKegiatan->filter(function ($k) {
            $kategori = mb_strtolower($k->kategori?->nama_kategori ?? '');
            $jenis = mb_strtolower($k->kategori?->jenisKegiatan?->nama_jenis ?? '');

            return str_contains($kategori, 'penelitian') || str_contains($jenis, 'penelitian');
        })->count();

        $totalPengabdian = $semuaKegiatan->filter(function ($k) {
            $kategori = mb_strtolower($k->kategori?->nama_kategori ?? '');
            $jenis = mb_strtolower($k->kategori?->jenisKegiatan?->nama_jenis ?? '');

            return str_contains($kategori, 'pengabdian') || str_contains($jenis, 'pengabdian');
        })->count();

        $totalPengajaran = $kelasDiampu->count();
        $totalKegiatan = $semuaKegiatan->count();
        $perluRevisi = $semuaKegiatan->whereIn('status_terakhir', ['direvisi', 'revisi'])->count();

        $stats = [
            [
                'label' => 'Total Kegiatan',
                'value' => $totalKegiatan,
                'url'   => url('/dosen/kegiatan'),
                'aria'  => 'Lihat semua kegiatan',
                'alert' => false,
            ],
            [
                'label' => 'Penelitian',
                'value' => $totalPenelitian,
                'url'   => url('/penelitian'),
                'aria'  => 'Lihat detail penelitian',
                'alert' => false,
            ],
            [
                'label' => 'Pengabdian',
                'value' => $totalPengabdian,
                'url'   => url('/pengabdian'),
                'aria'  => 'Lihat detail pengabdian',
                'alert' => false,
            ],
            [
                'label' => 'Pengajaran',
                'value' => $totalPengajaran,
                'url'   => url('/pengajaran'),
                'aria'  => 'Lihat detail pengajaran',
                'alert' => false,
            ],
            [
                'label' => 'Perlu Revisi',
                'value' => $perluRevisi,
                'url'   => url('/dosen/kegiatan?status=direvisi'),
                'aria'  => 'Lihat kegiatan yang perlu direvisi',
                'alert' => $perluRevisi > 0,
            ],
        ];

        $menunggu = $semuaKegiatan
            ->whereIn('status_terakhir', ['diajukan', 'direview', 'direvisi', 'revisi'])
            ->values()
            ->map(function ($item) {
                $tanggal = $item->tanggal_status_terakhir ?? $item->created_at;

                return [
                    'id'      => $item->id_kegiatan,
                    'nama'    => $item->nama_kegiatan ?? $item->judul ?? 'Kegiatan #' . $item->id_kegiatan,
                    'jenis'   => $item->kategori?->nama_kategori ?? '-',
                    'tanggal' => $tanggal ? Carbon::parse($tanggal)->locale('id')->translatedFormat('d M Y') : '-',
                ];
            })
            ->all();

        return view('dosen.dashboard', [
            'user'                => $user,
            'dosen'               => $dosen,
            'stats'               => $stats,
            'perTahun'            => $perTahun,
            'menunggu'            => $menunggu,
            'tahunAktif'          => $tahunAktif,
            'daftarTahunAkademik' => $daftarTahunAkademik,
            'kelasDiampu'         => $kelasDiampu,
        ]);
    }
}