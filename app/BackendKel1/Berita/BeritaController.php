<?php

namespace App\BackendKel1\Berita;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Models\Dokumen;
use App\Models\GambarBerita;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BeritaController extends Controller
{
    public function opsi(): JsonResponse
    {
        $kegiatanList = Kegiatan::query()
            ->with(['kategori', 'dokumen.jenisDokumen'])
            ->orderByDesc('id_kegiatan')
            ->get()
            ->map(function (Kegiatan $k) {
                $dokumentasi = $k->dokumen
                    ->filter(fn (Dokumen $d) => self::adalahDokumenGambar($d))
                    ->map(fn (Dokumen $d) => [
                        'id_dokumen' => $d->id_dokumen,
                        'nama' => $d->nama_file ?: 'Dokumentasi Kegiatan',
                        'jenis' => $d->jenisDokumen?->nama_jenis_dokumen ?? 'Dokumentasi',
                        'url' => self::urlDokumen($d),
                    ])
                    ->filter(fn (array $item) => ! empty($item['url']))
                    ->values()
                    ->all();

                return [
                    'id' => $k->id_kegiatan,
                    'nama' => $k->nama_kegiatan,
                    'kategori' => $k->kategori?->nama_kategori ?? 'Lainnya',
                    'dokumentasi' => $dokumentasi,
                ];
            })
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

    public function uploadGambarKonten(Request $request): JsonResponse
    {
        if ($this->bukanPengelola($request)) {
            return ApiResponse::gagal('Hanya admin atau kaprodi yang boleh mengunggah gambar berita.', 403);
        }

        $request->validate([
            'gambar' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ], [
            'gambar.required' => 'Berkas gambar wajib dipilih.',
            'gambar.image' => 'Berkas harus berupa gambar.',
            'gambar.mimes' => 'Format gambar harus JPG, PNG, atau WebP.',
            'gambar.max' => 'Ukuran gambar maksimal 5 MB.',
        ]);

        $file = $request->file('gambar');
        $path = $file->store('berita/konten', 'public');

        $gambar = GambarBerita::create([
            'id_berita' => null,
            'id_dokumen' => null,
            'id_user' => $request->user()?->id_user,
            'nama_file' => $file->getClientOriginalName() ?: basename($path),
            'path_gambar' => $path,
            'sumber' => 'upload',
        ]);

        return ApiResponse::ok([
            'id_gambar_berita' => $gambar->id_gambar_berita,
            'path' => $path,
            'url' => asset('storage/'.$path),
        ], 'Gambar konten berhasil diunggah.', 201);
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
            ->with(['kegiatan.kategori', 'kategori', 'user', 'gambarBerita'])
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
            ->with(['kegiatan.kategori', 'kategori', 'user', 'gambarBerita'])
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
            ->limit(5)
            ->get()
            ->map(function (Berita $item) {
                $eks = $item->sampul ? strtolower(pathinfo($item->sampul, PATHINFO_EXTENSION)) : '';

                return [
                    'id_berita' => $item->id_berita,
                    'judul_berita' => $item->judul_berita,
                    'sampul' => $item->sampul,
                    'sampul_url' => $item->sampul ? asset('storage/'.$item->sampul) : null,
                    'is_gambar' => in_array($eks, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
                    'tanggal_publish' => $item->tanggal_publish?->toIso8601String(),
                ];
            })
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

        [$idKegiatan, $idKategori] = $this->tentukanRelasi($data);

        $sampulPath = null;
        if ($request->hasFile('foto')) {
            $sampulPath = $request->file('foto')->store('berita', 'public');
        } elseif (! empty($data['sampul_dokumen_id']) && $idKegiatan !== null) {
            $sampulPath = $this->salinSampulDariDokumen((int) $data['sampul_dokumen_id'], $idKegiatan);
        }

        $berita = Berita::create([
            'id_user' => $request->user()->id_user,
            'id_kegiatan' => $idKegiatan,
            'id_kategori' => $idKategori,
            'judul_berita' => $data['judul'],
            'isi_berita' => self::bersihkanHtml($data['isi']),
            'status' => $status,
            'tanggal_publish' => $status === 'publish' ? now() : null,
            'sampul' => $sampulPath,
        ]);

        $this->sinkronkanGambarBerita($berita, $request->user()?->id_user);
        $berita->load(['kegiatan.kategori', 'kategori', 'user', 'gambarBerita']);

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

        [$idKegiatan, $idKategori] = $this->tentukanRelasi($data, $berita);

        if ($request->hasFile('foto')) {
            if ($berita->sampul) {
                Storage::disk('public')->delete($berita->sampul);
            }
            $berita->sampul = $request->file('foto')->store('berita', 'public');
        } elseif (! empty($data['sampul_dokumen_id']) && $idKegiatan !== null) {
            $salinan = $this->salinSampulDariDokumen((int) $data['sampul_dokumen_id'], $idKegiatan);
            if ($salinan) {
                if ($berita->sampul) {
                    Storage::disk('public')->delete($berita->sampul);
                }
                $berita->sampul = $salinan;
            }
        }

        $berita->id_kegiatan = $idKegiatan;
        $berita->id_kategori = $idKategori;
        $berita->judul_berita = $data['judul'];
        $berita->isi_berita = self::bersihkanHtml($data['isi']);
        $berita->status = $status;

        if ($status === 'publish' && ! $berita->tanggal_publish) {
            $berita->tanggal_publish = now();
        } elseif ($status === 'draft') {
            $berita->tanggal_publish = null;
        }

        $berita->save();
        $this->sinkronkanGambarBerita($berita, $request->user()?->id_user);
        $berita->load(['kegiatan.kategori', 'kategori', 'user', 'gambarBerita']);

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
        $berita->load(['kegiatan.kategori', 'kategori', 'user', 'gambarBerita']);

        return ApiResponse::ok(self::bentuk($berita), $pesan);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($this->bukanPengelola($request)) {
            return ApiResponse::gagal('Hanya admin atau kaprodi yang boleh menghapus berita.', 403);
        }

        $berita = Berita::query()->with('gambarBerita')->find($id);
        if (! $berita) {
            return ApiResponse::gagal('Berita tidak ditemukan.');
        }

        if ($berita->sampul) {
            Storage::disk('public')->delete($berita->sampul);
        }

        // Hanya hapus berkas fisik yang berasal dari upload baru milik berita ini (dokumen kegiatan tetap aman)
        foreach ($berita->gambarBerita as $gb) {
            if ($gb->sumber === 'upload' && $gb->path_gambar) {
                Storage::disk('public')->delete($gb->path_gambar);
            }
        }

        if (preg_match_all('#/storage/(berita/konten/[^"\'\s>]+)#i', (string) $berita->isi_berita, $matches)) {
            foreach (array_unique($matches[1]) as $kontenPath) {
                Storage::disk('public')->delete($kontenPath);
            }
        }

        $berita->delete();

        return ApiResponse::ok(null, 'Berita berhasil dihapus.');
    }

    public static function bentuk(Berita $b): array
    {
        $ekstensi = $b->sampul ? strtolower(pathinfo($b->sampul, PATHINFO_EXTENSION)) : '';
        $isGambar = in_array($ekstensi, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
        $gambarKonten = $b->relationLoaded('gambarBerita')
            ? $b->gambarBerita->map(fn (GambarBerita $g) => [
                'id_gambar_berita' => $g->id_gambar_berita,
                'id_dokumen' => $g->id_dokumen,
                'nama_file' => $g->nama_file,
                'sumber' => $g->sumber,
                'url' => preg_match('#^https?://#i', $g->path_gambar)
                    ? $g->path_gambar
                    : asset('storage/'.ltrim($g->path_gambar, '/')),
            ])->values()->all()
            : [];

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
            'gambar_konten' => $gambarKonten,
            'created_at' => $b->created_at?->toIso8601String(),
        ];
    }

    public static function bersihkanHtml(string $html): string
    {
        $allowed = '<p><br><h2><h3><h4><strong><b><em><i><u><s><ul><ol><li><blockquote><a><figure><img><figcaption><table><thead><tbody><tr><th><td>';
        $clean = strip_tags($html, $allowed);
        $clean = preg_replace('/\s+on\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean) ?? $clean;
        $clean = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1=$2#$2', $clean) ?? $clean;

        return trim($clean);
    }

    private function sinkronkanGambarBerita(Berita $berita, ?int $idUser): void
    {
        $isi = (string) $berita->isi_berita;

        // 1. Kumpulkan semua path upload berita/konten/... yang dipakai di isi_berita
        $uploadPaths = [];
        if (preg_match_all('#/storage/(berita/konten/[^"\'\s>]+)#i', $isi, $matches)) {
            $uploadPaths = array_values(array_unique($matches[1]));
        }

        // Kaitkan record upload yang masih dipakai (yang baru diunggah id_berita=null atau milik berita ini)
        foreach ($uploadPaths as $path) {
            $sudahAda = GambarBerita::query()
                ->where('id_berita', $berita->id_berita)
                ->where('sumber', 'upload')
                ->where('path_gambar', $path)
                ->exists();

            if (! $sudahAda) {
                $tercatat = GambarBerita::query()
                    ->whereNull('id_berita')
                    ->where('sumber', 'upload')
                    ->where('path_gambar', $path)
                    ->first();

                if ($tercatat) {
                    $tercatat->update(['id_berita' => $berita->id_berita]);
                } else {
                    GambarBerita::create([
                        'id_berita' => $berita->id_berita,
                        'id_dokumen' => null,
                        'id_user' => $idUser,
                        'nama_file' => basename($path),
                        'path_gambar' => $path,
                        'sumber' => 'upload',
                    ]);
                }
            }
        }

        // Hapus record upload milik berita ini yang sudah dihapus penulis dari editor
        $uploadDihapus = GambarBerita::query()
            ->where('id_berita', $berita->id_berita)
            ->where('sumber', 'upload')
            ->when($uploadPaths !== [], fn ($q) => $q->whereNotIn('path_gambar', $uploadPaths))
            ->get();

        foreach ($uploadDihapus as $item) {
            if ($item->path_gambar) {
                Storage::disk('public')->delete($item->path_gambar);
            }
            $item->delete();
        }

        // 2. Sinkronkan gambar yang diambil dari dokumentasi kegiatan
        GambarBerita::query()
            ->where('id_berita', $berita->id_berita)
            ->where('sumber', 'kegiatan')
            ->delete();

        if ($berita->id_kegiatan !== null) {
            $dokumenKegiatan = Dokumen::query()
                ->where('id_kegiatan', $berita->id_kegiatan)
                ->get();

            foreach ($dokumenKegiatan as $dok) {
                if (! self::adalahDokumenGambar($dok)) {
                    continue;
                }

                $url = self::urlDokumen($dok);
                $relPath = self::pathPublikDariDokumen($dok);

                $dipakaiDiIsi = ($url && str_contains($isi, $url))
                    || ($relPath && str_contains($isi, $relPath));

                if ($dipakaiDiIsi) {
                    GambarBerita::create([
                        'id_berita' => $berita->id_berita,
                        'id_dokumen' => $dok->id_dokumen,
                        'id_user' => $idUser,
                        'nama_file' => $dok->nama_file ?: basename((string) ($relPath ?: $url)),
                        'path_gambar' => $relPath ?: (string) $url,
                        'sumber' => 'kegiatan',
                    ]);
                }
            }
        }
    }

    private function salinSampulDariDokumen(int $idDokumen, int $idKegiatan): ?string
    {
        $dok = Dokumen::query()
            ->where('id_dokumen', $idDokumen)
            ->where('id_kegiatan', $idKegiatan)
            ->first();

        if (! $dok || ! self::adalahDokumenGambar($dok)) {
            return null;
        }

        $srcPath = self::pathPublikDariDokumen($dok);
        if ($srcPath && Storage::disk('public')->exists($srcPath)) {
            $ext = pathinfo($srcPath, PATHINFO_EXTENSION) ?: 'jpg';
            $destPath = 'berita/'.Str::random(40).'.'.$ext;
            Storage::disk('public')->copy($srcPath, $destPath);

            return $destPath;
        }

        return null;
    }

    private static function adalahDokumenGambar(Dokumen $d): bool
    {
        $tipe = strtolower((string) $d->tipe_dokumen);
        if (str_starts_with($tipe, 'image/')) {
            return true;
        }

        $pathUrl = parse_url((string) $d->link_dokumen, PHP_URL_PATH) ?: (string) $d->link_dokumen;
        $extLink = strtolower(pathinfo($pathUrl, PATHINFO_EXTENSION));
        $extNama = strtolower(pathinfo((string) $d->nama_file, PATHINFO_EXTENSION));

        return in_array($extLink, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)
            || in_array($extNama, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    private static function pathPublikDariDokumen(Dokumen $d): ?string
    {
        $link = trim((string) $d->link_dokumen);
        if ($link === '') {
            return null;
        }

        if (preg_match('#/storage/(.+)$#i', $link, $m)) {
            return ltrim($m[1], '/');
        }

        if (! preg_match('#^https?://#i', $link) && ! str_starts_with($link, 'dokumen/')) {
            return ltrim($link, '/');
        }

        return null;
    }

    private static function urlDokumen(Dokumen $d): ?string
    {
        $link = trim((string) $d->link_dokumen);
        if ($link === '') {
            return null;
        }

        if (preg_match('#/storage/(.+)$#i', $link, $m)) {
            return asset('storage/'.ltrim($m[1], '/'));
        }

        if (preg_match('#^https?://#i', $link)) {
            return $link;
        }

        if (str_starts_with($link, 'dokumen/')) {
            return asset($link);
        }

        return asset('storage/'.ltrim($link, '/'));
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
            'sampul_dokumen_id' => ['nullable', 'integer', Rule::exists('dokumen', 'id_dokumen')],
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
