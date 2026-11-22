<?php

namespace App\Http\Controllers\BackendKel3\DokumenSurat;

use App\BackendKel3\Pengajuan\AlurPengajuan;
use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use App\Models\JenisDokumen;
use App\Models\Kegiatan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DokumenSuratController extends Controller
{
    public function index(Request $request, int $id): JsonResponse
    {
        $kegiatan = Kegiatan::untukUser($request->user())->findOrFail($id);

        return ApiResponse::ok($kegiatan->dokumen()->with('jenisDokumen')->get()->map($this->bentuk(...)));
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'file' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'dokumen' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'berkas' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'files' => 'nullable|array|max:20',
            'files.*' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:5120',
            'nama_file' => 'nullable|string|max:255',
            'id_jenis_dokumen' => 'nullable|integer|exists:jenis_dokumen,id_jenis_dokumen',
            'jenis_dokumen' => 'nullable|string|max:100',
            'file_path' => 'nullable|url:http,https|max:2048',
            'link_dokumen' => 'nullable|url:http,https|max:2048',
            'id_detail_kegiatan' => 'nullable|integer|exists:detail_kegiatan,id_detail_kegiatan',
            'ajukan_kembali' => 'sometimes|boolean',
        ]);
        $files = $request->file('files') ?? array_filter([
            $request->file('file') ?? $request->file('dokumen') ?? $request->file('berkas'),
        ]);
        $link = $data['link_dokumen'] ?? $data['file_path'] ?? null;
        if (! $files && ! $link) {
            return ApiResponse::gagal('Pilih berkas atau isi tautan dokumen.', 422);
        }
        $paths = [];
        try {
            $saved = DB::transaction(function () use ($request, $id, $data, $files, $link, &$paths) {
                $kegiatan = $this->dapatDiubah($request, $id);
                $jenis = $data['id_jenis_dokumen'] ?? JenisDokumen::firstOrCreate([
                    'nama_jenis_dokumen' => $data['jenis_dokumen'] ?? 'Dokumen pendukung',
                ])->getKey();
                $saved = collect();
                foreach ($files ?: [null] as $file) {
                    $path = $file ? $file->store('dokumen_perbaikan', 'public') : null;
                    if ($path) {
                        $paths[] = $path;
                    }
                    $saved->push(Dokumen::create([
                        'id_kegiatan' => $kegiatan->getKey(),
                        'id_jenis_dokumen' => $jenis,
                        'id_detail_kegiatan' => $data['id_detail_kegiatan'] ?? null,
                        'nama_file' => $file?->getClientOriginalName() ?? $data['nama_file'] ?? 'Dokumen',
                        'tipe_dokumen' => $file?->getMimeType() ?? 'tautan',
                        'link_dokumen' => $path ? Storage::disk('public')->url($path) : $link,
                        'is_lampiran' => false,
                    ]));
                }
                if ($request->boolean('ajukan_kembali')) {
                    app(AlurPengajuan::class)->ajukanUlang($id, (int) $request->user()->getKey());
                }

                return $saved;
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($paths);
            throw $e;
        }

        return ApiResponse::ok($saved->map($this->bentuk(...)), 'Dokumen berhasil disimpan.', 201);
    }

    public function destroy(Request $request, int $id, int $dokumenId): JsonResponse
    {
        DB::transaction(function () use ($request, $id, $dokumenId) {
            $kegiatan = $this->dapatDiubah($request, $id);
            $dokumen = $kegiatan->dokumen()->findOrFail($dokumenId);
            abort_if($dokumen->buktiHistory()->exists(), 403, 'Bukti riwayat tidak dapat dihapus.');
            $dokumen->delete();
            // Berkas fisik disimpan sebagai arsip; hanya relasi aktif yang dihapus.
        });

        return ApiResponse::ok(null, 'Dokumen berhasil dihapus.');
    }

    public function showSurat(Request $request, int $id): JsonResponse
    {
        $k = Kegiatan::untukUser($request->user())->with('kategori')->findOrFail($id);
        $status = $k->timeline()->reorder()->orderByDesc('tanggal_status')->orderByDesc('id_timeline')->value('status');
        if (! in_array($status, ['diterima', 'selesai'], true) || ! $k->nomor_sk) {
            return ApiResponse::gagal('Surat belum diterbitkan.', 404);
        }
        if (! $k->kategori?->isi_surat) {
            return ApiResponse::gagal('Kategori ini belum memiliki template surat.', 404);
        }

        return ApiResponse::ok([
            'id_kegiatan' => $id,
            'nama_kegiatan' => $k->nama_kegiatan,
            'nomor_sk' => $k->nomor_sk,
            'tanggal_sk' => $k->tanggal_sk?->toDateString(),
            'template_surat' => $k->kategori->isi_surat,
            'lampiran_peserta' => (bool) $k->kategori->lampiran_peserta,
            'dokumen' => $k->dokumen()->where('is_lampiran', true)->get()->map($this->bentuk(...)),
        ]);
    }

    private function dapatDiubah(Request $request, int $id): Kegiatan
    {
        $k = Kegiatan::where('id_user', $request->user()->getKey())->lockForUpdate()->findOrFail($id);
        $status = $k->timeline()->reorder()->orderByDesc('tanggal_status')->orderByDesc('id_timeline')->value('status');
        abort_unless($status === null || $status === 'direvisi', 403, 'Lampiran hanya dapat diubah sebelum diajukan atau saat direvisi.');

        return $k;
    }

    private function bentuk(Dokumen $dokumen): array
    {
        return $dokumen->toArray() + [
            'id' => $dokumen->getKey(),
            'jenis_dokumen' => $dokumen->jenisDokumen?->nama_jenis_dokumen,
            'file_path' => $dokumen->link_dokumen,
        ];
    }
}
