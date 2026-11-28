<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use App\Support\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JenisDokumenController extends Controller
{
    public function index()
    {
        $hasil = ApiClient::pengguna()->get('/jenis-dokumen', ['per_page' => 100]);
        $rows = collect($hasil['data'] ?? [])->map(fn (array $item) => $this->bentukBaris($item))->all();

        return view('admin.jenis-dokumen', ['rows' => $rows]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        try {
            $hasil = ApiClient::pengguna()->post('/jenis-dokumen', [
                'nama_jenis_dokumen' => $data['name'],
                'keterangan' => $data['description'] ?? null,
            ]);
        } catch (ApiException $e) {
            return $this->responGagalApi($e);
        }

        return response()->json([
            'ok' => true,
            'row' => $this->bentukBaris($hasil['data'] ?? []),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $this->validasi($request);

        try {
            $hasil = ApiClient::pengguna()->put("/jenis-dokumen/{$id}", [
                'nama_jenis_dokumen' => $data['name'],
                'keterangan' => $data['description'] ?? null,
            ]);
        } catch (ApiException $e) {
            return $this->responGagalApi($e);
        }

        return response()->json([
            'ok' => true,
            'row' => $this->bentukBaris($hasil['data'] ?? []),
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            ApiClient::pengguna()->delete("/jenis-dokumen/{$id}");
        } catch (ApiException $e) {
            return $this->responGagalApi($e);
        }

        return response()->json(['ok' => true]);
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Nama jenis dokumen wajib diisi.',
            'name.max' => 'Nama jenis dokumen maksimal 100 karakter.',
        ]);
    }

    /** @param array<string, mixed> $item */
    private function bentukBaris(array $item): array
    {
        return [
            'id' => $item['id_jenis_dokumen'] ?? null,
            'name' => $item['nama_jenis_dokumen'] ?? '',
            'description' => $item['keterangan'] ?? null,
        ];
    }

    private function responGagalApi(ApiException $e): JsonResponse
    {
        $status = method_exists($e, 'getStatusCode')
            ? $e->getStatusCode()
            : ($e->status ?? ($e->getCode() ?: 422));

        if ($status < 400 || $status > 599) {
            $status = 422;
        }

        return response()->json([
            'message' => $e->getMessage(),
        ], $status);
    }
}
