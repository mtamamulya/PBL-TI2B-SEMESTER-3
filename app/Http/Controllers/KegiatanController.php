<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class KegiatanController extends Controller
{
    public function index(Request $request)
    {
        $isDosen = $request->routeIs('dosen.*');
        $hasDynamicDetails = Schema::hasTable('detail_kegiatan')
            && Schema::hasColumn('detail_kegiatan', 'id_isian_kategori');
        $relations = [
            'kategori.jenisKegiatan', 'statusTerkini', 'prestasi', 'dokumen',
            'anggota.user.mahasiswa', 'anggota.user.dosen',
        ];
        if ($hasDynamicDetails) {
            $relations[] = 'detail.isianKategori';
        } elseif (Schema::hasTable('detail_kegiatan')) {
            $relations[] = 'detail';
        }

        $kegiatan = Kegiatan::untukUser($request->user())
            ->with($relations)
            ->withCount('anggota')->denganTanggal()->orderByDesc('tanggal_mulai')->orderByDesc('id_kegiatan')->get();
        $databaseActivities = $kegiatan->map(fn ($k) => [
            'id' => $k->getKey(),
            'name' => $k->nama_kegiatan,
            'type' => $k->kategori?->nama_kategori ?? '—',
            'date' => $k->tanggal_mulai?->locale('id')->translatedFormat('d M Y') ?? '—',
            'raw_date' => $k->tanggal_mulai?->toDateString() ?? '',
            'created_at' => $k->created_at?->toDateString(),
            'status' => match ($k->statusTerkini?->status) {
                'diajukan' => 'Diajukan',
                'direview' => 'Direview',
                'diterima' => 'Disetujui',
                'direvisi' => 'Perlu revisi',
                'ditolak' => 'Ditolak',
                'selesai' => 'Selesai',
                default => 'Belum diajukan',
            },
            'note' => $k->statusTerkini?->catatan_revisi,
            'capaian' => $this->activityValue($k, 'posisi_dalam_kegiatan', $hasDynamicDetails) ?? '—',
            'tingkat' => ucfirst($this->activityValue($k, 'tingkat_kejuaraan', $hasDynamicDetails) ?? '—'),
            'penyelenggara' => $this->activityValue($k, 'penyelenggara', $hasDynamicDetails) ?? '—',
            'jenis' => $k->prestasi->first()?->jenis_prestasi ?? '—',
            'bentuk' => $k->anggota_count > 1 ? 'Tim' : 'Individu',
            'detail_url' => $isDosen ? route('dosen.kegiatan.show', $k->getKey()) : null,
            'file_name' => $k->dokumen->first()?->nama_file,
            'members' => $k->anggota->map(fn ($member) => [
                'name' => $member->user?->mahasiswa?->nama ?? $member->user?->dosen?->nama ?? $member->user?->email ?? '—',
                'role' => $member->peran,
            ]),
            'documents' => $k->dokumen->map(fn ($document) => [
                'name' => $document->nama_file,
                'type' => $document->tipe_dokumen,
                'url' => $this->documentUrl($document->link_dokumen),
            ]),
        ]);
        $allActivities = $databaseActivities->values();
        $counts = $allActivities->countBy(fn ($activity) => mb_strtolower($activity['type']));
        $categoryCounts = [
            'LOMBA' => $counts->get('lomba', 0),
            'VOLUNTEER' => $counts->get('volunteer', 0),
            'KKL' => $counts->get('kkl', 0) + $counts->get('kuliah kerja lapangan', 0),
            'MAGANG' => $counts->get('magang', 0),
            'PENGABDIAN' => $counts->get('pengabdian', 0),
            'PENELITIAN' => $counts->get('penelitian', 0),
        ];

        // Semester ganjil dimulai Agustus, semester genap Februari.
        $cohortYear = (int) ($request->user()?->mahasiswa?->tahun_masuk
            ?? config('mahasiswa.angkatan', now()->month >= 8 ? now()->year : now()->year - 1));
        $semesters = collect(range(1, 8))->map(function ($number) use ($cohortYear, $allActivities) {
            $activitiesInSemester = $allActivities->filter(function ($activity) use ($cohortYear, $number) {
                $activityDate = $activity['raw_date'];
                if (! $activityDate) {
                    return false;
                }

                $activityDate = Carbon::parse($activityDate);
                $monthsFromEnrollment = (($activityDate->year - $cohortYear) * 12) + $activityDate->month - 8;
                if ($monthsFromEnrollment < 0) {
                    return false;
                }

                return intdiv($monthsFromEnrollment, 6) + 1 === $number;
            });

            return ['label' => 'Sem '.$number, 'value' => $activitiesInSemester->count()];
        });

        return view($request->routeIs('dosen.*') ? 'dosen.kegiatan.index' : 'mahasiswa.kegiatan.index', [
            'categoryCounts' => $categoryCounts,
            'categories' => $allActivities->pluck('type')->filter(fn ($category) => $category !== '—')->unique()->sort()->values(),
            'semesters' => $semesters,
            'chartMax' => max(20, (int) (ceil($semesters->max('value') / 4) * 4)),
            'allActivities' => $allActivities,
        ]);
    }

    private function activityValue(Kegiatan $kegiatan, string $input, bool $hasDynamicDetails): ?string
    {
        if ($hasDynamicDetails) {
            return $kegiatan->nilaiIsian($input);
        }

        $detail = $kegiatan->relationLoaded('detail') ? $kegiatan->detail->first() : null;

        return $detail?->getAttribute($input) ?? $kegiatan->getAttribute($input);
    }

    public function show(Request $request, int $id)
    {
        $kegiatan = Kegiatan::untukUser($request->user())->with([
            'kategori.jenisKegiatan', 'detail.isianKategori', 'statusTerkini', 'dokumen.jenisDokumen',
            'anggota.user.mahasiswa', 'anggota.user.dosen', 'anggota.user.role',
        ])->findOrFail($id);

        return view($request->routeIs('dosen.*') ? 'dosen.kegiatan.show' : 'kegiatan.show', [
            'id' => $kegiatan->getKey(),
            'catatan' => $kegiatan->statusTerkini?->catatan_revisi,
            'k' => [
                'nama' => $kegiatan->nama_kegiatan,
                'kategori' => $kegiatan->kategori?->nama_kategori ?? '—',
                'jenis' => $kegiatan->kategori?->jenisKegiatan?->nama_jenis_kegiatan ?? '—',
                'penyelenggara' => $kegiatan->nilaiIsian('penyelenggara') ?? '—',
                'tahun' => $kegiatan->tanggal_mulai?->year ?? '—',
                'mulai' => $kegiatan->tanggal_mulai?->locale('id')->translatedFormat('j F Y') ?? '—',
                'selesai' => $kegiatan->tanggal_selesai?->locale('id')->translatedFormat('j F Y') ?? '—',
                'status' => ucfirst($kegiatan->statusTerkini?->status ?? 'Belum diajukan'),
            ],
            'anggota' => $kegiatan->anggota->map(fn ($a) => [
                'nama' => $a->user?->mahasiswa?->nama ?? $a->user?->dosen?->nama ?? $a->user?->email ?? '—',
                'status' => $a->user?->role?->nama_role ?? '—',
            ]),
            'dokumen' => $kegiatan->dokumen->map(fn ($d) => [
                'file' => $d->nama_file, 'nama' => $d->nama_file,
                'url' => $this->documentUrl($d->link_dokumen),
                'jenis' => $d->jenisDokumen?->nama_jenis_dokumen ?? $d->tipe_dokumen,
                'keterangan' => null, 'tautan' => $this->documentUrl($d->link_dokumen),
            ]),
        ]);
    }

    private function documentUrl(?string $link): ?string
    {
        if (! $link || $link === '#') {
            return null;
        }
        if (preg_match('~^https?://~i', $link)) {
            return $link;
        }
        if (str_starts_with($link, '/') && ! str_starts_with($link, '//')) {
            return $link;
        }
        if (parse_url($link, PHP_URL_SCHEME) !== null || str_starts_with($link, '//')) {
            return null;
        }

        return Storage::disk('public')->url($link);
    }
}
