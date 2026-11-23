<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PublicBeritaController extends Controller
{
    // Halaman daftar berita: /kabar (tujuan tombol "Muat lebih banyak")
    public function index(Request $request)
    {
        $semua = $this->ambilSemuaBerita();

        $perHalaman = 4;
        $halaman = LengthAwarePaginator::resolveCurrentPage();

        $berita = new LengthAwarePaginator(
            $semua->forPage($halaman, $perHalaman)->values(),
            $semua->count(),
            $perHalaman,
            $halaman,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $terbaru = $semua->take(3);
        $prestasi = $semua->filter(fn ($item) => strtolower($item['kategori'] ?? '') === 'prestasi')->take(3);
        if ($prestasi->isEmpty()) {
            $prestasi = $semua->skip(3)->take(3);
        }

        return view('berita-index', compact('berita', 'terbaru', 'prestasi'));
    }

    public function show(string $slug)
    {
        $berita = null;
        $terkait = [];

        // 1. Jika identifier adalah angka, coba ambil via API
        if (is_numeric($slug)) {
            try {
                $response = ApiClient::publik()->get('/berita/'.$slug);
                $data = $response['data'] ?? null;
                if ($data && ($data['status'] ?? '') === 'publish') {
                    $berita = $this->formatBerita($data);
                    $terkait = collect($data['terkait'] ?? [])->map(function ($t) {
                        $tgl = ! empty($t['tanggal_publish']) ? Carbon::parse($t['tanggal_publish'])->locale('id') : null;

                        return [
                            'id' => $t['id_berita'],
                            'slug' => (string) $t['id_berita'],
                            'judul' => $t['judul_berita'] ?? 'Tanpa Judul',
                            'gambar' => ! empty($t['sampul']) ? asset('storage/'.$t['sampul']) : asset('images/logo.png'),
                            'tanggal_ringkas' => $tgl ? $tgl->translatedFormat('d M Y') : '-',
                        ];
                    })->all();
                }
            } catch (\Throwable) {
                // Fallback ke config('berita')
            }
        }

        // 2. Fallback ke config('berita') jika belum ditemukan
        if (! $berita) {
            $configBerita = config('berita', []);
            if (isset($configBerita[$slug])) {
                $berita = $configBerita[$slug];
                $terkait = collect($configBerita)
                    ->except($slug)
                    ->take(4)
                    ->all();
            }
        }

        abort_unless($berita !== null, 404);

        return view('berita-show', compact('berita', 'terkait'));
    }

    private function ambilSemuaBerita()
    {
        try {
            $response = ApiClient::publik()->get('/berita', ['per_page' => 100]);
            $items = $response['data'] ?? [];
            if (! empty($items)) {
                return collect($items)
                    ->filter(fn (array $item) => ($item['status'] ?? '') === 'publish')
                    ->map(fn (array $b) => $this->formatBerita($b))
                    ->values();
            }
        } catch (\Throwable) {
            // Fallback
        }

        return collect(config('berita', []))->values();
    }

    private function formatBerita(array $b): array
    {
        $isi = $b['isi_berita'] ?? '';
        $paragraf = preg_split("/\r\n\r\n|\n\n|\r\r/", trim($isi));
        $paragraf = array_values(array_filter(array_map('trim', (array) $paragraf)));
        if (empty($paragraf)) {
            $paragraf = [trim($isi)];
        }

        $rawTanggal = $b['tanggal_publish'] ?? $b['created_at'] ?? null;
        $tgl = $rawTanggal ? Carbon::parse($rawTanggal)->locale('id') : Carbon::now()->locale('id');
        $wordCount = str_word_count(strip_tags($isi));
        $waktuBaca = max(1, (int) ceil($wordCount / 200)).' menit';

        return [
            'id' => $b['id_berita'] ?? null,
            'slug' => (string) ($b['id_berita'] ?? ''),
            'judul' => $b['judul_berita'] ?? 'Tanpa Judul',
            'penulis' => 'Humas TRK',
            'kategori' => $b['nama_kategori'] ?? 'Umum',
            'tanggal_pendek' => $tgl->format('d/m/Y'),
            'tanggal_panjang' => $tgl->translatedFormat('l, j F Y'),
            'tanggal_ringkas' => $tgl->translatedFormat('d M Y'),
            'jam' => $tgl->format('H:i'),
            'dilihat' => 0,
            'waktu_baca' => $waktuBaca,
            'gambar' => ! empty($b['sampul']) ? asset('storage/'.$b['sampul']) : asset('images/logo.png'),
            'paragraf' => $paragraf,
        ];
    }
}
