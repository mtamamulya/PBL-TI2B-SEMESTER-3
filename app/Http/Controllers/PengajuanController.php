<?php

namespace App\Http\Controllers;

use App\BackendKel2\PengajuanMahasiswa\PengajuanMahasiswaController;
use App\BackendKel3\Pengajuan\AlurPengajuan;
use App\Models\JenisDokumen;
use App\Models\Kegiatan;
use App\Models\Timeline;
use App\Models\User;
use App\Repositories\SuratPersetujuanStore;
use App\Support\ApiClient;
use App\Support\ApiException;
use App\Support\PanggilApiWeb;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PengajuanController extends Controller
{
    use PanggilApiWeb;

    private const KEY = 'pengajuan.items';

    private const DELETED_KEY = 'pengajuan.deleted';

    private const EDITABLE = ['Diajukan', 'Direview', 'Perlu revisi', 'Direvisi'];

    private const STUDENT_CATEGORY_ORDER = ['lomba', 'volunteer', 'kkl', 'magang', 'pengabdian', 'penelitian'];

    private function canUseExternalApi(): bool
    {
        return ! app()->runningUnitTests() && filled(session('api_token'));
    }

    private function mapStatusLabel(?string $rawStatus): string
    {
        return match (strtolower((string) $rawStatus)) {
            'diajukan' => 'Diajukan',
            'direview' => 'Direview',
            'diterima' => 'Disetujui',
            'ditolak' => 'Ditolak',
            'direvisi' => 'Perlu revisi',
            'selesai' => 'Selesai',
            default => 'Diajukan',
        };
    }

    private function studentProfile(?User $user = null): array
    {
        $user = $user ?: Auth::user();
        $mhs = $user?->mahasiswa;
        $penempatan = $user && $mhs
            ? DB::table('enrollment_mahasiswa_tingkat_kelas as emtk')
                ->join('enrollment_tingkat_kelas as etk', 'etk.id_enrollment_tingkat_kelas', '=', 'emtk.id_enrollment_tingkat_kelas')
                ->join('tahun_akademik as ta', 'ta.id_tahun_akademik', '=', 'etk.id_tahun_akademik')
                ->join('prodi as p', 'p.id_prodi', '=', 'etk.id_prodi')
                ->where('emtk.id_mahasiswa', $mhs->getKey())
                ->orderByDesc('ta.status')
                ->orderByDesc('ta.id_tahun_akademik')
                ->value('p.nama_prodi')
            : null;

        return [
            'name' => $mhs?->nama ?? $user?->email ?? config('mahasiswa.nama'),
            'nim' => $mhs?->nim ?? ($user ? '—' : config('mahasiswa.nim')),
            'prodi' => $penempatan ?? ($user ? '—' : config('mahasiswa.prodi')),
            'angkatan' => $mhs?->tahun_masuk ?? ($user ? '—' : config('mahasiswa.angkatan')),
        ];
    }

    private function databaseKegiatan(): Collection
    {
        $user = Auth::user();

        if (! $user) {
            return collect();
        }

        return Kegiatan::where('id_user', $user->getKey())
            ->with(['kategori', 'statusTerkini', 'timeline', 'user.mahasiswa', 'detail.isianKategori', 'dokumen'])
            ->orderByDesc('id_kegiatan')
            ->get();
    }

    private function items(): array
    {
        return session(self::KEY, []);
    }

    private function all(): Collection
    {
        $mine = collect($this->items())->keyBy('id');
        $deleted = collect(session(self::DELETED_KEY, []));

        return $mine
            ->reject(fn (array $item) => $deleted->contains($item['id']))
            ->values();
    }

    public function activityOptions(): Collection
    {
        $fromDb = $this->databaseKegiatan()->map(function (Kegiatan $kegiatan) {
            $category = $kegiatan->kategori?->nama_kategori ?? 'Kegiatan';
            $date = $kegiatan->tanggal_mulai?->toDateString() ?? Carbon::parse($kegiatan->created_at)->toDateString();

            return [
                'id' => $kegiatan->getKey(),
                'title' => $kegiatan->nama_kegiatan,
                'name' => $kegiatan->nama_kegiatan,
                'category' => $category,
                'type' => $category,
                'date' => Carbon::parse($date)->locale('id')->translatedFormat('d M Y'),
                'raw_date' => $date,
                'status' => $this->mapStatusLabel($kegiatan->statusTerkini?->status),
                'note' => $kegiatan->nilaiIsian('deskripsi'),
                'capaian' => $kegiatan->nilaiIsian('posisi_dalam_kegiatan') ?? 'Peserta',
                'tingkat' => $kegiatan->nilaiIsian('tingkat_kejuaraan') ?? '—',
                'penyelenggara' => $kegiatan->nilaiIsian('penyelenggara') ?? '—',
                'file_name' => $kegiatan->dokumen->first()?->nama_file,
                'members' => $kegiatan->nilaiIsian('anggota') ? json_decode($kegiatan->nilaiIsian('anggota'), true) : [],
                'documents' => $kegiatan->dokumen->map(fn ($document) => [
                    'name' => $document->nama_file,
                    'type' => $document->tipe_dokumen,
                    'url' => $document->link_dokumen,
                ])->all(),
            ];
        });

        $fromSession = $this->all()
            ->concat(collect(session('kegiatan.history', [])))
            ->map(function (array $item) {
                $data = $item['data'] ?? [];
                $date = $data['start_date'] ?? Carbon::parse($item['submitted_at'])->toDateString();

                return [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'name' => $item['title'],
                    'category' => $item['category'],
                    'type' => $item['category'],
                    'date' => Carbon::parse($date)->locale('id')->translatedFormat('d M Y'),
                    'raw_date' => $date,
                    'status' => $item['status'],
                    'note' => $data['description'] ?? null,
                    'capaian' => $data['role'] ?? 'Peserta',
                    'tingkat' => $data['level'] ?? $data['scope'] ?? '—',
                    'penyelenggara' => $data['organizer'] ?? '—',
                    'file_name' => $item['documents'][0]['name'] ?? null,
                    'members' => $data['members'] ?? [],
                    'documents' => $item['documents'] ?? [],
                ];
            });

        return $fromDb->concat($fromSession)->values();
    }

    private function find(int $id): array
    {
        $item = $this->all()->firstWhere('id', $id);
        abort_if(! $item, 404);

        return $item;
    }

    private function canEdit(array $item): bool
    {
        return in_array($item['status'], self::EDITABLE, true);
    }

    private function findEditable(int $id): array
    {
        $item = $this->find($id);
        abort_unless($this->canEdit($item), 403, 'Pengajuan dengan status '.$item['status'].' tidak dapat diedit.');

        return $item;
    }

    private function rows(): Collection
    {
        return $this->databaseKegiatan()->map(function (Kegiatan $k) {
            $statusLabel = $this->mapStatusLabel($k->statusTerkini?->status);
            $categoryName = $k->kategori?->nama_kategori ?? 'Kegiatan';
            $submittedAt = $k->timeline->first()?->tanggal_status
                ?? $k->created_at
                ?? now();

            return [
                'id' => $k->id_kegiatan,
                'title' => $k->nama_kegiatan,
                'category' => $categoryName,
                'type' => $this->typeFor($categoryName),
                'status' => $statusLabel,
                'submitted_at' => Carbon::parse($submittedAt),
                'editable' => in_array($statusLabel, self::EDITABLE, true),
            ];
        })
            ->sortByDesc(fn ($r) => $r['submitted_at']->timestamp * 1000 + $r['id'])
            ->values();
    }

    public function index(Request $request)
    {
        return $this->renderIndex($request, 'mahasiswa.pengajuan.index');
    }

    public function dashboard()
    {
        $submissions = $this->rows();
        $profile = $this->studentProfile();

        return view('mahasiswa.pengajuan.dashboard', [
            'mahasiswa' => array_merge(config('mahasiswa', []), [
                'nama' => $profile['name'],
                'nim' => $profile['nim'],
                'prodi' => $profile['prodi'],
                'angkatan' => $profile['angkatan'],
            ]),
            'totalPengajuan' => $submissions->count(),
            'totalKegiatan' => $submissions->count(),
            'latestSubmissions' => $submissions->take(3),
        ]);
    }

    private function renderIndex(Request $request, string $view)
    {
        $all = $this->rows();
        $q = trim($request->string('q')->toString());
        $status = $request->string('status')->toString();
        $isDate = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
        $from = $isDate($request->string('from')->toString());
        $to = $isDate($request->string('to')->toString());

        $filtered = $all->filter(function ($r) use ($q, $status, $from, $to) {
            $date = $r['submitted_at']->toDateString();

            return (! $status || $r['status'] === $status)
                && (! $q || str_contains(mb_strtolower($r['title'].' '.$r['category']), mb_strtolower($q)))
                && (! $from || $date >= $from)
                && (! $to || $date <= $to);
        })->values();

        $perPage = 10;
        $page = max(1, (int) $request->input('page', 1));
        $rows = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $counts = $all->countBy('status');

        return view($view, compact('rows', 'all', 'counts', 'q', 'status', 'from', 'to'));
    }

    private function isKategoriButuhSurat(?string $kebutuhan): bool
    {
        return in_array(mb_strtolower(trim((string) $kebutuhan)), ['surat', 'butuh_surat', 'butuh surat'], true);
    }

    private function filterCategoriesByRole(Collection $categories, ?string $targetRole, bool $onlySurat = false): Collection
    {
        $filtered = $categories->filter(function (array $kat) use ($targetRole, $onlySurat) {
            if ($onlySurat && $targetRole !== 'dosen' && ! request()->routeIs('pengajuan.dosen.*') && ! $this->isKategoriButuhSurat($kat['kebutuhan'] ?? null)) {
                return false;
            }

            if ($targetRole === null || $kat['jenis'] === '') {
                return true;
            }

            $jenisLower = mb_strtolower($kat['jenis']);

            return match ($targetRole) {
                'mahasiswa' => str_contains($jenisLower, 'mahasiswa'),
                'dosen' => str_contains($jenisLower, 'dosen'),
                default => true,
            };
        })->values();

        if ($targetRole !== 'mahasiswa') {
            return $filtered;
        }

        return $filtered
            ->filter(fn (array $category) => $this->kategoriMahasiswaKey($category['nama_kategori'] ?? '') !== null)
            ->unique(fn (array $category) => $this->kategoriMahasiswaKey($category['nama_kategori'] ?? ''))
            ->sortBy(fn (array $category) => array_search(
                $this->kategoriMahasiswaKey($category['nama_kategori'] ?? ''),
                self::STUDENT_CATEGORY_ORDER,
                true
            ))
            ->values();
    }

    private function kategoriMahasiswaKey(string $nama): ?string
    {
        $nama = mb_strtolower(trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $nama) ?? $nama));
        $nama = match ($nama) {
            'kuliah kerja lapangan' => 'kkl',
            default => $nama,
        };

        return in_array($nama, self::STUDENT_CATEGORY_ORDER, true)
            ? $nama
            : null;
    }

    private function completeStudentCategories(Collection $categories, bool $onlySurat = false): Collection
    {
        $apiCategories = $this->filterCategoriesByRole($categories, 'mahasiswa', $onlySurat)
            ->keyBy(fn (array $category) => $this->kategoriMahasiswaKey($category['nama_kategori'] ?? ''));
        $localCategories = $this->localDynamicCategories('mahasiswa', $onlySurat)
            ->keyBy(fn (array $category) => $this->kategoriMahasiswaKey($category['nama_kategori'] ?? ''));

        $missingFromApi = collect(self::STUDENT_CATEGORY_ORDER)
            ->filter(fn (string $key) => ! $apiCategories->has($key))
            ->values();
        $missingFromLocal = collect(self::STUDENT_CATEGORY_ORDER)
            ->filter(fn (string $key) => ! $localCategories->has($key))
            ->values();

        if ($missingFromApi->isNotEmpty()) {
            \Log::warning('API kategori mahasiswa belum memuat semua kategori kartu; melengkapi dari data lokal.', [
                'missing_categories' => $missingFromApi->all(),
            ]);
        }

        if ($missingFromLocal->isNotEmpty()) {
            \Log::warning('Database lokal belum memiliki semua kategori kartu mahasiswa.', [
                'missing_categories' => $missingFromLocal->all(),
            ]);
        }

        return collect(self::STUDENT_CATEGORY_ORDER)
            ->map(fn (string $key) => $apiCategories->get($key) ?? $localCategories->get($key))
            ->filter()
            ->values();
    }

    private function localDynamicCategories(?string $targetRole = null, bool $onlySurat = false): Collection
    {
        $categories = DB::table('kategori')
            ->leftJoin('jenis_kegiatan', 'kategori.id_jenis_kegiatan', '=', 'jenis_kegiatan.id_jenis_kegiatan')
            ->select('kategori.*', 'jenis_kegiatan.nama_jenis_kegiatan as jenis')
            ->orderBy('kategori.id_kategori')
            ->get()
            ->map(function ($row) {
                $isian = DB::table('isian_kategori')
                    ->where('id_kategori', $row->id_kategori)
                    ->orderBy('id_isian_kategori')
                    ->get()
                    ->map(fn ($i) => [
                        'id_isian_kategori' => (int) $i->id_isian_kategori,
                        'input' => $i->input,
                        'jenis_input' => $i->jenis_input ?? 'teks',
                        'wajib' => in_array(mb_strtoupper((string) ($i->aturan ?? '')), ['NOT NULL', 'WAJIB', 'REQUIRED'], true),
                        'jumlah_isian' => $i->jumlah_isian,
                    ])
                    ->all();

                return [
                    'id_kategori' => (int) $row->id_kategori,
                    'nama_kategori' => $row->nama_kategori ?? '',
                    'jenis' => (string) ($row->jenis ?? ''),
                    'deskripsi' => $row->deskripsi ?? null,
                    'kebutuhan' => $row->kebutuhan ?? 'kegiatan',
                    'lampiran_peserta' => false,
                    'isian' => $isian,
                ];
            });

        return $this->filterCategoriesByRole($categories, $targetRole, $onlySurat);
    }

    private function dynamicCategories(?string $targetRole = null, bool $onlySurat = false): Collection
    {
        if (! $this->canUseExternalApi()) {
            return $this->localDynamicCategories($targetRole, $onlySurat);
        }

        try {
            $hasil = ApiClient::pengguna()->get('/kategori-kegiatan', ['per_page' => 100]);
            $daftar = $hasil['data'] ?? [];

            if (isset($daftar['data']) && is_array($daftar['data'])) {
                $daftar = $daftar['data'];
            }

            $categories = collect($daftar)
                ->map(function (array $item) {
                    $idKategori = (int) ($item['id_kategori'] ?? 0);
                    $jenis = (string) ($item['jenis']
                        ?? $item['nama_jenis_kegiatan']
                        ?? ($item['jenis_kegiatan']['nama_jenis_kegiatan'] ?? ''));

                    $detail = $this->detailKategoriApi($idKategori);

                    return [
                        'id_kategori' => $idKategori,
                        'nama_kategori' => $item['nama_kategori'] ?? '',
                        'jenis' => $jenis,
                        'deskripsi' => $item['deskripsi'] ?? null,
                        'kebutuhan' => $detail['kebutuhan'] ?? $item['kebutuhan'] ?? 'kegiatan',
                        'lampiran_peserta' => (bool) ($detail['lampiran_peserta'] ?? $item['lampiran_peserta'] ?? false),
                        'isian' => $detail['isian'] ?? [],
                    ];
                });

            if ($targetRole === 'mahasiswa') {
                return $this->completeStudentCategories($categories, $onlySurat);
            }

            $filteredCategories = $this->filterCategoriesByRole($categories, $targetRole, $onlySurat);

            if ($filteredCategories->isEmpty()) {
                \Log::warning('API kategori kegiatan tidak mengembalikan kategori yang dapat digunakan; menggunakan data lokal.', [
                    'role' => $targetRole,
                ]);

                return $this->localDynamicCategories($targetRole, $onlySurat);
            }

            return $filteredCategories;
        } catch (ApiException $e) {
            if ($e->getStatusCode() === 401) {
                throw $e;
            }

            \Log::warning('Gagal memuat kategori kegiatan dari API: '.$e->getMessage());

            return $this->localDynamicCategories($targetRole, $onlySurat);
        }
    }

    private function detailKategoriApi(int $idKategori): array
    {
        if ($idKategori <= 0) {
            return [];
        }

        if (! $this->canUseExternalApi()) {
            $local = $this->localDynamicCategories(null, false)->firstWhere('id_kategori', $idKategori);

            return is_array($local) ? $local : [];
        }

        try {
            $res = ApiClient::pengguna()->get('/kategori-kegiatan/'.$idKategori);

            return $res['data'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function mahasiswaOptions(): Collection
    {
        if (! Schema::hasTable('mahasiswa')) {
            return collect();
        }

        $currentUserId = (int) (Auth::id() ?? 0);

        return DB::table('mahasiswa')
            ->when($currentUserId > 0, fn ($q) => $q->where('id_user', '!=', $currentUserId))
            ->orderBy('nama')
            ->select('id_mahasiswa', 'id_user', 'nim', 'nama')
            ->get()
            ->map(fn ($m) => [
                'id_user' => (int) $m->id_user,
                'nim' => (string) $m->nim,
                'nama' => (string) $m->nama,
                'label' => "{$m->nama} ({$m->nim})",
            ])
            ->values();
    }

    private function savedTeamData(int $idKegiatan): array
    {
        if (! Schema::hasTable('anggota_kegiatan')) {
            return ['participation' => 'Individu', 'member_ids' => []];
        }

        $currentUserId = (int) (Auth::id() ?? 0);
        $rows = DB::table('anggota_kegiatan')
            ->where('id_kegiatan', $idKegiatan)
            ->orderBy('id_anggota_kegiatan')
            ->get();

        if ($rows->isEmpty()) {
            return ['participation' => 'Individu', 'member_ids' => []];
        }

        $memberIds = $rows
            ->filter(fn ($r) => (int) $r->id_user !== $currentUserId && mb_strtolower((string) $r->peran) !== 'ketua')
            ->pluck('id_user')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $hasTeam = $memberIds !== [] || $rows->contains(fn ($r) => mb_strtolower((string) $r->peran) === 'ketua');

        return [
            'participation' => $hasTeam ? 'Tim' : 'Individu',
            'member_ids' => $memberIds,
        ];
    }

    private function savedDetailValues(int $idKegiatan): array
    {
        $rows = DB::table('detail_kegiatan')
            ->join('isian_kategori', 'detail_kegiatan.id_isian_kategori', '=', 'isian_kategori.id_isian_kategori')
            ->where('detail_kegiatan.id_kegiatan', $idKegiatan)
            ->orderBy('detail_kegiatan.id_detail_kegiatan')
            ->select('detail_kegiatan.id_isian_kategori', 'detail_kegiatan.isian', 'isian_kategori.jumlah_isian')
            ->get();

        $values = [];
        foreach ($rows as $row) {
            $idIsian = (int) $row->id_isian_kategori;
            $isMultiple = empty($row->jumlah_isian);

            if ($isMultiple) {
                $values[$idIsian][] = $row->isian;
            } else {
                $values[$idIsian] = $row->isian;
            }
        }

        return $values;
    }

    public function create()
    {
        return view('mahasiswa.pengajuan.create', [
            'item' => null,
            'advisors' => $this->advisorOptions(),
            'categoriesData' => $this->dynamicCategories('mahasiswa', onlySurat: true),
            'mahasiswaList' => $this->mahasiswaOptions(),
        ]);
    }

    public function createActivity()
    {
        return view('mahasiswa.pengajuan.create', [
            'item' => null,
            'advisors' => $this->advisorOptions(),
            'categoriesData' => $this->dynamicCategories('mahasiswa', onlySurat: false),
            'mahasiswaList' => $this->mahasiswaOptions(),
            'isActivityHistory' => true,
        ]);
    }

    public function createDosen()
    {
        return view('dosen.pengajuan.create', [
            'item' => null,
            'advisors' => $this->advisorOptions(),
            'categoriesData' => $this->dynamicCategories('dosen', onlySurat: true),
        ]);
    }

    public function edit(int $id)
    {
        $kegiatan = Kegiatan::where('id_user', Auth::id())->with(['kategori', 'dokumen'])->find($id);
        $role = request()->routeIs('pengajuan.dosen.*') ? 'dosen' : 'mahasiswa';

        if ($kegiatan) {
            $backend = new PengajuanMahasiswaController;
            $detail = $backend->showDetailData($id);
            $row = $detail['row'];
            $status = $row['status'] ?? 'Diajukan';

            abort_unless(in_array($status, self::EDITABLE, true), 403, 'Pengajuan dengan status '.$status.' tidak dapat diedit.');

            $teamData = $this->savedTeamData($id);

            $item = [
                'id' => $kegiatan->id_kegiatan,
                'id_kategori' => $kegiatan->id_kategori,
                'title' => $kegiatan->nama_kegiatan,
                'category' => $kegiatan->kategori?->nama_kategori ?? 'Kegiatan',
                'type' => $row['type'] ?? 'Kegiatan',
                'status' => $status,
                'submitted_at' => Carbon::parse($row['submitted_at'])->toIso8601String(),
                'data' => [
                    'id_kategori' => $kegiatan->id_kategori,
                    'title' => $kegiatan->nama_kegiatan,
                    'participation' => $teamData['participation'],
                    'member_ids' => $teamData['member_ids'],
                    'detail' => $this->savedDetailValues($id),
                ],
                'documents' => $kegiatan->dokumen->map(fn ($doc) => [
                    'name' => $doc->nama_file ?? $doc->nama_dokumen ?? 'Dokumen',
                    'type' => $doc->tipe_dokumen ?? 'Dokumentasi',
                    'url' => $doc->link_dokumen ?? '',
                ])->all(),
            ];

            return view('mahasiswa.pengajuan.create', [
                'item' => $item,
                'advisors' => $this->advisorOptions(),
                'categoriesData' => $this->dynamicCategories($role, onlySurat: true),
                'mahasiswaList' => $this->mahasiswaOptions(),
            ]);
        }

        return view('mahasiswa.pengajuan.create', [
            'item' => $this->findEditable($id),
            'advisors' => $this->advisorOptions(),
            'categoriesData' => $this->dynamicCategories($role, onlySurat: true),
            'mahasiswaList' => $this->mahasiswaOptions(),
        ]);
    }

    private function advisorOptions(): Collection
    {
        return collect(config('mahasiswa.dosen_mata_kuliah', []));
    }

    public function show(int $id)
    {
        $kegiatan = Kegiatan::untukUser(Auth::user())
            ->with(['kategori', 'user.mahasiswa', 'timeline', 'dokumen.jenisDokumen'])
            ->findOrFail($id);

        $student = $this->studentProfile($kegiatan->user);

        $actorMap = [
            'diajukan' => $student['name'],
            'direview' => 'Tim administrasi',
            'diterima' => 'Reviewer akademik',
            'ditolak' => 'Reviewer akademik',
            'direvisi' => 'Reviewer akademik',
            'selesai' => 'Tim administrasi',
        ];

        $defaultNoteMap = [
            'diajukan' => 'Pengajuan dikirim untuk ditinjau.',
            'direview' => 'Verifikasi persyaratan dan periode pengajuan.',
            'diterima' => 'Pengajuan disetujui. Menunggu penerbitan dokumen hasil.',
            'ditolak' => 'Pengajuan ditolak. Proses dihentikan.',
            'direvisi' => 'Perlu perbaikan sesuai catatan reviewer.',
            'selesai' => 'Dokumen diterbitkan dan proses pengajuan ditutup.',
        ];

        $apiKegiatan = null;
        try {
            if ($this->canUseExternalApi()) {
                $responsApi = $this->apiGet('mahasiswa/timeline', [
                    'id_kegiatan' => $id,
                    'per_page' => 1,
                ]);
                $apiKegiatan = $responsApi['data'][0] ?? null;
            }
        } catch (\Throwable) {
            // Fallback ke database lokal
        }

        if (is_array($apiKegiatan) && ! empty($apiKegiatan['timeline'])) {
            $apiTimelines = collect($apiKegiatan['timeline']);
            $latestApi = $apiTimelines->last();

            $statusLabel = $this->mapStatusLabel($apiKegiatan['status'] ?? ($latestApi['status'] ?? 'diajukan'));
            $submittedAt = $apiTimelines->first()['tanggal_status'] ?? $kegiatan->created_at ?? now();
            $reviewNote = $latestApi['catatan_revisi'] ?? null;

            $steps = $apiTimelines->map(function (array $t) use ($actorMap, $defaultNoteMap) {
                $statusKey = strtolower((string) ($t['status'] ?? 'diajukan'));

                return [
                    'title' => $t['label_status'] ?? $this->mapStatusLabel($statusKey),
                    'at' => Carbon::parse($t['tanggal_status'] ?? now())->setTimezone('Asia/Jakarta'),
                    'actor' => $actorMap[$statusKey] ?? 'Tim administrasi',
                    'note' => ! empty($t['catatan_revisi'])
                        ? $t['catatan_revisi']
                        : ($defaultNoteMap[$statusKey] ?? 'Status pengajuan diperbarui.'),
                ];
            });
        } else {
            $timelines = $kegiatan->timeline;
            $latestTimeline = $timelines->last();
            $statusLabel = $this->mapStatusLabel($latestTimeline?->status);
            $submittedAt = $timelines->first()?->tanggal_status ?? $kegiatan->created_at ?? now();
            $reviewNote = $latestTimeline?->catatan_revisi;

            $steps = $timelines->isNotEmpty()
                ? $timelines->map(function (Timeline $t) use ($actorMap, $defaultNoteMap) {
                    $statusKey = strtolower($t->status);

                    return [
                        'title' => $this->mapStatusLabel($statusKey),
                        'at' => Carbon::parse($t->tanggal_status ?? $t->created_at)->setTimezone('Asia/Jakarta'),
                        'actor' => $actorMap[$statusKey] ?? 'Tim administrasi',
                        'note' => $t->catatan_revisi ?: ($defaultNoteMap[$statusKey] ?? 'Status pengajuan diperbarui.'),
                    ];
                })
                : collect([[
                    'title' => 'Diajukan',
                    'at' => Carbon::parse($submittedAt)->setTimezone('Asia/Jakarta'),
                    'actor' => $student['name'],
                    'note' => 'Pengajuan dikirim untuk ditinjau.',
                ]]);
        }

        $categoryName = $apiKegiatan['kategori'] ?? ($kegiatan->kategori?->nama_kategori ?? 'Kegiatan');

        $row = [
            'id' => $kegiatan->id_kegiatan,
            'title' => $apiKegiatan['nama_kegiatan'] ?? $kegiatan->nama_kegiatan,
            'category' => $categoryName,
            'type' => $this->typeFor($categoryName),
            'status' => $statusLabel,
            'submitted_at' => Carbon::parse($submittedAt)->setTimezone('Asia/Jakarta'),
            'editable' => in_array($statusLabel, self::EDITABLE, true),
            'review_note' => $reviewNote,
            'nomor_sk' => $apiKegiatan['nomor_sk'] ?? null,
            'tanggal_sk' => $apiKegiatan['tanggal_sk'] ?? null,
        ];

        $detailRows = DB::table('detail_kegiatan')
            ->join('isian_kategori', 'detail_kegiatan.id_isian_kategori', '=', 'isian_kategori.id_isian_kategori')
            ->where('detail_kegiatan.id_kegiatan', $id)
            ->orderBy('detail_kegiatan.id_detail_kegiatan')
            ->select('isian_kategori.input', 'isian_kategori.jenis_input', 'detail_kegiatan.isian')
            ->get();

        $detailUploadedFiles = $detailRows
            ->where('jenis_input', 'berkas')
            ->filter(fn ($d) => filled($d->isian))
            ->map(function ($d) {
                $path = (string) $d->isian;
                $url = str_starts_with($path, 'http') ? $path : asset('storage/'.$path);

                return [
                    'name' => $d->input.' ('.basename($path).')',
                    'meta' => strtoupper(pathinfo($path, PATHINFO_EXTENSION) ?: 'FILE'),
                    'url' => $url,
                ];
            });

        $files = $kegiatan->dokumen->map(function ($doc) {
            $path = $doc->link_dokumen ?? $doc->path ?? $doc->file_path ?? $doc->url ?? null;
            $name = $doc->nama_file ?? $doc->nama_dokumen ?? $doc->name ?? ($path ? basename($path) : 'Dokumen pendukung');

            return [
                'name' => $name,
                'meta' => $doc->jenisDokumen?->nama_jenis_dokumen ?? $doc->tipe_dokumen ?? $doc->jenis_dokumen ?? $doc->type ?? strtoupper(pathinfo($name, PATHINFO_EXTENSION) ?: 'FILE'),
                'url' => $path ? (str_starts_with($path, 'http') ? $path : asset('storage/'.$path)) : null,
            ];
        })->reject(fn (array $file) => strcasecmp((string) $file['meta'], 'Surat hasil') === 0)
            ->concat($detailUploadedFiles)
            ->values()
            ->all();

        try {
            $dokumenApi = $this->apiGet('kegiatan/'.$id.'/dokumen')['data'] ?? [];
            if (is_array($dokumenApi) && ! empty($dokumenApi)) {
                $files = collect($dokumenApi)
                    ->reject(fn (array $doc) => strcasecmp((string) ($doc['jenis_dokumen'] ?? ''), 'Surat hasil') === 0)
                    ->map(fn (array $doc) => [
                        'name' => $doc['nama_file'] ?? 'Dokumen pendukung',
                        'meta' => $doc['jenis_dokumen'] ?? $doc['tipe_dokumen'] ?? 'Dokumen',
                        'url' => $doc['url'] ?? $doc['link_dokumen'] ?? null,
                        'preview_url' => $doc['link_preview'] ?? $doc['url'] ?? $doc['link_dokumen'] ?? null,
                    ])
                    ->concat($detailUploadedFiles)
                    ->values()
                    ->all();
            }
        } catch (\Throwable) {
            // Fallback to the already-loaded database relation.
        }

        $resultDocuments = [];
        try {
            $hasilApi = $this->apiGet('kegiatan/'.$id.'/dokumen-hasil')['data'] ?? [];
            if (is_array($hasilApi) && isset($hasilApi[0])) {
                $hasil = $hasilApi[0];
                $resultDocuments[] = [
                    'name' => $hasil['nama_file'] ?? 'Dokumen hasil',
                    'meta' => $hasil['jenis_dokumen'] ?? $hasil['tipe_dokumen'] ?? 'Dokumen hasil',
                    'url' => $hasil['url'] ?? $hasil['link_dokumen'] ?? null,
                    'preview_url' => $hasil['link_preview'] ?? $hasil['url'] ?? $hasil['link_dokumen'] ?? null,
                ];
            }
        } catch (\Throwable) {
            // A 403 before completion means the result is not available yet.
        }

        $item = $kegiatan->toArray();

        $detailFields = $detailRows
            ->map(function ($d) {
                $nilai = $d->isian;
                if ($d->jenis_input === 'dosen' && is_numeric($nilai)) {
                    $nilai = DB::table('dosen')->where('id_dosen', (int) $nilai)->value('nama') ?? $nilai;
                } elseif ($d->jenis_input === 'berkas' && filled($nilai)) {
                    $nilai = str_starts_with((string) $nilai, 'http') ? $nilai : asset('storage/'.$nilai);
                }

                return [
                    'label' => $d->input,
                    'value' => $nilai,
                    'is_file' => $d->jenis_input === 'berkas',
                ];
            })
            ->all();

        $teamMembers = [];
        if (Schema::hasTable('anggota_kegiatan')) {
            $teamMembers = DB::table('anggota_kegiatan')
                ->leftJoin('mahasiswa', 'anggota_kegiatan.id_user', '=', 'mahasiswa.id_user')
                ->leftJoin('users', 'anggota_kegiatan.id_user', '=', 'users.id_user')
                ->where('anggota_kegiatan.id_kegiatan', $id)
                ->orderBy('anggota_kegiatan.id_anggota_kegiatan')
                ->select(
                    'anggota_kegiatan.peran',
                    'mahasiswa.nama as nama_mhs',
                    'mahasiswa.nim',
                    'users.email'
                )
                ->get()
                ->map(fn ($m) => [
                    'nama' => $m->nama_mhs ?? $m->email ?? '-',
                    'nim' => $m->nim ?? '-',
                    'peran' => ucfirst((string) ($m->peran ?? 'Anggota')),
                ])
                ->all();
        }

        return view('mahasiswa.pengajuan.show', compact('row', 'steps', 'student', 'files', 'resultDocuments', 'item', 'detailFields', 'teamMembers'));
    }

    public function destroy(int $id)
    {
        $kegiatan = Kegiatan::where('id_user', Auth::id())->find($id);

        if ($kegiatan) {
            if ($this->canUseExternalApi()) {
                try {
                    ApiClient::pengguna()->delete('/pengajuan-mahasiswa/'.$id);

                    return redirect()->route('pengajuan.index')
                        ->with('success', 'Pengajuan berhasil dihapus.');
                } catch (ApiException $e) {
                    if ($e->getStatusCode() === 401) {
                        throw $e;
                    }

                    return back()->with('error', 'Gagal menghapus pengajuan melalui API: '.$e->getMessage());
                }
            }

            $kegiatan->delete();

            return redirect()->route('pengajuan.index')
                ->with('success', 'Pengajuan berhasil dihapus.');
        }

        $this->find($id);

        $items = collect($this->items())
            ->reject(fn (array $item) => $item['id'] === $id)
            ->values()
            ->all();
        $deleted = collect(session(self::DELETED_KEY, []))
            ->push($id)
            ->unique()
            ->values()
            ->all();

        session([
            self::KEY => $items,
            self::DELETED_KEY => $deleted,
        ]);

        return redirect()->route('pengajuan.index')
            ->with('success', 'Pengajuan berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        $members = ['exclude_unless:participation,Tim', 'required', 'json', function ($attr, $value, $fail) {
            if (count(json_decode($value, true) ?? []) < 1) {
                $fail('Tambahkan minimal satu anggota tim.');
            }
        }];

        $common = [
            'category' => ['required', Rule::in(['Lomba', 'Volunteer', 'Magang', 'Pengabdian'])],
            'location' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['required', 'array:name,type,url'],
            'documents.*.name' => ['required', 'string', 'max:255'],
            'documents.*.type' => ['required', Rule::in(['Dokumentasi', 'Surat Mitra', 'Surat Mahasiswa', 'Surat Pengabdian'])],
            'documents.*.url' => ['required', 'url', 'max:500'],
        ];

        $specific = match ($request->input('category')) {
            'Lomba' => [
                'title' => ['required', 'string', 'max:255'],
                'participation' => ['required', Rule::in(['Individu', 'Tim'])],
                'members' => $members,
                'level' => ['required', Rule::in(['Kabupaten', 'Provinsi', 'Nasional', 'Internasional'])],
                'organizer' => ['required', 'string', 'max:255'],
                'info_url' => ['nullable', 'url'],
                'advisors' => ['nullable', 'array'],
                'advisors.*' => ['required', 'string', Rule::in($this->advisorOptions()->pluck('nama')->all())],
            ],
            'Volunteer' => [
                'title' => ['required', 'string', 'max:255'],
                'organizer' => ['required', 'string', 'max:255'],
                'role' => ['required', 'string', 'max:255'],
            ],
            'Magang' => [
                'role' => ['required', 'string', 'max:255'],
                'organizer' => ['required', 'string', 'max:255'],
                'duration_months' => ['required', 'integer', 'min:1', 'max:60'],
            ],
            'Pengabdian' => [
                'title' => ['required', 'string', 'max:255'],
                'organizer' => ['required', 'string', 'max:255'],
                'info_url' => ['nullable', 'url'],
                'advisor' => ['nullable', 'string', 'max:255'],
            ],
            default => [],
        };

        $data = $request->validate($common + $specific);

        if ($data['category'] === 'Magang') {
            $data['title'] = 'Magang '.$data['role'].' di '.$data['organizer'];
        }

        if ($data['category'] === 'Lomba') {
            $data['advisors'] = array_values(array_unique($data['advisors'] ?? []));
            $data['advisor'] = $data['advisors'][0] ?? null;
        }

        if (isset($data['members'])) {
            $data['members'] = json_decode($data['members'], true);
        }

        return $data;
    }

    private function typeFor(string $category): string
    {
        return match ($category) {
            'Lomba', 'Volunteer', 'Magang' => 'Dukungan '.strtolower($category).' mahasiswa',
            default => 'Kegiatan '.$category,
        };
    }

    public function store(Request $request)
    {
        return $this->saveSubmission($request, 'pengajuan.show');
    }

    public function storeActivity(Request $request)
    {
        if ($request->filled('id_kategori')) {
            $validated = $this->validateDynamicSubmission($request, isUpdate: false);
            $idUser = auth()->id() ?? DB::table('users')->value('id_user');

            $kegiatan = Kegiatan::create([
                'id_kategori' => $validated['kategori_api']['id_kategori'],
                'id_user' => $idUser,
                'nama_kegiatan' => $validated['title'],
            ]);

            $this->syncDetailKegiatan((int) $kegiatan->id_kegiatan, $validated['detail']);
            $this->syncAnggotaKegiatan((int) $kegiatan->id_kegiatan, $validated['participation'], $validated['member_ids']);

            return redirect()->route('kegiatan.index')
                ->with('success', 'Kegiatan berhasil ditambahkan ke riwayat.');
        }

        $data = $this->validated($request);
        $documents = $data['documents'] ?? [];
        unset($data['documents']);

        $history = collect(session('kegiatan.history', []));
        $id = max(1000, (int) $history->max('id') + 1);
        $history->push([
            'id' => $id,
            'title' => $data['title'],
            'category' => $data['category'],
            'type' => $data['category'],
            'status' => 'Tercatat',
            'submitted_at' => now()->toIso8601String(),
            'data' => $data,
            'documents' => $documents,
        ]);
        session(['kegiatan.history' => $history->all()]);

        return redirect()->route('kegiatan.index')
            ->with('success', 'Kegiatan berhasil ditambahkan ke riwayat.');
    }

    public function storeDosen(Request $request)
    {
        $user = $request->user();
        abort_unless($user, 401);
        abort_unless($user->status && $user->role?->nama_role === 'dosen', 403);
        $request->validate(['lampiran' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048']]);
        $validated = $this->validateDynamicSubmission($request);
        $file = $request->file('lampiran');
        $paths = $validated['uploaded_paths'];
        try {
            $path = $file->store('lampiran-pengajuan', 'public');
            throw_unless($path, new \RuntimeException('Lampiran gagal disimpan.'));
            $paths[] = $path;
            $kegiatan = DB::transaction(function () use ($validated, $file, $path, $user) {
                $kegiatan = Kegiatan::create([
                    'id_kategori' => $validated['id_kategori'], 'id_user' => $user->getKey(),
                    'nama_kegiatan' => $validated['title'],
                ]);
                $this->syncDetailKegiatan($kegiatan->getKey(), $validated['detail']);
                $jenis = JenisDokumen::firstOrCreate(['nama_jenis_dokumen' => 'Dokumen Pendukung']);
                $kegiatan->dokumen()->create([
                    'id_jenis_dokumen' => $jenis->getKey(), 'nama_file' => $file->getClientOriginalName(),
                    'tipe_dokumen' => $file->getClientMimeType(), 'link_dokumen' => $path, 'is_lampiran' => $validated['kategori_api']['lampiran_peserta'] ?? false,
                ]);
                app(AlurPengajuan::class)->ajukanBaru($kegiatan->getKey(), $user->getKey());

                return $kegiatan;
            });
        } catch (\Throwable $error) {
            Storage::disk('public')->delete($paths);
            throw $error;
        }

        return redirect()->route('pengajuan.dosen.show', $kegiatan->getKey())->with('success', 'Pengajuan berhasil dikirim.');
    }

    private function validateDynamicSubmission(Request $request, bool $isUpdate = false): array
    {
        $idKategori = (int) $request->input('id_kategori');
        $isDosenRoute = $request->routeIs('pengajuan.dosen.*');
        $kategoriApi = $isDosenRoute && $idKategori > 0
            ? ($this->apiGet('/kategori-kegiatan/'.$idKategori)['data'] ?? [])
            : $this->detailKategoriApi($idKategori);

        $rules = [
            'id_kategori' => ['required', 'integer', function ($attr, $value, $fail) use ($request, $kategoriApi, $isDosenRoute) {
                if (empty($kategoriApi)) {
                    $fail('Kategori kegiatan tidak valid atau tidak ditemukan di API.');

                    return;
                }

                if (! $request->routeIs('kegiatan.*', 'pengajuan.dosen.*') && ! $this->isKategoriButuhSurat($kategoriApi['kebutuhan'] ?? null)) {
                    $fail('Kategori ini tidak memerlukan surat. Silakan daftarkan langsung melalui menu Kegiatan.');

                    return;
                }

                $jenisLower = mb_strtolower((string) ($kategoriApi['jenis']
                    ?? $kategoriApi['jenis_kegiatan']['nama_jenis_kegiatan']
                    ?? ''));

                if ($jenisLower !== '') {
                    if ($isDosenRoute && ! str_contains($jenisLower, 'dosen')) {
                        $fail('Kategori ini bukan untuk pengajuan dosen.');
                    }
                    if (! $isDosenRoute && ! str_contains($jenisLower, 'mahasiswa')) {
                        $fail('Kategori ini bukan untuk pengajuan mahasiswa.');
                    }
                }

                if (! $isDosenRoute) {
                    $namaKategori = $kategoriApi['nama_kategori']
                        ?? DB::table('kategori')->where('id_kategori', $value)->value('nama_kategori')
                        ?? '';
                    if ($this->kategoriMahasiswaKey($namaKategori) === null) {
                        $fail('Pilih salah satu kategori kegiatan mahasiswa yang tersedia.');
                    }
                }
            }],
            'title' => ['required', 'string', 'max:255'],
            'participation' => ['nullable', Rule::in(['Individu', 'Tim'])],
            'member_ids' => ['exclude_unless:participation,Tim', 'required', 'array', 'min:1'],
            'member_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id_user', Rule::notIn([(int) (Auth::id() ?? 0)])],
            'detail' => ['nullable', 'array'],
            'detail_files.*' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $files = is_array($value) ? $value : [$value];
                    foreach ($files as $file) {
                        if (! $file instanceof UploadedFile || ! $file->isValid()) {
                            $fail("The {$attribute} field must be a file.");

                            return;
                        }
                        $ext = strtolower($file->getClientOriginalExtension());
                        if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                            $fail("The {$attribute} field must be a file of type: pdf, jpg, jpeg, png.");

                            return;
                        }
                        if ($file->getSize() > 2048 * 1024) {
                            $fail("The {$attribute} field must not be greater than 2048 kilobytes.");

                            return;
                        }
                    }
                },
            ],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['required', 'array:name,type,url'],
            'documents.*.name' => ['required', 'string', 'max:255'],
            'documents.*.type' => ['required', Rule::in(['Dokumentasi', 'Surat Mitra', 'Surat Mahasiswa', 'Surat Pengabdian'])],
            'documents.*.url' => ['required', 'url', 'max:500'],
        ];

        $messages = [
            'id_kategori.required' => 'Kategori kegiatan wajib dipilih.',
            'title.required' => 'Nama / judul kegiatan wajib diisi.',
            'member_ids.required' => 'Tambahkan minimal satu teman mahasiswa jika memilih kepesertaan Tim.',
            'member_ids.min' => 'Tambahkan minimal satu teman mahasiswa jika memilih kepesertaan Tim.',
            'member_ids.*.distinct' => 'Anggota tim yang dipilih tidak boleh duplikat.',
            'member_ids.*.not_in' => 'Anda otomatis tercatat sebagai Ketua Tim, tidak perlu memilih diri sendiri.',
        ];

        foreach ($kategoriApi['isian'] ?? [] as $isian) {
            $idIsian = $isian['id_isian_kategori'];
            $label = $isian['input'] ?? 'Isian';
            $jenisInput = $isian['jenis_input'] ?? 'teks';
            $wajib = ! empty($isian['wajib']);
            $isMultiple = empty($isian['jumlah_isian']);

            if ($jenisInput === 'berkas') {
                if ($isMultiple) {
                    $rules["detail_files.{$idIsian}"] = [$wajib && ! $isUpdate ? 'required' : 'nullable', 'array', $wajib && ! $isUpdate ? 'min:1' : 'min:0'];
                    $rules["detail_files.{$idIsian}.*"] = ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'];
                    $messages["detail_files.{$idIsian}.required"] = "{$label} wajib diunggah minimal satu.";
                    $messages["detail_files.{$idIsian}.*.mimes"] = "Format {$label} harus berupa PDF, JPG, JPEG, atau PNG.";
                    $messages["detail_files.{$idIsian}.*.max"] = "Ukuran {$label} maksimal 2 MB.";
                } else {
                    $rules["detail_files.{$idIsian}"] = [$wajib && ! $isUpdate ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'];
                    $messages["detail_files.{$idIsian}.required"] = "{$label} wajib diunggah.";
                    $messages["detail_files.{$idIsian}.mimes"] = "Format {$label} harus berupa PDF, JPG, JPEG, atau PNG.";
                    $messages["detail_files.{$idIsian}.max"] = "Ukuran {$label} maksimal 2 MB.";
                }

                continue;
            }

            if ($isMultiple) {
                $rules["detail.{$idIsian}"] = [$wajib ? 'required' : 'nullable', 'array', $wajib ? 'min:1' : 'min:0'];
                $rules["detail.{$idIsian}.*"] = [$wajib ? 'required' : 'nullable', 'string', 'max:2000'];
                $messages["detail.{$idIsian}.required"] = "{$label} wajib diisi minimal satu.";
                $messages["detail.{$idIsian}.*.required"] = "Semua baris {$label} wajib diisi.";
            } else {
                $typeRule = match ($jenisInput) {
                    'angka' => ['numeric'],
                    'tanggal' => ['date'],
                    default => ['string', 'max:2000'],
                };
                $rules["detail.{$idIsian}"] = array_merge([$wajib ? 'required' : 'nullable'], $typeRule);
                $messages["detail.{$idIsian}.required"] = "{$label} wajib diisi.";
            }
        }

        $validated = $request->validate($rules, $messages);

        $detail = $validated['detail'] ?? [];
        $validated['uploaded_paths'] = [];
        if ($isDosenRoute) {
            $detail = array_intersect_key($detail, array_flip(array_column($kategoriApi['isian'] ?? [], 'id_isian_kategori')));
        }

        $uploadedDetailFiles = $request->file('detail_files', []);
        if (is_array($uploadedDetailFiles)) {
            foreach ($uploadedDetailFiles as $idIsian => $uploadedFile) {
                if (is_array($uploadedFile)) {
                    $paths = [];
                    foreach ($uploadedFile as $fileItem) {
                        if ($fileItem instanceof UploadedFile && $fileItem->isValid()) {
                            $stored = $fileItem->store('lampiran-isian', 'public');
                            $paths[] = $stored;
                            $validated['uploaded_paths'][] = $stored;
                        }
                    }
                    if ($paths !== []) {
                        $detail[$idIsian] = $paths;
                    }
                } elseif ($uploadedFile instanceof UploadedFile && $uploadedFile->isValid()) {
                    $detail[$idIsian] = $uploadedFile->store('lampiran-isian', 'public');
                    $validated['uploaded_paths'][] = $detail[$idIsian];
                }
            }
        }

        $validated['participation'] = $request->input('participation', 'Individu');
        $validated['member_ids'] = $validated['participation'] === 'Tim'
            ? array_values(array_unique(array_map('intval', $request->input('member_ids', []))))
            : [];
        $validated['detail'] = $detail;
        $validated['kategori_api'] = $kategoriApi;

        return $validated;
    }

    private function syncDetailKegiatan(int $idKegiatan, array $detail): void
    {
        foreach ($detail as $idIsian => $nilai) {
            DB::table('detail_kegiatan')
                ->where('id_kegiatan', $idKegiatan)
                ->where('id_isian_kategori', (int) $idIsian)
                ->delete();

            $items = is_array($nilai) ? $nilai : [$nilai];

            foreach ($items as $val) {
                if ($val === null || trim((string) $val) === '') {
                    continue;
                }

                DB::table('detail_kegiatan')->insert([
                    'id_kegiatan' => $idKegiatan,
                    'id_isian_kategori' => (int) $idIsian,
                    'isian' => (string) $val,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function syncDokumenKegiatan(int $idKegiatan, array $documents): void
    {
        DB::table('dokumen')->where('id_kegiatan', $idKegiatan)->delete();

        foreach ($documents as $doc) {
            if (empty($doc['url'])) {
                continue;
            }

            $tipeDokumen = $doc['type'] ?? 'Dokumentasi';
            $idJenisDokumen = null;

            if (Schema::hasTable('jenis_dokumen') && Schema::hasColumn('dokumen', 'id_jenis_dokumen')) {
                $kolomNama = Schema::hasColumn('jenis_dokumen', 'nama_jenis_dokumen')
                    ? 'nama_jenis_dokumen'
                    : (Schema::hasColumn('jenis_dokumen', 'nama_jenis') ? 'nama_jenis' : 'nama');

                $jenis = DB::table('jenis_dokumen')->where($kolomNama, $tipeDokumen)->first();

                if ($jenis) {
                    $idJenisDokumen = $jenis->id_jenis_dokumen ?? $jenis->id;
                } else {
                    $insertJenis = [$kolomNama => $tipeDokumen];
                    if (Schema::hasColumn('jenis_dokumen', 'created_at')) {
                        $insertJenis['created_at'] = now();
                        $insertJenis['updated_at'] = now();
                    }
                    $idJenisDokumen = DB::table('jenis_dokumen')->insertGetId($insertJenis);
                }
            }

            $payload = [
                'id_kegiatan' => $idKegiatan,
                'nama_file' => $doc['name'],
                'tipe_dokumen' => $tipeDokumen,
                'link_dokumen' => $doc['url'],
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($idJenisDokumen !== null) {
                $payload['id_jenis_dokumen'] = $idJenisDokumen;
            }

            DB::table('dokumen')->insert($payload);
        }
    }

    private function syncAnggotaKegiatan(int $idKegiatan, string $participation, array $memberIds): void
    {
        if (! Schema::hasTable('anggota_kegiatan')) {
            return;
        }

        DB::table('anggota_kegiatan')->where('id_kegiatan', $idKegiatan)->delete();

        if ($participation !== 'Tim') {
            return;
        }

        $leaderId = (int) (Auth::id() ?? DB::table('kegiatan')->where('id_kegiatan', $idKegiatan)->value('id_user'));
        $uniqueMembers = collect($memberIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0 && $id !== $leaderId)
            ->unique()
            ->values();

        if ($leaderId > 0) {
            DB::table('anggota_kegiatan')->insert([
                'id_kegiatan' => $idKegiatan,
                'id_user' => $leaderId,
                'peran' => 'Ketua',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($uniqueMembers as $memberUserId) {
            DB::table('anggota_kegiatan')->insert([
                'id_kegiatan' => $idKegiatan,
                'id_user' => $memberUserId,
                'peran' => 'Anggota',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function saveSubmission(Request $request, string $showRoute)
    {
        if ($request->filled('id_kategori')) {
            $validated = $this->validateDynamicSubmission($request, isUpdate: false);
            $kategoriApi = $validated['kategori_api'];

            $idUser = auth()->id()
                ?? DB::table('users')->value('id_user');

            if (! $this->canUseExternalApi()) {
                $kegiatan = Kegiatan::create([
                    'id_kategori' => $kategoriApi['id_kategori'],
                    'id_user' => $idUser,
                    'nama_kegiatan' => $validated['title'],
                ]);

                DB::table('timeline')->insert([
                    'id_kegiatan' => $kegiatan->id_kegiatan,
                    'status' => 'diajukan',
                    'tanggal_status' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->syncDetailKegiatan((int) $kegiatan->id_kegiatan, $validated['detail']);
                $this->syncDokumenKegiatan((int) $kegiatan->id_kegiatan, $validated['documents'] ?? []);
                $this->syncAnggotaKegiatan((int) $kegiatan->id_kegiatan, $validated['participation'], $validated['member_ids']);

                return redirect()->route($showRoute, $kegiatan->id_kegiatan)
                    ->with('success', 'Pengajuan berhasil dikirim.');
            }

            $payload = [
                'id_kategori' => $kategoriApi['id_kategori'],
                'id_user' => $idUser,
                'nama_kegiatan' => $validated['title'],
                'documents' => $validated['documents'] ?? [],
            ];

            try {
                $result = ApiClient::pengguna()->post('/pengajuan-mahasiswa', $payload);
                $newId = $result['data']['id_kegiatan'] ?? null;

                if ($newId) {
                    $this->syncDetailKegiatan((int) $newId, $validated['detail']);
                    $this->syncDokumenKegiatan((int) $newId, $validated['documents'] ?? []);
                    $this->syncAnggotaKegiatan((int) $newId, $validated['participation'], $validated['member_ids']);

                    return redirect()->route($showRoute, $newId)
                        ->with('success', 'Pengajuan berhasil dikirim melalui API.');
                }

            } catch (ApiException $e) {
                if ($e->getStatusCode() === 401) {
                    throw $e;
                }

                return back()
                    ->withInput()
                    ->with('error', 'Gagal menyimpan pengajuan ke API: '.$e->getMessage());
            }
        }

        // Fallback ke session saat pengujian tanpa id_kategori
        $data = $this->validated($request);
        $documents = $data['documents'] ?? [];
        unset($data['documents']);

        $items = $this->items();
        $id = max(100, (int) collect($items)->max('id')) + 1;

        $items[] = [
            'id' => $id,
            'title' => $data['title'],
            'category' => $data['category'],
            'type' => $this->typeFor($data['category']),
            'status' => 'Diajukan',
            'submitted_at' => now()->toIso8601String(),
            'data' => $data,
            'documents' => $documents,
        ];
        session([self::KEY => $items]);

        return redirect()->route($showRoute, $id)
            ->with('success', 'Pengajuan berhasil dikirim.');
    }

    public function update(Request $request, int $id)
    {
        $kegiatan = Kegiatan::where('id_user', Auth::id())->find($id);

        if ($kegiatan) {
            $backend = new PengajuanMahasiswaController;
            $detail = $backend->showDetailData($id);
            $status = $detail['row']['status'] ?? 'Diajukan';

            abort_unless(in_array($status, self::EDITABLE, true), 403, 'Pengajuan dengan status '.$status.' tidak dapat diedit.');

            $validated = $this->validateDynamicSubmission($request, isUpdate: true);
            $showRoute = request()->routeIs('pengajuan.dosen.*') ? 'pengajuan.dosen.show' : 'pengajuan.show';

            if (! $this->canUseExternalApi()) {
                $kegiatan->update([
                    'id_kategori' => $validated['kategori_api']['id_kategori'],
                    'nama_kegiatan' => $validated['title'],
                ]);

                $this->syncDetailKegiatan($id, $validated['detail']);
                $this->syncDokumenKegiatan($id, $validated['documents'] ?? []);
                $this->syncAnggotaKegiatan($id, $validated['participation'], $validated['member_ids']);

                if (in_array(strtolower(trim((string) $status)), ['perlu revisi', 'direvisi'], true)) {
                    DB::table('timeline')->insert([
                        'id_kegiatan' => $id,
                        'status' => 'diajukan',
                        'tanggal_status' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return redirect()->route($showRoute, $id)
                    ->with('success', 'Perubahan pengajuan berhasil disimpan.');
            }

            $payload = [
                'id_kategori' => $validated['kategori_api']['id_kategori'],
                'nama_kegiatan' => $validated['title'],
                'documents' => $validated['documents'] ?? [],
            ];

            try {
                ApiClient::pengguna()->post('/pengajuan-mahasiswa/'.$id, $payload);
                $this->syncDetailKegiatan($id, $validated['detail']);
                $this->syncDokumenKegiatan($id, $validated['documents'] ?? []);
                $this->syncAnggotaKegiatan($id, $validated['participation'], $validated['member_ids']);

                if (in_array(strtolower(trim((string) $status)), ['perlu revisi', 'direvisi'], true)) {
                    DB::table('timeline')->insert([
                        'id_kegiatan' => $id,
                        'status' => 'diajukan',
                        'tanggal_status' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                return redirect()->route($showRoute, $id)
                    ->with('success', 'Perubahan pengajuan berhasil disimpan melalui API.');
            } catch (ApiException $e) {
                if ($e->getStatusCode() === 401) {
                    throw $e;
                }

                return back()
                    ->withInput()
                    ->with('error', 'Gagal memperbarui pengajuan ke API: '.$e->getMessage());
            }
        }

        $item = $this->findEditable($id);
        $data = $this->validated($request);
        $documents = $data['documents'] ?? [];
        unset($data['documents']);

        $updated = array_merge($item, [
            'title' => $data['title'],
            'category' => $data['category'],
            'type' => $this->typeFor($data['category']),
            'data' => $data,
            'documents' => $documents,
            'updated_at' => now()->toIso8601String(),
        ]);

        $items = collect($this->items())->reject(fn ($i) => $i['id'] === $id)->push($updated)->values()->all();
        session([self::KEY => $items]);

        return redirect()->route('pengajuan.show', $id)
            ->with('success', 'Perubahan pengajuan berhasil disimpan.');
    }

    public function surat(int $id)
    {
        $kegiatan = Kegiatan::untukUser(Auth::user())
            ->with(['kategori', 'user.mahasiswa', 'dokumen', 'timeline'])
            ->findOrFail($id);

        $latestTimelineStatus = strtolower((string) ($kegiatan->timeline->sortByDesc('id_timeline')->first()?->status ?? ''));
        $backend = new PengajuanMahasiswaController;
        $detail = $backend->showDetailData($id);
        $status = strtolower(trim((string) ($detail['row']['status'] ?? '')));

        $isApproved = in_array($latestTimelineStatus, ['diterima', 'selesai'], true)
            || in_array($status, ['disetujui', 'diterima', 'selesai'], true);

        abort_unless(
            $isApproved,
            403,
            'Surat hasil hanya tersedia untuk pengajuan yang sudah disetujui.'
        );

        $student = $this->studentProfile($kegiatan->user);
        $snap = app(SuratPersetujuanStore::class)->find((string) $id);

        $detailRows = DB::table('detail_kegiatan')
            ->join('isian_kategori', 'detail_kegiatan.id_isian_kategori', '=', 'isian_kategori.id_isian_kategori')
            ->where('detail_kegiatan.id_kegiatan', $id)
            ->select('isian_kategori.input', 'detail_kegiatan.isian')
            ->get();

        $isianMap = [];
        foreach ($detailRows as $row) {
            if (! empty($row->input)) {
                $isianMap[strtolower(trim((string) $row->input))] = $row->isian ?? '-';
            }
        }

        $rawMulai = $kegiatan->tanggal_mulai ?? ($isianMap['tanggal mulai'] ?? null);
        $rawSelesai = $kegiatan->tanggal_selesai ?? ($isianMap['tanggal selesai'] ?? null);
        $mulai = $rawMulai ? Carbon::parse($rawMulai)->locale('id')->translatedFormat('j F Y') : '-';
        $selesai = $rawSelesai ? Carbon::parse($rawSelesai)->locale('id')->translatedFormat('j F Y') : null;
        $tanggal = ($selesai && $selesai !== $mulai) ? "{$mulai} s.d. {$selesai}" : $mulai;

        $lokasi = $isianMap['lokasi']
            ?? $isianMap['tempat magang']
            ?? $isianMap['tempat']
            ?? $isianMap['penyelenggara']
            ?? '-';

        $nomorSurat = $kegiatan->nomor_sk ?? null;
        $tanggalSurat = ! empty($kegiatan->tanggal_sk)
            ? Carbon::parse($kegiatan->tanggal_sk)->locale('id')->translatedFormat('j F Y')
            : Carbon::now()->locale('id')->translatedFormat('j F Y');

        $template = $kegiatan->kategori?->template_surat
            ?: "Dengan hormat,\n\nBerdasarkan pengajuan kegiatan mahasiswa Program Studi Teknologi Rekayasa Komputer, dengan ini Ketua Program Studi menerangkan dan menyetujui pelaksanaan kegiatan berikut:\n\nNama : [NAMA_PENGAJU]\nNIM / Identitas : [IDENTITAS]\nNama Kegiatan : [NAMA_KEGIATAN]\nTanggal : [TANGGAL]\nLokasi : [LOKASI]\n\nDemikian surat ini diterbitkan agar dapat dipergunakan sebagaimana mestinya. Atas perhatian dan kerja samanya, kami ucapkan terima kasih.";

        $pemetaan = [
            '[NAMA_PENGAJU]' => $student['name'] ?? '-',
            '[IDENTITAS]' => $student['nim'] ?? '-',
            '[NAMA_KEGIATAN]' => $kegiatan->nama_kegiatan ?? '-',
            '[TANGGAL]' => $tanggal,
            '[LOKASI]' => $lokasi,
            '[TANGGAL_SURAT]' => $tanggalSurat,
            '[NOMOR_SURAT]' => (string) ($nomorSurat ?: '-'),
        ];

        $isiSurat = nl2br(e(str_replace(array_keys($pemetaan), array_values($pemetaan), $template)));

        $ttdPath = $snap['tanda_tangan_path'] ?? null;
        $ttdUrl = ($ttdPath && Storage::disk('local')->exists($ttdPath))
            ? route('pengajuan.surat.ttd', $id)
            : null;

        $peserta = [];
        if (Schema::hasTable('anggota_kegiatan')) {
            $peserta = DB::table('anggota_kegiatan')
                ->leftJoin('mahasiswa', 'anggota_kegiatan.id_user', '=', 'mahasiswa.id_user')
                ->leftJoin('dosen', 'anggota_kegiatan.id_user', '=', 'dosen.id_user')
                ->leftJoin('users', 'anggota_kegiatan.id_user', '=', 'users.id_user')
                ->where('anggota_kegiatan.id_kegiatan', $id)
                ->orderBy('anggota_kegiatan.id_anggota_kegiatan')
                ->select(
                    'mahasiswa.nama as nama_mhs',
                    'dosen.nama as nama_dosen',
                    'users.email as nama_user',
                    'anggota_kegiatan.peran'
                )
                ->get()
                ->map(fn ($a) => [$a->nama_mhs ?? $a->nama_dosen ?? $a->nama_user ?? '-', ucfirst((string) ($a->peran ?? 'Anggota'))])
                ->all();
        }

        $lampiranDokumen = $kegiatan->dokumen
            ->filter(fn ($d) => (bool) ($d->is_lampiran ?? false))
            ->pluck('nama_file')
            ->values()
            ->all();

        return view('mahasiswa.pengajuan.surat', [
            'id' => $id,
            'judul' => $kegiatan->nama_kegiatan ?? '-',
            'nomorSurat' => $nomorSurat,
            'tanggalSurat' => $tanggalSurat,
            'isiSurat' => $isiSurat,
            'namaKaprodi' => $snap['kaprodi']['nama'] ?? 'Ketua Program Studi',
            'nipKaprodi' => $snap['kaprodi']['nip'] ?? '-',
            'ttdUrl' => $ttdUrl,
            'adaLampiran' => (bool) ($kegiatan->lampiran_peserta ?? false),
            'peserta' => $peserta,
            'lampiranDokumen' => $lampiranDokumen,
            'autoPrint' => request()->boolean('print'),
        ]);
    }

    public function ttdSurat(int $id)
    {
        Kegiatan::untukUser(Auth::user())->findOrFail($id);

        $snap = app(SuratPersetujuanStore::class)->find((string) $id);
        $path = $snap['tanda_tangan_path'] ?? null;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
