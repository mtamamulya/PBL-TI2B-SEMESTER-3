<?php

namespace App\Http\Controllers;

use App\BackendKel2\Akademik\PeriodeAkademik;
use App\BackendKel2\MataKuliah\MataKuliahController as BackendMataKuliahController;
use App\Models\Kegiatan;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MahasiswaController extends Controller
{
    public function dashboard(Request $request)
    {
        // DATA DUMMY: ganti dengan query Eloquent / API Kelompok 1
        $base = collect([
            ['mk' => 'Kecerdasan Buatan',  'dosen' => 'Angga Wahyu Wibowo', 'tingkat' => 2, 'semester' => 'Ganjil'],
            ['mk' => 'Jaringan Komputer',  'dosen' => 'Kuwat Santoso',      'tingkat' => 2, 'semester' => 'Ganjil'],
            ['mk' => 'Sistem Basis Data',  'dosen' => 'Wiktasari',          'tingkat' => 3, 'semester' => 'Genap'],
            ['mk' => 'Internet of Things', 'dosen' => 'Tahan Prahara',      'tingkat' => 1, 'semester' => 'Genap'],
            ['mk' => 'Computer Vision',    'dosen' => 'Prayitno',           'tingkat' => 2, 'semester' => 'Ganjil'],
            ['mk' => 'Sistem Operasi',     'dosen' => 'Arif Setyawan',      'tingkat' => 2, 'semester' => 'Ganjil'],
        ]);

        $semua = collect(range(1, 5))
            ->flatMap(fn () => $base)
            ->map(fn ($r) => $r + ['tahun' => '2026/2027'])
            ->values();

        $filter = [
            'q' => trim((string) $request->query('q')),
            'semester' => (string) $request->query('semester'),
            'tingkat' => (string) $request->query('tingkat'),
            'tahun' => (string) $request->query('tahun'),
        ];

        $rows = $semua->filter(function ($r) use ($filter) {
            return ($filter['q'] === '' || mb_stripos($r['mk'].' '.$r['dosen'], $filter['q']) !== false)
                && ($filter['semester'] === '' || $r['semester'] === $filter['semester'])
                && ($filter['tingkat'] === '' || (string) $r['tingkat'] === $filter['tingkat'])
                && ($filter['tahun'] === '' || $r['tahun'] === $filter['tahun']);
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

        return view('mahasiswa.mahasiswa.dashboard-mhs', [
            'nama' => config('mahasiswa.nama'),
            'ringkasan' => [
                'tahun_akademik' => '2026/2027',
                'jenis_semester' => 'Ganjil',
                'sks_tempuh' => 84,
                'sks_total' => 144,
                'semester_ke' => config('mahasiswa.semester'),
                'jumlah_mk' => 7,
                'sks_semester' => 21,
                'pengajuan_aktif' => 2,
                'dokumen_siap' => 1,
            ],
            'tahunList' => ['2026/2027' => '2026/2027', '2025/2026' => '2025/2026', '2024/2025' => '2024/2025'],
            'filter' => $filter,
            'mataKuliah' => $mataKuliah,
        ]);
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
            // Fallback ke data lokal jika server API Kelompok 1 (port 8001) belum aktif
        }

        // Fallback otomatis jika API Kelompok 1 sedang offline / belum ada data
        if ($rows->isEmpty() && $filter['q'] === '' && $filter['semester'] === '' && $filter['tingkat'] === '' && $filter['tahun'] === '') {
            $arsip = collect([
                ['Kecerdasan Buatan',  2, 'Ganjil', '2026/2027', 'Angga Wahyu Wibowo'],
                ['Jaringan Komputer',  2, 'Ganjil', '2026/2027', 'Kuwat Santoso'],
                ['Sistem Basis Data',  3, 'Genap',  '2025/2026', 'Wiktasari'],
                ['Internet of Things', 1, 'Genap',  '2025/2026', 'Tahan Prahara'],
                ['Computer Vision',    2, 'Ganjil', '2026/2027', 'Prayitno'],
                ['Sistem Operasi',     2, 'Ganjil', '2026/2027', 'Arif Setyawan'],
                ['Jaringan Komputer',  2, 'Ganjil', '2025/2026', 'Kuwat Santoso'],
                ['Sistem Basis Data',  3, 'Genap',  '2025/2026', 'Wiktasari'],
                ['Sistem Operasi',     1, 'Genap',  '2024/2025', 'Arif Setyawan'],
                ['Internet of Things', 2, 'Ganjil', '2026/2027', 'Tahan Prahara'],
            ])->map(fn ($r) => ['mk' => $r[0], 'dosen' => $r[4], 'tingkat' => $r[1], 'periode' => $r[2], 'tahun' => $r[3], 'sks' => null]);

            $katalog = collect([
                ['Algoritma dan Pemrograman', 1, 'Ganjil', 4, '2024/2025', 'Tim Dosen'],
                ['Matematika Dasar', 1, 'Ganjil', 3, '2024/2025', 'Tim Dosen'],
                ['Dasar Elektronika', 1, 'Ganjil', 3, '2024/2025', 'Tim Dosen'],
                ['Internet of Things', 1, 'Genap', 4, '2024/2025', 'Tahan Prahara'],
                ['Sistem Operasi', 1, 'Genap', 4, '2024/2025', 'Arif Setyawan'],
                ['Kecerdasan Buatan', 2, 'Ganjil', 2, '2026/2027', 'Angga Wahyu Wibowo'],
                ['Jaringan Komputer', 2, 'Ganjil', 4, '2025/2026', 'Kuwat Santoso'],
                ['Computer Vision', 2, 'Ganjil', 3, '2026/2027', 'Prayitno'],
                ['Pemrograman Web', 2, 'Genap', 4, '2025/2026', 'Tim Dosen'],
                ['Mikrokontroler', 2, 'Genap', 3, '2025/2026', 'Tim Dosen'],
                ['Sistem Basis Data', 3, 'Ganjil', 4, '2025/2026', 'Wiktasari'],
                ['Keamanan Jaringan', 3, 'Ganjil', 3, '2026/2027', 'Tim Dosen'],
                ['Machine Learning', 3, 'Genap', 3, '2026/2027', 'Angga Wahyu Wibowo'],
                ['Rekayasa Perangkat Lunak', 3, 'Genap', 3, '2025/2026', 'Tim Dosen'],
                ['Proyek Akhir', 4, 'Ganjil', 6, '2026/2027', 'Tim Dosen'],
                ['Manajemen Proyek TI', 4, 'Ganjil', 2, '2026/2027', 'Tim Dosen'],
                ['Magang Industri', 4, 'Genap', 6, '2026/2027', 'Tim Dosen'],
                ['Tugas Akhir', 4, 'Genap', 6, '2026/2027', 'Tim Dosen'],
            ])->map(fn ($r) => [
                'mk' => $r[0], 'dosen' => $r[5], 'tingkat' => $r[1],
                'periode' => $r[2], 'sks' => $r[3], 'tahun' => $r[4],
            ]);

            $berjalan = collect([
                ['Kecerdasan Buatan',  2, 'Ganjil', 2, 'Angga Wahyu Wibowo'],
                ['Jaringan Komputer',  2, 'Ganjil', 4, 'Kuwat Santoso'],
                ['Computer Vision',    2, 'Ganjil', 3, 'Prayitno'],
                ['Sistem Operasi',     2, 'Ganjil', 4, 'Arif Setyawan'],
                ['Jaringan Komputer',  2, 'Ganjil', 4, 'Kuwat Santoso'],
                ['Internet of Things', 2, 'Ganjil', 4, 'Tahan Prahara'],
            ])->map(fn ($r) => ['mk' => $r[0], 'dosen' => $r[4], 'tingkat' => $r[1], 'periode' => $r[2], 'tahun' => $defaultTahun, 'sks' => $r[3]]);

            $rows = match ($tab) {
                'berjalan' => $berjalan,
                'katalog' => $katalog,
                default => $arsip,
            };
        }

        // Filter sisi server (memastikan pencarian & dropdown tetap akurat)
        $cocok = fn ($r) => ($filter['q'] === '' || mb_stripos($r['mk'], $filter['q']) !== false)
            && ($filter['semester'] === '' || $r['periode'] === $filter['semester'])
            && ($filter['tingkat'] === '' || (string) $r['tingkat'] === $filter['tingkat'])
            && ($filter['tahun'] === '' || $r['tahun'] === $filter['tahun']);

        $rows = match ($tab) {
            'berjalan' => $berjalan->values(),
            'katalog' => $rows->filter($cocok)->sortBy([['tingkat', 'asc'], ['mk', 'asc']])->values(),
            default => $rows->filter($cocok)->values(),
        };

        $tahunList = $rows->pluck('tahun')
            ->merge([$defaultTahun, '2026/2027', '2025/2026', '2024/2025'])
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return view('mahasiswa.mahasiswa.mata-kuliah', [
            'nama' => config('mahasiswa.nama'),
            'profil' => ['nim' => config('mahasiswa.nim'), 'prodi' => config('mahasiswa.prodi')],
            'tab' => $tab,
            'rows' => $rows,
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
