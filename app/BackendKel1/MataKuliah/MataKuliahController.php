<?php

namespace App\BackendKel1\MataKuliah;

use App\BackendKel1\Kurikulum\KurikulumAngkatan;
use App\Http\Controllers\Controller;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Baca (tanpa token): GET /mata-kuliah, /mata-kuliah/{id}. Filter: id_kurikulum, angkatan, semester, q (kode atau nama).
 * Tulis (token, peran admin atau kaprodi): POST /kurikulum/{id}/mata-kuliah, PUT dan DELETE /mata-kuliah/{id}.
 */
class MataKuliahController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'id_kurikulum' => ['nullable', 'integer'],
            'angkatan' => ['nullable', 'integer', 'between:2000,2100', 'prohibits:id_kurikulum'],
            'semester' => ['nullable', 'integer', 'between:1,8'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $p = MataKuliah::query()
            ->when($request->filled('id_kurikulum'), fn ($q) => $q->where('id_kurikulum', (int) $request->query('id_kurikulum')))
            ->when($request->filled('angkatan'), fn ($q) => KurikulumAngkatan::saring($q, (int) $request->query('angkatan')))
            ->when($request->filled('semester'), fn ($q) => $q->where('semester', (int) $request->query('semester')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $kata = '%'.$request->query('q').'%';
                $q->where(fn ($w) => $w->where('kode_mk', 'like', $kata)->orWhere('nama_mk', 'like', $kata));
            })
            ->orderBy('id_kurikulum')->orderBy('semester')->orderBy('kode_mk')
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::paginated($p, $p->getCollection()->map(fn ($m) => self::bentuk($m))->all());
    }

    public function show(int $id): JsonResponse
    {
        $m = MataKuliah::query()->find($id);

        return $m ? ApiResponse::ok(self::bentuk($m)) : ApiResponse::gagal('Mata kuliah tidak ditemukan');
    }

    /** POST /kurikulum/{id}/mata-kuliah: kode_mk harus unik di dalam kurikulum itu. */
    public function store(Request $request, int $id): JsonResponse
    {
        if (! Kurikulum::query()->whereKey($id)->exists()) {
            return ApiResponse::gagal('Kurikulum tidak ditemukan');
        }

        $data = $this->validasi($request, $id);
        $m = MataKuliah::query()->create(['id_kurikulum' => $id] + $data);

        return ApiResponse::ok(self::bentuk($m->refresh()), 'Mata kuliah berhasil ditambahkan.', 201);
    }

    /** PUT /mata-kuliah/{id}: kurikulum pemilik tidak bisa dipindah. */
    public function update(Request $request, int $id): JsonResponse
    {
        $m = MataKuliah::query()->find($id);
        if (! $m) {
            return ApiResponse::gagal('Mata kuliah tidak ditemukan');
        }

        $m->fill($this->validasi($request, (int) $m->id_kurikulum, $id))->save();

        return ApiResponse::ok(self::bentuk($m->refresh()), 'Mata kuliah berhasil diperbarui.');
    }

    public function destroy(int $id): JsonResponse
    {
        $m = MataKuliah::query()->find($id);
        if (! $m) {
            return ApiResponse::gagal('Mata kuliah tidak ditemukan');
        }

        try {
            if ($m->enrollmentMataKuliah()->exists()) {
                return ApiResponse::gagal('Mata kuliah sudah dibuka pada tahun ajaran, jadi tidak dapat dihapus.', 409);
            }
            $m->delete();
        } catch (QueryException) {
            return ApiResponse::gagal('Mata kuliah tidak dapat dihapus karena masih dipakai data lain.', 409);
        }

        return ApiResponse::ok(null, 'Mata kuliah berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request, int $idKurikulum, ?int $abaikanId = null): array
    {
        $unik = Rule::unique('mata_kuliah', 'kode_mk')->where('id_kurikulum', $idKurikulum);
        if ($abaikanId !== null) {
            $unik->ignore($abaikanId, 'id_mk');
        }

        return $request->validate([
            'semester' => ['required', 'integer', 'between:1,8'],
            'kode_mk' => ['required', 'string', 'max:30', $unik],
            'nama_mk' => ['required', 'string', 'max:150'],
            'sks_teori' => ['required', 'integer', 'between:0,99'],
            'sks_praktik' => ['required', 'integer', 'between:0,99'],
            'jam_teori' => ['required', 'integer', 'between:0,99'],
            'jam_praktek' => ['required', 'integer', 'between:0,99'],
        ], [
            'kode_mk.unique' => 'Kode mata kuliah sudah dipakai pada kurikulum ini.',
        ]);
    }

    /** Nama kunci mengikuti kolom database (perhatikan: sks_praktik tetapi jam_praktek). */
    public static function bentuk(MataKuliah $m): array
    {
        return [
            'id_mk' => $m->id_mk,
            'id_kurikulum' => $m->id_kurikulum,
            'semester' => (int) $m->semester,
            'kode_mk' => $m->kode_mk,
            'nama_mk' => $m->nama_mk,
            'sks_teori' => (int) $m->sks_teori,
            'sks_praktik' => (int) $m->sks_praktik,
            'jam_teori' => (int) $m->jam_teori,
            'jam_praktek' => (int) $m->jam_praktek,
        ];
    }
}
