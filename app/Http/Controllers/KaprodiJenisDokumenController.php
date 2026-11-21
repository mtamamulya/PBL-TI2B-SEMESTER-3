<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use Illuminate\Http\Request;

class KaprodiJenisDokumenController extends Controller
{
    /** Baca saja: ambil dari REST API via ApiClient. */
    public function index(Request $request)
    {
        $params = ['per_page' => 100];
        if ($request->filled('q')) {
            $params['q'] = $request->query('q');
        }

        $hasil = ApiClient::pengguna()->get('/jenis-dokumen', $params);

        $rows = collect($hasil['data'] ?? [])->map(fn (array $item) => [
            'id' => $item['id_jenis_dokumen'] ?? null,
            'name' => $item['nama_jenis_dokumen'] ?? '',
            'description' => $item['keterangan'] ?? null,
        ])->values()->all();

        return view('kaprodi.jenis-dokumen', [
            'rows' => $rows,
        ]);
    }
}
