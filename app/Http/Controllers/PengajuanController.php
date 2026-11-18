<?php

namespace App\Http\Controllers;

use App\BackendKel2\PengajuanMahasiswa\PengajuanMahasiswaController;
use App\Models\Kegiatan;
use App\Models\Timeline;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PengajuanController extends Controller
{
    private const KEY = 'pengajuan.items';

    private const DELETED_KEY = 'pengajuan.deleted';

    private const EDITABLE = ['Diajukan', 'Direview', 'Perlu revisi'];

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

        $query = Kegiatan::with(['kategori', 'statusTerkini', 'timeline', 'user.mahasiswa']);

        // Filter kegiatan milik user yang sedang login (atau anggota kegiatan);
        // jika user yang login belum punya data kegiatan, tampilkan semua kegiatan dari DB agar tetap bisa diuji.
        if ($user && Kegiatan::untukUser($user)->exists()) {
            $query->untukUser($user);
        }

        return $query->orderByDesc('id_kegiatan')->get();
    }

    private function items(): array
    {
        return session(self::KEY, []);
    }

    private function dummyItems(): Collection
    {
        $entries = [
            [
                'status' => 'Selesai',
                'submitted_at' => '2026-02-12T09:00:00+07:00',
                'category' => 'Lomba',
                'title' => 'Pengajuan Lomba KMPIN VII 2026',
                'organizer' => 'Kemendiktisaintek',
                'location' => 'Semarang',
                'start_date' => '2026-03-10',
                'end_date' => '2026-03-12',
                'description' => 'Partisipasi dalam Kompetisi Mahasiswa Informatika Politeknik Nasional 2026.',
                'participation' => 'Tim',
                'members' => [['name' => config('mahasiswa.nama'), 'role' => 'Ketua'], ['name' => 'Ivan Sigit Santoso', 'role' => 'Anggota']],
                'level' => 'Nasional',
            ],
            [
                'status' => 'Disetujui',
                'submitted_at' => '2026-03-03T10:30:00+07:00',
                'category' => 'Lomba',
                'title' => 'Gemastik XIX - Divisi Pengembangan Aplikasi',
                'organizer' => 'Pusat Prestasi Nasional',
                'location' => 'Yogyakarta',
                'start_date' => '2026-04-14',
                'end_date' => '2026-04-16',
                'description' => 'Pengajuan dukungan untuk mengikuti kompetisi pengembangan aplikasi tingkat nasional.',
                'participation' => 'Tim',
                'members' => [['name' => config('mahasiswa.nama'), 'role' => 'Ketua'], ['name' => 'Ivan Sigit Santoso', 'role' => 'Anggota']],
                'level' => 'Nasional',
            ],
            [
                'status' => 'Selesai',
                'submitted_at' => '2026-03-21T13:15:00+07:00',
                'category' => 'Lomba',
                'title' => 'Program Kreativitas Mahasiswa bidang Karsa Cipta',
                'organizer' => 'Direktorat Pembelajaran dan Kemahasiswaan',
                'location' => 'Semarang',
                'start_date' => '2026-05-04',
                'end_date' => '2026-05-06',
                'description' => 'Pengembangan prototipe perangkat pemantau kualitas udara berbasis IoT.',
                'participation' => 'Tim',
                'members' => [['name' => config('mahasiswa.nama'), 'role' => 'Ketua'], ['name' => 'Ivan Sigit Santoso', 'role' => 'Anggota']],
                'level' => 'Nasional',
            ],
            [
                'status' => 'Disetujui',
                'submitted_at' => '2026-04-08T08:45:00+07:00',
                'category' => 'Pengabdian',
                'title' => 'Seminar Nasional Teknologi Terapan 2026',
                'organizer' => 'Politeknik Negeri Semarang',
                'location' => 'Semarang',
                'start_date' => '2026-04-27',
                'end_date' => '2026-04-27',
                'description' => 'Mengikuti seminar dan mempresentasikan hasil karya teknologi terapan.',
                'scope' => 'Nasional',
                'role' => 'Peserta',
                'participation' => 'Individu',
            ],
            [
                'status' => 'Selesai',
                'submitted_at' => '2026-04-26T14:00:00+07:00',
                'category' => 'Volunteer',
                'title' => 'Relawan Mengajar Digital untuk Pelajar Semarang',
                'organizer' => 'Komunitas Sahabat Pendidikan',
                'location' => 'Semarang',
                'start_date' => '2026-06-06',
                'end_date' => '2026-06-07',
                'description' => 'Berbagi pengenalan komputer dan literasi digital kepada pelajar.',
                'role' => 'Mentor teknologi',
            ],
            [
                'status' => 'Disetujui',
                'submitted_at' => '2026-05-11T11:20:00+07:00',
                'category' => 'Lomba',
                'title' => 'Lomba Inovasi Teknologi Tepat Guna Jawa Tengah',
                'organizer' => 'Pemerintah Provinsi Jawa Tengah',
                'location' => 'Surakarta',
                'start_date' => '2026-06-20',
                'end_date' => '2026-06-21',
                'description' => 'Kompetisi karya inovasi teknologi tepat guna tingkat provinsi.',
                'participation' => 'Tim',
                'members' => [['name' => config('mahasiswa.nama'), 'role' => 'Ketua'], ['name' => 'Ivan Sigit Santoso', 'role' => 'Anggota']],
                'level' => 'Provinsi',
            ],
            [
                'status' => 'Selesai',
                'submitted_at' => '2026-05-29T09:40:00+07:00',
                'category' => 'Magang',
                'title' => 'Magang Software Developer di PT Aplikasi Nusantara',
                'organizer' => 'PT Aplikasi Nusantara',
                'location' => 'Yogyakarta',
                'start_date' => '2026-07-01',
                'end_date' => '2026-09-30',
                'description' => 'Program magang pengembangan aplikasi web selama tiga bulan.',
                'role' => 'Software Developer Intern',
                'duration_months' => 3,
            ],
            [
                'status' => 'Disetujui',
                'submitted_at' => '2026-06-17T15:10:00+07:00',
                'category' => 'Pengabdian',
                'title' => 'Pelatihan Keamanan Jaringan dan Cloud Computing',
                'organizer' => 'Himpunan Mahasiswa Teknologi Rekayasa Komputer',
                'location' => 'Semarang',
                'start_date' => '2026-07-04',
                'end_date' => '2026-07-05',
                'description' => 'Pelatihan peningkatan kompetensi keamanan jaringan dan komputasi awan.',
                'scope' => 'Kampus',
                'role' => 'Peserta',
                'participation' => 'Individu',
            ],
            [
                'status' => 'Direview',
                'submitted_at' => '2026-07-02T10:00:00+07:00',
                'category' => 'Volunteer',
                'title' => 'Aksi Bersih Pantai dan Edukasi Lingkungan',
                'organizer' => 'Komunitas Pesisir Lestari',
                'location' => 'Jepara',
                'start_date' => '2026-08-08',
                'end_date' => '2026-08-09',
                'description' => 'Kegiatan relawan untuk membersihkan kawasan pantai dan mengedukasi warga.',
                'role' => 'Koordinator dokumentasi',
            ],
            [
                'status' => 'Diajukan',
                'submitted_at' => '2026-07-20T08:30:00+07:00',
                'category' => 'Lomba',
                'title' => 'Kompetisi UI/UX Design Festival Mahasiswa 2026',
                'organizer' => 'Asosiasi Pendidikan Tinggi Informatika dan Komputer',
                'location' => 'Bandung',
                'start_date' => '2026-09-12',
                'end_date' => '2026-09-13',
                'description' => 'Kompetisi perancangan antarmuka dan pengalaman pengguna aplikasi layanan publik.',
                'participation' => 'Tim',
                'members' => [['name' => config('mahasiswa.nama'), 'role' => 'Ketua'], ['name' => 'Ivan Sigit Santoso', 'role' => 'Anggota']],
                'level' => 'Nasional',
            ],
            [
                'status' => 'Perlu revisi',
                'submitted_at' => '2026-08-14T13:25:00+07:00',
                'category' => 'Magang',
                'title' => 'Magang Network Engineer di PT Digital Solusi',
                'organizer' => 'PT Digital Solusi',
                'location' => 'Semarang',
                'start_date' => '2026-10-01',
                'end_date' => '2026-12-31',
                'description' => 'Program praktik kerja lapangan pada tim infrastruktur jaringan.',
                'role' => 'Network Engineer Intern',
                'duration_months' => 3,
            ],
            [
                'status' => 'Direview',
                'submitted_at' => '2026-09-05T16:05:00+07:00',
                'category' => 'Lomba',
                'title' => 'Kompetisi Robotika Politeknik Indonesia 2026',
                'organizer' => 'Badan Koordinasi Kemahasiswaan Politeknik Indonesia',
                'location' => 'Surabaya',
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-12',
                'description' => 'Kompetisi robotika untuk mahasiswa politeknik tingkat nasional.',
                'participation' => 'Tim',
                'members' => [['name' => config('mahasiswa.nama'), 'role' => 'Ketua'], ['name' => 'Ivan Sigit Santoso', 'role' => 'Anggota']],
                'level' => 'Nasional',
            ],
            [
                'status' => 'Ditolak',
                'submitted_at' => '2026-09-18T09:20:00+07:00',
                'category' => 'Volunteer',
                'title' => 'Relawan Literasi Digital untuk UMKM',
                'organizer' => 'Dinas Koperasi dan UMKM Kota Semarang',
                'location' => 'Semarang',
                'start_date' => '2026-10-03',
                'end_date' => '2026-10-04',
                'description' => 'Pendampingan penggunaan layanan digital bagi pelaku UMKM lokal.',
                'role' => 'Pendamping teknologi',
            ],
            [
                'status' => 'Diajukan',
                'submitted_at' => '2026-09-27T13:40:00+07:00',
                'category' => 'Lomba',
                'title' => 'National Cyber Security Competition 2026',
                'organizer' => 'Asosiasi Cyber Security Indonesia',
                'location' => 'Jakarta',
                'start_date' => '2026-11-07',
                'end_date' => '2026-11-08',
                'description' => 'Kompetisi keamanan siber dengan kategori capture the flag.',
                'participation' => 'Tim',
                'members' => [['name' => config('mahasiswa.nama'), 'role' => 'Ketua'], ['name' => 'Ivan Sigit Santoso', 'role' => 'Anggota']],
                'level' => 'Nasional',
            ],
            [
                'status' => 'Direview',
                'submitted_at' => '2026-10-04T10:15:00+07:00',
                'category' => 'Pengabdian',
                'title' => 'Workshop Pengembangan Aplikasi Berbasis Cloud',
                'organizer' => 'Cloud Computing Indonesia',
                'location' => 'Daring',
                'start_date' => '2026-10-29',
                'end_date' => '2026-10-30',
                'description' => 'Workshop praktik membangun dan menerapkan aplikasi pada layanan cloud.',
                'scope' => 'Nasional',
                'role' => 'Peserta',
                'participation' => 'Individu',
            ],
            [
                'status' => 'Perlu revisi',
                'submitted_at' => '2026-10-13T14:50:00+07:00',
                'category' => 'Magang',
                'title' => 'Magang UI/UX Designer di PT Kreasi Digital',
                'organizer' => 'PT Kreasi Digital',
                'location' => 'Semarang',
                'start_date' => '2026-12-01',
                'end_date' => '2027-02-28',
                'description' => 'Program magang pada tim desain produk digital selama tiga bulan.',
                'role' => 'UI/UX Designer Intern',
                'duration_months' => 3,
            ],
            [
                'status' => 'Direview',
                'submitted_at' => '2026-10-22T08:55:00+07:00',
                'category' => 'Pengabdian',
                'title' => 'Seminar Nasional Transformasi Digital Kampus',
                'organizer' => 'Forum Pendidikan Tinggi Indonesia',
                'location' => 'Surakarta',
                'start_date' => '2026-11-14',
                'end_date' => '2026-11-14',
                'description' => 'Seminar tentang penerapan teknologi digital untuk meningkatkan layanan kampus.',
                'scope' => 'Nasional',
                'role' => 'Narasumber',
                'participation' => 'Individu',
            ],
        ];

        return collect($entries)->map(function ($entry, $i) {
            $data = $entry;
            unset($data['status'], $data['submitted_at']);

            return [
                'id' => $i + 1,
                'title' => $entry['title'],
                'category' => $entry['category'],
                'type' => $this->typeFor($entry['category']),
                'status' => $entry['status'],
                'submitted_at' => $entry['submitted_at'],
                'data' => $data,
                'documents' => null,
            ];
        });
    }

    private function all(): Collection
    {
        $mine = collect($this->items())->keyBy('id');
        $deleted = collect(session(self::DELETED_KEY, []));

        return $this->dummyItems()->keyBy('id')->replace($mine)
            ->reject(fn (array $item) => $deleted->contains($item['id']))
            ->values();
    }

    public function activityOptions(): Collection
    {
        return $this->all()
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
            })
            ->values();
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

    private function toRow(array $i): array
    {
        return [
            'id' => $i['id'],
            'title' => $i['title'],
            'category' => $i['category'],
            'type' => $i['type'],
            'status' => $i['status'],
            'submitted_at' => Carbon::parse($i['submitted_at']),
            'editable' => $this->canEdit($i),
        ];
    }

    private function rows(): Collection
    {
        $kegiatanList = $this->databaseKegiatan();

        if ($kegiatanList->isNotEmpty()) {
            return $kegiatanList->map(function (Kegiatan $k) {
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

        // Fallback ke dummy jika tabel kegiatan di database masih kosong
        return $this->all()->map(fn ($i) => $this->toRow($i))
            ->sortByDesc(fn ($r) => $r['submitted_at']->timestamp * 1000 + $r['id'])
            ->values();
    }

    private function readableSize(int $b): string
    {
        if ($b < 1024) {
            return $b.' B';
        }

        return $b < 1048576 ? round($b / 1024).' KB' : number_format($b / 1048576, 1, ',', '.').' MB';
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

    public function create()
    {
        return view('mahasiswa.pengajuan.create', [
            'item' => null,
            'advisors' => $this->advisorOptions(),
        ]);
    }

    public function createActivity()
    {
        return view('mahasiswa.pengajuan.create', [
            'item' => null,
            'advisors' => $this->advisorOptions(),
            'isActivityHistory' => true,
        ]);
    }

    public function createDosen()
    {
        return view('dosen.pengajuan.create', [
            'item' => null,
            'advisors' => $this->advisorOptions(),
        ]);
    }

    public function edit(int $id)
    {
        if (Kegiatan::where('id_kegiatan', $id)->exists()) {
            $backend = new PengajuanMahasiswaController;
            $detail = $backend->showDetailData($id);

            $row = $detail['row'];
            $status = $row['status'] ?? 'Diajukan';

            abort_unless(in_array($status, self::EDITABLE, true), 403, 'Pengajuan dengan status '.$status.' tidak dapat diedit.');

            $kategoriNama = $row['category'] ?? 'Lomba';
            $mappedCategory = match (true) {
                str_contains(strtolower($kategoriNama), 'volunteer') => 'Volunteer',
                str_contains(strtolower($kategoriNama), 'magang') => 'Magang',
                str_contains(strtolower($kategoriNama), 'pengabdian') => 'Pengabdian',
                default => 'Lomba',
            };

            $item = [
                'id' => $row['id'],
                'title' => $row['title'],
                'category' => $mappedCategory,
                'type' => $row['type'],
                'status' => $status,
                'submitted_at' => Carbon::parse($row['submitted_at'])->toIso8601String(),
                'data' => [
                    'title' => $row['title'],
                    'category' => $mappedCategory,
                    'location' => $detail['item']->lokasi ?? '',
                    'start_date' => $detail['item']->tanggal_mulai ?? now()->toDateString(),
                    'end_date' => $detail['item']->tanggal_mulai ?? now()->toDateString(),
                    'description' => $detail['item']->deskripsi ?? '',
                    'participation' => 'Individu',
                    'level' => 'Nasional',
                    'organizer' => '-',
                ],
                'documents' => [],
            ];

            return view('mahasiswa.pengajuan.create', [
                'item' => $item,
                'advisors' => $this->advisorOptions(),
            ]);
        }

        return view('mahasiswa.pengajuan.create', [
            'item' => $this->findEditable($id),
            'advisors' => $this->advisorOptions(),
        ]);
    }

    private function advisorOptions(): Collection
    {
        return collect(config('mahasiswa.dosen_mata_kuliah', []));
    }

    public function show(int $id)
    {
        $kegiatan = Kegiatan::with(['kategori', 'user.mahasiswa', 'timeline', 'dokumen'])
            ->find($id);

        if ($kegiatan) {
            $timelines = $kegiatan->timeline;
            $latestTimeline = $timelines->last();
            $statusLabel = $this->mapStatusLabel($latestTimeline?->status);
            $categoryName = $kegiatan->kategori?->nama_kategori ?? 'Kegiatan';
            $submittedAt = $timelines->first()?->tanggal_status
                ?? $kegiatan->created_at
                ?? now();

            $row = [
                'id' => $kegiatan->id_kegiatan,
                'title' => $kegiatan->nama_kegiatan,
                'category' => $categoryName,
                'type' => $this->typeFor($categoryName),
                'status' => $statusLabel,
                'submitted_at' => Carbon::parse($submittedAt),
                'editable' => in_array($statusLabel, self::EDITABLE, true),
                'review_note' => $latestTimeline?->catatan_revisi,
            ];

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

            $steps = $timelines->isNotEmpty()
                ? $timelines->map(function (Timeline $t) use ($actorMap, $defaultNoteMap) {
                    $statusKey = strtolower($t->status);

                    return [
                        'title' => ucfirst($statusKey),
                        'at' => Carbon::parse($t->tanggal_status ?? $t->created_at),
                        'actor' => $actorMap[$statusKey] ?? 'Tim administrasi',
                        'note' => $t->catatan_revisi ?: ($defaultNoteMap[$statusKey] ?? 'Status pengajuan diperbarui.'),
                    ];
                })
                : collect([[
                    'title' => 'Diajukan',
                    'at' => Carbon::parse($submittedAt),
                    'actor' => $student['name'],
                    'note' => 'Pengajuan dikirim untuk ditinjau.',
                ]]);

            $files = $kegiatan->dokumen->map(function ($doc) {
                $path = $doc->path ?? $doc->file_path ?? $doc->url ?? null;
                $name = $doc->nama_dokumen ?? $doc->name ?? ($path ? basename($path) : 'Dokumen pendukung');

                return [
                    'name' => $name,
                    'meta' => $doc->jenis_dokumen ?? $doc->type ?? strtoupper(pathinfo($name, PATHINFO_EXTENSION) ?: 'FILE'),
                    'url' => $path ? (str_starts_with($path, 'http') ? $path : asset('storage/'.$path)) : null,
                ];
            })->all();

            $item = $kegiatan->toArray();

            return view('mahasiswa.pengajuan.show', compact('row', 'steps', 'student', 'files', 'item'));
        }

        // Fallback jika ID belum ada di tabel kegiatan database
        $item = $this->find($id);
        $row = $this->toRow($item);

        $flows = [
            'Diajukan' => ['Diajukan'],
            'Direview' => ['Diajukan', 'Direview'],
            'Disetujui' => ['Diajukan', 'Direview', 'Diterima'],
            'Selesai' => ['Diajukan', 'Direview', 'Diterima', 'Selesai'],
            'Ditolak' => ['Diajukan', 'Direview', 'Ditolak'],
            'Perlu revisi' => ['Diajukan', 'Direview', 'Direvisi'],
        ];

        $d = $row['submitted_at'];
        $mine = $id > 100;
        $meta = [
            'Diajukan' => [$mine ? $d : $d->copy()->setTime(9, 0), config('mahasiswa.nama'), 'Pengajuan dikirim untuk ditinjau.'],
            'Direview' => [$mine ? $d->copy()->addMinutes(5) : $d->copy()->setTime(13, 0), 'Tim administrasi', 'Verifikasi persyaratan dan periode pengajuan.'],
            'Diterima' => [$d->copy()->addDay()->setTime(10, 15), 'Reviewer akademik', 'Pengajuan disetujui. Menunggu penerbitan dokumen hasil.'],
            'Ditolak' => [$d->copy()->addDay()->setTime(10, 30), 'Reviewer akademik', 'Pengajuan di luar periode penerimaan. Proses dihentikan.'],
            'Direvisi' => [$d->copy()->addDay()->setTime(10, 0), 'Reviewer akademik', 'Rincian anggaran dan lembar pengesahan perlu diperbaiki.'],
            'Selesai' => [$d->copy()->addDays(2)->setTime(14, 0), 'Tim administrasi', 'Dokumen diterbitkan dan proses pengajuan ditutup.'],
        ];

        $steps = collect($flows[$row['status']])->map(fn ($s) => [
            'title' => $s, 'at' => $meta[$s][0], 'actor' => $meta[$s][1], 'note' => $meta[$s][2],
        ]);

        $student = $this->studentProfile();

        $files = collect($item['documents'] ?? [])->map(fn ($f) => [
            'name' => $f['name'] ?? 'Dokumen pendukung',
            'meta' => $f['type'] ?? (isset($f['size']) ? strtoupper(pathinfo($f['name'], PATHINFO_EXTENSION)).' • '.$this->readableSize((int) $f['size']) : 'Tautan dokumen'),
            'url' => $f['url'] ?? (isset($f['path']) ? asset('storage/'.$f['path']) : null),
        ])->all();

        return view('mahasiswa.pengajuan.show', compact('row', 'steps', 'student', 'files', 'item'));
    }

    public function destroy(int $id)
    {
        if (Kegiatan::where('id_kegiatan', $id)->exists()) {
            $backend = new PengajuanMahasiswaController;
            $backend->apiDestroy($id);

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

    private function saveSubmission(Request $request, string $showRoute)
    {
        $data = $this->validated($request);

        $kategori = DB::table('kategori')
            ->where('nama_kategori', 'like', '%'.$data['category'].'%')
            ->first()
            ?? DB::table('kategori')->first();

        if ($kategori) {
            $idUser = auth()->id()
                ?? DB::table('users')->value('id_user');

            $apiRequest = Request::create('/api/v1/pengajuan-mahasiswa', 'POST', [
                'id_kategori' => $kategori->id_kategori,
                'id_user' => $idUser,
                'nama_kegiatan' => $data['title'],
                'lokasi' => $data['location'] ?? null,
                'deskripsi' => $data['description'] ?? null,
                'tanggal_mulai' => $data['start_date'] ?? null,
            ]);

            if ($request->hasFile('lampiran')) {
                $apiRequest->files->set('lampiran', $request->file('lampiran'));
            }

            $backend = new PengajuanMahasiswaController;
            $response = $backend->apiStore($apiRequest);
            $result = $response->getData(true);

            $newId = $result['data']['id_kegiatan'] ?? null;

            if ($newId) {
                return redirect()->route($showRoute, $newId)
                    ->with('success', 'Pengajuan berhasil dikirim ke database.');
            }
        }

        // Fallback ke session saat tabel kategori masih kosong (misalnya di environment testing)
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
        if (Kegiatan::where('id_kegiatan', $id)->exists()) {
            $backend = new PengajuanMahasiswaController;
            $detail = $backend->showDetailData($id);
            $status = $detail['row']['status'] ?? 'Diajukan';

            abort_unless(in_array($status, self::EDITABLE, true), 403, 'Pengajuan dengan status '.$status.' tidak dapat diedit.');

            $kategori = DB::table('kategori')
                ->where('nama_kategori', 'like', '%'.$request->input('category').'%')
                ->first();

            $request->merge([
                'id_kategori' => $kategori?->id_kategori,
                'nama_kegiatan' => $request->input('title') ?? $request->input('nama_kegiatan'),
            ]);

            $backend->apiUpdate($request, $id);

            return redirect()->route('pengajuan.show', $id)
                ->with('success', 'Perubahan pengajuan berhasil disimpan.');
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
