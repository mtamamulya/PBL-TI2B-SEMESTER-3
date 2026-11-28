<?php

namespace App\Http\Controllers;

use App\Models\Prestasi;
use App\Repositories\DosenStore;
use App\Support\ApiClient;
use Illuminate\Support\Str;

class LandingKel1HomeController extends Controller
{
    public function __construct(private DosenStore $dosenStore) {}

    public function index()
    {
        $prestasi = Prestasi::query()
            ->with('kegiatan:id_kegiatan,nama_kegiatan')
            ->latest('id_prestasi')
            ->take(8)
            ->get()
            ->map(function (Prestasi $record) {
                $badge = $record->capaian ?: ucfirst($record->jenis_prestasi);
                $event = $record->kegiatan?->nama_kegiatan;

                return [
                    'id' => $record->getKey(),
                    'slug' => null,
                    'badge' => $badge,
                    'color' => match (strtolower($badge)) {
                        'juara 1' => '#c09900',
                        'juara 2' => '#949494',
                        'juara 3' => '#AD5C00',
                        default => '#0B3A8C',
                    },
                    'title' => $event ?: $record->nama_lomba,
                    'sub' => $record->bidang_lomba,
                    'catatan' => $record->catatan,
                ];
            });

        $counts = Prestasi::query()
            ->selectRaw('jenis_prestasi, COUNT(*) as total')
            ->groupBy('jenis_prestasi')
            ->pluck('total', 'jenis_prestasi');
        $stats = [
            [Prestasi::query()->count(), 'Total prestasi'],
            [$counts->get('akademik', 0), 'Prestasi akademik'],
            [$counts->get('non-akademik', 0), 'Prestasi non-akademik'],
        ];

        // Dosen AKTIF dari database (mode database). Mode json: null, jadi view memakai data sementaranya.
        $dosen = $this->dosenStore->publik();

        // Berita terbaru dari API (publik, tanpa token)
        $news = $this->ambilBerita();

        return view('home', compact('prestasi', 'stats', 'dosen', 'news'));
    }

    /** Ambil 6 berita terbaru yang sudah publish via REST API. */
    private function ambilBerita(): array
    {
        try {
            $hasil = ApiClient::publik()->get('/berita', ['per_page' => 6]);
            $items = $hasil['data'] ?? [];
        } catch (\Throwable) {
            $items = [];
        }

        return collect($items)
            ->filter(fn (array $item) => ($item['status'] ?? '') === 'publish')
            ->take(6)
            ->map(fn (array $item) => [
                'id' => $item['id_berita'] ?? null,
                'title' => $item['judul_berita'] ?? 'Tanpa Judul',
                'text' => Str::limit(strip_tags($item['isi_berita'] ?? ''), 120),
                'img' => $item['sampul'] ?? null,
            ])
            ->values()
            ->all();
    }
}
