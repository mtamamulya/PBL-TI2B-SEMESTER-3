<?php

namespace App\BackendKel1\Kurikulum;

use App\BackendKel1\IsiKolomAda;
use App\BackendKel1\MataKuliah\MataKuliahController;
use App\Http\Controllers\Controller;
use App\Models\Kurikulum;
use App\Models\MataKuliah;
use App\Models\Prodi;
use App\Support\ApiResponse;
use App\Support\LinkFile;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Baca (tanpa token): GET /kurikulum, /kurikulum/{id}, /kurikulum/{id}/mata-kuliah.
 * Tulis (token, peran admin atau kaprodi): POST /kurikulum, PUT /kurikulum/{id}, DELETE /kurikulum/{id}, PATCH /kurikulum/{id}/status.
 */
class KurikulumController extends Controller
{
    use IsiKolomAda;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', 'in:aktif,nonaktif'],
            'angkatan' => ['nullable', 'integer', 'between:2000,2100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $p = Kurikulum::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status') === 'aktif'))
            ->when($request->filled('angkatan'), fn ($q) => KurikulumAngkatan::saring($q, (int) $request->query('angkatan')))
            ->when($request->filled('q'), fn ($q) => $q->where('nama_kurikulum', 'like', '%'.$request->query('q').'%'))
            ->orderByDesc('id_kurikulum')
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::paginated($p, $p->getCollection()->map(fn ($k) => self::bentuk($k))->all());
    }

    public function show(int $id): JsonResponse
    {
        $k = Kurikulum::query()->find($id);

        return $k ? ApiResponse::ok(self::bentuk($k)) : ApiResponse::gagal('Kurikulum tidak ditemukan');
    }

    /** Mata kuliah satu kurikulum, urut semester lalu kode. Filter opsional: ?semester=1..8 */
    public function mataKuliah(Request $request, int $id): JsonResponse
    {
        $request->validate(['semester' => ['nullable', 'integer', 'between:1,8']]);

        if (! Kurikulum::query()->whereKey($id)->exists()) {
            return ApiResponse::gagal('Kurikulum tidak ditemukan');
        }

        $rows = MataKuliah::query()
            ->where('id_kurikulum', $id)
            ->when($request->filled('semester'), fn ($q) => $q->where('semester', (int) $request->query('semester')))
            ->orderBy('semester')->orderBy('kode_mk')
            ->get()
            ->map(fn ($m) => MataKuliahController::bentuk($m))
            ->all();

        return ApiResponse::ok($rows);
    }

    /** POST /kurikulum (multipart/form-data, boleh memuat pdf). Kurikulum baru berstatus aktif. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $idProdi = Prodi::query()->orderBy('id_prodi')->value('id_prodi');
        if ($idProdi === null) {
            return ApiResponse::gagal('Data prodi belum ada di database.', 422);
        }

        $k = new Kurikulum;
        $k->id_prodi = $idProdi;
        $k->nama_kurikulum = $data['nama_kurikulum'];
        $k->status = true;
        if ($request->hasFile('pdf')) {
            $k->link = $request->file('pdf')->store('kurikulum', 'public');
        }
        $this->isiKolomAda($k, ['tahun_terbit' => $data['tahun_terbit'], 'deskripsi' => $data['deskripsi'] ?? null]);
        $k->save();

        return ApiResponse::ok(self::bentuk($k->refresh()), 'Kurikulum berhasil ditambahkan.', 201);
    }

    /** PUT /kurikulum/{id}: field diganti seluruhnya. Kirim pdf baru untuk mengganti berkas lama. */
    public function update(Request $request, int $id): JsonResponse
    {
        $k = Kurikulum::query()->find($id);
        if (! $k) {
            return ApiResponse::gagal('Kurikulum tidak ditemukan');
        }

        $data = $this->validasi($request);

        $k->nama_kurikulum = $data['nama_kurikulum'];
        $this->isiKolomAda($k, ['tahun_terbit' => $data['tahun_terbit'], 'deskripsi' => $data['deskripsi'] ?? null]);
        if ($request->hasFile('pdf')) {
            LinkFile::hapus($k->link);
            $k->link = $request->file('pdf')->store('kurikulum', 'public');
        }
        $k->save();

        return ApiResponse::ok(self::bentuk($k->refresh()), 'Kurikulum berhasil diperbarui.');
    }

    /** PATCH /kurikulum/{id}/status: membalik aktif atau nonaktif, tanpa body. */
    public function status(int $id): JsonResponse
    {
        $k = Kurikulum::query()->find($id);
        if (! $k) {
            return ApiResponse::gagal('Kurikulum tidak ditemukan');
        }

        $k->status = ! $k->status;
        $k->save();

        return ApiResponse::ok(self::bentuk($k->refresh()), 'Status kurikulum berhasil diubah.');
    }

    /** DELETE /kurikulum/{id}: mata kuliah milik kurikulum ikut terhapus. */
    public function destroy(int $id): JsonResponse
    {
        $k = Kurikulum::query()->find($id);
        if (! $k) {
            return ApiResponse::gagal('Kurikulum tidak ditemukan');
        }

        try {
            DB::transaction(function () use ($k) {
                MataKuliah::query()->where('id_kurikulum', $k->id_kurikulum)->delete();
                $k->delete();
            });
        } catch (QueryException) {
            return ApiResponse::gagal('Kurikulum tidak dapat dihapus karena masih dipakai data lain.', 409);
        }

        LinkFile::hapus($k->link);

        return ApiResponse::ok(null, 'Kurikulum berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama_kurikulum' => ['required', 'string', 'max:120'],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'tahun_terbit' => ['required', 'integer', 'digits:4', 'between:1990,2100'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ], [
            'nama_kurikulum.required' => 'Nama kurikulum wajib diisi.',
            'tahun_terbit.required' => 'Tahun terbit wajib diisi.',
            'tahun_terbit.digits' => 'Tahun terbit harus 4 angka, mis. 2026.',
            'tahun_terbit.between' => 'Tahun terbit tidak valid.',
            'pdf.mimes' => 'Dokumen harus berformat PDF.',
            'pdf.max' => 'Ukuran PDF maksimal 20 MB.',
        ]);
    }

    /** Nama kunci mengikuti kolom database. Kolom tahun_terbit dan deskripsi bernilai null sampai migration usulan dipasang. */
    public static function bentuk(Kurikulum $k): array
    {
        return [
            'id_kurikulum' => $k->id_kurikulum,
            'id_prodi' => $k->id_prodi,
            'nama_kurikulum' => $k->nama_kurikulum,
            'tahun_terbit' => $k->getAttribute('tahun_terbit'),
            'deskripsi' => $k->getAttribute('deskripsi'),
            'status' => (bool) $k->status,
            'link' => LinkFile::url($k->link),
        ];
    }
}
