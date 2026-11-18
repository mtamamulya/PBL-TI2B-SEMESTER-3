<?php

namespace App\BackendKel1\Berita;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BeritaController extends Controller
{
    public function opsi(): JsonResponse
    {
        $kegiatanList = Kegiatan::query()
            ->with('kategori')
            ->orderByDesc('id_kegiatan')
            ->get()
            ->map(fn ($k) => [
                'id' => $k->id_kegiatan,
                'nama' => $k->nama_kegiatan,
                'kategori' => $k->kategori?->nama_kategori ?? 'Lainnya',
            ])
            ->all();

        $kategori = Kategori::query()
            ->orderBy('nama_kategori')
            ->pluck('nama_kategori')
            ->all();

        if ($kategori === []) {
            $kategori = ['Prestasi Mahasiswa', 'Prestasi Dosen', 'Kunjungan Industri', 'Info Akademik', 'Lomba', 'Magang'];
        }

        return ApiResponse::ok([
            'kategori' => $kategori,
            'kegiatanList' => $kegiatanList,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in(Berita::STATUSES)],
            'id_kategori' => ['nullable', 'integer'],
            'id_kegiatan' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:150'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $pengguna = $request->user('sanctum');

        $query = Berita::query()
            ->with(['kegiatan.kategori', 'kategori', 'user'])
            ->when($pengguna === null, fn ($q) => $q->where('status', 'publish'))
            ->when($pengguna !== null && $request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('id_kategori'), fn ($q) => $q->where('id_kategori', (int) $request->query('id_kategori')))
            ->when($request->filled('id_kegiatan'), fn ($q) => $q->where('id_kegiatan', (int) $request->query('id_kegiatan')))
            ->when($request->filled('q'), fn ($q) => $q->where('judul_berita', 'like', '%'.$request->query('q').'%'))
            ->orderByDesc('created_at')
            ->orderByDesc('id_berita');

        $paginator = $query->paginate((int) $request->query('per_page', 15));

        return ApiResponse::paginated(
            $paginator,
            $paginator->getCollection()->map(fn (Berita $b) => self::bentuk($b))->all(),
            'Daftar berita berhasil diambil'
        );
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $berita = Berita::query()
            ->with(['kegiatan.kategori', 'kategori', 'user'])
            ->find($id);

        if (! $berita) {
            return ApiResponse::gagal('Berita tidak ditemukan.');
        }

        if ($request->user('sanctum') === null && $berita->status !== 'publish') {
            return ApiResponse::gagal('Berita tidak ditemukan.');
        }

        $terkait = Berita::query()
            ->where('status', 'publish')
            ->where('id_berita', '!=', $berita->id_berita)
            ->orderByDesc('tanggal_publish')
            ->limit(4)
            ->get()
            ->map(fn (Berita $item) => [
                'id_berita' => $item->id_berita,
                'judul_berita' => $item->judul_berita,
                'sampul' => $item->sampul,
                'tanggal_publish' => $item->tanggal_publish?->toIso8601String(),
            ])
            ->all();

        $data = self::bentuk($berita);
        $data['terkait'] = $terkait;

        return ApiResponse::ok($data, 'Detail berita berhasil diambil');
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->bukanPengelola($request)) {
            return ApiResponse::gagal('Hanya admin atau kaprodi yang boleh menambah berita.', 403);
        }

        $data = $this->validasi($request);
        $status = strtolower($data['status']);

        $sampulPath = null;
        if ($request->hasFile('foto')) {
            $sampulPath = $request->file('foto')->store('berita', 'public');
        }

        [$idKegiatan, $idKategori] = $this->tentukanRelasi($data);

        $berita = Berita::create([
            'id_user' => $request->user()->id_user,
            'id_kegiatan' => $idKegiatan,
            'id_kategori' => $idKategori,
            'judul_berita' => $data['judul'],
            'isi_berita' => $data['isi'],
            'status' => $status,
            'tanggal_publish' => $status === 'publish' ? now() : null,
            'sampul' => $sampulPath,
        ]);

        $berita->load(['kegiatan.kategori', 'kategori', 'user']);

        return ApiResponse::ok(self::bentuk($berita), 'Berita berhasil disimpan.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($this->bukanPengelola($request)) {
            return ApiResponse::gagal('Hanya admin atau kaprodi yang boleh mengubah berita.', 403);
        }

        $berita = Berita::query()->find($id);
        if (! $berita) {
            return ApiResponse::gagal('Berita tidak ditemukan.');
        }

        $data = $this->validasi($request);
        $status = strtolower($data['status']);

        if ($request->hasFile('foto')) {
            if ($berita->sampul) {
                Storage::disk('public')->delete($berita->sampul);
            }
            $berita->sampul = $request->file('foto')->store('berita', 'public');
        }

        [$idKegiatan, $idKategori] = $this->tentukanRelasi($data, $berita);

        $berita->id_kegiatan = $idKegiatan;
        $berita->id_kategori = $idKategori;
        $berita->judul_berita = $data['judul'];
        $berita->isi_berita = $data['isi'];
        $berita->status = $status;

        if ($status === 'publish' && ! $berita->tanggal_publish) {
            $berita->tanggal_publish = now();
        } elseif ($status === 'draft') {
            $berita->tanggal_publish = null;
        }

        $berita->save();
        $berita->load(['kegiatan.kategori', 'kategori', 'user']);

        return ApiResponse::ok(self::bentuk($berita), 'Berita berhasil diperbarui.');
    }

