<?php

namespace App\Http\Controllers;

use App\BackendKel1\Berita\BeritaController;
use App\Support\ApiClient;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminBeritaController extends Controller
{
    private function opsi(): array
    {
        $hasil = ApiClient::pengguna()->get('/berita/opsi');

        return [
            'kategori' => $hasil['data']['kategori'] ?? ['Prestasi Mahasiswa', 'Prestasi Dosen', 'Kunjungan Industri', 'Info Akademik', 'Lomba', 'Magang'],
            'kegiatanList' => $hasil['data']['kegiatanList'] ?? [],
        ];
    }

    private function labelStatus(string $status): string
    {
        return match (strtolower($status)) {
            'publish' => 'Publish',
            'diajukan' => 'Pengajuan',
            'revisi', 'direvisi' => 'Revisi',
            'ditolak' => 'Ditolak',
            default => 'Draft',
        };
    }

    public function index(Request $request)
    {
        $hasil = ApiClient::pengguna()->get('/berita', ['per_page' => 100]);
        $opsi = $this->opsi();

        $rows = [];
        foreach ($hasil['data'] ?? [] as $b) {
            $rows[] = [
                'id' => $b['id_berita'],
                'judul' => $b['judul_berita'],
                'kegiatan' => $b['nama_kegiatan'] ?? ($b['nama_kategori'] ?? 'Mandiri / Tanpa Kegiatan'),
                'tanggal' => ! empty($b['tanggal_publish']) ? Carbon::parse($b['tanggal_publish'])->locale('id')->translatedFormat('d F Y') : '-',
                'raw_date' => $b['tanggal_publish'] ?? null,
                'status' => $this->labelStatus((string) ($b['status'] ?? 'draft')),
            ];
        }

        return view('admin.berita', [
            'rows' => $rows,
            'kegiatan' => $opsi['kategori'],
        ]);
    }

    public function create()
    {
        return view('admin.berita-tambah', $this->opsi());
    }

    public function store(Request $request)
    {
        $payload = $request->only(['judul', 'isi', 'status', 'sumber', 'kegiatan_id', 'kategori', 'sampul_dokumen_id']);
        $berkas = $request->hasFile('foto') ? ['foto' => $request->file('foto')] : [];

        $hasil = ApiClient::pengguna()->post('/berita', $payload, $berkas);

        return redirect()->route('admin.berita')->with('status', $hasil['message'] ?? 'Berita berhasil disimpan.');
    }

    public function uploadGambarKonten(Request $request)
    {
        $request->validate([
            'upload' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        try {
            $hasil = ApiClient::pengguna()->post('/berita/gambar-konten', [], [
                'gambar' => $request->file('upload'),
            ]);

            return response()->json([
                'url' => $hasil['data']['url'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => ['message' => $e->getMessage() ?: 'Gagal mengunggah gambar konten.'],
            ], 422);
        }
    }

    public function show(string $id)
    {
        $hasil = ApiClient::pengguna()->get('/berita/'.$id);
        $item = $hasil['data'];

        $sampulUrl = $item['sampul_url'] ?? null;
        $isGambar = (bool) ($item['is_gambar'] ?? false);

        $berita = [
            'id' => $item['id_berita'],
            'judul' => $item['judul_berita'],
            'isi' => $item['isi_berita'],
            'status' => $this->labelStatus((string) ($item['status'] ?? 'draft')),
            'kegiatan' => $item['nama_kegiatan'] ?? ($item['nama_kategori'] ?? 'Mandiri / Tanpa Kegiatan'),
            'tanggal' => $item['tanggal_publish'] ?? null,
            'foto' => $sampulUrl,
            'catatan_revisi' => $item['catatan_revisi'] ?? null,
        ];

        $tanggal = $berita['tanggal'] ? Carbon::parse($berita['tanggal'])->locale('id') : null;
        $kata = str_word_count(strip_tags($berita['isi']));

        $terkait = [];
        foreach ($item['terkait'] ?? [] as $t) {
            $terkait[] = [
                'id' => $t['id_berita'],
                'judul' => $t['judul_berita'],
                'foto' => ($t['is_gambar'] ?? false) ? ($t['sampul_url'] ?? null) : null,
                'tanggal' => ! empty($t['tanggal_publish']) ? Carbon::parse($t['tanggal_publish'])->locale('id')->translatedFormat('d F Y') : '-',
            ];
        }

        $isiMentah = (string) ($berita['isi'] ?? '');
        $isiHtml = BeritaController::bersihkanHtml($isiMentah);
        if ($isiHtml !== '' && ! preg_match('/<(p|h[2-4]|ul|ol|blockquote|figure|table)\b/i', $isiHtml)) {
            $baris = array_values(array_filter(preg_split('/\R{1,}/', trim(strip_tags($isiMentah)))));
            $isiHtml = implode('', array_map(fn ($p) => '<p>'.e($p).'</p>', $baris));
        }

        return view('admin.berita-detail', [ // ganti 'kaprodi.berita-detail' pada KaprodiBeritaController
            'berita' => $berita,
            'isiHtml' => $isiHtml,
            'paragraf' => array_values(array_filter(preg_split('/\R{1,}/', trim(strip_tags($isiMentah))))),
            'foto' => $isGambar ? $sampulUrl : null,
            'hariTanggal' => $tanggal ? $tanggal->translatedFormat('l, j F Y') : '-',
            'tanggalPendek' => $tanggal ? $tanggal->format('d/m/Y') : '-',
            'waktuBaca' => max(1, (int) ceil($kata / 200)).' menit',
            'terkait' => $terkait,
        ]);
    }

    public function edit(string $id)
    {
        $hasil = ApiClient::pengguna()->get('/berita/'.$id);
        $item = $hasil['data'];

        $berita = [
            'id' => $item['id_berita'],
            'judul' => $item['judul_berita'],
            'isi' => $item['isi_berita'],
            'status' => strtolower((string) ($item['status'] ?? 'draft')),
            'kegiatan_id' => $item['id_kegiatan'] ?? null,
            'kegiatan' => $item['nama_kategori'] ?? '',
            'sumber' => ! empty($item['id_kegiatan']) ? 'prev' : 'none',
            'foto' => $item['sampul_url'] ?? null,
        ];

        return view('admin.berita-edit', $this->opsi() + ['berita' => $berita]);
    }

    public function update(Request $request, string $id)
    {
        $payload = $request->only(['judul', 'isi', 'status', 'sumber', 'kegiatan_id', 'kategori', 'sampul_dokumen_id']);
        $berkas = $request->hasFile('foto') ? ['foto' => $request->file('foto')] : [];

        $hasil = ApiClient::pengguna()->put('/berita/'.$id, $payload, $berkas);

        return redirect()->route('admin.berita')->with('status', $hasil['message'] ?? 'Berita berhasil diperbarui.');
    }

    public function tinjau(Request $request, string $id)
    {
        $hasil = ApiClient::pengguna()->patch('/berita/'.$id.'/tinjau', $request->only(['aksi', 'catatan_revisi']));

        return redirect()->route('admin.berita')->with('status', $hasil['message'] ?? 'Tinjauan berita berhasil disimpan.');
    }

    public function destroy(string $id)
    {
        $hasil = ApiClient::pengguna()->delete('/berita/'.$id);

        return redirect()->route('admin.berita')->with('status', $hasil['message'] ?? 'Berita berhasil dihapus.');
    }
}
