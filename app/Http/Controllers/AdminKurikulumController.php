<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Kurikulum TRK (admin; kaprodi memakai kelas turunan). Seluruh data lewat HTTP ke API (ApiClient),
 * jadi bila server API mati halaman menjawab 503. Bentuk baris untuk view tetap seperti KurikulumStore::forAdmin().
 */
class AdminKurikulumController extends Controller
{
    private const API = '/kurikulum';

    protected string $tampilan = 'admin.kurikulum';

    public function index()
    {
        $kurikulum = [];
        $halaman = 1;

        // API membatasi per_page maksimum 100; DataTables butuh semua baris, jadi ambil tiap halaman.
        do {
            $hasil = ApiClient::pengguna()->get(self::API, ['per_page' => 100, 'page' => $halaman]);
            foreach ($hasil['data'] ?? [] as $item) {
                $kurikulum[] = self::untukView($item);
            }
            $terakhir = max(1, (int) ($hasil['meta']['last_page'] ?? 1));
            $halaman++;
        } while ($halaman <= $terakhir);

        return view($this->tampilan, compact('kurikulum'));
    }

    public function store(Request $request): JsonResponse
    {
        $hasil = ApiClient::pengguna()->post(self::API, $this->payload($request), $this->berkas($request));

        return response()->json(['data' => self::untukView($hasil['data'])], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $hasil = ApiClient::pengguna()->put(self::API.'/'.$id, $this->payload($request), $this->berkas($request));

        return response()->json(['data' => self::untukView($hasil['data'])]);
    }

    public function status(string $id): JsonResponse
    {
        $hasil = ApiClient::pengguna()->patch(self::API.'/'.$id.'/status');

        return response()->json(['data' => self::untukView($hasil['data'])]);
    }

    public function destroy(string $id): JsonResponse
    {
        ApiClient::pengguna()->delete(self::API.'/'.$id);

        return response()->json(['ok' => true]);
    }

    /** Nama field form web -> nama field API. */
    private function payload(Request $request): array
    {
        return [
            'nama_kurikulum' => $request->input('name'),
            'deskripsi' => $request->input('description'),
            'tahun_terbit' => $request->input('year'),
        ];
    }

    /** @return array<string, UploadedFile> */
    private function berkas(Request $request): array
    {
        return $request->hasFile('pdf') ? ['pdf' => $request->file('pdf')] : [];
    }

    /** Bentuk API (nama kolom database) -> bentuk lama yang dipakai Blade dan JavaScript. */
    public static function untukView(array $k): array
    {
        return [
            'id' => (string) $k['id_kurikulum'],
            'name' => (string) $k['nama_kurikulum'],
            'description' => (string) ($k['deskripsi'] ?? ''),
            'year' => (string) ($k['tahun_terbit'] ?? ''),
            'status' => ! empty($k['status']) ? 'aktif' : 'nonaktif',
            'pdf_url' => $k['link'] ?? null,
        ];
    }
}