    public function tinjau(Request $request, int $id): JsonResponse
    {
        if ($this->bukanAdmin($request)) {
            return ApiResponse::gagal('Hanya admin yang boleh meninjau dan memverifikasi berita.', 403);
        }

        $berita = Berita::query()->find($id);
        if (! $berita) {
            return ApiResponse::gagal('Berita tidak ditemukan.');
        }

        $data = $request->validate([
            'aksi' => ['required', 'in:terima,revisi,tolak'],
            'catatan_revisi' => ['nullable', 'string', 'max:1000', 'required_if:aksi,revisi,tolak'],
        ], [
            'aksi.required' => 'Keputusan tinjauan wajib dipilih.',
            'aksi.in' => 'Keputusan tinjauan tidak valid.',
            'catatan_revisi.required_if' => 'Catatan revisi atau alasan penolakan wajib diisi.',
        ]);

        if ($data['aksi'] === 'terima') {
            $berita->status = 'publish';
            $berita->tanggal_publish = $berita->tanggal_publish ?? now();
            $berita->catatan_revisi = null;
            $pesan = 'Berita berhasil disetujui dan dipublikasikan.';
        } elseif ($data['aksi'] === 'revisi') {
            $berita->status = 'revisi';
            $berita->tanggal_publish = null;
            $berita->catatan_revisi = $data['catatan_revisi'] ?? null;
            $pesan = 'Berita dikembalikan untuk direvisi.';
        } else {
            $berita->status = 'ditolak';
            $berita->tanggal_publish = null;
            $berita->catatan_revisi = $data['catatan_revisi'] ?? null;
            $pesan = 'Berita telah ditolak.';
        }

        $berita->save();
        $berita->load(['kegiatan.kategori', 'kategori', 'user']);

        return ApiResponse::ok(self::bentuk($berita), $pesan);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($this->bukanPengelola($request)) {
            return ApiResponse::gagal('Hanya admin atau kaprodi yang boleh menghapus berita.', 403);
        }

        $berita = Berita::query()->find($id);
        if (! $berita) {
            return ApiResponse::gagal('Berita tidak ditemukan.');
        }

        if ($berita->sampul) {
            Storage::disk('public')->delete($berita->sampul);
        }

        $berita->delete();

        return ApiResponse::ok(null, 'Berita berhasil dihapus.');
    }

    public static function bentuk(Berita $b): array
    {
        $ekstensi = $b->sampul ? strtolower(pathinfo($b->sampul, PATHINFO_EXTENSION)) : '';
        $isGambar = in_array($ekstensi, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);

        return [
            'id_berita' => $b->id_berita,
            'id_kegiatan' => $b->id_kegiatan,
            'id_kategori' => $b->id_kategori,
            'id_user' => $b->id_user,
            'judul_berita' => $b->judul_berita,
            'isi_berita' => $b->isi_berita,
            'status' => $b->status,
            'tanggal_publish' => $b->tanggal_publish?->toIso8601String(),
            'sampul' => $b->sampul,
            'sampul_url' => $b->sampul ? asset('storage/'.$b->sampul) : null,
            'is_gambar' => $isGambar,
            'catatan_revisi' => $b->catatan_revisi,
            'nama_kegiatan' => $b->kegiatan?->nama_kegiatan,
            'nama_kategori' => $b->kategori?->nama_kategori ?? $b->kegiatan?->kategori?->nama_kategori,
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }

    private function bukanPengelola(Request $request): bool
    {
        $role = $request->user()?->loadMissing('role')->role?->nama_role;

        return ! in_array($role, ['admin', 'kaprodi'], true);
    }

    private function bukanAdmin(Request $request): bool
    {
        $role = $request->user()?->loadMissing('role')->role?->nama_role;

        return $role !== 'admin';
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'judul' => ['required', 'string', 'max:255'],
            'isi' => ['required', 'string'],
            'status' => ['required', 'in:publish,Publish,draft,Draft,diajukan,Diajukan'],
            'sumber' => ['nullable', 'in:prev,none'],
            'kegiatan_id' => ['nullable', 'integer', Rule::exists('kegiatan', 'id_kegiatan')],
            'kategori' => ['nullable', 'string', 'max:100'],
            'foto' => ['nullable', 'mimes:jpeg,png,jpg,pdf', 'max:5120'],
        ], [
            'judul.required' => 'Judul berita wajib diisi.',
            'isi.required' => 'Isi berita wajib diisi.',
            'foto.mimes' => 'Berkas sampul harus berupa JPG, PNG, atau PDF.',
            'foto.max' => 'Ukuran berkas sampul maksimal 5 MB.',
        ]);
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function tentukanRelasi(array $data, ?Berita $existing = null): array
    {
        $sumber = $data['sumber'] ?? (! empty($data['kegiatan_id']) ? 'prev' : 'none');

        if ($sumber === 'prev' && ! empty($data['kegiatan_id'])) {
            $kegiatan = Kegiatan::query()->find((int) $data['kegiatan_id']);

            return [$kegiatan?->id_kegiatan, $kegiatan?->id_kategori ?? $existing?->id_kategori];
        }

        $idKategori = null;
        if (! empty($data['kategori'])) {
            $idKategori = Kategori::query()
                ->where('nama_kategori', $data['kategori'])
                ->value('id_kategori');
        }

        return [null, $idKategori ?? $existing?->id_kategori];
    }
}
