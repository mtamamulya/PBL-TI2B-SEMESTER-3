<?php

namespace App\BackendKel3\PrestasiBerita;

use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Models\Kegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BeritaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $data = Berita::where('id_user', $user->id_user)
            ->with('kegiatan')
            ->orderByDesc('created_at')
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
            'status' => ['draft', 'diajukan'],
        ]]);
    }

    public function store(BeritaRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! empty($validated['id_kegiatan'])) {
            Kegiatan::untukUser($user)->findOrFail($validated['id_kegiatan']);
        }

        $pathSampul = null;
        if ($request->hasFile('sampul')) {
            $pathSampul = $request->file('sampul')->store('berita/sampul', 'public');
        }

        $berita = Berita::create([
            'id_user' => $user->id_user,
            'id_kegiatan' => $validated['id_kegiatan'] ?? null,
            'judul_berita' => $validated['judul_berita'],
            'isi_berita' => $validated['isi_berita'],
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
            ->with('kegiatan')
            ->findOrFail($id);

        return response()->json(['success' => true, 'message' => 'Detail berita berhasil diambil.', 'data' => $berita]);
    }

    public function update(BeritaRequest $request, int $id)
    {
        $user = $request->user();
        $berita = Berita::where('id_user', $user->id_user)->findOrFail($id);

        abort_unless(in_array($berita->status, ['draft', 'revisi'], true), 403, 'Berita ini tidak dapat diubah.');
        $validated = $request->validated();

        if (! empty($validated['id_kegiatan'])) {
            Kegiatan::untukUser($user)->findOrFail($validated['id_kegiatan']);
        }

        if ($request->hasFile('sampul')) {
            if ($berita->sampul) {
                $oldPath = str_replace('/storage/', '', $berita->sampul);
                Storage::disk('public')->delete($oldPath);
            }
            $validated['sampul'] = Storage::url($request->file('sampul')->store('berita/sampul', 'public'));
        } else {
            unset($validated['sampul']);
        }

        $berita->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Berita berhasil diperbarui.',
            'data' => $berita->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $user = $request->user();
        $berita = Berita::where('id_user', $user->id_user)->findOrFail($id);

        abort_unless(in_array($berita->status, ['draft', 'revisi'], true), 403, 'Berita ini tidak dapat dihapus.');
        if ($berita->sampul) {
            $oldPath = str_replace('/storage/', '', $berita->sampul);
            Storage::disk('public')->delete($oldPath);
        }

        $berita->delete();

        return response()->json(['success' => true, 'message' => 'Berita berhasil dihapus.']);
    }
}
