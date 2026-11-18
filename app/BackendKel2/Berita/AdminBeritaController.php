<?php

namespace App\BackendKel2\Berita;

use App\BackendKel2\Support\RoleGuard;
use App\Http\Controllers\Controller;
use App\Models\Berita;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminBeritaController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->user($request);
        RoleGuard::ensure($user, ['admin']);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Berita::STATUSES)],
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $items = Berita::query()
            ->with(['kategori', 'kegiatan', 'user'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] ?? null, fn ($query, $term) => $query->where('judul_berita', 'like', '%'.$term.'%'))
            ->latest('created_at')
            ->paginate($filters['per_page'] ?? 15);

        return ApiResponse::paginated($items, $items->items(), 'Daftar berita berhasil diambil.');
    }

    public function review(Request $request, Berita $berita)
    {
        $user = $this->user($request);
        RoleGuard::ensure($user, ['admin']);
        abort_unless(in_array($berita->status, ['diajukan', 'draft'], true), 409, 'Berita tidak sedang menunggu tinjauan.');

        $data = $request->validate([
            'keputusan' => ['required', Rule::in(['publish', 'revisi', 'tolak'])],
            'catatan' => ['required_if:keputusan,revisi,tolak', 'nullable', 'string', 'max:2000'],
        ]);
        $berita->status = $data['keputusan'] === 'tolak' ? 'ditolak' : $data['keputusan'];
        $berita->catatan_revisi = $data['catatan'] ?? null;
        $berita->tanggal_publish = $data['keputusan'] === 'publish' ? now() : null;
        $berita->save();

        return ApiResponse::ok($berita->refresh()->load(['kategori', 'kegiatan', 'user']), 'Status berita berhasil diperbarui.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
