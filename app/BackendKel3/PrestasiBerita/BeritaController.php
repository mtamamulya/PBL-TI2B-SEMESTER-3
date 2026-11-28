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
        $query = Berita::where('berita.id_user', $user->id_user)
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
            });

        $arah = $request->input('arah') === 'asc' ? 'asc' : 'desc';
        $kolom = $request->input('urut');
        if ($kolom === 'kategori') {
            $query->select('berita.*')
                ->leftJoin('kategori as langsung', 'langsung.id_kategori', '=', 'berita.id_kategori')
                ->leftJoin('kegiatan as sumber', 'sumber.id_kegiatan', '=', 'berita.id_kegiatan')
                ->leftJoin('kategori as terkait', 'terkait.id_kategori', '=', 'sumber.id_kategori')
                ->orderByRaw("COALESCE(langsung.nama_kategori, terkait.nama_kategori, CASE WHEN berita.id_prestasi IS NULL THEN 'Mandiri' ELSE 'Prestasi' END) ".$arah);
        } elseif ($kolom === 'status') {
            $query->orderByRaw("CASE berita.status WHEN 'publish' THEN 'dipublikasikan' WHEN 'revisi' THEN 'direvisi' ELSE berita.status END ".$arah);
        } elseif ($kolom === 'judul') {
            $query->orderBy('judul_berita', $arah);
        } else {
            $query->orderBy('berita.created_at', $kolom === 'tanggal' ? $arah : 'desc');
        }

        $data = $query->orderByDesc('berita.id_berita')
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
    }

    public function store(BeritaRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        $this->periksaSumber($request, $validated);

        $pathSampul = null;
        if ($request->hasFile('sampul')) {
            $pathSampul = $request->file('sampul')->store('berita/sampul', 'public');
        }

        $berita = Berita::create([
            'id_user' => $user->id_user,
            'id_kegiatan' => $validated['id_kegiatan'] ?? null,
            'id_prestasi' => $validated['id_prestasi'] ?? null,
            'id_kategori' => $validated['id_kategori'] ?? null,
            'judul_berita' => $validated['judul_berita'],
            'isi_berita' => $validated['isi_berita'] ?? '',
            'sampul' => $pathSampul,
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

        return response()->json(['success' => true, 'message' => 'Detail berita berhasil diambil.', 'data' => $berita]);
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
                $validated['sampul'] = $request->file('sampul')->store('berita/sampul', 'public');
                if ($oldPath) {
                    DB::afterCommit(fn () => Storage::disk('public')->delete(str_replace('/storage/', '', $oldPath)));
                }
            } else {
                unset($validated['sampul']);
            }
            $berita->update($validated);

            return $berita;
        });

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
