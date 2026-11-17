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
            'tipeIsian' => $this->daftarTipeIsian(),
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
                ->route('admin.kegiatan.kategori-kegiatan')
                ->with('error', 'Gagal mengambil data dari API: '.$e->getMessage());
        }

        return view('admin.kategori-kegiatan-form', [
            'row' => $item,
            'jenis' => $jenis,
            'tipeIsian' => $this->daftarTipeIsian(),
            'apiError' => $apiError,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $hasil = ApiClient::pengguna()->post('/kategori-kegiatan', $this->payloadApi($request));

            return response()->json([
                'ok' => true,
                'row' => $this->bentukBaris($hasil['data'] ?? []),
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
                'row' => $this->bentukBaris($hasil['data'] ?? []),
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

    private function payloadApi(Request $request): array
    {
        $payload = [
            'jenis' => $request->input('jenis'),
            'id_jenis_kegiatan' => $request->input('id_jenis_kegiatan'),
            'nama_kategori' => $request->input('nama_kategori', $request->input('name')),
            'deskripsi' => $request->input('deskripsi', $request->input('description')),
            'kebutuhan' => $request->input('kebutuhan', 'surat'),
            'isi_surat' => $request->input('isi_surat', $request->input('template_surat')),
        ];

        if ($request->has('lampiran_peserta')) {
            $payload['lampiran_peserta'] = $request->boolean('lampiran_peserta');
        }

        // Bentuk isian dari form sudah sama dengan API; cukup disaring field-nya.
        // Kunci jumlah_isian selalu dikirim (null = tanpa batas).
        if ($request->has('isian')) {
            $payload['isian'] = array_values(array_map(function ($item) {
                $item = is_array($item) ? $item : [];
                $baris = [
                    'input' => trim((string) ($item['input'] ?? '')),
                    'jenis_input' => $item['jenis_input'] ?? 'teks',
                    'wajib' => filter_var($item['wajib'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'jumlah_isian' => array_key_exists('jumlah_isian', $item) && $item['jumlah_isian'] !== null
                        ? (int) $item['jumlah_isian']
                        : null,
                ];
                if (! empty($item['id_isian_kategori'])) {
                    $baris['id_isian_kategori'] = (int) $item['id_isian_kategori'];
                }

                return $baris;
            }, (array) $request->input('isian', [])));
        }

        $payload = array_filter($payload, fn ($v) => $v !== null);

        // Template/keterangan yang dikosongkan harus ikut terkirim agar bisa dihapus
        foreach (['isi_surat', 'deskripsi'] as $k) {
            if ($request->hasAny([$k, $k === 'isi_surat' ? 'template_surat' : 'description'])) {
                $payload[$k] ??= '';
            }
        }

        return $payload;
    }

    private function bentukBaris(array $item): array
    {
        return array_merge($item, [
            'id' => $item['id_kategori'] ?? null,
            'name' => $item['nama_kategori'] ?? '',
            'description' => $item['deskripsi'] ?? null,
            'template_surat' => $item['isi_surat'] ?? null,
            'isian' => $item['isian'] ?? [],
        ]);
    }

    private function daftarJenis(): array
    {
        $hasil = ApiClient::pengguna()->get('/jenis-kegiatan');

        return array_column($hasil['data'] ?? [], 'nama_jenis_kegiatan');
    }

    private function daftarTipeIsian(): array
    {
        try {
            $hasil = ApiClient::pengguna()->get('/tipe-isian');

            return $hasil['data'] ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

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