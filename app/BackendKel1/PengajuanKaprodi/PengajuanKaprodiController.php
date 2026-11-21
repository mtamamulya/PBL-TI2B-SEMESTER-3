<?php

namespace App\BackendKel1\PengajuanKaprodi;

use App\BackendKel1\PengajuanAdmin\PengajuanAdminController;
use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use App\Models\Timeline;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * GET  /api/v1/kaprodi/pengajuan               (daftar pengajuan yang diteruskan admin - khusus kaprodi)
 * GET  /api/v1/kaprodi/pengajuan/{id}          (detail pengajuan lengkap - khusus kaprodi)
 * POST /api/v1/kaprodi/pengajuan/{id}/keputusan (setujui / revisi / tolak - khusus kaprodi)
 */
class PengajuanKaprodiController extends Controller
{
    private const STATUS_VALID = ['direview', 'diterima', 'selesai', 'direvisi', 'ditolak'];

    public function index(Request $request): JsonResponse
    {
        if ($this->bukanKaprodi($request)) {
            return $this->dilarang();
        }

        $request->validate([
            'status' => ['nullable', 'string', 'in:semua,menunggu,'.implode(',', self::STATUS_VALID)],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $statusFilter = $request->query('status', 'semua');

        $query = Kegiatan::query()
            ->with([
                'kategori.jenisKegiatan',
                'user.mahasiswa',
                'user.dosen',
                'user.role',
                'statusTerkini',
                'detail.isianKategori',
            ])
            // Hanya pengajuan yang pernah dikirim admin ke kaprodi (pernah berstatus direview)
            ->whereHas('timeline', fn (Builder $t) => $t->where('status', 'direview'))
            ->when($statusFilter === 'menunggu', function (Builder $q) {
                $q->whereHas('statusTerkini', fn (Builder $t) => $t->where('status', 'direview'));
            })
            ->when(in_array($statusFilter, self::STATUS_VALID, true), function (Builder $q) use ($statusFilter) {
                $q->whereHas('statusTerkini', fn (Builder $t) => $t->where('status', $statusFilter));
            })
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $kata = '%'.$request->query('q').'%';
                $q->where(function (Builder $sub) use ($kata) {
                    $sub->where('nama_kegiatan', 'like', $kata)
                        ->orWhere('nomor_sk', 'like', $kata)
                        ->orWhereHas('user.mahasiswa', fn (Builder $m) => $m->where('nama', 'like', $kata)->orWhere('nim', 'like', $kata))
                        ->orWhereHas('user.dosen', fn (Builder $d) => $d->where('nama', 'like', $kata)->orWhere('nip', 'like', $kata));
                });
            })
            ->orderByDesc('id_kegiatan');

        $paginator = $query->paginate((int) $request->query('per_page', 15));

        return ApiResponse::paginated(
            $paginator,
            $paginator->getCollection()->map(fn (Kegiatan $k) => PengajuanAdminController::bentuk($k))->all()
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if ($this->bukanKaprodi($request)) {
            return $this->dilarang();
        }

        $k = $this->cariUntukKaprodi($id);
        if (! $k) {
            return ApiResponse::gagal('Pengajuan kegiatan tidak ditemukan.');
        }

        return ApiResponse::ok(PengajuanAdminController::bentuk($k, true));
    }

    public function keputusan(Request $request, int $id): JsonResponse
    {
        if ($this->bukanKaprodi($request)) {
            return $this->dilarang();
        }

        $k = $this->cariUntukKaprodi($id);
        if (! $k) {
            return ApiResponse::gagal('Pengajuan kegiatan tidak ditemukan.');
        }

        if ($k->statusTerkini?->status !== 'direview') {
            return ApiResponse::gagal('Pengajuan ini tidak sedang menunggu tanda tangan Kaprodi.', 409);
        }

        if (! $request->filled('catatan_revisi') && $request->filled('catatan')) {
            $request->merge(['catatan_revisi' => $request->input('catatan')]);
        }

        $data = $request->validate([
            'aksi' => ['required', 'in:setujui,revisi,tolak'],
            'catatan_revisi' => ['nullable', 'string', 'max:1000', 'required_if:aksi,revisi,tolak'],
        ], [
            'aksi.required' => 'Aksi keputusan wajib dipilih.',
            'aksi.in' => 'Aksi keputusan tidak valid.',
            'catatan_revisi.required_if' => 'Catatan wajib diisi saat meminta revisi atau menolak.',
            'catatan_revisi.max' => 'Catatan maksimal 1000 karakter.',
        ]);

        DB::transaction(function () use ($k, $data) {
            $terakhir = $k->statusTerkini;
            $waktuStatus = ($terakhir?->tanggal_status && now()->lte($terakhir->tanggal_status))
                ? $terakhir->tanggal_status->copy()->addSecond()
                : now();

            if ($data['aksi'] === 'setujui') {
                Timeline::create([
                    'id_kegiatan' => $k->id_kegiatan,
                    'status' => 'diterima',
                    'tanggal_status' => $waktuStatus,
                    'catatan_revisi' => null,
                ]);

                return;
            }

            // Revisi atau tolak: kembalikan ke antrean admin dan kosongkan nomor SK
            $statusBaru = $data['aksi'] === 'revisi' ? 'direvisi' : 'ditolak';

            $k->update([
                'nomor_sk' => null,
                'tanggal_sk' => null,
            ]);

            $k->dokumen()->update(['is_lampiran' => false]);

            Timeline::create([
                'id_kegiatan' => $k->id_kegiatan,
                'status' => $statusBaru,
                'tanggal_status' => $waktuStatus,
                'catatan_revisi' => trim((string) $data['catatan_revisi']),
            ]);
        });

        $pesan = match ($data['aksi']) {
            'setujui' => 'Surat berhasil ditandatangani dan pengajuan disetujui Kaprodi.',
            'revisi' => 'Pengajuan dikembalikan ke Admin untuk direvisi.',
            'tolak' => 'Pengajuan ditolak oleh Kaprodi.',
        };

        return ApiResponse::ok(PengajuanAdminController::bentuk($this->cariUntukKaprodi($id), true), $pesan);
    }

    private function cariUntukKaprodi(int $id): ?Kegiatan
    {
        return Kegiatan::query()
            ->whereHas('timeline', fn (Builder $t) => $t->where('status', 'direview'))
            ->with([
                'kategori.jenisKegiatan',
                'user.mahasiswa',
                'user.dosen',
                'user.role',
                'statusTerkini',
                'timeline',
                'detail.isianKategori',
                'dokumen',
                'anggota.user.mahasiswa',
                'anggota.user.dosen',
                'anggota.user.role',
            ])
            ->find($id);
    }

    private function bukanKaprodi(Request $request): bool
    {
        return $request->user()?->loadMissing('role')->role?->nama_role !== 'kaprodi';
    }

    private function dilarang(): JsonResponse
    {
        return ApiResponse::gagal('Hanya Kaprodi yang dapat mengakses halaman ini.', 403);
    }
}
