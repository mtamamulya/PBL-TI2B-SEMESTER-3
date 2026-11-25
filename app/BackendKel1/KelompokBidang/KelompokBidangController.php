<?php

namespace App\BackendKel1\KelompokBidang;

use App\Http\Controllers\Controller;
use App\Models\KelompokBidang;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * GET  /api/v1/kelompok-bidang, /kelompok-bidang/opsi, /kelompok-bidang/{id}   (semua pengguna yang login, termasuk dosen)
 * POST/PUT/DELETE /api/v1/kelompok-bidang                                       (admin dan kaprodi)
 *
 * Kelompok bidang dikelola admin/kaprodi dan dipakai dosen (penelitian/kegiatan).
 * `id_rujukan` membuatnya bertingkat: kelompok bidang bisa merujuk ke kelompok bidang lain (induk).
 */
class KelompokBidangController extends Controller
{
    /** Peran yang boleh menambah, mengubah, dan menghapus. */
    private const PERAN_TULIS = ['admin', 'kaprodi'];

    /** Daftar berhalaman (untuk tabel admin/kaprodi). */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'id_rujukan' => ['nullable', 'integer'],
            'tanpa_rujukan' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $p = KelompokBidang::query()
            ->with('rujukan:id_kelompok_bidang,nama_kelompok_bidang')
            ->withCount(self::hitungan())
            ->when($request->filled('q'), fn ($q) => $q->where('nama_kelompok_bidang', 'like', '%'.$request->query('q').'%'))
            ->when($request->filled('id_rujukan'), fn ($q) => $q->where('id_rujukan', (int) $request->query('id_rujukan')))
            ->when($request->boolean('tanpa_rujukan'), fn ($q) => $q->whereNull('id_rujukan'))
            ->orderBy('nama_kelompok_bidang')
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::paginated($p, $p->getCollection()->map(fn ($k) => self::bentuk($k))->all());
    }

    /** Semua kelompok bidang tanpa halaman, ringkas, untuk dropdown (form dosen, pilihan rujukan). */
    public function opsi(): JsonResponse
    {
        $rows = KelompokBidang::query()
            ->orderBy('nama_kelompok_bidang')
            ->get(['id_kelompok_bidang', 'nama_kelompok_bidang', 'id_rujukan'])
            ->map(fn ($k) => [
                'id_kelompok_bidang' => $k->id_kelompok_bidang,
                'nama_kelompok_bidang' => $k->nama_kelompok_bidang,
                'id_rujukan' => $k->id_rujukan,
            ])
            ->all();

        return ApiResponse::ok($rows);
    }

    public function show(int $id): JsonResponse
    {
        $k = $this->cari($id);

        return $k ? ApiResponse::ok(self::bentuk($k, true)) : ApiResponse::gagal('Kelompok bidang tidak ditemukan');
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->tidakBolehMenulis($request)) {
            return $this->dilarang();
        }

        $data = $this->validasi($request);

        try {
            $k = KelompokBidang::create(Arr::only($data, ['nama_kelompok_bidang', 'id_rujukan']));
        } catch (QueryException) {
            return ApiResponse::gagal('Kelompok bidang gagal disimpan. Periksa isian form.', 409);
        }

        return ApiResponse::ok(self::bentuk($this->cari($k->getKey()), true), 'Kelompok bidang dibuat.')->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($this->tidakBolehMenulis($request)) {
            return $this->dilarang();
        }

        $k = KelompokBidang::query()->find($id);
        if (! $k) {
            return ApiResponse::gagal('Kelompok bidang tidak ditemukan');
        }

        $data = $this->validasi($request, $id);

        // `id_rujukan` tidak dikirim = rujukan dibiarkan. Dikirim null = dilepas (jadi kelompok bidang tingkat atas).
        $ubah = ['nama_kelompok_bidang' => $data['nama_kelompok_bidang']];
        if (array_key_exists('id_rujukan', $data)) {
            $ubah['id_rujukan'] = $data['id_rujukan'];
        }

        try {
            $k->update($ubah);
        } catch (QueryException) {
            return ApiResponse::gagal('Kelompok bidang gagal diperbarui. Periksa isian form.', 409);
        }

        return ApiResponse::ok(self::bentuk($this->cari($id), true), 'Kelompok bidang diperbarui.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($this->tidakBolehMenulis($request)) {
            return $this->dilarang();
        }

        $k = KelompokBidang::query()->find($id);
        if (! $k) {
            return ApiResponse::gagal('Kelompok bidang tidak ditemukan');
        }

        if (self::adaKolomKegiatan() && $k->kegiatan()->exists()) {
            return ApiResponse::gagal('Kelompok bidang ini sudah dipakai oleh kegiatan, jadi tidak bisa dihapus.', 409);
        }

        if ($k->turunan()->exists()) {
            return ApiResponse::gagal('Kelompok bidang ini masih menjadi rujukan kelompok bidang lain. Pindahkan atau hapus turunannya dulu.', 409);
        }

        try {
            $k->delete();
        } catch (QueryException) {
            return ApiResponse::gagal('Kelompok bidang ini masih dipakai data lain, jadi tidak bisa dihapus.', 409);
        }

        return ApiResponse::ok(null, 'Kelompok bidang dihapus.');
    }

    /** Bentuk respons. Nama kunci mengikuti kolom database. `turunan` hanya di detail. */
    public static function bentuk(KelompokBidang $k, bool $detail = false): array
    {
        $out = [
            'id_kelompok_bidang' => $k->id_kelompok_bidang,
            'nama_kelompok_bidang' => $k->nama_kelompok_bidang,
            'id_rujukan' => $k->id_rujukan,
            'rujukan' => $k->rujukan?->nama_kelompok_bidang,
            'total_turunan' => (int) ($k->turunan_count ?? 0),
            'total_kegiatan' => (int) ($k->kegiatan_count ?? 0),
        ];

        if ($detail) {
            $out['turunan'] = $k->turunan
                ->sortBy('nama_kelompok_bidang')
                ->values()
                ->map(fn ($t) => [
                    'id_kelompok_bidang' => $t->id_kelompok_bidang,
                    'nama_kelompok_bidang' => $t->nama_kelompok_bidang,
                ])
                ->all();
        }

        return $out;
    }

    /** Relasi yang dihitung. `kegiatan` hanya dihitung bila kolom kegiatan.id_kelompok_bidang sudah ada di database. */
    private static function hitungan(): array
    {
        return self::adaKolomKegiatan() ? ['turunan', 'kegiatan'] : ['turunan'];
    }

    private static function adaKolomKegiatan(): bool
    {
        static $ada = null;

        return $ada ??= Schema::hasColumn('kegiatan', 'id_kelompok_bidang');
    }

    private function cari(int $id): ?KelompokBidang
    {
        return KelompokBidang::query()
            ->with(['rujukan:id_kelompok_bidang,nama_kelompok_bidang', 'turunan:id_kelompok_bidang,nama_kelompok_bidang,id_rujukan'])
            ->withCount(self::hitungan())
            ->find($id);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, ?int $id = null): array
    {
        // Spasi di awal/akhir dibuang supaya "AI" dan "AI " tidak lolos sebagai dua nama.
        if (is_string($request->input('nama_kelompok_bidang'))) {
            $request->merge(['nama_kelompok_bidang' => trim($request->input('nama_kelompok_bidang'))]);
        }

        $data = $request->validate([
            'nama_kelompok_bidang' => [
                'required', 'string', 'max:100',
                Rule::unique('kelompok_bidang', 'nama_kelompok_bidang')->ignore($id, 'id_kelompok_bidang'),
            ],
            'id_rujukan' => ['nullable', 'integer', Rule::exists('kelompok_bidang', 'id_kelompok_bidang')],
        ], [
            'nama_kelompok_bidang.required' => 'Nama kelompok bidang wajib diisi.',
            'nama_kelompok_bidang.max' => 'Nama kelompok bidang maksimal 100 karakter.',
            'nama_kelompok_bidang.unique' => 'Nama kelompok bidang sudah ada.',
            'id_rujukan.exists' => 'Kelompok bidang rujukan tidak ditemukan.',
        ]);

        if ($id !== null && isset($data['id_rujukan']) && $this->membuatSiklus($id, (int) $data['id_rujukan'])) {
            throw ValidationException::withMessages([
                'id_rujukan' => 'Rujukan tidak valid: kelompok bidang tidak boleh merujuk ke dirinya sendiri atau ke turunannya.',
            ]);
        }

        return $data;
    }

    /** Apakah menjadikan $idRujukan sebagai induk dari $id membuat lingkaran (diri sendiri atau turunannya)? */
    private function membuatSiklus(int $id, int $idRujukan): bool
    {
        $kini = $idRujukan;
        $dilihat = [];

        while ($kini !== null) {
            if ($kini === $id || isset($dilihat[$kini])) {
                return true;
            }

            $dilihat[$kini] = true;
            $induk = KelompokBidang::query()->whereKey($kini)->value('id_rujukan');
            $kini = $induk === null ? null : (int) $induk;
        }

        return false;
    }

    private function tidakBolehMenulis(Request $request): bool
    {
        return ! in_array($request->user()?->loadMissing('role')->role?->nama_role, self::PERAN_TULIS, true);
    }

    private function dilarang(): JsonResponse
    {
        return ApiResponse::gagal('Hanya admin atau kaprodi yang boleh mengubah kelompok bidang.', 403);
    }
}
