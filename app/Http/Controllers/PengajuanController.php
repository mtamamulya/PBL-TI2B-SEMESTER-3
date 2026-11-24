<?php

namespace App\Http\Controllers;

use App\Models\Kegiatan;
use App\Models\DetailKegiatan;
use App\Models\Dokumen;
use App\Models\Timeline;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PengajuanController extends Controller
{
    private const KEY = 'pengajuan.items';

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
        return Kegiatan::untukUser(Auth::user())
            ->with(['kategori', 'statusTerkini', 'timeline', 'user.mahasiswa', 'detail.isianKategori', 'dokumen.jenisDokumen'])
            ->orderByDesc('id_kegiatan')
            ->get();
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
                'organizer' => 'Politeiknik Negeri Semarang',
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

    public function activityOptions(): Collection
    {
        return $this->databaseKegiatan()->map(function (Kegiatan $kegiatan) {
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
            })
            ->values();
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
        $kegiatan = Kegiatan::where('id_user', Auth::id())
            ->with(['kategori', 'statusTerkini', 'timeline', 'detail.isianKategori', 'dokumen'])
            ->findOrFail($id);
        $status = $this->mapStatusLabel($kegiatan->statusTerkini?->status);
        abort_unless(in_array($status, self::EDITABLE, true), 403, 'Pengajuan dengan status '.$status.' tidak dapat diedit.');

        $category = $kegiatan->kategori?->nama_kategori ?? 'Lomba';
        $data = [
            'title' => $kegiatan->nama_kegiatan,
            'category' => $category,
            'location' => $kegiatan->nilaiIsian('lokasi') ?? '',
            'start_date' => $kegiatan->tanggal_mulai?->toDateString() ?? now()->toDateString(),
            'end_date' => $kegiatan->tanggal_selesai?->toDateString() ?? now()->toDateString(),
            'description' => $kegiatan->nilaiIsian('deskripsi') ?? '',
            'advisor' => $kegiatan->dosen_pendamping ?? '',
            'participation' => $kegiatan->nilaiIsian('bentuk_partisipasi') ?? 'Individu',
            'level' => $kegiatan->nilaiIsian('tingkat_kejuaraan') ?? 'Nasional',
            'organizer' => $kegiatan->nilaiIsian('penyelenggara') ?? '',
            'role' => $kegiatan->nilaiIsian('posisi_dalam_kegiatan') ?? '',
            'duration_months' => $kegiatan->nilaiIsian('durasi_magang_bulan'),
            'info_url' => $kegiatan->nilaiIsian('tautan_informasi'),
        ];
        $members = $kegiatan->nilaiIsian('anggota');
        $data['members'] = $members ? json_decode($members, true) : [];

        return view('mahasiswa.pengajuan.create', [
            'item' => [
                'id' => $kegiatan->getKey(),
                'title' => $kegiatan->nama_kegiatan,
                'category' => $category,
                'type' => $this->typeFor($category),
                'status' => $status,
                'submitted_at' => ($kegiatan->timeline->first()?->tanggal_status ?? $kegiatan->created_at)->toIso8601String(),
                'data' => $data,
                'documents' => $kegiatan->dokumen->map(fn ($document) => [
                    'name' => $document->nama_file,
                    'type' => $document->tipe_dokumen,
                    'url' => $document->link_dokumen,
                ])->all(),
            ],
            'advisors' => $this->advisorOptions(),
        ]);
    }

    private function advisorOptions(): Collection
    {
        return collect(config('mahasiswa.dosen_mata_kuliah', []));
    }

    public function show(int $id)
    {
        $kegiatan = Kegiatan::untukUser(Auth::user())
            ->with(['kategori', 'user.mahasiswa', 'timeline', 'dokumen.jenisDokumen', 'detail.isianKategori'])
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

        $timelines = $kegiatan->timeline;
        $latestTimeline = $timelines->last();
        $statusLabel = $this->mapStatusLabel($latestTimeline?->status);
        $submittedAt = $timelines->first()?->tanggal_status ?? $kegiatan->created_at ?? now();
        $reviewNote = $latestTimeline?->catatan_revisi;
        $steps = $timelines->isNotEmpty()
            ? $timelines->map(function (Timeline $timeline) use ($actorMap, $defaultNoteMap) {
                $statusKey = strtolower($timeline->status);

                return [
                    'title' => $this->mapStatusLabel($statusKey),
                    'at' => Carbon::parse($timeline->tanggal_status ?? $timeline->created_at)->setTimezone('Asia/Jakarta'),
                    'actor' => $actorMap[$statusKey] ?? 'Tim administrasi',
                    'note' => $timeline->catatan_revisi ?: ($defaultNoteMap[$statusKey] ?? 'Status pengajuan diperbarui.'),
                ];
            })
            : collect([[
                'title' => 'Diajukan',
                'at' => Carbon::parse($submittedAt)->setTimezone('Asia/Jakarta'),
                'actor' => $student['name'],
                'note' => 'Pengajuan dikirim untuk ditinjau.',
            ]]);
        $categoryName = $kegiatan->kategori?->nama_kategori ?? 'Kegiatan';

        $row = [
            'id' => $kegiatan->id_kegiatan,
            'title' => $kegiatan->nama_kegiatan,
            'category' => $categoryName,
            'type' => $this->typeFor($categoryName),
            'status' => $statusLabel,
            'submitted_at' => Carbon::parse($submittedAt)->setTimezone('Asia/Jakarta'),
            'editable' => in_array($statusLabel, self::EDITABLE, true),
            'review_note' => $reviewNote,
            'nomor_sk' => $kegiatan->nomor_sk,
            'tanggal_sk' => $kegiatan->tanggal_sk,
        ];

        $files = $kegiatan->dokumen->map(function ($doc) {
            $path = $doc->link_dokumen;
            $name = $doc->nama_file ?? ($path ? basename($path) : 'Dokumen pendukung');
            $url = $path && preg_match('~^https?://~i', $path)
                ? $path
                : ($path ? Storage::disk('public')->url($path) : null);

            return [
                'name' => $name,
                'meta' => $doc->jenisDokumen?->nama_jenis_dokumen ?? $doc->tipe_dokumen ?? strtoupper(pathinfo($name, PATHINFO_EXTENSION) ?: 'FILE'),
                'url' => $url,
            ];
        })->all();

        $item = $kegiatan->toArray();

        return view('mahasiswa.pengajuan.show', compact('row', 'steps', 'student', 'files', 'item'));
    }

    public function destroy(int $id)
    {
        $kegiatan = Kegiatan::where('id_user', Auth::id())->with('dokumen')->findOrFail($id);

        DB::transaction(function () use ($kegiatan) {
            foreach ($kegiatan->dokumen as $document) {
                $path = $document->link_dokumen;
                if ($path && ! preg_match('~^https?://~i', $path) && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            $kegiatan->delete();
        });

        return redirect()->route('pengajuan.index')
            ->with('success', 'Pengajuan berhasil dihapus.');
    }

    private function validated(Request $request): array
    {
        $members = ['exclude_unless:participation,Tim', 'required', 'json', function ($attr, $value, $fail) {
            $decoded = json_decode($value, true);
            if (! is_array($decoded) || count($decoded) < 1) {
                $fail('Tambahkan minimal satu anggota tim.');
                return;
            }
            foreach ($decoded as $member) {
                if (! is_array($member) || empty(trim((string) ($member['name'] ?? ''))) || empty(trim((string) ($member['role'] ?? '')))) {
                    $fail('Setiap anggota tim harus memiliki nama dan peran.');
                    return;
                }
            }
        }];

        $common = [
            'category' => ['required', Rule::in(['Lomba', 'Volunteer', 'Magang', 'Pengabdian'])],
            'location' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'description' => ['nullable', 'string', 'max:5000'],
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
                'info_url' => ['nullable', 'url', 'max:500'],
                'advisors' => ['nullable', 'array', 'max:5'],
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
                'advisor' => ['nullable', 'string', 'max:255'],
            ],
            'Pengabdian' => [
                'title' => ['required', 'string', 'max:255'],
                'organizer' => ['required', 'string', 'max:255'],
                'info_url' => ['nullable', 'url', 'max:500'],
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
        $this->persistKegiatan($data, false);

        return redirect()->route('kegiatan.index')
            ->with('success', 'Kegiatan berhasil disimpan ke database.');
    }

    public function storeDosen(Request $request)
    {
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

        return redirect()->route('pengajuan.dosen.show', $id)
            ->with('success', 'Pengajuan berhasil dikirim.');
    }

    private function saveSubmission(Request $request, string $showRoute)
    {
        $data = $this->validated($request);
        $kegiatan = $this->persistKegiatan($data, true);

        return redirect()->route($showRoute, $kegiatan->getKey())
            ->with('success', 'Pengajuan berhasil disimpan ke database.');
    }

    private function persistKegiatan(array $data, bool $submit): Kegiatan
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $kategori = DB::table('kategori')->where('nama_kategori', $data['category'])->first();
        if (! $kategori) {
            throw ValidationException::withMessages([
                'category' => 'Kategori kegiatan belum tersedia di database.',
            ]);
        }

        $advisor = $data['category'] === 'Lomba'
            ? implode(', ', $data['advisors'] ?? [])
            : ($data['advisor'] ?? null);

        return DB::transaction(function () use ($data, $submit, $user, $kategori, $advisor) {
            $kegiatan = Kegiatan::create([
                'id_kategori' => $kategori->id_kategori,
                'id_user' => $user->getKey(),
                'nama_kegiatan' => $data['title'],
                'dosen_pendamping' => $advisor,
            ]);

            $this->saveDetails($kegiatan, (int) $kategori->id_kategori, $data);
            $this->saveDocuments($kegiatan, $data['documents'] ?? []);

            if ($submit) {
                DB::table('timeline')->insert([
                    'id_kegiatan' => $kegiatan->getKey(),
                    'status' => 'diajukan',
                    'tanggal_status' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return Kegiatan::with(['kategori', 'statusTerkini', 'timeline', 'detail.isianKategori', 'dokumen'])
                ->findOrFail($kegiatan->getKey());
        });
    }

    private function saveDetails(Kegiatan $kegiatan, int $categoryId, array $data): void
    {
        $fields = [
            'lokasi' => ['teks', $data['location'] ?? null],
            'deskripsi' => ['teks', $data['description'] ?? null],
            'tanggal_mulai' => ['tanggal', $data['start_date'] ?? null],
            'tanggal_selesai' => ['tanggal', $data['end_date'] ?? null],
            'penyelenggara' => ['teks', $data['organizer'] ?? null],
            'tingkat_kejuaraan' => ['teks', $data['level'] ?? null],
            'bentuk_partisipasi' => ['teks', $data['participation'] ?? null],
            'anggota' => ['teks', $data['members'] ?? null],
            'posisi_dalam_kegiatan' => ['teks', $data['role'] ?? null],
            'durasi_magang_bulan' => ['angka', $data['duration_months'] ?? null],
            'tautan_informasi' => ['teks', $data['info_url'] ?? null],
        ];

        foreach ($fields as $input => [$type, $value]) {
            $idIsian = DB::table('isian_kategori')
                ->where('id_kategori', $categoryId)
                ->where('input', $input)
                ->value('id_isian_kategori');
            if (! $idIsian && ($value === null || $value === '')) {
                continue;
            }
            if (! $idIsian) {
                $idIsian = DB::table('isian_kategori')->insertGetId([
                    'id_kategori' => $categoryId,
                    'input' => $input,
                    'jenis_input' => $type,
                    'aturan' => 'NULL',
                    'jumlah_isian' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ], 'id_isian_kategori');
            }

            if ($value === null || $value === '') {
                DetailKegiatan::where('id_kegiatan', $kegiatan->getKey())
                    ->where('id_isian_kategori', $idIsian)
                    ->delete();
                continue;
            }

            $answer = is_array($value)
                ? json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)
                : (string) $value;
            DetailKegiatan::updateOrCreate(
                ['id_kegiatan' => $kegiatan->getKey(), 'id_isian_kategori' => $idIsian],
                ['isian' => $answer]
            );
        }
    }

    private function saveDocuments(Kegiatan $kegiatan, array $documents): void
    {
        foreach ($documents as $document) {
            DB::table('jenis_dokumen')->insertOrIgnore([
                'nama_jenis_dokumen' => $document['type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $idJenisDokumen = DB::table('jenis_dokumen')
                ->where('nama_jenis_dokumen', $document['type'])
                ->value('id_jenis_dokumen');

            Dokumen::create([
                'id_jenis_dokumen' => $idJenisDokumen,
                'id_kegiatan' => $kegiatan->getKey(),
                'nama_file' => $document['name'],
                'tipe_dokumen' => $document['type'],
                'link_dokumen' => $document['url'],
                'is_lampiran' => true,
            ]);
        }
    }

    public function update(Request $request, int $id)
    {
        $kegiatan = Kegiatan::where('id_user', Auth::id())
            ->with(['statusTerkini', 'detail'])
            ->findOrFail($id);
        $status = $this->mapStatusLabel($kegiatan->statusTerkini?->status);
        abort_unless(in_array($status, self::EDITABLE, true), 403, 'Pengajuan dengan status '.$status.' tidak dapat diedit.');

        $data = $this->validated($request);
        $kategori = DB::table('kategori')->where('nama_kategori', $data['category'])->first();
        if (! $kategori) {
            throw ValidationException::withMessages([
                'category' => 'Kategori kegiatan belum tersedia di database.',
            ]);
        }
        if ((int) $kegiatan->id_kategori !== (int) $kategori->id_kategori && $kegiatan->detail->isNotEmpty()) {
            throw ValidationException::withMessages([
                'category' => 'Kategori kegiatan tidak dapat diubah karena detail kegiatan sudah tersimpan.',
            ]);
        }

        $advisor = $data['category'] === 'Lomba'
            ? implode(', ', $data['advisors'] ?? [])
            : ($data['advisor'] ?? null);

        DB::transaction(function () use ($kegiatan, $kategori, $data, $advisor) {
            $kegiatan->id_kategori = $kategori->id_kategori;
            $kegiatan->nama_kegiatan = $data['title'];
            $kegiatan->dosen_pendamping = $advisor;
            $kegiatan->save();
            $this->saveDetails($kegiatan, (int) $kategori->id_kategori, $data);
            $this->saveDocuments($kegiatan, $data['documents'] ?? []);
        });

        return redirect()->route('pengajuan.show', $id)
            ->with('success', 'Perubahan pengajuan berhasil disimpan.');
    }
}
