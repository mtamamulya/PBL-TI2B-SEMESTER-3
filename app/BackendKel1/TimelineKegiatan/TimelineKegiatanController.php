<?php

namespace App\BackendKel1\TimelineKegiatan;

use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use App\Models\Timeline;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/v1/mahasiswa/timeline  (timeline seluruh kegiatan milik mahasiswa yang login)
 * GET /api/v1/dosen/timeline      (timeline seluruh kegiatan milik dosen yang login)
 */
class TimelineKegiatanController extends Controller
{
    private const STATUS_VALID = ['diajukan', 'direvisi', 'direview', 'ditolak', 'diterima', 'selesai'];

    public function mahasiswa(Request $request): JsonResponse
    {
        return $this->daftar($request, 'mahasiswa');
    }

    public function dosen(Request $request): JsonResponse
    {
        return $this->daftar($request, 'dosen');
    }

    private function daftar(Request $request, string $peran): JsonResponse
    {
        $user = $request->user();

        if ($user?->loadMissing('role')->role?->nama_role !== $peran) {
            return ApiResponse::gagal("Hanya {$peran} yang dapat mengakses timeline ini.", 403);
        }

        $request->validate([
            'id_kegiatan' => ['nullable', 'integer'],
            'status' => ['nullable', 'string', 'in:'.implode(',', self::STATUS_VALID)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // Kepemilikan dibatasi di query, jadi id_kegiatan milik orang lain hanya menghasilkan daftar kosong.
        $query = Kegiatan::query()
            ->untukUser($user)
            ->with(['kategori.jenisKegiatan', 'statusTerkini', 'timeline'])
            ->when($request->filled('id_kegiatan'), fn (Builder $q) => $q->where('id_kegiatan', (int) $request->query('id_kegiatan')))
            ->when($request->filled('status'), function (Builder $q) use ($request) {
                $q->whereHas('statusTerkini', fn (Builder $t) => $t->where('status', $request->query('status')));
            })
            ->orderByDesc('id_kegiatan');

        $paginator = $query->paginate((int) $request->query('per_page', 15));

        return ApiResponse::paginated(
            $paginator,
            $paginator->getCollection()->map(fn (Kegiatan $k) => self::bentuk($k))->all()
        );
    }

    private static function bentuk(Kegiatan $k): array
    {
        $terkini = $k->statusTerkini;
        $status = $terkini?->status ?? 'diajukan';

        return [
            'id_kegiatan' => $k->id_kegiatan,
            'nama_kegiatan' => $k->nama_kegiatan,
            'kategori' => $k->kategori?->nama_kategori,
            'jenis' => $k->kategori?->jenisKegiatan?->nama_jenis_kegiatan,
            'status' => $status,
            'label_status' => self::labelStatus($status),
            'nomor_sk' => $k->nomor_sk,
            'tanggal_sk' => $k->tanggal_sk?->toDateString(),
            'timeline' => $k->timeline
                ->sortBy([['tanggal_status', 'asc'], ['id_timeline', 'asc']])
                ->values()
                ->map(fn (Timeline $t) => [
                    'id_timeline' => $t->id_timeline,
                    'status' => $t->status,
                    'label_status' => self::labelStatus($t->status),
                    'tanggal_status' => $t->tanggal_status?->toIso8601String(),
                    'catatan_revisi' => $t->catatan_revisi,
                ])->all(),
        ];
    }

    private static function labelStatus(string $status): string
    {
        return match ($status) {
            'diajukan' => 'Diajukan',
            'direvisi' => 'Direvisi',
            'ditolak' => 'Ditolak',
            'direview' => 'Menunggu Tanda Tangan Kaprodi',
            'diterima' => 'Disetujui',
            'selesai' => 'Selesai',
            default => ucfirst($status),
        };
    }
}
