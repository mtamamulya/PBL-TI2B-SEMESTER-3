<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use App\Support\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KategoriKegiatanController extends Controller
{
    public function index()
    {
        try {
            $hasil = ApiClient::pengguna()->get('/kategori-kegiatan', ['per_page' => 100]);
            $rows = array_map(fn (array $item) => $this->bentukBaris($item), $hasil['data'] ?? []);
            $jenis = $this->daftarJenis();
            $apiError = null;
        } catch (ApiException $e) {
            $rows = [];
            $jenis = [];
            $apiError = $e->getMessage().' (Status: '.$e->getCode().')';
        }

        return view('admin.kategori-kegiatan', [
            'rows' => $rows,
            'jenis' => $jenis,
            'apiError' => $apiError,
        ]);
    }

    /** Halaman tambah: form di kiri, pratinjau surat di kanan. */
    public function create()
    {
        try {
            $jenis = $this->daftarJenis();
            $apiError = null;
        } catch (ApiException $e) {
            \Log::error('Gagal memuat form tambah kategori: '.$e->getMessage().' (Code: '.$e->getCode().')');
            $jenis = [];
            $apiError = $e->getMessage().' (Status: '.$e->getCode().')';
        }

        return view('admin.kategori-kegiatan-form', [
            'row' => null,
            'jenis' => $jenis,
            'apiError' => $apiError,
        ]);
    }

    public function edit(int $id)
    {
        try {
            $res = ApiClient::pengguna()->get('/kategori-kegiatan/'.$id);
            $item = $this->bentukBaris($res['data'] ?? []);
            $jenis = $this->daftarJenis();
            $apiError = null;
        } catch (ApiException $e) {
            \Log::error('Gagal memuat form edit kategori: '.$e->getMessage().' (Code: '.$e->getCode().')');

            return redirect()
                ->route('admin.kegiatan.kategori-kegiatan.index')
                ->with('error', 'Gagal mengambil data dari API: '.$e->getMessage());
        }

        return view('admin.kategori-kegiatan-form', [
            'row' => $item,
            'jenis' => $jenis,
            'apiError' => $apiError,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $hasil = ApiClient::pengguna()->post('/kategori-kegiatan', $this->payloadApi($request));

            return response()->json([
                'ok' => true,
                'row' => $this->bentukBaris($hasil['data']),
            ]);
        } catch (ApiException $e) {
            return $this->responsGagal($e);
        }
    }

    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $hasil = ApiClient::pengguna()->put('/kategori-kegiatan/'.$id, $this->payloadApi($request));

            return response()->json([
                'ok' => true,
                'row' => $this->bentukBaris($hasil['data']),
            ]);
        } catch (ApiException $e) {
            return $this->responsGagal($e);
        }
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            ApiClient::pengguna()->delete('/kategori-kegiatan/'.$id);

            return response()->json(['ok' => true]);
        } catch (ApiException $e) {
            return $this->responsGagal($e);
        }
    }

    /** Mengubah payload dari form web agar sesuai dengan format kolom API Backend. */
    private function payloadApi(Request $request): array
    {
        $payload = [
            'jenis' => $request->input('jenis'),
            'id_jenis_kegiatan' => $request->input('id_jenis_kegiatan'),
            'nama_kategori' => $request->input('nama_kategori', $request->input('name')),
            'deskripsi' => $request->input('deskripsi', $request->input('description')),
            'kebutuhan' => $request->input('kebutuhan', 'kegiatan'),
            'isi_surat' => $request->input('isi_surat', $request->input('template_surat')),
        ];

        if ($request->has('isian')) {
            $payload['isian'] = $request->input('isian');
        }

        return array_filter($payload, fn ($v) => $v !== null);
    }

    /** Memetakan respons JSON dari API agar kunci lama (`id`, `name`, `description`) tetap terbaca di Blade. */
    private function bentukBaris(array $item): array
    {
        return array_merge($item, [
            'id' => $item['id_kategori'] ?? null,
            'name' => $item['nama_kategori'] ?? '',
            'description' => $item['deskripsi'] ?? null,
        ]);
    }

    /** Mengambil daftar nama jenis kegiatan dari API. */
    private function daftarJenis(): array
    {
        $hasil = ApiClient::pengguna()->get('/jenis-kegiatan');

        return array_column($hasil['data'] ?? [], 'nama_jenis_kegiatan');
    }

    /** Mengambil daftar tipe isian form (teks, angka, berkas, dosen, dll.) dari API. */
    private function daftarTipeIsian(): array
    {
        $hasil = ApiClient::pengguna()->get('/tipe-isian');

        return $hasil['data'] ?? [];
    }

    /** Meneruskan error HTTP (422, 404, 409, 403) dari API ke JSON response web. */
    private function responsGagal(ApiException $e): JsonResponse
    {
        $status = $e->getCode();
        if ($status < 400 || $status > 599) {
            $status = 500;
        }

        return response()->json([
            'ok' => false,
            'message' => $e->getMessage(),
            'errors' => property_exists($e, 'errors') ? $e->errors : null,
        ], $status);
    }
}
