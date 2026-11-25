<?php

namespace App\BackendKel3\Dashboard;

use App\Http\Controllers\Controller;
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

        $tahun = $this->ringkasan->tahunAkademik($request->integer('tahun_akademik') ?: null);
        $semua = $this->ringkasan->kegiatan($user);
        $perluRevisi = $this->ringkasan->perluRevisi($semua);

        // Kartu kegiatan menghitung SEMUA tahun; hanya kartu Pengajaran dibatasi satu tahun akademik.
        // Keterangan di bawah angka membuat perbedaan itu terlihat oleh dosen.
        $semuaTahun = 'Semua tahun';

        $stats = [
            ['label' => 'Total Kegiatan', 'value' => $semua->count(), 'keterangan' => $semuaTahun, 'url' => url('/dosen/kegiatan'), 'aria' => 'Lihat semua kegiatan', 'alert' => false],
            ['label' => 'Penelitian', 'value' => $this->ringkasan->hitungJenis($semua, 'penelitian'), 'keterangan' => $semuaTahun, 'url' => url('/penelitian'), 'aria' => 'Lihat detail penelitian', 'alert' => false],
            ['label' => 'Pengabdian', 'value' => $this->ringkasan->hitungJenis($semua, 'pengabdian'), 'keterangan' => $semuaTahun, 'url' => url('/pengabdian'), 'aria' => 'Lihat detail pengabdian', 'alert' => false],
            ['label' => 'Pengajaran', 'value' => $this->ringkasan->kelasDiampu($dosen, $tahun)->count(), 'keterangan' => $tahun?->tahun_akademik ?? 'Belum ada tahun akademik', 'url' => url('/pengajaran'), 'aria' => 'Lihat detail pengajaran', 'alert' => false],
            ['label' => 'Perlu Revisi', 'value' => $perluRevisi, 'keterangan' => $semuaTahun, 'url' => url('/dosen/kegiatan?status=direvisi'), 'aria' => 'Lihat kegiatan yang perlu direvisi', 'alert' => $perluRevisi > 0],
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

        // Hanya yang benar-benar dipakai view. Daftar kelas (dengan relasi) dan daftar tahun akademik
        // dulu ikut dikirim padahal tidak ditampilkan, sehingga eager load-nya sia-sia.
        return view('dosen.dashboard', [
            'user' => $user,
            'dosen' => $dosen,
            'stats' => $stats,
            'perTahun' => $this->ringkasan->grafikPerTahun($semua),
            'menunggu' => $menunggu,
        ]);
    }
}
