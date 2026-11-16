<?php

namespace App\BackendKel1\PengajuanAdmin;

use App\Http\Controllers\Controller;
use App\Models\BuktiHistory;
use App\Models\Kegiatan;
use App\Models\Timeline;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * GET  /api/v1/admin/pengajuan              (daftar antrean / filter pengajuan - khusus admin)
 * GET  /api/v1/admin/pengajuan/{id}         (detail pengajuan lengkap - khusus admin)
 * POST /api/v1/admin/pengajuan/{id}/keputusan (setujui / revisi / tolak - khusus admin)
 */
class PengajuanAdminController extends Controller
{
    private const STATUS_VALID = ['diajukan', 'direvisi', 'direview', 'ditolak', 'diterima', 'selesai'];

    private const STATUS_TERKUNCI = ['diterima', 'selesai'];

    public function index(Request $request): JsonResponse
    {
        if ($this->bukanAdmin($request)) {
            return $this->dilarang();
        }

        $request->validate([
            'status' => ['nullable', 'string', 'in:antrean,semua,'.implode(',', self::STATUS_VALID)],
            'id_jenis_kegiatan' => ['nullable', 'integer'],
            'jenis' => ['nullable', 'string', 'max:100'],
            'id_kategori' => ['nullable', 'integer'],
            'kebutuhan' => ['nullable', 'in:kegiatan,surat'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $statusFilter = $request->query('status', 'antrean');

        $query = Kegiatan::query()
            ->with([
                'kategori.jenisKegiatan',
                'user.mahasiswa',
                'user.dosen',
                'user.role',
                'statusTerkini',
                'detail.isianKategori',
            ])
            ->when($statusFilter === 'antrean', function (Builder $q) {
                $q->whereHas('statusTerkini', fn (Builder $t) => $t->whereIn('status', ['diajukan', 'direvisi']));
            })
            ->when(in_array($statusFilter, self::STATUS_VALID, true), function (Builder $q) use ($statusFilter) {
                $q->whereHas('statusTerkini', fn (Builder $t) => $t->where('status', $statusFilter));
            })
            ->when($request->filled('id_jenis_kegiatan'), function (Builder $q) use ($request) {
                $q->whereHas('kategori', fn (Builder $k) => $k->where('id_jenis_kegiatan', (int) $request->query('id_jenis_kegiatan')));
            })
            ->when($request->filled('jenis'), function (Builder $q) use ($request) {
                $q->whereHas('kategori.jenisKegiatan', fn (Builder $j) => $j->where('nama_jenis_kegiatan', $request->query('jenis')));
            })
            ->when($request->filled('id_kategori'), fn (Builder $q) => $q->where('id_kategori', (int) $request->query('id_kategori')))
            ->when($request->filled('kebutuhan'), function (Builder $q) use ($request) {
                $q->whereHas('kategori', fn (Builder $k) => $k->where('kebutuhan', $request->query('kebutuhan')));
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
            $paginator->getCollection()->map(fn (Kegiatan $k) => self::bentuk($k))->all()
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if ($this->bukanAdmin($request)) {
            return $this->dilarang();
        }

        $k = $this->cariLengkap($id);
        if (! $k) {
            return ApiResponse::gagal('Pengajuan kegiatan tidak ditemukan.');
        }

        return ApiResponse::ok(self::bentuk($k, true));
    }

    public function keputusan(Request $request, int $id): JsonResponse
    {
        if ($this->bukanAdmin($request)) {
            return $this->dilarang();
        }

        $k = $this->cariLengkap($id);
        if (! $k) {
            return ApiResponse::gagal('Pengajuan kegiatan tidak ditemukan.');
        }

        $statusSekarang = $k->statusTerkini?->status;
        if (in_array($statusSekarang, self::STATUS_TERKUNCI, true)) {
            return ApiResponse::gagal('Pengajuan sudah disetujui Kaprodi, keputusan tidak dapat diubah.', 409);
        }

        // Terima alias nama field dari form web lama (nomor_surat & catatan)
        if (! $request->filled('nomor_sk') && $request->filled('nomor_surat')) {
            $request->merge(['nomor_sk' => $request->input('nomor_surat')]);
        }
        if (! $request->filled('catatan_revisi') && $request->filled('catatan')) {
            $request->merge(['catatan_revisi' => $request->input('catatan')]);
        }

        $data = $request->validate([
            'aksi' => ['required', 'in:setujui,revisi,tolak'],
            'nomor_sk' => ['nullable', 'string', 'max:100', 'required_if:aksi,setujui'],
            'tanggal_sk' => ['nullable', 'date_format:Y-m-d'],
            'catatan_revisi' => ['nullable', 'string', 'max:1000', 'required_if:aksi,revisi,tolak'],
            'lampiran' => ['nullable', 'array'],
            'lampiran.*' => ['integer'],
        ], [
            'aksi.required' => 'Aksi keputusan wajib dipilih.',
            'aksi.in' => 'Aksi keputusan tidak valid.',
            'nomor_sk.required_if' => 'Nomor SK/surat wajib diisi saat menyetujui pengajuan.',
            'nomor_sk.max' => 'Nomor SK/surat maksimal 100 karakter.',
            'tanggal_sk.date_format' => 'Format tanggal SK harus YYYY-MM-DD.',
            'catatan_revisi.required_if' => 'Catatan revisi atau alasan penolakan wajib diisi.',
            'catatan_revisi.max' => 'Catatan maksimal 1000 karakter.',
        ]);

        $lampiranIds = array_values(array_unique(array_map('intval', $data['lampiran'] ?? [])));
        if ($lampiranIds !== []) {
            $jumlahCocok = $k->dokumen()->whereIn('id_dokumen', $lampiranIds)->count();
            if ($jumlahCocok !== count($lampiranIds)) {
                throw ValidationException::withMessages([
                    'lampiran' => 'Terdapat dokumen lampiran yang tidak berasal dari pengajuan ini.',
                ]);
            }
        }

        DB::transaction(function () use ($k, $data, $lampiranIds) {
            $terakhir = $k->statusTerkini;
            $waktuStatus = ($terakhir?->tanggal_status && now()->lte($terakhir->tanggal_status))
                ? $terakhir->tanggal_status->copy()->addSecond()
                : now();

            if ($data['aksi'] === 'setujui') {
                $k->update([
                    'nomor_sk' => trim((string) $data['nomor_sk']),
                    'tanggal_sk' => $data['tanggal_sk'] ?? $waktuStatus->toDateString(),
                ]);

                $timeline = Timeline::create([
                    'id_kegiatan' => $k->id_kegiatan,
                    'status' => 'direview',
                    'tanggal_status' => $waktuStatus,
                    'catatan_revisi' => null,
                ]);

                $k->dokumen()->update(['is_lampiran' => false]);
                if ($lampiranIds !== []) {
                    $k->dokumen()->whereIn('id_dokumen', $lampiranIds)->update(['is_lampiran' => true]);
                    foreach ($lampiranIds as $idDokumen) {
                        BuktiHistory::create([
                            'id_dokumen' => $idDokumen,
                            'id_timeline' => $timeline->id_timeline,
                        ]);
                    }
                }

                return;
            }

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
            'setujui' => 'Pengajuan disetujui admin dan diteruskan ke Kaprodi.',
            'revisi' => 'Pengajuan dikembalikan kepada pengaju untuk direvisi.',
            'tolak' => 'Pengajuan ditolak oleh admin.',
        };

        return ApiResponse::ok(self::bentuk($this->cariLengkap($id), true), $pesan);
    }

    public static function bentuk(Kegiatan $k, bool $lengkap = false): array
    {
        $terkini = $k->statusTerkini;
        $status = $terkini?->status ?? 'diajukan';
        $pengaju = self::profilUser($k->user);

        $out = [
            'id_kegiatan' => $k->id_kegiatan,
            'nama_kegiatan' => $k->nama_kegiatan,
            'id_kategori' => $k->id_kategori,
            'kategori' => $k->kategori?->nama_kategori,
            'id_jenis_kegiatan' => $k->kategori?->id_jenis_kegiatan,
            'jenis' => $k->kategori?->jenisKegiatan?->nama_jenis_kegiatan,
            'kebutuhan' => $k->kategori?->kebutuhan,
            'pengaju' => $pengaju,
            'tanggal_mulai' => $k->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $k->tanggal_selesai?->toDateString(),
            'status' => $status,
            'label_status' => self::labelStatus($status),
            'nomor_sk' => $k->nomor_sk,
            'tanggal_sk' => $k->tanggal_sk?->toDateString(),
            'catatan_revisi' => $terkini?->catatan_revisi,
            'tanggal_status' => $terkini?->tanggal_status?->toIso8601String(),
        ];

        if ($lengkap) {
            $out['template_surat'] = $k->kategori?->isi_surat;
            $out['lampiran_peserta'] = (bool) ($k->kategori?->lampiran_peserta ?? false);

            $out['isian'] = $k->detail->map(fn ($d) => [
                'id_detail_kegiatan' => $d->id_detail_kegiatan,
                'id_isian_kategori' => $d->id_isian_kategori,
                'input' => $d->isianKategori?->input,
                'jenis_input' => $d->isianKategori?->jenis_input,
                'isian' => $d->isian,
            ])->all();

            $out['anggota'] = $k->anggota->map(function ($a) {
                $profil = self::profilUser($a->user);

                return [
                    'id_anggota_kegiatan' => $a->id_anggota_kegiatan,
                    'id_user' => $a->id_user,
                    'nama' => $profil['nama'],
                    'identitas' => $profil['identitas'],
                    'jenis_pengaju' => $profil['jenis_pengaju'],
                    'peran' => $a->peran,
                ];
            })->all();

            $out['dokumen'] = $k->dokumen->map(fn ($doc) => [
                'id_dokumen' => $doc->id_dokumen,
                'nama_file' => $doc->nama_file,
                'tipe_dokumen' => $doc->tipe_dokumen,
                'link_dokumen' => $doc->link_dokumen,
                'is_lampiran' => (bool) $doc->is_lampiran,
                'id_detail_kegiatan' => $doc->id_detail_kegiatan,
            ])->all();

            $out['riwayat'] = $k->timeline->map(fn (Timeline $t) => [
                'id_timeline' => $t->id_timeline,
                'status' => $t->status,
                'label_status' => self::labelStatus($t->status),
                'tanggal_status' => $t->tanggal_status?->toIso8601String(),
                'catatan_revisi' => $t->catatan_revisi,
            ])->all();
        }

        return $out;
    }

    private function cariLengkap(int $id): ?Kegiatan
    {
        return Kegiatan::query()
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

    private static function profilUser(?User $user): array
    {
        if (! $user) {
            return [
                'id_user' => null,
                'nama' => '-',
                'identitas' => null,
                'jenis_pengaju' => 'Pengguna',
                'email' => null,
            ];
        }

        if ($user->mahasiswa) {
            return [
                'id_user' => $user->id_user,
                'nama' => $user->mahasiswa->nama,
                'identitas' => $user->mahasiswa->nim,
                'jenis_pengaju' => 'Mahasiswa',
                'email' => $user->email,
            ];
        }

        if ($user->dosen) {
            return [
                'id_user' => $user->id_user,
                'nama' => $user->dosen->nama,
                'identitas' => $user->dosen->nip,
                'jenis_pengaju' => 'Dosen',
                'email' => $user->email,
            ];
        }

        return [
            'id_user' => $user->id_user,
            'nama' => $user->email,
            'identitas' => null,
            'jenis_pengaju' => ucfirst($user->role?->nama_role ?? 'Pengguna'),
            'email' => $user->email,
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

    private function bukanAdmin(Request $request): bool
    {
        return $request->user()?->loadMissing('role')->role?->nama_role !== 'admin';
    }

    private function dilarang(): JsonResponse
    {
        return ApiResponse::gagal('Hanya admin yang dapat mengakses tinjauan pengajuan.', 403);
    }
}
