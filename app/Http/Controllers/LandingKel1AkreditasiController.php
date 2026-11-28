<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use App\Support\ApiException;
use Illuminate\Support\Str;

class LandingKel1AkreditasiController extends Controller
{
    /** Halaman publik: menampilkan PDF akreditasi yang sedang aktif (diambil dari API). */
    public function index()
    {
        try {
            $aktif = ApiClient::publik()->get('/akreditasi/aktif')['data'] ?? null;
        } catch (ApiException $e) {
            // 404 = belum ada akreditasi aktif; kegagalan lain (mis. 503) diteruskan.
            if ($e->getStatusCode() !== 404) {
                throw $e;
            }
            $aktif = null;
        }

        return view('akreditasi', [
            'title' => 'Akreditasi',
            'subtitle' => 'Dokumen akreditasi Program Studi Teknologi Rekayasa Komputer Politeknik Negeri Semarang',
            'pdfUrl' => $aktif['link'] ?? null,
            'fileName' => $aktif
                ? Str::slug($aktif['lembaga'].' '.($aktif['tahun_terbit'] ?? '')).'.pdf'
                : 'akreditasi-trk.pdf',
        ]);
    }
}
