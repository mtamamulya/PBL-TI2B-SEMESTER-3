<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use App\Support\ApiException;
use Illuminate\Http\Request;

/**
 * Halaman "Kegiatan" gabungan: tab Semua | Dosen | Mahasiswa | Prodi.
 * Hanya kegiatan yang SUDAH DISETUJUI (pengajuan baru/revisi/tolak = urusan halaman Pengajuan).
 * Dipakai bersama admin (AdminKegiatanController meng-extend kelas ini) dan kaprodi.
 * Sumber data: API GET /admin/kegiatan (semua halaman). Bentuk baris dari API sudah siap dipakai view.
 */
class KaprodiKegiatanController extends Controller
{
    protected string $tampilan = 'kaprodi.kegiatan';

    /** Batas pengaman jumlah halaman yang diambil (200 baris per halaman). */
    private const MAKS_HALAMAN = 50;

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), ['semua', 'dosen', 'mahasiswa', 'prodi'], true) ? $request->query('tab') : 'semua';

        $per = ['dosen' => [], 'mahasiswa' => [], 'prodi' => []];
        $apiError = null;

        try {
            foreach ($this->ambilSemua() as $baris) {
                if (isset($per[$baris['jenis'] ?? ''])) {
                    $per[$baris['jenis']][] = $baris;
                }
            }
        } catch (ApiException $e) {
            // 401/403 ditangani handler global (login / akses ditolak); selain itu tampilkan pesan di halaman.
            if (in_array($e->getStatusCode(), [401, 403], true)) {
                throw $e;
            }
            \Log::error('Gagal memuat daftar kegiatan: '.$e->getMessage().' (Status: '.$e->getStatusCode().')');
            $apiError = $e->getMessage().' (Status: '.$e->getStatusCode().')';
        }

        // Tab "Semua" = gabungan ketiganya, terbaru di atas.
        $semua = array_merge($per['dosen'], $per['mahasiswa'], $per['prodi']);
        usort($semua, fn ($a, $b) => [$b['raw_date'] ?? '', $a['nama'] ?? ''] <=> [$a['raw_date'] ?? '', $b['nama'] ?? '']);

        $data = ['semua' => $semua] + $per;

        return view($this->tampilan, [
            'data' => $data,
            'tab' => $tab,
            'tahun' => array_map(fn ($rows) => $this->tahun($rows), $data),
            'apiError' => $apiError,
        ]);
    }

    /**
     * Ambil SEMUA halaman /admin/kegiatan.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function ambilSemua(): array
    {
        $baris = [];
        $halaman = 1;

        do {
            $hasil = ApiClient::pengguna()->get('/admin/kegiatan', ['per_page' => 200, 'page' => $halaman]);
            $baris = array_merge($baris, $hasil['data'] ?? []);
            $terakhir = (int) ($hasil['meta']['last_page'] ?? 1);
            $halaman++;
        } while ($halaman <= $terakhir && $halaman <= self::MAKS_HALAMAN);

        return $baris;
    }

    /** Daftar tahun (urut naik) dari baris kegiatan. */
    private function tahun(array $rows): array
    {
        $t = array_values(array_unique(array_filter(array_column($rows, 'tahun'))));
        sort($t);

        return array_map('intval', $t);
    }
}