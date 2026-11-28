<?php

namespace App\BackendKel2\Dashboard;

use App\BackendKel2\Support\RoleGuard;
use App\Http\Controllers\Controller;
use App\Models\Kegiatan;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function mahasiswa(Request $request)
    {
        $user = $this->user($request);
        RoleGuard::ensure($user, ['mahasiswa']);

        return ApiResponse::ok($this->dataMahasiswa($user), 'Ringkasan dashboard mahasiswa berhasil diambil.');
    }

    public function contohMahasiswa()
    {
        abort_unless(app()->environment(['local', 'testing']), 404);

        return ApiResponse::ok(
            ContohDashboardMahasiswa::data(),
            'Data contoh dashboard mahasiswa berhasil diambil.'
        );
    }

    public function dataMahasiswa(User $user): array
    {
        $activities = Kegiatan::query()->untukUser($user)
            ->with(['kategori', 'statusTerkini', 'detail.isianKategori'])
            ->withCount(['dokumen as dokumen_siap_count' => fn ($query) => $query
                ->whereNotNull('link_dokumen')
                ->whereNotIn('link_dokumen', ['', '#'])])
            ->get();
        $yearJoined = (int) ($user->mahasiswa?->tahun_masuk ?? now()->year);
        $completed = $activities->filter(fn (Kegiatan $activity) => $activity->statusTerkini?->status === 'selesai');
        $active = $activities->filter(fn (Kegiatan $activity) => in_array(
            $activity->statusTerkini?->status,
            ['diajukan', 'direview', 'diterima', 'direvisi'],
            true
        ));

        return [
            'total_kegiatan' => $activities->count(),
            'selesai' => $completed->count(),
            'pengajuan_aktif' => $active->count(),
            'dokumen_siap' => $completed->sum('dokumen_siap_count'),
            'per_kategori' => $this->categoryCounts($activities),
            'per_semester' => $this->semesterCounts($activities, $yearJoined),
            'kegiatan_selesai' => $completed->sortByDesc(fn (Kegiatan $activity) => $activity->tanggal_mulai?->timestamp ?? 0)->values(),
        ];
    }

    public function kegiatan(Request $request)
    {
        $user = $this->user($request);
        RoleGuard::ensure($user, ['dosen', 'admin']);
        $query = Kegiatan::query();
        if (mb_strtolower((string) $user->role?->nama_role) !== 'admin') {
            $query->untukUser($user);
        }
        $activities = $query->with(['kategori', 'statusTerkini', 'detail.isianKategori'])->get();
        $yearJoined = (int) ($user->mahasiswa?->tahun_masuk ?? now()->year);
        $completed = $activities->filter(fn (Kegiatan $activity) => $activity->statusTerkini?->status === 'selesai');

        return ApiResponse::ok([
            'total_kegiatan' => $activities->count(),
            'per_kategori' => $this->categoryCounts($activities),
            'per_semester' => $this->semesterCounts($activities, $yearJoined),
            'kegiatan_selesai' => $completed->sortByDesc(fn (Kegiatan $activity) => $activity->tanggal_mulai?->timestamp ?? 0)->values(),
        ], 'Ringkasan kegiatan berhasil diambil.');
    }

    private function categoryCounts(Collection $activities): Collection
    {
        return $activities->groupBy(fn (Kegiatan $activity) => $activity->kategori?->nama_kategori ?? 'Tanpa kategori')
            ->map(fn (Collection $items, string $category) => ['kategori' => $category, 'jumlah' => $items->count()])
            ->values();
    }

    private function semesterCounts(Collection $activities, int $yearJoined): array
    {
        $counts = array_fill(1, 14, 0);
        foreach ($activities as $activity) {
            $start = $activity->tanggal_mulai;
            if (! $start) {
                continue;
            }

            $month = (int) $start->format('n');
            $academicYearStart = $month >= 8 ? (int) $start->format('Y') : (int) $start->format('Y') - 1;
            $semesterInYear = $month >= 8 || $month === 1 ? 1 : 2;
            $semester = (($academicYearStart - $yearJoined) * 2) + $semesterInYear;
            if (isset($counts[$semester])) {
                $counts[$semester]++;
            }
        }

        return collect($counts)->map(fn (int $count, int $number) => [
            'semester' => $number,
            'label' => 'Sem '.$number,
            'jumlah' => $count,
        ])->values()->all();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
