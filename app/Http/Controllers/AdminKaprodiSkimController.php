<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use App\Support\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Halaman master Skim (admin + kaprodi, keduanya bisa CRUD).
 * Data lewat REST API /api/v1/skim (dibuat Virgi di app/BackendKel1/Skim).
 *
 * MODE DUMMY (sementara, selama API belum ada): isi SKIM_DUMMY=true di .env.
 * Data dummy disimpan di session sehingga tambah/ubah/hapus terasa nyata.
 * Hapus baris SKIM_DUMMY dari .env setelah API Virgi masuk.
 */
class AdminKaprodiSkimController extends Controller
{
    public function index(Request $request)
    {
        [$prefix, $layout] = $this->peran($request);

        if ($this->dummy()) {
            $rows = array_values($this->dummyData($request));
        } else {
            $hasil = ApiClient::pengguna()->get('/skim', ['per_page' => 100]);
            $rows = collect($hasil['data'] ?? [])->map(fn (array $i) => $this->bentukBaris($i))->values()->all();
        }

        return view('admin.skim', compact('rows', 'prefix', 'layout'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        if ($this->dummy()) {
            $semua = $this->dummyData($request);
            $id = (max(array_keys($semua) ?: [0]) + 1);
            $semua[$id] = ['id' => $id, 'name' => $data['name'], 'total' => 0];
            $request->session()->put('skim_dummy', $semua);

            return response()->json(['ok' => true, 'row' => $semua[$id]]);
        }

        try {
            $hasil = ApiClient::pengguna()->post('/skim', ['nama_skim' => $data['name']]);
        } catch (ApiException $e) {
            return $this->responGagalApi($e);
        }

        return response()->json(['ok' => true, 'row' => $this->bentukBaris($hasil['data'] ?? [])]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $this->validasi($request);

        if ($this->dummy()) {
            $semua = $this->dummyData($request);
            if (! isset($semua[(int) $id])) {
                return response()->json(['message' => 'Skim tidak ditemukan.'], 404);
            }
            $semua[(int) $id]['name'] = $data['name'];
            $request->session()->put('skim_dummy', $semua);

            return response()->json(['ok' => true, 'row' => $semua[(int) $id]]);
        }

        try {
            $hasil = ApiClient::pengguna()->put("/skim/{$id}", ['nama_skim' => $data['name']]);
        } catch (ApiException $e) {
            return $this->responGagalApi($e);
        }

        return response()->json(['ok' => true, 'row' => $this->bentukBaris($hasil['data'] ?? [])]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        if ($this->dummy()) {
            $semua = $this->dummyData($request);
            if (! isset($semua[(int) $id])) {
                return response()->json(['message' => 'Skim tidak ditemukan.'], 404);
            }
            if (($semua[(int) $id]['total'] ?? 0) > 0) {
                return response()->json(['message' => 'Skim masih dipakai kegiatan dan tidak dapat dihapus.'], 409);
            }
            unset($semua[(int) $id]);
            $request->session()->put('skim_dummy', $semua);

            return response()->json(['ok' => true]);
        }

        try {
            ApiClient::pengguna()->delete("/skim/{$id}");
        } catch (ApiException $e) {
            return $this->responGagalApi($e);
        }

        return response()->json(['ok' => true]);
    }

    /** @return array{0:string,1:string} */
    private function peran(Request $request): array
    {
        $kaprodi = $request->routeIs('kaprodi.*');

        return [$kaprodi ? 'kaprodi' : 'admin', $kaprodi ? 'layouts.kaprodi' : 'layouts.admin'];
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ], [
            'name.required' => 'Nama skim wajib diisi.',
            'name.max' => 'Nama skim maksimal 100 karakter.',
        ]);
    }

    /** @param array<string, mixed> $item */
    private function bentukBaris(array $item): array
    {
        return [
            'id' => $item['id_skim'] ?? null,
            'name' => $item['nama_skim'] ?? '',
            'total' => (int) ($item['total_kegiatan'] ?? 0),
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

        return response()->json(['message' => $e->getMessage()], $status);
    }

    private function dummy(): bool
    {
        return ! app()->runningUnitTests()
            && filter_var(env('SKIM_DUMMY', false), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<int, array{id:int,name:string,total:int}> */
    private function dummyData(Request $request): array
    {
        return $request->session()->get('skim_dummy', [
            1 => ['id' => 1, 'name' => 'Penelitian Dasar', 'total' => 0],
            2 => ['id' => 2, 'name' => 'Penelitian Terapan', 'total' => 2],
            3 => ['id' => 3, 'name' => 'Pengabdian Kepada Masyarakat', 'total' => 0],
        ]);
    }
}
