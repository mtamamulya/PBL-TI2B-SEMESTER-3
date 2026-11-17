<?php

namespace App\BackendKel1\Akreditasi;

use App\BackendKel1\IsiKolomAda;
use App\Http\Controllers\Controller;
use App\Models\Akreditasi;
use App\Support\ApiResponse;
use App\Support\LinkFile;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Baca (tanpa token): GET /akreditasi, /akreditasi/aktif, /akreditasi/{id}.
 * Tulis (token, khusus admin): POST /akreditasi, PUT dan DELETE /akreditasi/{id}, POST /akreditasi/{id}/aktifkan.
 * Hanya SATU akreditasi yang boleh aktif.
 */
class AkreditasiController extends Controller
{
    use IsiKolomAda;

    public function index(Request $request): JsonResponse
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        $p = Akreditasi::query()->orderByDesc('id_akreditasi')->paginate((int) $request->query('per_page', 15));

        return ApiResponse::paginated($p, $p->getCollection()->map(fn ($a) => self::bentuk($a))->all());
    }

    /** Akreditasi yang sedang berlaku (status aktif). */
    public function aktif(): JsonResponse
    {
        $a = Akreditasi::query()->where('status', true)->orderByDesc('id_akreditasi')->first();

        return $a ? ApiResponse::ok(self::bentuk($a)) : ApiResponse::gagal('Belum ada akreditasi yang aktif');
    }

    public function show(int $id): JsonResponse
    {
        $a = Akreditasi::query()->find($id);

        return $a ? ApiResponse::ok(self::bentuk($a)) : ApiResponse::gagal('Akreditasi tidak ditemukan');
    }

    /** POST /akreditasi (multipart/form-data, boleh memuat pdf). Kirim aktifkan=true untuk langsung mengaktifkan. */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validasi($request);

        $a = DB::transaction(function () use ($request, $data) {
            $a = new Akreditasi;
            $a->id_user = $request->user()->id_user;
            $a->keterangan = $data['lembaga'];
            $a->status = false;
            if ($request->hasFile('pdf')) {
                $a->link = $request->file('pdf')->store('akreditasi', 'public');
            }
            $this->isiOpsional($a, $data);
            $a->save();

            if ($request->boolean('aktifkan')) {
                $this->jadikanAktif($a);
            }

            return $a;
        });

        return ApiResponse::ok(self::bentuk($a->refresh()), 'Akreditasi berhasil ditambahkan.', 201);
    }

    /** PUT /akreditasi/{id}: field diganti seluruhnya. Status aktif diubah lewat /aktifkan, bukan di sini. */
    public function update(Request $request, int $id): JsonResponse
    {
        $a = Akreditasi::query()->find($id);
        if (! $a) {
            return ApiResponse::gagal('Akreditasi tidak ditemukan');
        }

        $data = $this->validasi($request);

        $a->keterangan = $data['lembaga'];
        $this->isiOpsional($a, $data);
        if ($request->hasFile('pdf')) {
            LinkFile::hapus($a->link);
            $a->link = $request->file('pdf')->store('akreditasi', 'public');
        }
        $a->save();

        return ApiResponse::ok(self::bentuk($a->refresh()), 'Akreditasi berhasil diperbarui.');
    }

    /** POST /akreditasi/{id}/aktifkan: akreditasi lain otomatis nonaktif. */
    public function aktifkan(int $id): JsonResponse
    {
        $a = Akreditasi::query()->find($id);
        if (! $a) {
            return ApiResponse::gagal('Akreditasi tidak ditemukan');
        }

        DB::transaction(fn () => $this->jadikanAktif($a));

        return ApiResponse::ok(self::bentuk($a->refresh()), 'Akreditasi berhasil diaktifkan.');
    }

    /** DELETE /akreditasi/{id}: yang sedang aktif tidak boleh dihapus (409). */
    public function destroy(int $id): JsonResponse
    {
        $a = Akreditasi::query()->find($id);
        if (! $a) {
            return ApiResponse::gagal('Akreditasi tidak ditemukan');
        }
        if ($a->status) {
            return ApiResponse::gagal('Akreditasi yang aktif tidak bisa dihapus. Aktifkan akreditasi lain terlebih dahulu.', 409);
        }

        try {
            $a->delete();
        } catch (QueryException) {
            return ApiResponse::gagal('Akreditasi tidak dapat dihapus karena masih dipakai data lain.', 409);
        }

        LinkFile::hapus($a->link);

        return ApiResponse::ok(null, 'Akreditasi berhasil dihapus.');
    }

    private function jadikanAktif(Akreditasi $a): void
    {
        Akreditasi::query()->where('id_akreditasi', '!=', $a->id_akreditasi)->update(['status' => false]);
        $a->status = true;
        $a->save();
    }

    /** @param  array<string, mixed>  $data */
    private function isiOpsional(Akreditasi $a, array $data): void
    {
        $this->isiKolomAda($a, [
            'nomor_sk' => $data['nomor_sk'] ?? null,
            'tahun_terbit' => $data['tahun_terbit'],
            'berlaku_sampai' => $data['berlaku_sampai'] ?? null,
        ]);
    }

    /** @return array<string, mixed> */
    private function validasi(Request $request): array
    {
        return $request->validate([
            'lembaga' => ['required', 'string', 'max:80'],
            'nomor_sk' => ['nullable', 'string', 'max:120'],
            'tahun_terbit' => ['required', 'integer', 'digits:4', 'between:1990,2100'],
            'berlaku_sampai' => ['nullable', 'date'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
        ], [
            'lembaga.required' => 'Lembaga akreditasi wajib diisi.',
            'tahun_terbit.required' => 'Tahun terbit wajib diisi.',
            'tahun_terbit.digits' => 'Tahun terbit harus 4 angka, mis. 2026.',
            'tahun_terbit.between' => 'Tahun terbit tidak valid.',
            'berlaku_sampai.date' => 'Tanggal berlaku tidak valid.',
            'pdf.mimes' => 'Dokumen harus berformat PDF.',
            'pdf.max' => 'Ukuran PDF maksimal 20 MB.',
        ]);
    }

    /** `lembaga` diambil dari kolom keterangan. `nomor_sk` null sampai kolomnya ditambahkan Kelompok 3. */
    public static function bentuk(Akreditasi $a): array
    {
        $berlaku = $a->getAttribute('berlaku_sampai');

        return [
            'id_akreditasi' => $a->id_akreditasi,
            'lembaga' => $a->keterangan,
            'nomor_sk' => $a->getAttribute('nomor_sk'),
            'tahun_terbit' => $a->getAttribute('tahun_terbit'),
            'berlaku_sampai' => $berlaku ? substr((string) $berlaku, 0, 10) : null,
            'status' => (bool) $a->getAttribute('status'),
            'link' => LinkFile::url($a->link),
        ];
    }
}
