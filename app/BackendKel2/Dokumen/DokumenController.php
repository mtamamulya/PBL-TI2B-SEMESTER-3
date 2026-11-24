<?php

namespace App\BackendKel2\Dokumen;

use App\BackendKel2\Dokumen\Requests\SimpanTautanDokumenRequest;
use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use App\Models\Kegiatan;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DokumenController extends Controller
{
    public function index(Request $request, Kegiatan $kegiatan)
    {
        Gate::authorize('viewAny', [Dokumen::class, $kegiatan]);

        return ApiResponse::ok(
            $kegiatan->dokumen()->with('jenisDokumen')->get()->map(fn (Dokumen $dokumen) => $this->bentuk($dokumen)),
            'Daftar dokumen berhasil diambil.'
        );
    }

    public function show(Request $request, Kegiatan $kegiatan, Dokumen $dokumen)
    {
        abort_unless((int) $dokumen->id_kegiatan === (int) $kegiatan->getKey(), 404);
        Gate::authorize('view', $dokumen);

        return ApiResponse::ok($this->bentuk($dokumen->load('jenisDokumen')), 'Detail dokumen berhasil diambil.');
    }

    public function store(SimpanTautanDokumenRequest $request, Kegiatan $kegiatan, TautanDokumenService $tautanDokumen)
    {
        Gate::authorize('create', [Dokumen::class, $kegiatan]);
        $dokumen = $tautanDokumen->simpan($kegiatan, $request->validated());

        return ApiResponse::ok($this->bentuk($dokumen->load('jenisDokumen')), 'Tautan dokumen berhasil disimpan.', 201);
    }

    public function hasil(Kegiatan $kegiatan)
    {
        Gate::authorize('viewHasil', [Dokumen::class, $kegiatan]);

        if ($kegiatan->statusTerkini?->status !== 'selesai') {
            return ApiResponse::gagal('Dokumen hasil hanya dapat diakses setelah kegiatan selesai.', 403);
        }

        $dokumen = $kegiatan->dokumen()
            ->reorder()
            ->with('jenisDokumen')
            ->whereHas('jenisDokumen', fn ($query) => $query->where('nama_jenis_dokumen', JenisDokumenBaku::SURAT_HASIL))
            ->orderByDesc('id_dokumen')
            ->get()
            ->map(fn (Dokumen $d) => $this->bentuk($d));

        return ApiResponse::ok($dokumen, $dokumen->isEmpty() ? 'Dokumen hasil belum tersedia.' : 'Dokumen hasil berhasil diambil.');
    }

    public function destroy(Kegiatan $kegiatan, Dokumen $dokumen)
    {
        abort_unless((int) $dokumen->id_kegiatan === (int) $kegiatan->getKey(), 404);
        Gate::authorize('deleteMahasiswa', $dokumen);

        if ($kegiatan->statusTerkini?->status === null || ! in_array($kegiatan->statusTerkini->status, ['diajukan', 'direvisi'], true)) {
            return ApiResponse::gagal('Dokumen hanya dapat dihapus saat kegiatan berstatus diajukan atau direvisi.', 403);
        }

        $dokumen->load('jenisDokumen');
        if ($dokumen->jenisDokumen?->nama_jenis_dokumen === JenisDokumenBaku::SURAT_HASIL) {
            return ApiResponse::gagal('Dokumen Surat hasil tidak dapat dihapus melalui endpoint ini.', 403);
        }

        if ($dokumen->is_lampiran) {
            return ApiResponse::gagal('Dokumen yang dipilih sebagai lampiran tidak dapat dihapus.', 403);
        }

        if ($dokumen->buktiHistory()->exists()) {
            return ApiResponse::gagal('Dokumen tidak dapat dihapus karena masih digunakan sebagai bukti riwayat.', 409);
        }

        if ($dokumen->pengajaran()->exists()) {
            return ApiResponse::gagal('Dokumen tidak dapat dihapus karena masih digunakan oleh data pengajaran.', 409);
        }

        $dokumen->delete();

        return ApiResponse::ok([], 'Dokumen berhasil dihapus.');
    }

    private function bentuk(Dokumen $dokumen): array
    {
        $tautanDokumen = app(TautanDokumenService::class);

        return [
            'id_dokumen' => $dokumen->getKey(),
            'id_kegiatan' => $dokumen->id_kegiatan,
            'id_detail_kegiatan' => $dokumen->id_detail_kegiatan,
            'id_jenis_dokumen' => $dokumen->id_jenis_dokumen,
            'jenis_dokumen' => $dokumen->jenisDokumen?->nama_jenis_dokumen,
            'nama_file' => $dokumen->nama_file,
            'tipe_dokumen' => $dokumen->tipe_dokumen,
            'link_dokumen' => $dokumen->link_dokumen,
            'link_preview' => $tautanDokumen->buatTautanPreview($dokumen->link_dokumen),
            'url' => $tautanDokumen->url($dokumen),
            'is_lampiran' => (bool) $dokumen->is_lampiran,
        ];
    }
}
