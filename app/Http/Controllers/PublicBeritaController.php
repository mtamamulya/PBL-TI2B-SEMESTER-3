<?php

namespace App\Http\Controllers;

use App\BackendKel1\Berita\BeritaController;
use App\Support\ApiClient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

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
                $teksPolos = trim(strip_tags(implode(' ', (array) ($berita['paragraf'] ?? []))));
                $kata = count(array_filter(preg_split('/\s+/u', $teksPolos)));
                $berita['waktu_baca'] = max(1, (int) ceil($kata / 200)).' menit';
                $terkait = collect($configBerita)
                    ->except($slug)
                    ->take(5)
                    ->all();
            }
        }

        abort_unless($berita !== null, 404);

        // Catat view count pengunjung unik (menggunakan cache database yang persisten & aman)
        $cacheKey = 'berita_views_'.$slug;
        $sessionKey = 'sudah_baca_berita_'.$slug;

        if (! session()->has($sessionKey)) {
            if (! Cache::has($cacheKey)) {
                Cache::forever($cacheKey, 1);
            } else {
                Cache::increment($cacheKey);
            }
            session()->put($sessionKey, true);
        }

        $berita['dilihat'] = (int) Cache::get($cacheKey, 1);

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
        $isHtml = strip_tags($isi) !== $isi;
        $isiHtml = $isHtml ? BeritaController::bersihkanHtml($isi) : null;

        // Bersihkan teks untuk ringkasan/cuplikan cuplikan (tanpa kode HTML, tag gambar, atau &nbsp;)
        $teksMurni = html_entity_decode(strip_tags($isi), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $teksMurni = trim(preg_replace('/\s+/u', ' ', $teksMurni));
        $ringkasan = Str::limit($teksMurni, 260);

        if ($isHtml) {
            preg_match_all('/<p\b[^>]*>(.*?)<\/p>/is', $isi, $matches);
            $paragrafBersih = array_map(function ($p) {
                return trim(html_entity_decode(strip_tags($p), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }, $matches[1] ?? []);
            $paragraf = array_values(array_filter($paragrafBersih));
            if (empty($paragraf)) {
                $paragraf = [$teksMurni];
            }
        } else {
            $paragraf = preg_split("/\r\n\r\n|\n\n|\r\r/", trim($isi));
            $paragraf = array_values(array_filter(array_map('trim', (array) $paragraf)));
            if (empty($paragraf)) {
                $paragraf = [trim($isi)];
            }
        }

        $rawTanggal = $b['tanggal_publish'] ?? $b['created_at'] ?? null;
        $tgl = $rawTanggal ? Carbon::parse($rawTanggal)->locale('id') : Carbon::now()->locale('id');
        $kata = count(array_filter(preg_split('/\s+/u', $teksMurni)));
        $waktuBaca = max(1, (int) ceil($kata / 200)).' menit';

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
            'ringkasan' => $ringkasan,
            'isi_html' => $isiHtml,
        ];
    }
}
