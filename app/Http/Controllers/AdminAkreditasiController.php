<?php

namespace App\Http\Controllers;

use App\Repositories\AkreditasiStore;
use App\Support\ApiClient;
use App\Support\ApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Akreditasi (admin saja). Data lewat HTTP ke API (ApiClient): bila server API mati halaman menjawab 503.
 * Hanya SATU akreditasi yang boleh aktif (aturan ada di API). Bentuk baris untuk view tetap seperti AkreditasiStore::forAdmin().
 */
class AdminAkreditasiController extends Controller
{
    private const API = '/akreditasi';

    public function index()
    {
        return view('admin.akreditasi', [
            'akreditasi' => $this->semua(),
            'lembagaList' => AkreditasiStore::LEMBAGA,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->payload($request) + ['aktifkan' => $request->boolean('aktifkan') ? 1 : 0];
        $hasil = ApiClient::pengguna()->post(self::API, $data, $this->berkas($request));

        // Kirim ulang semua baris: status akreditasi lain mungkin ikut berubah.
        return response()->json(['data' => $this->semua(), 'item' => self::untukView($hasil['data'])], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        ApiClient::pengguna()->put(self::API.'/'.$id, $this->payload($request), $this->berkas($request));

        return response()->json(['data' => $this->semua()]);
    }

    /** Jadikan akreditasi ini yang aktif (yang lain otomatis nonaktif). */
    public function status(string $id): JsonResponse
    {
        ApiClient::pengguna()->post(self::API.'/'.$id.'/aktifkan');

        return response()->json(['data' => $this->semua()]);
    }

    public function destroy(string $id): JsonResponse
    {
        try {
            ApiClient::pengguna()->delete(self::API.'/'.$id);
        } catch (ApiException $e) {
            if ($e->getStatusCode() === 409) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            throw $e;
        }

        return response()->json(['data' => $this->semua()]);
    }

    /** @return array<int, array<string, mixed>> */
    private function semua(): array
    {
        $rows = [];
        $halaman = 1;
        do {
            $hasil = ApiClient::pengguna()->get(self::API, ['per_page' => 100, 'page' => $halaman]);
            foreach ($hasil['data'] ?? [] as $item) {
                $rows[] = self::untukView($item);
            }
            $terakhir = max(1, (int) ($hasil['meta']['last_page'] ?? 1));
            $halaman++;
        } while ($halaman <= $terakhir);

        return $rows;
    }

    /** Nama field form web -> nama field API. */
    private function payload(Request $request): array
    {
        return [
            'lembaga' => $request->input('lembaga'),
            'nomor_sk' => $request->input('nomor_sk'),
            'tahun_terbit' => $request->input('year'),
            'berlaku_sampai' => $request->input('berlaku_sampai'),
        ];
    }

    /** @return array<string, UploadedFile> */
    private function berkas(Request $request): array
    {
        return $request->hasFile('pdf') ? ['pdf' => $request->file('pdf')] : [];
    }

    /** Bentuk API -> bentuk lama untuk Blade dan JavaScript. */
    public static function untukView(array $a): array
    {
        return [
            'id' => (string) $a['id_akreditasi'],
            'lembaga' => (string) $a['lembaga'],
            'peringkat' => '',
            'nomor_sk' => (string) ($a['nomor_sk'] ?? ''),
            'year' => (string) ($a['tahun_terbit'] ?? ''),
            'berlaku_sampai' => (string) ($a['berlaku_sampai'] ?? ''),
            'status' => ! empty($a['status']) ? 'aktif' : 'nonaktif',
            'pdf_url' => $a['link'] ?? null,
        ];
    }
}
