<?php

namespace App\Http\Controllers;

use App\BackendKel2\Akademik\PeriodeAkademik;
use App\BackendKel2\Dashboard\DashboardController as BackendDashboardController;
use App\BackendKel2\MataKuliah\MataKuliahController as BackendMataKuliahController;
use App\Models\Kegiatan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MahasiswaController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $data = app(BackendDashboardController::class)->dataMahasiswa($user);
        $mahasiswa = $user->mahasiswa;
        $periode = PeriodeAkademik::sekarang();
        $periodeAktif = $this->parseAcademicPeriod($periode->label());
        $tahunAktif = DB::table('tahun_akademik')
            ->where('status', true)
            ->orderByDesc('id_tahun_akademik')
            ->first();

        $enrollment = collect();
        if ($mahasiswa) {
            $enrollment = DB::table('enrollment_mahasiswa_tingkat_kelas as emtk')
                ->join('enrollment_tingkat_kelas as etk', 'etk.id_enrollment_tingkat_kelas', '=', 'emtk.id_enrollment_tingkat_kelas')
                ->join('enrollment_kelas_mata_kuliah as ekmk', 'ekmk.id_enrollment_tingkat_kelas', '=', 'etk.id_enrollment_tingkat_kelas')
                ->join('enrollment_mata_kuliah as emk', 'emk.id_enrollment_mk', '=', 'ekmk.id_enrollment_mk')
                ->join('mata_kuliah as mk', 'mk.id_mk', '=', 'emk.id_mk')
                ->join('tahun_akademik as ta', 'ta.id_tahun_akademik', '=', 'emk.id_tahun_akademik')
                ->join('tingkat as t', 't.id_tingkat', '=', 'etk.id_tingkat')
                ->leftJoin('dosen as d', 'd.id_dosen', '=', 'ekmk.id_dosen')
                ->where('emtk.id_mahasiswa', $mahasiswa->getKey())
                ->whereColumn('emk.id_tahun_akademik', 'etk.id_tahun_akademik')
                ->select([
                    'emk.id_enrollment_mk',
                    'emk.id_tahun_akademik',
                    'etk.id_prodi',
                    'mk.nama_mk',
                    'mk.sks_teori',
                    'mk.sks_praktik',
                    'ta.tahun_akademik',
                    't.tingkat',
                    'd.nama as dosen',
                ])
                ->distinct()
                ->orderByDesc('emk.id_tahun_akademik')
                ->orderBy('mk.nama_mk')
                ->get()
                ->map(function ($row) {
                    $period = $this->parseAcademicPeriod($row->tahun_akademik);

                    return [
                        'id_enrollment_mk' => (int) $row->id_enrollment_mk,
                        'id_tahun_akademik' => (int) $row->id_tahun_akademik,
                        'id_prodi' => (int) $row->id_prodi,
                        'mk' => $row->nama_mk,
                        'dosen' => $row->dosen ?? '—',
                        'tingkat' => (int) $row->tingkat,
                        'semester' => $period['semester'],
                        'tahun' => $period['tahun'],
                        'sks' => (int) $row->sks_teori + (int) $row->sks_praktik,
                    ];
                });
        }

        if (! $tahunAktif) {
            $tahunAktif = DB::table('tahun_akademik')->get()->first(function ($row) use ($periodeAktif) {
                $period = $this->parseAcademicPeriod($row->tahun_akademik);

                return $period === $periodeAktif;
            });
        }

        $currentCourses = $enrollment->filter(function (array $row) use ($tahunAktif, $periodeAktif) {
            return $tahunAktif
                ? $row['id_tahun_akademik'] === (int) $tahunAktif->id_tahun_akademik
                : $row['tahun'] === $periodeAktif['tahun'] && $row['semester'] === $periodeAktif['semester'];
        });

        $prodiId = $mahasiswa
            ? DB::table('enrollment_mahasiswa_tingkat_kelas as emtk')
                ->join('enrollment_tingkat_kelas as etk', 'etk.id_enrollment_tingkat_kelas', '=', 'emtk.id_enrollment_tingkat_kelas')
                ->join('tahun_akademik as ta', 'ta.id_tahun_akademik', '=', 'etk.id_tahun_akademik')
                ->where('emtk.id_mahasiswa', $mahasiswa->getKey())
                ->orderByDesc('ta.status')
                ->orderByDesc('ta.id_tahun_akademik')
                ->value('etk.id_prodi')
            : null;
        $prodiId = $prodiId ?? ($enrollment->first()['id_prodi'] ?? null);

        $kurikulumAktif = $prodiId
            ? DB::table('kurikulum')->where('id_prodi', $prodiId)->where('status', true)->orderByDesc('id_kurikulum')->value('id_kurikulum')
            : null;
        $sksTotal = $kurikulumAktif
            ? (int) DB::table('mata_kuliah')->where('id_kurikulum', $kurikulumAktif)->sum(DB::raw('sks_teori + sks_praktik'))
            : 0;

        $semua = $enrollment->map(fn ($row) => collect($row)->only(['mk', 'dosen', 'tingkat', 'semester', 'tahun'])->all())->values();
        $filter = [
            'q' => trim((string) $request->query('q')),
            'semester' => (string) $request->query('semester'),
            'tingkat' => (string) $request->query('tingkat'),
            'tahun' => (string) $request->query('tahun'),
        ];

        $rows = $semua->filter(function ($row) use ($filter) {
            return ($filter['q'] === '' || mb_stripos($row['mk'].' '.$row['dosen'], $filter['q']) !== false)
                && ($filter['semester'] === '' || $row['semester'] === $filter['semester'])
                && ($filter['tingkat'] === '' || (string) $row['tingkat'] === $filter['tingkat'])
                && ($filter['tahun'] === '' || $row['tahun'] === $filter['tahun']);
        })->values();

        $perPage = 10;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $mataKuliah = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $ringkasan = [
            'tahun_akademik' => $periodeAktif['tahun'],
            'jenis_semester' => $periodeAktif['semester'],
            'sks_tempuh' => $enrollment->unique('id_enrollment_mk')->sum('sks'),
            'sks_total' => $sksTotal,
            'semester_ke' => $mahasiswa ? $periode->semesterKe($mahasiswa->tahun_masuk) : '—',
            'jumlah_mk' => $currentCourses->count(),
            'sks_semester' => $currentCourses->sum('sks'),
            'pengajuan_aktif' => $data['pengajuan_aktif'],
            'dokumen_siap' => $data['dokumen_siap'],
        ];

        return view('mahasiswa.mahasiswa.dashboard-mhs', [
            'nama' => $mahasiswa?->nama ?? $user->email,
            'ringkasan' => $ringkasan,
            'kegiatanSelesai' => $data['kegiatan_selesai'],
            'tahunList' => $semua->pluck('tahun')->merge([$periodeAktif['tahun']])->filter()->unique()->sortDesc()->values()->all(),
            'filter' => $filter,
            'mataKuliah' => $mataKuliah,
        ]);
    }

    private function parseAcademicPeriod(string $label): array
    {
        preg_match('/\d{4}\/\d{4}/', $label, $yearMatch);
        preg_match('/ganjil|genap/i', $label, $semesterMatch);

        return [
            'tahun' => $yearMatch[0] ?? $label,
            'semester' => isset($semesterMatch[0]) ? ucfirst(strtolower($semesterMatch[0])) : 'Ganjil',
        ];
    }

    public function mataKuliah(Request $request, string $tab = 'berjalan')
    {
        abort_unless(in_array($tab, ['semua', 'berjalan', 'katalog', 'riwayat'], true), 404);
        $tab = $tab === 'riwayat' ? 'semua' : $tab;

        $filter = [
            'q' => trim((string) $request->query('q')),
            'semester' => (string) $request->query('semester'),
            'tingkat' => (string) $request->query('tingkat'),
            'tahun' => (string) $request->query('tahun'),
        ];

        $angkatan = (string) ($request->user()?->angkatan ?? config('mahasiswa.angkatan', '2024'));
        $backend = app(BackendMataKuliahController::class);

        $mapItem = function (array $item, string $defaultTahun = '2026/2027'): array {
            $sks = isset($item['sks'])
                ? (int) $item['sks']
                : ((int) ($item['sks_teori'] ?? 0) + (int) ($item['sks_praktik'] ?? 0));

            $semesterRaw = $item['periode'] ?? $item['jenis_semester'] ?? $item['semester'] ?? 'Ganjil';
            $periode = is_numeric($semesterRaw)
                ? (((int) $semesterRaw % 2 === 1) ? 'Ganjil' : 'Genap')
                : ucfirst(strtolower((string) $semesterRaw));

            $tingkat = (int) ($item['tingkat'] ?? (is_numeric($semesterRaw) ? ceil(((int) $semesterRaw) / 2) : 1));

            return [
                'mk' => (string) ($item['nama_mk'] ?? $item['nama_matakuliah'] ?? $item['nama'] ?? $item['mk'] ?? '-'),
                'dosen' => (string) ($item['dosen_pengampu'] ?? $item['dosen'] ?? $item['nama_dosen'] ?? '-'),
                'tingkat' => max(1, min(4, $tingkat)),
                'periode' => in_array($periode, ['Ganjil', 'Genap'], true) ? $periode : 'Ganjil',
                'tahun' => (string) ($item['tahun_akademik'] ?? $item['tahun'] ?? $defaultTahun),
                'sks' => $sks > 0 ? $sks : null,
            ];
        };

        $apiRequest = Request::create('/api/v1/mahasiswa', 'GET', array_filter([
            'angkatan' => $angkatan,
            'semester' => $filter['semester'] ?: null,
            'tingkat' => $filter['tingkat'] ?: null,
            'tahun_akademik' => $filter['tahun'] ?: null,
            'q' => $filter['q'] ?: null,
            'per_page' => 200,
            'page' => 1,
        ], fn ($v) => $v !== null));

        $periodeAktif = class_exists(PeriodeAkademik::class)
            ? PeriodeAkademik::sekarang()
            : null;

        $tahunAktifLabel = $periodeAktif ? $periodeAktif->label() : '2026/2027 Ganjil';
        $semesterKe = $periodeAktif ? $periodeAktif->semesterKe($angkatan) : config('mahasiswa.semester', 5);
        $defaultTahun = explode(' ', $tahunAktifLabel)[0] ?? '2026/2027';

        $rows = collect();
        $berjalan = collect();
        $arsip = collect();
        $katalog = collect();

        try {
            if ($tab === 'berjalan') {
                $res = $backend->semesterBerjalan($apiRequest)->getData(true);
                if (($res['status'] ?? '') === 'success') {
                    $tahunAktifLabel = $res['periode_akademik'] ?? $tahunAktifLabel;
                    $defaultTahun = explode(' ', $tahunAktifLabel)[0] ?? $defaultTahun;
                    $berjalan = collect($res['data_mata_kuliah'] ?? [])->map(fn ($item) => $mapItem($item, $defaultTahun))->values();
                    $rows = $berjalan;
                }
            } elseif ($tab === 'katalog') {
                $res = $backend->katalogMataKuliah($apiRequest)->getData(true);
                if (($res['status'] ?? '') === 'success') {
                    $katalog = collect($res['data'] ?? [])->map(fn ($item) => $mapItem($item, $defaultTahun))->values();
                    $rows = $katalog;
                }
            } else {
                $res = $backend->riwayatAkademik($apiRequest)->getData(true);
                if (($res['status'] ?? '') === 'success') {
                    $arsip = collect($res['data'] ?? [])->map(fn ($item) => $mapItem($item, $defaultTahun))->values();
                    $rows = $arsip;
                }
            }
        } catch (\Throwable $e) {
            // Jika API belum tersedia atau gagal, biarkan data kosong tanpa mengisi data tiruan
        }

        // Filter sisi server (memastikan pencarian & dropdown tetap akurat)
        $cocok = fn ($r) => ($filter['q'] === '' || mb_stripos($r['mk'], $filter['q']) !== false)
            && ($filter['semester'] === '' || $r['periode'] === $filter['semester'])
            && ($filter['tingkat'] === '' || (string) $r['tingkat'] === $filter['tingkat'])
            && ($filter['tahun'] === '' || $r['tahun'] === $filter['tahun']);

        $rows = match ($tab) {
            'berjalan' => $berjalan->filter($cocok)->values(),
            'katalog' => $katalog->filter($cocok)->sortBy([['tingkat', 'asc'], ['mk', 'asc']])->values(),
            default => $arsip->filter($cocok)->values(),
        };

        $tahunList = $rows->pluck('tahun')
            ->merge([$defaultTahun])
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        $pesanKosong = $rows->isEmpty() ? 'Belum ada data untuk ditampilkan' : null;

        return view('mahasiswa.mahasiswa.mata-kuliah', [
            'nama' => config('mahasiswa.nama'),
            'profil' => ['nim' => config('mahasiswa.nim'), 'prodi' => config('mahasiswa.prodi')],
            'tab' => $tab,
            'rows' => $rows,
            'pesanKosong' => $pesanKosong,
            'filter' => $filter,
            'tahunList' => $tahunList,
            'ringkasan' => [
                'tahun_akademik' => $tahunAktifLabel,
                'semester_ke' => $semesterKe,
                'sks_tempuh' => 84,
                'sks_total' => 144,
                'sks_diambil' => $berjalan->sum('sks'),
                'jumlah_berjalan' => $berjalan->count(),
            ],
        ]);
    }

    public function prestasi(Request $request)
    {
        $semua = collect([
            [
                'nama' => 'Lomba Inovasi Internet of Things', 'bidang' => 'Teknologi', 'bentuk' => 'Tim',
                'capaian' => 'Juara 2', 'tingkat' => 'Nasional', 'jenis' => 'Akademik',
                'penyelenggara' => 'Forum Inovasi Teknologi', 'tanggal' => '2026-09-24',
                'kegiatan' => 'Lomba inovasi IoT 2026', 'bukti' => null,
            ],
            [
                'nama' => 'Kompetisi Desain Poster Digital', 'bidang' => 'Desain', 'bentuk' => 'Individu',
                'capaian' => 'Juara 1', 'tingkat' => 'Internal', 'jenis' => 'Nonakademik',
                'penyelenggara' => 'BEM Politeknik', 'tanggal' => '2026-08-18',
                'kegiatan' => null, 'bukti' => null,
            ],
        ])->map(fn ($r) => $r + ['tahun' => substr($r['tanggal'], 0, 4)]);

        $filter = [
            'q' => trim((string) $request->query('q')),
            'tingkat' => (string) $request->query('tingkat'),
            'jenis' => (string) $request->query('jenis'),
            'tahun' => (string) $request->query('tahun'),
        ];

        $rows = $semua->filter(function ($r) use ($filter) {
            $teks = $r['nama'].' '.$r['penyelenggara'].' '.($r['kegiatan'] ?? '');

            return ($filter['q'] === '' || mb_stripos($teks, $filter['q']) !== false)
                && ($filter['tingkat'] === '' || $r['tingkat'] === $filter['tingkat'])
                && ($filter['jenis'] === '' || $r['jenis'] === $filter['jenis'])
                && ($filter['tahun'] === '' || $r['tahun'] === $filter['tahun']);
        })->values();

        return view('mahasiswa.mahasiswa.prestasi', [
            'nama' => config('mahasiswa.nama'),
            'profil' => ['nim' => config('mahasiswa.nim'), 'prodi' => config('mahasiswa.prodi')],
            'rows' => $rows,
            'total' => $semua->count(),
            'filter' => $filter,
            'tingkatList' => ['Internal', 'Regional', 'Nasional', 'Internasional'],
            'jenisList' => ['Akademik', 'Nonakademik'],
            'tahunList' => $semua->pluck('tahun')->unique()->sortDesc()->values()->all(),
            'ringkasan' => [
                'total' => $semua->count(),
                'akademik' => $semua->where('jenis', 'Akademik')->count(),
                'nonakademik' => $semua->where('jenis', 'Nonakademik')->count(),
                'akademik_ket' => 'Teknologi rekayasa komputer',
                'nonakademik_ket' => 'Desain dan kreativitas',
            ],
        ]);
    }

    public function prestasiTambah()
    {
        $kegiatanList = Kegiatan::untukPrestasi(request()->user())
            ->with(['kategori', 'detail.isianKategori'])->orderBy('nama_kegiatan')->get()
            ->map(fn (Kegiatan $activity) => [
                'id' => $activity->getKey(),
                'label' => $activity->nama_kegiatan.' • '.$activity->kategori->nama_kategori.' (#'.$activity->getKey().')',
                'nama' => $activity->nama_kegiatan,
                'penyelenggara' => $activity->nilaiIsian('penyelenggara') ?? '',
            ]);

        return view('mahasiswa.mahasiswa.prestasi-tambah', [
            'nama' => config('mahasiswa.nama'),
            'profil' => ['nim' => config('mahasiswa.nim'), 'prodi' => config('mahasiswa.prodi')],
            'tingkatList' => ['Internal', 'Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional'],
            'jenisList' => ['Akademik', 'Nonakademik'],
            'jenisDokList' => ['Sertifikat', 'Dokumentasi kegiatan'],
            'daftarMahasiswa' => ['Ivan Sigit Santoso', 'Anisa Aulia Nurusyifa', config('mahasiswa.nama'), 'Dewi Lestari', 'Fajar Nugroho', 'Rizky Pratama', 'Salsabila Putri'],
            'peranList' => ['Ketua', 'Anggota'],
            'kegiatanList' => $kegiatanList,
        ]);
    }

    public function prestasiSimpan(Request $request)
    {
        $data = $this->validasiPrestasi($request);

        return redirect()->route('mahasiswa.prestasi')->with('status', 'Prestasi berhasil disimpan.');
    }

    public function prestasiEdit(string $prestasi)
    {
        $data = [
            'id' => $prestasi,
            'sumber' => 'sebelumnya',
            'kegiatan' => 'Lomba inovasi IoT 2026 • Lomba',
            'nama_lomba' => 'Lomba Inovasi Internet of Things',
            'penyelenggara' => 'Forum Inovasi Teknologi',
            'lokasi' => 'Semarang',
            'dosen' => 'Tahan Prahara',
            'tingkat' => 'Nasional',
            'capaian' => 'Juara 2',
            'jenis' => 'Akademik',
            'kategori' => 'Teknologi',
            'tanggal_mulai' => '2026-09-22',
            'tanggal_selesai' => '2026-09-24',
            'bentuk' => 'Tim',
            'deskripsi' => 'Mengembangkan sistem pemantauan berbasis IoT untuk lomba tingkat nasional.',
            'anggota' => [
                ['nama' => 'Ivan Sigit Santoso',    'peran' => 'Anggota'],
                ['nama' => 'Anisa Aulia Nurusyifa', 'peran' => 'Anggota'],
            ],
            'dokumen' => [
                ['id' => 11, 'jenis' => 'Sertifikat',    'nama' => 'sertifikat-iot.pdf', 'ukuran' => '1,2 MB', 'url' => null],
                ['id' => 12, 'jenis' => 'Dokumentasi kegiatan', 'nama' => 'foto-iot.jpg', 'ukuran' => '2,4 MB', 'url' => null],
            ],
        ];

        return $this->prestasiTambah()->with('prestasi', $data);
    }

    public function prestasiUpdate(Request $request, string $prestasi)
    {
        $data = $this->validasiPrestasi($request);

        return redirect()->route('mahasiswa.prestasi')->with('status', 'Prestasi berhasil diperbarui.');
    }

    public function prestasiHapus(string $prestasi)
    {
        return redirect()->route('mahasiswa.prestasi')->with('status', 'Prestasi berhasil dihapus.');
    }

    private function validasiPrestasi(Request $request): array
    {
        $tingkat = ['Internal', 'Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional'];
        $jenisDok = ['Sertifikat', 'Dokumentasi kegiatan'];

        $validator = Validator::make($request->all(), [
            'sumber' => ['required', Rule::in(['sebelumnya', 'mandiri'])],
            'id_kegiatan' => ['exclude_unless:sumber,sebelumnya', 'required', 'integer'],
            'kegiatan' => ['required_if:sumber,sebelumnya', 'nullable', 'string'],
            'nama_lomba' => ['required', 'string', 'max:150'],
            'penyelenggara' => ['required', 'string', 'max:150'],
            'lokasi' => ['required', 'string', 'max:150'],
            'dosen' => ['nullable', 'string', 'max:100'],
            'tingkat' => ['required', Rule::in($tingkat)],
            'capaian' => ['required', 'string', 'max:100'],
            'jenis' => ['required', Rule::in(['Akademik', 'Nonakademik'])],
            'kategori' => ['nullable', 'string', 'max:100'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'bentuk' => ['required', Rule::in(['Individu', 'Tim'])],
            'anggota' => ['exclude_unless:bentuk,Tim', 'required', 'array', 'min:1'],
            'anggota.*.nama' => ['required', 'string', 'max:150'],
            'anggota.*.peran' => ['required', 'string', 'max:100'],
            'deskripsi' => ['nullable', 'string', 'max:1000'],
            'dokumen' => ['nullable', 'array', 'max:5'],
            'dokumen.*' => ['array:url,jenis'],
            'dokumen.*.url' => [
                'nullable', 'url', 'max:2048',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $host = strtolower((string) parse_url($value, PHP_URL_HOST));
                    $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
                    $domainDiizinkan = in_array($host, [
                        'drive.google.com',
                        'docs.google.com',
                        '1drv.ms',
                        'onedrive.live.com',
                    ], true) || str_ends_with($host, '.sharepoint.com');

                    if ($scheme !== 'https' || ! $domainDiizinkan) {
                        $fail('Tautan harus berupa tautan berbagi Google Drive atau OneDrive yang aman (https).');
                    }
                },
            ],
            'dokumen.*.jenis' => ['nullable', Rule::in($jenisDok)],
            'dokumen_pertahankan' => ['nullable', 'array', 'max:5'],
            'dokumen_pertahankan.*' => ['integer'],
            'dokumen_hapus' => ['nullable', 'array'],
            'dokumen_hapus.*' => ['integer'],
        ], [
            'required' => ':attribute wajib diisi.',
            'required_if' => ':attribute wajib diisi.',
            'in' => ':attribute tidak valid.',
            'max' => ':attribute terlalu panjang atau terlalu banyak.',
            'dokumen.*.url.url' => 'Masukkan tautan Google Drive atau OneDrive yang valid.',
            'after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'anggota.required' => 'Tambahkan minimal satu anggota tim.',
            'anggota.min' => 'Tambahkan minimal satu anggota tim.',
        ], [
            'nama_lomba' => 'Nama lomba', 'penyelenggara' => 'Penyelenggara', 'lokasi' => 'Lokasi',
            'tingkat' => 'Tingkat kejuaraan', 'capaian' => 'Capaian peringkat', 'jenis' => 'Jenis prestasi',
            'tanggal_mulai' => 'Tanggal mulai', 'tanggal_selesai' => 'Tanggal selesai',
            'bentuk' => 'Bentuk partisipasi', 'kegiatan' => 'Kegiatan sebelumnya',
            'anggota.*.nama' => 'Nama anggota', 'anggota.*.peran' => 'Peran anggota',
            'dokumen.*.url' => 'Tautan bukti', 'dokumen.*.jenis' => 'Jenis dokumen',
        ]);

        $validator->after(function ($validator) use ($request) {
            if ($request->input('sumber') === 'sebelumnya' && ! $validator->errors()->has('id_kegiatan')
                && ! Kegiatan::untukPrestasi($request->user())->whereKey($request->integer('id_kegiatan'))->exists()) {
                $validator->errors()->add('kegiatan', 'Pilih kegiatan yang sudah diterima dan Anda ikuti.');
            }
            $dokumenList = $request->input('dokumen', []);
            $jumlahDokumenBaru = 0;
            if (is_array($dokumenList)) {
                foreach ($dokumenList as $index => $dokumen) {
                    if (! is_array($dokumen)) {
                        continue;
                    }

                    $punyaTaitan = filled($dokumen['url'] ?? null);
                    $punyaJenis = filled($dokumen['jenis'] ?? null);

                    if ($punyaTaitan && $punyaJenis) {
                        $jumlahDokumenBaru++;
                    }

                    if ($punyaTaitan !== $punyaJenis) {
                        $field = $punyaTaitan ? "dokumen.$index.jenis" : "dokumen.$index.url";
                        $validator->errors()->add($field, $punyaTaitan
                            ? 'Pilih jenis dokumen untuk tautan ini.'
                            : 'Masukkan tautan untuk jenis dokumen ini.');
                    }
                }
            }

            $dokumenDipertahankan = $request->input('dokumen_pertahankan', []);
            $jumlahDipertahankan = is_array($dokumenDipertahankan) ? count($dokumenDipertahankan) : 0;
            if ($jumlahDokumenBaru + $jumlahDipertahankan === 0) {
                $validator->errors()->add('dokumen', 'Tambahkan minimal satu tautan bukti prestasi.');
            }
        });

        return $validator->validate();
    }
}
