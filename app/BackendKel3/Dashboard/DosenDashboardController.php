<?php

namespace App\BackendKel3\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\TahunAkademik;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class DosenDashboardController extends Controller
{
    public function __construct(private RingkasanDosen $ringkasan) {}

    /** GET /dosen/dashboard (halaman web). Angkanya berasal dari RingkasanDosen, sama dengan API. */
    public function index(Request $request)
    {
        $user = Auth::user();
        $dosen = $user?->dosen;

        $daftarTahunAkademik = TahunAkademik::orderByDesc('id_tahun_akademik')->get();
        $tahunAktif = $this->ringkasan->tahunAkademik($request->integer('tahun_akademik') ?: null);

        $semua = $this->ringkasan->kegiatan($user);
        $kelasDiampu = $this->ringkasan->kelasDiampu($dosen, $tahunAktif, true);
        $perluRevisi = $this->ringkasan->perluRevisi($semua);

        $stats = [
            ['label' => 'Total Kegiatan', 'value' => $semua->count(), 'url' => url('/dosen/kegiatan'), 'aria' => 'Lihat semua kegiatan', 'alert' => false],
            ['label' => 'Penelitian', 'value' => $this->ringkasan->hitungJenis($semua, 'penelitian'), 'url' => url('/penelitian'), 'aria' => 'Lihat detail penelitian', 'alert' => false],
            ['label' => 'Pengabdian', 'value' => $this->ringkasan->hitungJenis($semua, 'pengabdian'), 'url' => url('/pengabdian'), 'aria' => 'Lihat detail pengabdian', 'alert' => false],
            ['label' => 'Pengajaran', 'value' => $kelasDiampu->count(), 'url' => url('/pengajaran'), 'aria' => 'Lihat detail pengajaran', 'alert' => false],
            ['label' => 'Perlu Revisi', 'value' => $perluRevisi, 'url' => url('/dosen/kegiatan?status=direvisi'), 'aria' => 'Lihat kegiatan yang perlu direvisi', 'alert' => $perluRevisi > 0],
        ];

        $menunggu = $this->ringkasan->menunggu($semua)->map(function ($item) {
            $tanggal = $item->tanggal_status_terakhir;

            return [
                'id' => $item->id_kegiatan,
                'nama' => $this->ringkasan->judul($item),
                'jenis' => $item->kategori?->nama_kategori ?? '-',
                'tanggal' => $tanggal ? Carbon::parse($tanggal)->locale('id')->translatedFormat('d M Y') : '-',
            ];
        })->all();

        return view('dosen.dashboard', [
            'user' => $user,
            'dosen' => $dosen,
            'stats' => $stats,
            'perTahun' => $this->ringkasan->grafikPerTahun($semua),
            'menunggu' => $menunggu,
            'tahunAktif' => $tahunAktif,
            'daftarTahunAkademik' => $daftarTahunAkademik,
            'kelasDiampu' => $kelasDiampu,
        ]);
    }
}