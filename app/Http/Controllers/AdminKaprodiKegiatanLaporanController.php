<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use App\Support\ApiException;
use App\Support\Laporan\LaporanKegiatan;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unduh laporan data kegiatan semua / dosen / mahasiswa / prodi (PDF atau Excel) per kurun waktu tahun.
 * Dipakai admin (/admin/kegiatan/{dosen|mahasiswa|prodi|semua}/laporan) DAN kaprodi (/kaprodi/...).
 * Sumber data: API GET /admin/kegiatan (semua halaman), sama dengan halaman daftar, jadi angkanya selalu cocok.
 * API hanya mengembalikan kegiatan yang sudah disetujui, jadi status laporan selalu "Disetujui".
 *
 * Parameter GET: dari (tahun), sampai (tahun), format (pdf | xlsx).
 */
class AdminKaprodiKegiatanLaporanController extends Controller
{
    private const STATUS = 'Disetujui';

    /** Batas pengaman jumlah halaman yang diambil (200 baris per halaman). */
    private const MAKS_HALAMAN = 50;

    public function dosen(Request $request): Response
    {
        return $this->unduh($request, 'dosen');
    }

    public function mahasiswa(Request $request): Response
    {
        return $this->unduh($request, 'mahasiswa');
    }

    public function prodi(Request $request): Response
    {
        return $this->unduh($request, 'prodi');
    }

    public function semua(Request $request): Response
    {
        return $this->unduh($request, 'semua');
    }

    /**
     * Ambil SEMUA halaman /admin/kegiatan (opsional per jenis) dan ubah ke bentuk baris yang dipakai LaporanKegiatan::susun().
     *
     * @return array<int, array<string, mixed>>
     */
    private function ambilBaris(string $jenis): array
    {
        $query = ['per_page' => 200];
        if ($jenis !== 'semua') {
            $query['jenis'] = $jenis;
        }

        $baris = [];
        $halaman = 1;

        do {
            $hasil = ApiClient::pengguna()->get('/admin/kegiatan', $query + ['page' => $halaman]);

            foreach ($hasil['data'] ?? [] as $r) {
                if (is_array($r)) {
                    $baris[] = $this->petakan($r);
                }
            }

            $terakhir = (int) ($hasil['meta']['last_page'] ?? 1);
            $halaman++;
        } while ($halaman <= $terakhir && $halaman <= self::MAKS_HALAMAN);

        return $baris;
    }

    /** Samakan baris API dengan kolom yang dibaca LaporanKegiatan (dosen / pengaju / penanggung_jawab / pelaku). */
    private function petakan(array $r): array
    {
        $pengaju = (string) ($r['pengaju'] ?? '-');

        return [
            'nama' => $r['nama'] ?? '-',
            'kategori' => $r['kategori'] ?? '-',
            'jenis' => ucfirst((string) ($r['jenis'] ?? '')),
            'pelaku' => $pengaju,
            'dosen' => $pengaju,
            'pengaju' => $pengaju,
            'penanggung_jawab' => $pengaju,
            'tanggal' => $r['tanggal'] ?? '-',
            'tanggal_iso' => (string) ($r['raw_date'] ?? ''),
            'tahun' => (int) ($r['tahun'] ?? 0), // 0 = tanpa tanggal; tidak ikut laporan per tahun
            'semester' => $r['semester'] ?? '',
            'status' => self::STATUS,
            'tingkat' => $r['tingkat'] ?? '',
            'kelas' => $r['kelas'] ?? '',
        ];
    }

    private function unduh(Request $request, string $jenis): Response
    {
        $kaprodi = $request->routeIs('kaprodi.*');

        try {
            $rows = $this->ambilBaris($jenis);
        } catch (ApiException $e) {
            if (in_array($e->getStatusCode(), [401, 403], true)) {
                throw $e;
            }
            \Log::error('Gagal memuat data laporan kegiatan: '.$e->getMessage().' (Code: '.$e->getStatusCode().')');

            return redirect()
                ->route($kaprodi ? 'kaprodi.kegiatan.index' : 'admin.kegiatan.index')
                ->with('error', 'Laporan gagal dibuat: '.$e->getMessage());
        }

        // Tahun: bila kosong/tidak valid pakai tahun terbaru; bila terbalik ditukar.
        $tahunList = array_values(array_unique(array_filter(array_column($rows, 'tahun'))));
        sort($tahunList);
        $terbaru = end($tahunList) ?: (int) date('Y');
        $dari = (int) $request->query('dari', $terbaru);
        $sampai = (int) $request->query('sampai', $terbaru);
        $dari = in_array($dari, $tahunList, true) ? $dari : $terbaru;
        $sampai = in_array($sampai, $tahunList, true) ? $sampai : $terbaru;
        if ($dari > $sampai) {
            [$dari, $sampai] = [$sampai, $dari];
        }

        $format = $request->query('format') === 'xlsx' ? 'xlsx' : 'pdf';

        $laporan = LaporanKegiatan::susun($jenis, $rows, $dari, $sampai, self::STATUS);
        $oleh = (string) session($kaprodi ? 'kaprodi.nama' : 'admin.nama', $kaprodi ? 'Kaprodi' : 'Admin');
        $waktu = now()->setTimezone('Asia/Jakarta')->locale('id')->translatedFormat('j F Y, H:i').' WIB';

        $isi = $format === 'pdf'
            ? LaporanKegiatan::pdf($laporan, $oleh, $waktu)
            : LaporanKegiatan::xlsx($laporan, $oleh, $waktu);

        $mime = $format === 'pdf'
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        return response($isi, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$laporan['nama_file'].'.'.$format.'"',
            'Content-Length' => (string) strlen($isi),
            'Cache-Control' => 'no-store',
        ]);
    }
}
