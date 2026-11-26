<?php

namespace App\BackendKel3\PrestasiBerita;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Models\Kategori;
use App\Models\Kegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BeritaController extends Controller
{
    private const STATUS_LABELS = [
        'draft' => 'Draft',
        'diajukan' => 'Diajukan',
        'revisi' => 'Direvisi',
        'publish' => 'Dipublikasikan',
        'ditolak' => 'Ditolak',
    ];

    public function index(Request $request)
    {
        try {
            $query = Berita::where('id_user', $request->user()->id_user)
                ->with(['kegiatan.kategori', 'kategori']);

            // Filter status (sebelum pagination)
            if ($request->filled('status') && $request->status !== 'Semua') {
                $query->where('status', $request->status);
            }

            // Pencarian judul atau kategori
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('judul_berita', 'like', "%{$search}%")
                        ->orWhereHas('kategori', function ($q) use ($search) {
                            $q->where('nama_kategori', 'like', "%{$search}%");
                        });
                });
            }

            $beritas = $query->orderBy('created_at', 'desc')->paginate(10);

            $data = $beritas->map(function (Berita $b) {
                $kategoriNama = $b->kategori?->nama_kategori
                    ?? $b->kegiatan?->kategori?->nama_kategori
                    ?? 'Mandiri';

                return [
                    'id' => $b->id_berita,
                    'judul' => $b->judul_berita,
                    'kategori' => $kategoriNama,
                    'tanggal' => $b->created_at->format('d F Y'),
                    'status' => self::STATUS_LABELS[$b->status] ?? $b->status,
                    'status_code' => $b->status,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Daftar berita berhasil diambil.',
                'data' => $data,
                'meta' => [
                    'current_page' => $beritas->currentPage(),
                    'last_page' => $beritas->lastPage(),
                    'per_page' => $beritas->perPage(),
                    'total' => $beritas->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Berita index error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil daftar berita.',
            ], 500);
        }
    }

    public function opsi(Request $request)
    {
        $user = $request->user();

        $kegiatan = Kegiatan::untukPrestasi($user)
            ->with('kategori')
            ->orderBy('nama_kegiatan')
            ->get()
            ->map(fn (Kegiatan $k) => [
                'id' => $k->id_kegiatan,
                'nama' => $k->nama_kegiatan.' · '.($k->kategori?->nama_kategori ?? 'Umum'),
            ]);

        // Return SEMUA kategori (master kategori Kel 1)
        $kategori = Kategori::orderBy('nama_kategori')
            ->get(['id_kategori', 'nama_kategori']);

        return response()->json([
            'success' => true,
            'message' => 'Opsi form berita berhasil diambil.',
            'data' => [
                'kegiatan' => $kegiatan,
                'kategori' => $kategori,
            ],
        ]);
    }

    public function store(BeritaRequest $request)
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            Log::info('Berita store request:', [
                'user_id' => $user->id_user,
                'sumber' => $validated['sumber'],
                'judul' => $validated['judul'],
                'status' => $validated['status'],
            ]);

            DB::beginTransaction();

            $dataSimpan = [
                'id_user' => $user->id_user,
                'judul_berita' => $validated['judul'],
                'isi_berita' => $validated['isi'],
                'status' => $validated['status'],
            ];

            // Handle sumber kegiatan
            if ($validated['sumber'] === 'kegiatan' && ! empty($validated['kegiatan_sebelumnya'])) {
                $kegiatan = Kegiatan::untukPrestasi($user)
                    ->where('nama_kegiatan', 'like', '%'.$validated['kegiatan_sebelumnya'].'%')
                    ->first();

                if ($kegiatan) {
                    $dataSimpan['id_kegiatan'] = $kegiatan->id_kegiatan;
                    $dataSimpan['id_kategori'] = $kegiatan->id_kategori;
                }
            }

            // Handle kategori mandiri
            if ($validated['sumber'] === 'mandiri' && ! empty($validated['kategori'])) {
                // UBAH: Hapus ->where('kebutuhan', 'berita')
                $kategori = Kategori::where('nama_kategori', $validated['kategori'])->first();

                if ($kategori) {
                    $dataSimpan['id_kategori'] = $kategori->id_kategori;
                }
            }

            // Handle upload foto
            if ($request->hasFile('foto')) {
                $dataSimpan['sampul'] = $request->file('foto')->store('berita/sampul', 'public');
            }

            $berita = Berita::create($dataSimpan);
            DB::commit();

            Log::info('Berita created:', ['id' => $berita->id_berita]);

            return response()->json([
                'success' => true,
                'message' => $validated['status'] === 'diajukan'
                    ? 'Berita berhasil diajukan.'
                    : 'Draft berhasil disimpan.',
                'data' => ['id' => $berita->id_berita],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Berita store error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan berita: '.$e->getMessage(),
            ], 500);
        }
    }

    public function show(Request $request, int $id)
    {
        try {
            $berita = Berita::where('id_user', $request->user()->id_user)
                ->with(['kegiatan.kategori', 'kategori'])
                ->findOrFail($id);

            $kategori = Kategori::where('kebutuhan', 'berita')
                ->orderBy('nama_kategori')
                ->get(['id_kategori', 'nama_kategori']);

            $kategoriNama = $berita->kategori?->nama_kategori
                ?? $berita->kegiatan?->kategori?->nama_kategori
                ?? 'Mandiri';

            return response()->json([
                'success' => true,
                'message' => 'Detail berita berhasil diambil.',
                'data' => [
                    'id' => $berita->id_berita,
                    'sumber' => $berita->id_kegiatan ? 'kegiatan' : 'mandiri',
                    'kegiatan_sebelumnya' => $berita->kegiatan?->nama_kegiatan,
                    'kategori' => $kategoriNama,
                    'judul' => $berita->judul_berita,
                    'isi' => $berita->isi_berita,
                    'sampul' => $berita->sampul ? Storage::url($berita->sampul) : null,
                    'status' => self::STATUS_LABELS[$berita->status] ?? $berita->status,
                    'status_code' => $berita->status,
                    'catatan_revisi' => $berita->catatan_revisi,
                    'tanggal' => $berita->created_at->format('d F Y'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Berita show error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail berita.',
            ], 500);
        }
    }

    public function update(BeritaRequest $request, int $id)
    {
        try {
            $berita = Berita::where('id_user', $request->user()->id_user)->findOrFail($id);

            // Hanya draft dan revisi yang boleh diedit
            if (! in_array($berita->status, ['draft', 'revisi'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Berita dengan status ini tidak dapat diedit.',
                ], 403);
            }

            $validated = $request->validated();

            DB::beginTransaction();

            $dataUpdate = [
                'judul_berita' => $validated['judul'],
                'isi_berita' => $validated['isi'],
                'status' => $validated['status'],
            ];

            // Handle sumber kegiatan
            if ($validated['sumber'] === 'kegiatan' && ! empty($validated['kegiatan_sebelumnya'])) {
                $kegiatan = Kegiatan::untukPrestasi($request->user())
                    ->where('nama_kegiatan', 'like', '%'.$validated['kegiatan_sebelumnya'].'%')
                    ->first();

                if ($kegiatan) {
                    $dataUpdate['id_kegiatan'] = $kegiatan->id_kegiatan;
                    $dataUpdate['id_kategori'] = $kegiatan->id_kategori;
                }
            } else {
                $dataUpdate['id_kegiatan'] = null;
            }

            // Handle kategori mandiri
            if ($validated['sumber'] === 'mandiri' && ! empty($validated['kategori'])) {
                // UBAH: Hapus ->where('kebutuhan', 'berita')
                $kategori = Kategori::where('nama_kategori', $validated['kategori'])->first();

                if ($kategori) {
                    $dataUpdate['id_kategori'] = $kategori->id_kategori;
                }
            }

            // Handle upload foto baru
            if ($request->hasFile('foto')) {
                if ($berita->sampul) {
                    Storage::disk('public')->delete($berita->sampul);
                }
                $dataUpdate['sampul'] = $request->file('foto')->store('berita/sampul', 'public');
            }

            $berita->update($dataUpdate);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Berita berhasil diperbarui.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Berita update error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui berita: '.$e->getMessage(),
            ], 500);
        }
    }

    public function destroy(Request $request, int $id)
    {
        try {
            $berita = Berita::where('id_user', $request->user()->id_user)->findOrFail($id);

            if (! in_array($berita->status, ['draft', 'revisi'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Berita dengan status ini tidak dapat dihapus.',
                ], 403);
            }

            if ($berita->sampul) {
                Storage::disk('public')->delete($berita->sampul);
            }

            $berita->delete();

            return response()->json([
                'success' => true,
                'message' => 'Berita berhasil dihapus.',
            ]);
        } catch (\Exception $e) {
            Log::error('Berita destroy error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus berita.',
            ], 500);
        }
    }
}
