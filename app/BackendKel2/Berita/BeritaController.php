<?php

namespace App\BackendKel2\Berita;

use App\BackendKel2\Support\RoleGuard;
use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Models\Kategori;
use App\Models\Kegiatan;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BeritaController extends Controller
{
    public function categories()
    {
        return ApiResponse::ok(
            Kategori::query()->orderBy('nama_kategori')->get(['id_kategori', 'nama_kategori']),
            'Kategori berita berhasil diambil.'
        );
    }

    public function index(Request $request)
    {
        $user = $this->user($request);
        RoleGuard::ensure($user, ['dosen', 'mahasiswa']);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Berita::STATUSES)],
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $items = Berita::query()
            ->with(['kategori', 'kegiatan'])
            ->where('id_user', $user->getKey())
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('judul_berita', 'like', '%'.$term.'%'))
            ->latest('created_at')
            ->paginate($filters['per_page'] ?? 15);
        $counts = Berita::query()
            ->where('id_user', $user->getKey())
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'success' => true,
            'message' => 'Daftar berita berhasil diambil.',
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'last_page' => $items->lastPage(),
                'counts' => $counts,
            ],
        ]);
    }

    public function show(Request $request, Berita $berita)
    {
        $user = $this->user($request);
        RoleGuard::ensure($user, ['dosen', 'mahasiswa']);
        abort_unless((int) $berita->id_user === (int) $user->getKey(), 404);

        return ApiResponse::ok($berita->load(['kategori', 'kegiatan']), 'Detail berita berhasil diambil.');
    }

    public function store(Request $request)
    {
        $user = $this->user($request);
        RoleGuard::ensure($user, ['dosen', 'mahasiswa']);
        $data = $this->validated($request);
        $this->authorizeActivity($user, $data['_activity_id'] ?? null);
        $berita = Berita::create($this->attributes($data, $user, $request));

        return ApiResponse::ok($berita->load(['kategori', 'kegiatan']), 'Berita berhasil disimpan.', 201);
    }

    public function update(Request $request, Berita $berita)
    {
        $user = $this->user($request);
        RoleGuard::ensure($user, ['dosen', 'mahasiswa']);
        abort_unless((int) $berita->id_user === (int) $user->getKey(), 404);
        abort_unless(in_array($berita->status, ['draft', 'revisi'], true), 403, 'Berita tidak dapat diedit pada status ini.');

        $data = $this->validated($request, $berita);
        $this->authorizeActivity($user, $data['_activity_id'] ?? null);
        $oldCover = $berita->sampul;
        $berita->update($this->attributes($data, $user, $request, $berita));

        if ($request->hasFile('foto') && $oldCover) {
            Storage::disk('public')->delete($oldCover);
        }

        return ApiResponse::ok($berita->refresh()->load(['kategori', 'kegiatan']), 'Berita berhasil diperbarui.');
    }

    private function validated(Request $request, ?Berita $berita = null): array
    {
        $intent = $request->input('intent', 'draft');
        $status = mb_strtolower((string) $request->input('status', ''));
        $submitting = $intent === 'submit' || in_array($status, ['publish', 'diajukan'], true);

        $data = $request->validate([
            'intent' => ['nullable', Rule::in(['draft', 'submit'])],
            'status' => ['nullable', Rule::in(['draft', 'Draft', 'diajukan', 'Diajukan', 'publish', 'Publish'])],
            'id_kegiatan' => ['nullable', 'integer', 'exists:kegiatan,id_kegiatan'],
            'kegiatan_id' => ['nullable', 'integer', 'exists:kegiatan,id_kegiatan'],
            'id_kategori' => ['nullable', 'integer', 'exists:kategori,id_kategori'],
            'kategori' => ['nullable', 'string', 'max:100'],
            'judul' => [$submitting ? 'required' : 'nullable', 'string', 'max:255'],
            'isi' => [$submitting ? 'required' : 'nullable', 'string', 'max:1000'],
            'foto' => [
                $submitting && ! $berita?->sampul ? 'required' : 'nullable',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120',
            ],
        ]);

        $category = $data['id_kategori'] ?? null;
        if (! $category && ! empty($data['kategori'])) {
            $category = Kategori::query()->where('nama_kategori', $data['kategori'])->value('id_kategori');
            if (! $category) {
                throw ValidationException::withMessages(['kategori' => 'Kategori tidak tersedia.']);
            }
        }

        return $data + [
            '_activity_id' => $data['id_kegiatan'] ?? $data['kegiatan_id'] ?? null,
            '_category_id' => $category,
            '_submitting' => $submitting,
        ];
    }

    private function authorizeActivity(User $user, ?int $activityId): void
    {
        if ($activityId !== null && ! Kegiatan::query()->untukUser($user)->whereKey($activityId)->exists()) {
            throw ValidationException::withMessages(['kegiatan_id' => 'Kegiatan yang dipilih bukan milik Anda atau tidak diikuti.']);
        }
    }

    private function attributes(array $data, User $user, Request $request, ?Berita $berita = null): array
    {
        $attributes = [
            'id_user' => $user->getKey(),
            'id_kegiatan' => $data['_activity_id'] ?? null,
            'id_kategori' => $data['_category_id'] ?? null,
            'judul_berita' => $data['judul'] ?? $berita?->judul_berita,
            'isi_berita' => $data['isi'] ?? $berita?->isi_berita,
            'status' => ($data['_submitting'] ?? false) ? 'diajukan' : 'draft',
            'catatan_revisi' => null,
        ];

        if ($request->hasFile('foto')) {
            $attributes['sampul'] = $request->file('foto')->store('berita', 'public');
        }

        return $attributes;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
