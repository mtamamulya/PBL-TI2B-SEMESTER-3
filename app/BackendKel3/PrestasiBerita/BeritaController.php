<?php

namespace App\BackendKel3\PrestasiBerita;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Models\Prestasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class BeritaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $data = Berita::where('id_user', $user->id_user)
            ->with(['kegiatan.kategori', 'kategori'])
            ->when($request->filled('status'), function ($q) use ($request) {
                $status = $request->input('status');
                $q->whereIn('status', match ($status) {
                    'direvisi' => ['revisi', 'direvisi'],
                    'dipublikasikan' => ['publish'],
                    default => [$status],
                });
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%'.$request->input('search').'%';
                $q->where(fn ($rows) => $rows->where('judul_berita', 'like', $term)
                    ->orWhereHas('kategori', fn ($category) => $category->where('nama_kategori', 'like', $term))
                    ->orWhereHas('kegiatan.kategori', fn ($category) => $category->where('nama_kategori', 'like', $term)));
            })
            ->orderByDesc('created_at')->orderByDesc('id_berita')
            ->paginate(max(1, min(100, $request->integer('per_page', 10))));

        return response()->json([
            'success' => true,
            'message' => 'Daftar berita berhasil diambil.',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
        ]);
    }

    public function opsi(Request $request)
    {
        $user = $request->user();
<<<<<<< HEAD

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
=======
        $kegiatan = Kegiatan::untukUser($user)
            ->orderBy('nama_kegiatan')
            ->get()
            ->map(fn ($k) => [
                'id' => $k->id_kegiatan,
                'nama' => $k->nama_kegiatan,
            ]);

        return response()->json(['success' => true, 'message' => 'Opsi form berita berhasil diambil.', 'data' => [
            'kegiatan' => $kegiatan,
            'prestasi' => Prestasi::where('id_user', $user->getKey())->orderBy('nama_lomba')->get(['id_prestasi', 'nama_lomba']),
            'kategori' => Kategori::orderBy('nama_kategori')->get(['id_kategori', 'nama_kategori']),
            'status' => ['draft', 'diajukan'],
        ]]);
>>>>>>> 3c94cf178bd27d7d854ce99570d8b90f0dfbe1f2
    }

    public function store(BeritaRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        $this->periksaSumber($request, $validated);

<<<<<<< HEAD
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
=======
        $pathSampul = null;
        if ($request->hasFile('sampul')) {
            $pathSampul = $request->file('sampul')->store('berita/sampul', 'public');
>>>>>>> 3c94cf178bd27d7d854ce99570d8b90f0dfbe1f2
        }

        $berita = Berita::create([
            'id_user' => $user->id_user,
            'id_kegiatan' => $validated['id_kegiatan'] ?? null,
            'id_prestasi' => $validated['id_prestasi'] ?? null,
            'id_kategori' => $validated['id_kategori'] ?? null,
            'judul_berita' => $validated['judul_berita'],
            'isi_berita' => $validated['isi_berita'] ?? '',
            'sampul' => $pathSampul ? Storage::url($pathSampul) : null,
            'status' => $validated['status'] ?? 'draft',
            'tanggal_publish' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Berita berhasil disimpan.',
            'data' => $berita,
        ], 201);
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();
        $berita = Berita::where('id_user', $user->id_user)
            ->with(['kegiatan.kategori', 'kategori'])
            ->findOrFail($id);

<<<<<<< HEAD
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
=======
        return response()->json(['success' => true, 'message' => 'Detail berita berhasil diambil.', 'data' => $berita]);
>>>>>>> 3c94cf178bd27d7d854ce99570d8b90f0dfbe1f2
    }

    public function update(BeritaRequest $request, int $id)
    {
        $berita = DB::transaction(function () use ($request, $id) {
            $user = $request->user();
            $berita = Berita::where('id_user', $user->id_user)->lockForUpdate()->findOrFail($id);
            abort_unless(in_array($berita->status, ['draft', 'direvisi', 'revisi'], true), 403, 'Berita ini tidak dapat diubah.');
            $validated = $request->validated();
            $this->periksaSumber($request, $validated);

            if (array_key_exists('id_prestasi', $validated) && $validated['id_prestasi']) {
                $validated['id_kegiatan'] = null;
            } elseif (! empty($validated['id_kegiatan'])) {
                $validated['id_prestasi'] = null;
            }
            if (array_key_exists('isi_berita', $validated)) {
                $validated['isi_berita'] ??= '';
            }
            if (($validated['status'] ?? null) === 'diajukan' && blank(strip_tags($validated['isi_berita'] ?? $berita->isi_berita))) {
                throw ValidationException::withMessages(['isi_berita' => 'Isi berita sebelum mengajukan.']);
            }

            if ($request->hasFile('sampul')) {
                $oldPath = $berita->sampul;
                $validated['sampul'] = Storage::url($request->file('sampul')->store('berita/sampul', 'public'));
                if ($oldPath) {
                    DB::afterCommit(fn () => Storage::disk('public')->delete(str_replace('/storage/', '', $oldPath)));
                }
            } else {
                unset($validated['sampul']);
            }
            $berita->update($validated);

<<<<<<< HEAD
            // Handle kategori mandiri
            if ($validated['sumber'] === 'mandiri' && ! empty($validated['kategori'])) {
                // UBAH: Hapus ->where('kebutuhan', 'berita')
                $kategori = Kategori::where('nama_kategori', $validated['kategori'])->first();

                if ($kategori) {
                    $dataUpdate['id_kategori'] = $kategori->id_kategori;
                }
            }
=======
            return $berita;
        });
>>>>>>> 3c94cf178bd27d7d854ce99570d8b90f0dfbe1f2

        return response()->json([
            'success' => true,
            'message' => 'Berita berhasil diperbarui.',
            'data' => $berita->fresh(),
        ]);
    }

    private function periksaSumber(Request $request, array $validated): void
    {
        if (! empty($validated['id_prestasi'])) {
            Prestasi::where('id_user', $request->user()->getKey())->findOrFail($validated['id_prestasi']);
        }
        if (! empty($validated['id_kegiatan'])) {
            Kegiatan::untukUser($request->user())->findOrFail($validated['id_kegiatan']);
        }
    }

    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        $berita = Berita::where('id_user', $user->id_user)->findOrFail($id);

        abort_unless(in_array($berita->status, ['draft', 'direvisi', 'revisi'], true), 403, 'Berita ini tidak dapat dihapus.');
        if ($berita->sampul) {
            $oldPath = str_replace('/storage/', '', $berita->sampul);
            Storage::disk('public')->delete($oldPath);
        }

        $berita->delete();

        return response()->json(['success' => true, 'message' => 'Berita berhasil dihapus.']);
    }
}
