<?php

namespace App\Http\Controllers;

use App\BackendKel2\PengajuanMahasiswa\PengajuanMahasiswaController;
use App\Models\Kegiatan;
use App\Models\Timeline;
use App\Models\User;
use App\Support\ApiClient;
use App\Support\ApiException;
use App\Support\PanggilApiWeb;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PengajuanController extends Controller
{
    use PanggilApiWeb;

    private const KEY = 'pengajuan.items';

    private const DELETED_KEY = 'pengajuan.deleted';

    private const EDITABLE = ['Diajukan', 'Direview', 'Perlu revisi'];

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

        return [
            'name' => $mhs?->nama ?? $mhs?->nama_mahasiswa ?? config('mahasiswa.nama'),
            'nim' => $mhs?->nim ?? config('mahasiswa.nim'),
            'prodi' => $mhs?->prodi ?? $mhs?->program_studi ?? config('mahasiswa.prodi'),
        ];
    }

    private function databaseKegiatan(): Collection
    {
        $user = Auth::user();

        if (! $user) {
            return collect();
        }

        return Kegiatan::untukUser($user)
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

    private function filterCategoriesByRole(Collection $categories, ?string $targetRole): Collection
    {
        return $categories->filter(function (array $kat) use ($targetRole) {
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
    }

    private function localDynamicCategories(?string $targetRole = null): Collection
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

        return $this->filterCategoriesByRole($categories, $targetRole);
    }

    private function dynamicCategories(?string $targetRole = null): Collection
    {
        if (! $this->canUseExternalApi()) {
            return $this->localDynamicCategories($targetRole);
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
                        'kebutuhan' => $item['kebutuhan'] ?? 'kegiatan',
                        'lampiran_peserta' => (bool) ($detail['lampiran_peserta'] ?? $item['lampiran_peserta'] ?? false),
                        'isian' => $detail['isian'] ?? [],
                    ];
                });

            return $this->filterCategoriesByRole($categories, $targetRole);
        } catch (ApiException $e) {
            if ($e->getStatusCode() === 401) {
                throw $e;
            }

            \Log::warning('Gagal memuat kategori kegiatan dari API: '.$e->getMessage());

            return $this->localDynamicCategories($targetRole);
        }
    }

    private function detailKategoriApi(int $idKategori): array
    {
        if ($idKategori <= 0) {
            return [];
        }

        if (! $this->canUseExternalApi()) {
            $local = $this->localDynamicCategories(null)->firstWhere('id_kategori', $idKategori);

            return is_array($local) ? $local : [];
        }

        try {
            $res = ApiClient::pengguna()->get('/kategori-kegiatan/'.$idKategori);

            return $res['data'] ?? [];
        } catch (\Throwable) {
            return [];
        }
    }

    private function savedDetailValues(int $idKegiatan): array
    {
        $rows = DB::table('detail_kegiatan')
            ->join('isian_kategori', 'detail_kegiatan.id_isian_kategori', '=', 'isian_kategori.id_isian_kategori')
            ->where('detail_kegiatan.id_kegiatan', $idKegiatan)
            ->orderBy('detail_kegiatan.id_detail_kegiatan')
            ->select(
                'detail_kegiatan.id_isian_kategori',
                'detail_kegiatan.isian',
                'isian_kategori.jumlah_isian'
            )
            ->get();

        $values = [];
        foreach ($rows as $row) {
            $id = (string) $row->id_isian_kategori;
            $isMultiple = empty($row->jumlah_isian);

            if ($isMultiple) {
                $values[$id][] = $row->isian;
            } else {
                $values[$id] = $row->isian;
            }
        }

        return $values;
    }

    public function create()
    {
        return view('mahasiswa.pengajuan.create', [
            'item' => null,
            'advisors' => $this->advisorOptions(),
            'categoriesData' => $this->dynamicCategories('mahasiswa'),
        ]);
    }

    public function createActivity()
    {
        return view('mahasiswa.pengajuan.create', [
            'item' => null,
            'advisors' => $this->advisorOptions(),
            'categoriesData' => $this->dynamicCategories('mahasiswa'),
            'isActivityHistory' => true,
        ]);
    }

    public function createDosen()
    {
        return view('dosen.pengajuan.create', [
            'item' => null,
            'advisors' => $this->advisorOptions(),
            'categoriesData' => $this->dynamicCategories('dosen'),
        ]);
    }

    public function edit(int $id)
    {
        $kegiatan = Kegiatan::untukUser(Auth::user())->with(['kategori', 'dokumen'])->find($id);
        $role = request()->routeIs('pengajuan.dosen.*') ? 'dosen' : 'mahasiswa';

        if ($kegiatan) {
            $backend = new PengajuanMahasiswaController;
            $detail = $backend->showDetailData($id);
            $row = $detail['row'];
            $status = $row['status'] ?? 'Diajukan';

            abort_unless(in_array($status, self::EDITABLE, true), 403, 'Pengajuan dengan status '.$status.' tidak dapat diedit.');

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
                    'detail' => $this->savedDetailValues($id),
                ],
                'documents' => $kegiatan->dokumen->map(fn ($doc) => [
                    'name' => $doc->nama_file ?? $doc->nama_dokumen ?? 'Dokumen',
                    'type' => 'Dokumentasi',
                    'url' => $doc->link_dokumen ?? '',
                ])->all(),
            ];

            return view('mahasiswa.pengajuan.create', [
                'item' => $item,
                'advisors' => $this->advisorOptions(),
                'categoriesData' => $this->dynamicCategories($role),
            ]);
        }

        return view('mahasiswa.pengajuan.create', [
            'item' => $this->findEditable($id),
            'advisors' => $this->advisorOptions(),
            'categoriesData' => $this->dynamicCategories($role),
        ]);
    }

    private function advisorOptions(): Collection
    {
        return collect(config('mahasiswa.dosen_mata_kuliah', []));
    }

    public function show(int $id)
    {
        $kegiatan = Kegiatan::untukUser(Auth::user())
            ->with(['kategori', 'user.mahasiswa', 'timeline', 'dokumen'])
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

        $files = $kegiatan->dokumen->map(function ($doc) {
            $path = $doc->link_dokumen ?? $doc->path ?? $doc->file_path ?? $doc->url ?? null;
            $name = $doc->nama_file ?? $doc->nama_dokumen ?? $doc->name ?? ($path ? basename($path) : 'Dokumen pendukung');

            return [
                'name' => $name,
                'meta' => $doc->tipe_dokumen ?? $doc->jenis_dokumen ?? $doc->type ?? strtoupper(pathinfo($name, PATHINFO_EXTENSION) ?: 'FILE'),
                'url' => $path ? (str_starts_with($path, 'http') ? $path : asset('storage/'.$path)) : null,
            ];
        })->all();

        $item = $kegiatan->toArray();

        $detailFields = DB::table('detail_kegiatan')
            ->join('isian_kategori', 'detail_kegiatan.id_isian_kategori', '=', 'isian_kategori.id_isian_kategori')
            ->where('detail_kegiatan.id_kegiatan', $id)
            ->orderBy('detail_kegiatan.id_detail_kegiatan')
            ->select('isian_kategori.input', 'isian_kategori.jenis_input', 'detail_kegiatan.isian')
            ->get()
            ->map(function ($d) {
                $nilai = $d->isian;
                if ($d->jenis_input === 'dosen' && is_numeric($nilai)) {
                    $nilai = DB::table('dosen')->where('id_dosen', (int) $nilai)->value('nama') ?? $nilai;
                }

                return [
                    'label' => $d->input,
                    'value' => $nilai,
                    'is_file' => $d->jenis_input === 'berkas',
                ];
            })
            ->all();

        return view('mahasiswa.pengajuan.show', compact('row', 'steps', 'student', 'files', 'item', 'detailFields'));
    }

    public function destroy(int $id)
    {
        $kegiatan = Kegiatan::untukUser(Auth::user())->find($id);

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
        return $this->saveSubmission($request, 'pengajuan.dosen.show');
    }

    private function validateDynamicSubmission(Request $request, bool $isUpdate = false): array
    {
        $idKategori = (int) $request->input('id_kategori');
        $kategoriApi = $this->detailKategoriApi($idKategori);
        $isDosenRoute = $request->routeIs('pengajuan.dosen.*');

        $rules = [
            'id_kategori' => ['required', 'integer', function ($attr, $value, $fail) use ($kategoriApi, $isDosenRoute) {
                if (empty($kategoriApi)) {
                    $fail('Kategori kegiatan tidak valid atau tidak ditemukan di API.');

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
            }],
            'title' => ['required', 'string', 'max:255'],
            'detail' => ['nullable', 'array'],
            'detail_files' => ['nullable', 'array'],
            'detail_files.*' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'lampiran' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ];

        $messages = [
            'id_kategori.required' => 'Kategori kegiatan wajib dipilih.',
            'title.required' => 'Nama / judul kegiatan wajib diisi.',
        ];

        foreach ($kategoriApi['isian'] ?? [] as $isian) {
            $idIsian = $isian['id_isian_kategori'];
            $label = $isian['input'] ?? 'Isian';
            $jenisInput = $isian['jenis_input'] ?? 'teks';
            $wajib = ! empty($isian['wajib']);
            $isMultiple = empty($isian['jumlah_isian']);

            if ($jenisInput === 'berkas') {
                $rules["detail_files.{$idIsian}"] = [$wajib && ! $isUpdate ? 'required' : 'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'];
                $messages["detail_files.{$idIsian}.required"] = "{$label} wajib diunggah.";

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
        if ($request->hasFile('detail_files')) {
            foreach ($request->file('detail_files') as $idIsian => $uploadedFile) {
                if ($uploadedFile) {
                    $detail[$idIsian] = $uploadedFile->store('lampiran-isian', 'public');
                }
            }
        }

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

                return redirect()->route($showRoute, $kegiatan->id_kegiatan)
                    ->with('success', 'Pengajuan berhasil dikirim.');
            }

            $payload = [
                'id_kategori' => $kategoriApi['id_kategori'],
                'id_user' => $idUser,
                'nama_kegiatan' => $validated['title'],
            ];

            $berkas = [];
            if ($request->hasFile('lampiran')) {
                $berkas['lampiran'] = $request->file('lampiran');
            }

            try {
                $result = ApiClient::pengguna()->post('/pengajuan-mahasiswa', $payload, $berkas);
                $newId = $result['data']['id_kegiatan'] ?? null;

                if ($newId) {
                    $this->syncDetailKegiatan((int) $newId, $validated['detail']);

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
        $kegiatan = Kegiatan::untukUser(Auth::user())->find($id);

        if ($kegiatan) {
            $backend = new PengajuanMahasiswaController;
            $detail = $backend->showDetailData($id);
            $status = $detail['row']['status'] ?? 'Diajukan';

            abort_unless(in_array($status, self::EDITABLE, true), 403, 'Pengajuan dengan status '.$status.' tidak dapat diedit.');

            $validated = $this->validateDynamicSubmission($request, isUpdate: true);

            if (! $this->canUseExternalApi()) {
                $kegiatan->update([
                    'id_kategori' => $validated['kategori_api']['id_kategori'],
                    'nama_kegiatan' => $validated['title'],
                ]);
                $this->syncDetailKegiatan($id, $validated['detail']);

                return redirect()->route('pengajuan.show', $id)
                    ->with('success', 'Perubahan pengajuan berhasil disimpan.');
            }

            $payload = [
                'id_kategori' => $validated['kategori_api']['id_kategori'],
                'nama_kegiatan' => $validated['title'],
            ];

            $berkas = [];
            if ($request->hasFile('lampiran')) {
                $berkas['lampiran'] = $request->file('lampiran');
            }

            try {
                ApiClient::pengguna()->post('/pengajuan-mahasiswa/'.$id, $payload, $berkas);
                $this->syncDetailKegiatan($id, $validated['detail']);

                return redirect()->route('pengajuan.show', $id)
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
}
