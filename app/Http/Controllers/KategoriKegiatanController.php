<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KategoriKegiatanController extends Controller
{
    private const JENIS_DEFAULT = ['Kegiatan Akademik', 'Kegiatan dosen'];

    public function index()
    {
        $hasil = ApiClient::pengguna()->get('/kategori-kegiatan', ['per_page' => 100]);
        $items = $hasil['data'] ?? [];

        $rows = array_map(fn (array $r) => $this->bentukBaris($r), $items);
        $jenis = $this->daftarJenis($items);

        return view('admin.kategori-kegiatan', [
            'rows' => $rows,
            'jenis' => $jenis,
        ]);
    }

    public function create()
    {
        $hasil = ApiClient::pengguna()->get('/kategori-kegiatan', ['per_page' => 100]);

        return view('admin.kategori-kegiatan-form', [
            'row' => null,
            'jenis' => $this->daftarJenis($hasil['data'] ?? []),
        ]);
    }

    public function edit(string $id)
    {
        $hasil = ApiClient::pengguna()->get("/kategori-kegiatan/{$id}");
        $daftar = ApiClient::pengguna()->get('/kategori-kegiatan', ['per_page' => 100]);

        return view('admin.kategori-kegiatan-form', [
            'row' => $this->bentukBaris($hasil['data'] ?? []),
            'jenis' => $this->daftarJenis($daftar['data'] ?? []),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $this->siapkanPayload($request);
        $hasil = ApiClient::pengguna()->post('/kategori-kegiatan', $payload);

        return response()->json([
            'ok' => true,
            'row' => $this->bentukBaris($hasil['data'] ?? []),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $payload = $this->siapkanPayload($request);
        $hasil = ApiClient::pengguna()->put("/kategori-kegiatan/{$id}", $payload);

        return response()->json([
            'ok' => true,
            'row' => $this->bentukBaris($hasil['data'] ?? []),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        ApiClient::pengguna()->delete("/kategori-kegiatan/{$id}");

        return response()->json(['ok' => true]);
    }

    private function siapkanPayload(Request $request): array
    {
        $isiSurat = $request->input('isi_surat', $request->input('template_surat'));
        $jenisNama = (string) $request->input('jenis', '');
        $idJenis = $this->cariIdJenis($jenisNama);

        return array_filter([
            'id_jenis_kegiatan' => $idJenis,
            'jenis' => $jenisNama,
            'nama_kategori' => $request->input('name'),
            'name' => $request->input('name'),
            'deskripsi' => $request->input('description'),
            'description' => $request->input('description'),
            'isi_surat' => $isiSurat,
        ], fn ($v) => $v !== null);
    }

    private function cariIdJenis(string $namaJenis): ?int
    {
        if ($namaJenis === '') {
            return null;
        }

        try {
            $hasil = ApiClient::pengguna()->get('/kategori-kegiatan', ['per_page' => 100]);
            foreach (($hasil['data'] ?? []) as $item) {
                $nama = $item['jenis'] ?? ($item['nama_jenis_kegiatan'] ?? '');
                if (strcasecmp($nama, $namaJenis) === 0 && isset($item['id_jenis_kegiatan'])) {
                    return (int) $item['id_jenis_kegiatan'];
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }

    private function bentukBaris(array $r): array
    {
        return [
            'id' => $r['id_kategori'] ?? ($r['id'] ?? null),
            'name' => $r['nama_kategori'] ?? ($r['name'] ?? ''),
            'jenis' => $r['jenis'] ?? ($r['nama_jenis_kegiatan'] ?? ''),
            'description' => $r['deskripsi'] ?? ($r['description'] ?? ''),
            'isi_surat' => $r['isi_surat'] ?? null,
        ];
    }

    private function daftarJenis(array $items): array
    {
        $dariApi = array_values(array_unique(array_filter(array_map(
            fn (array $r) => $r['jenis'] ?? ($r['nama_jenis_kegiatan'] ?? null),
            $items
        ))));

        return $dariApi !== [] ? $dariApi : self::JENIS_DEFAULT;
    }
}
