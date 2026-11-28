<?php

namespace App\Http\Controllers;

use App\BackendKel1\Kurikulum\KurikulumAngkatan;
use App\BackendKel2\Akademik\PeriodeAkademik;
use App\BackendKel2\Dashboard\ContohDashboardMahasiswa;
use App\BackendKel2\Dashboard\DashboardController as BackendDashboardController;
use App\Models\AnggotaKegiatan;
use App\Models\JenisDokumen;
use App\Models\Kegiatan;
use App\Models\Mahasiswa;
use App\Models\Prestasi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
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

        $penempatan = $this->penempatanMahasiswa($mahasiswa);
        $prodiId = $penempatan?->id_prodi;
        $prodiId = $prodiId ?? ($enrollment->first()['id_prodi'] ?? null);

        $kurikulumAktif = $prodiId && $mahasiswa
            ? KurikulumAngkatan::idUntukAngkatan((int) $mahasiswa->tahun_masuk, (int) $prodiId)
            : null;
        $sksTotal = $kurikulumAktif
            ? (int) DB::table('mata_kuliah')->where('id_kurikulum', $kurikulumAktif)->sum(DB::raw('sks_teori + sks_praktik'))
            : 0;

        $semua = $enrollment->map(fn ($row) => collect($row)->only(['mk', 'dosen', 'tingkat', 'semester', 'tahun'])->all())->values();
        $contoh = app()->environment(['local', 'testing']) && $request->boolean('contoh')
            ? ContohDashboardMahasiswa::data()
            : null;
        if ($contoh) {
            $semua = collect($contoh['mata_kuliah']);
        }

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

        if ($contoh) {
            $ringkasan = $contoh['ringkasan'];
        }

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

    private function penempatanMahasiswa(?Mahasiswa $mahasiswa): ?object
    {
        if (! $mahasiswa) {
            return null;
        }

        return DB::table('enrollment_mahasiswa_tingkat_kelas as emtk')
            ->join('enrollment_tingkat_kelas as etk', 'etk.id_enrollment_tingkat_kelas', '=', 'emtk.id_enrollment_tingkat_kelas')
            ->join('tahun_akademik as ta', 'ta.id_tahun_akademik', '=', 'etk.id_tahun_akademik')
            ->join('prodi as p', 'p.id_prodi', '=', 'etk.id_prodi')
            ->where('emtk.id_mahasiswa', $mahasiswa->getKey())
            ->orderByDesc('ta.status')
            ->orderByDesc('ta.id_tahun_akademik')
            ->select(['p.id_prodi', 'p.kode_prodi', 'p.nama_prodi'])
            ->first();
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

        $user = $request->user();
        $mahasiswa = $user?->mahasiswa;
        $penempatan = $this->penempatanMahasiswa($mahasiswa);
        $kodeProdi = $penempatan?->kode_prodi;
        $tahunMasuk = null;

        if ($user) {
            $tahunMasuk = DB::table('mahasiswa')
                ->where('id_user', $user->id_user ?? $user->id)
                ->value('tahun_masuk');
        }

        $angkatan = (string) ($tahunMasuk ?: config('mahasiswa.angkatan', '2024'));

        $periodeAktif = class_exists(PeriodeAkademik::class)
            ? PeriodeAkademik::sekarang()
            : null;

        $tahunAktifLabel = $periodeAktif ? $periodeAktif->label() : '2026/2027 Ganjil';
        $semesterKe = $periodeAktif ? $periodeAktif->semesterKe($angkatan) : (int) config('mahasiswa.semester', 5);
        $defaultTahun = explode(' ', $tahunAktifLabel)[0] ?? '2026/2027';

        $mapItem = function (array $item, string $defaultTahunLabel) use ($defaultTahun, $angkatan): array {
            $sks = isset($item['sks'])
                ? (int) $item['sks']
                : ((int) ($item['sks_teori'] ?? 0) + (int) ($item['sks_praktik'] ?? 0));

            $semesterAngka = isset($item['semester']) && is_numeric($item['semester'])
                ? (int) $item['semester']
                : null;

            $semesterRaw = $item['periode'] ?? $item['jenis_semester'] ?? ($semesterAngka ?? 'Ganjil');
            $periode = is_numeric($semesterRaw)
                ? (((int) $semesterRaw % 2 === 1) ? 'Ganjil' : 'Genap')
                : ucfirst(strtolower((string) $semesterRaw));

            $tingkat = (int) ($item['tingkat'] ?? ($semesterAngka ? ceil($semesterAngka / 2) : 1));
            $kodeMk = trim((string) ($item['kode_mk'] ?? ''));
            $namaMk = (string) ($item['nama_mk'] ?? $item['nama_matakuliah'] ?? $item['nama'] ?? $item['mk'] ?? '-');
            $tahunAkademik = $item['tahun_akademik'] ?? $item['tahun'] ?? null;

            if (! filled($tahunAkademik)) {
                $tahunAngkatan = is_numeric($angkatan) ? (int) $angkatan : null;
                $semesterValid = $semesterAngka !== null && $semesterAngka > 0;
                $tahunMulai = $tahunAngkatan
                    ? $tahunAngkatan + ($semesterValid ? intdiv($semesterAngka - 1, 2) : max(0, $tingkat - 1))
                    : null;

                $tahunAkademik = $tahunMulai
                    ? $tahunMulai.'/'.($tahunMulai + 1).' '.$periode
                    : ($defaultTahunLabel ?: $defaultTahun);
            }

            return [
                'kode_mk' => $kodeMk,
                'mk' => $kodeMk !== '' ? "{$kodeMk} - {$namaMk}" : $namaMk,
                'semester_ke' => $semesterAngka,
                'tingkat' => max(1, min(4, $tingkat)),
                'periode' => in_array($periode, ['Ganjil', 'Genap'], true) ? $periode : 'Ganjil',
                'tahun' => (string) $tahunAkademik,
                'sks' => $sks > 0 ? $sks : null,
            ];
        };

        $fetchMataKuliahApi = function (array $params): array {
            $apiClientClass = 'App\\Services\\ApiClient';
            $apiExceptionClass = 'App\\Exceptions\\ApiException';

            if (class_exists($apiClientClass)) {
                try {
                    $hasil = $apiClientClass::publik()->get('/mata-kuliah', $params);

                    return is_array($hasil['data'] ?? null) ? $hasil['data'] : [];
                } catch (\Throwable $e) {
                    if (class_exists($apiExceptionClass) && $e instanceof $apiExceptionClass) {
                        return [];
                    }

                    throw $e;
                }
            }

            $response = Http::acceptJson()->timeout(10)
                ->get('http://127.0.0.1:8001/api/v1/mata-kuliah', $params);

            if (! $response->successful()) {
                return [];
            }

            $data = $response->json('data');

            return is_array($data) ? $data : [];
        };

        $berjalan = collect();
        $arsip = collect();
        $katalog = collect();
        $pesanApi = null;

        if ($angkatan !== null && filled($kodeProdi)) {
            if ($tab === 'berjalan') {
                $rawItems = $fetchMataKuliahApi(array_filter([
                    'angkatan' => $angkatan,
                    'kode_prodi' => $kodeProdi,
                    'semester' => $semesterKe,
                    'q' => $filter['q'] ?: null,
                    'per_page' => 100,
                ], fn ($v) => $v !== null));

                $berjalan = collect($rawItems)
                    ->map(fn ($item) => $mapItem((array) $item, $tahunAktifLabel))
                    ->values();
            } elseif ($tab === 'katalog') {
                $rawItems = $fetchMataKuliahApi(array_filter([
                    'angkatan' => $angkatan,
                    'kode_prodi' => $kodeProdi,
                    'q' => $filter['q'] ?: null,
                    'per_page' => 100,
                ], fn ($v) => $v !== null));

                $katalog = collect($rawItems)
                    ->map(fn ($item) => $mapItem((array) $item, $tahunAktifLabel))
                    ->values();
            } else {
                $rawItems = $fetchMataKuliahApi(array_filter([
                    'angkatan' => $angkatan,
                    'kode_prodi' => $kodeProdi,
                    'q' => $filter['q'] ?: null,
                    'per_page' => 100,
                ], fn ($v) => $v !== null));

                $arsip = collect($rawItems)
                    ->map(fn ($item) => $mapItem((array) $item, $tahunAktifLabel))
                    ->filter(fn ($r) => $r['semester_ke'] !== null && $r['semester_ke'] < $semesterKe)
                    ->values();
            }
        } else {
            $pesanApi = 'Data angkatan atau program studi belum tersedia pada akun mahasiswa.';
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
            ->merge([$tahunAktifLabel])
            ->filter()
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        $pesanKosong = $rows->isEmpty() ? 'Belum ada data untuk ditampilkan' : null;

        return view('mahasiswa.mahasiswa.mata-kuliah', [
            'nama' => $mahasiswa?->nama ?? $user?->email ?? 'Mahasiswa',
            'profil' => [
                'nim' => $mahasiswa?->nim ?? '—',
                'prodi' => $penempatan?->nama_prodi ?? '—',
            ],
            'tab' => $tab,
            'rows' => $rows,
            'pesanKosong' => $pesanKosong,
            'pesanApi' => $pesanApi,
            'filter' => $filter,
            'tahunList' => $tahunList,
            'ringkasan' => [
                'tahun_akademik' => $tahunAktifLabel,
                'semester_ke' => $semesterKe,
                'sks_diambil' => $berjalan->sum('sks'),
                'jumlah_berjalan' => $berjalan->count(),
            ],
        ]);
    }

    public function prestasi(Request $request)
    {
        $user = $request->user();
        $mahasiswa = $user?->mahasiswa;
        $penempatan = $this->penempatanMahasiswa($mahasiswa);
        $prestasi = Prestasi::query()
            ->untukUser($user)
            ->with(['kegiatan', 'buktiPrestasi.jenisDokumen', 'creator.mahasiswa', 'creator.dosen'])
            ->latest('id_prestasi')
            ->get()
            ->map(function (Prestasi $record) use ($user): array {
                $tanggal = $record->tanggal_mulai;
                $pembuat = $record->creator?->mahasiswa?->nama
                    ?? $record->creator?->dosen?->nama
                    ?? $record->creator?->email
                    ?? '—';

                return [
                    'id' => (int) $record->getKey(),
                    'nama' => $record->nama_lomba,
                    'kategori' => $record->bidang_lomba ?: ($record->form_data['kategori'] ?? 'Lainnya'),
                    'bentuk' => ucfirst($record->bentuk_partisipasi),
                    'capaian' => $record->capaian,
                    'tingkat' => ucwords($record->tingkat_kejuaraan),
                    'jenis' => $record->jenis_prestasi === 'non-akademik' ? 'Nonakademik' : 'Akademik',
                    'penyelenggara' => $record->penyelenggara,
                    'tanggal' => $tanggal?->format('d/m/Y') ?? '—',
                    'tahun' => $tanggal?->year ?? '—',
                    'kegiatan' => $record->kegiatan?->nama_kegiatan ?? 'Tidak terkait',
                    'bukti' => $record->buktiPrestasi->map(fn ($bukti) => [
                        'id' => (int) $bukti->getKey(),
                        'jenis' => $bukti->jenisDokumen?->nama_jenis_dokumen ?? 'Dokumen bukti',
                        'nama' => $bukti->nama_file,
                        'url' => $bukti->link,
                    ])->all(),
                    'ditag' => (int) $record->id_user !== (int) $user?->getKey(),
                    'pembuat' => $pembuat,
                    'bisaUbah' => (int) $record->created_by === (int) $user?->getKey(),
                ];
            })->values();

        return view('mahasiswa.mahasiswa.prestasi', [
            'nama' => $mahasiswa?->nama ?? $user?->email ?? 'Mahasiswa',
            'profil' => [
                'nim' => $mahasiswa?->nim ?? '—',
                'prodi' => $penempatan?->nama_prodi ?? '—',
            ],
            'prestasi' => $prestasi,
            'total' => $prestasi->count(),
            'akademik' => $prestasi->where('jenis', 'Akademik')->count(),
            'nonakademik' => $prestasi->where('jenis', 'Nonakademik')->count(),
            'tingkatList' => ['Internal', 'Regional', 'Nasional', 'Internasional'],
            'jenisList' => ['Akademik', 'Nonakademik'],
            'tahunList' => $prestasi->pluck('tahun')->filter()->unique()->sortDesc()->values()->all(),
        ]);
    }

    public function profil(Request $request)
    {
        $user = $request->user();
        $mahasiswa = $user?->mahasiswa;
        $penempatan = $this->penempatanMahasiswa($mahasiswa);

        return view('mahasiswa.mahasiswa.profil', [
            'nama' => $mahasiswa?->nama ?? $user?->email ?? 'Mahasiswa',
            'angkatan' => $mahasiswa?->tahun_masuk ?? '—',
            'email' => $user?->email ?? '—',
            'nim' => $mahasiswa?->nim ?? '—',
            'prodi' => $penempatan?->nama_prodi ?? '—',
            'status' => $mahasiswa?->status_mahasiswa ?? ($user?->status ? 'Aktif' : 'Nonaktif'),
        ]);
    }

    public function prestasiTambah(Request $request)
    {
        $user = $request->user();
        $mahasiswa = $user?->mahasiswa;
        $penempatan = $this->penempatanMahasiswa($mahasiswa);
        $kegiatanList = Kegiatan::untukPrestasi($user)
            ->with(['kategori', 'detail.isianKategori'])->orderBy('nama_kegiatan')->get()
            ->map(fn (Kegiatan $activity) => [
                'id' => $activity->getKey(),
                'label' => $activity->nama_kegiatan.' • '.($activity->kategori?->nama_kategori ?? 'Kegiatan').' (#'.$activity->getKey().')',
                'nama' => $activity->nama_kegiatan,
                'penyelenggara' => $activity->nilaiIsian('penyelenggara') ?? '',
            ]);

        return view('mahasiswa.mahasiswa.prestasi-tambah', [
            'nama' => $mahasiswa?->nama ?? $user?->email ?? 'Mahasiswa',
            'profil' => [
                'nim' => $mahasiswa?->nim ?? '—',
                'prodi' => $penempatan?->nama_prodi ?? '—',
            ],
            'tingkatList' => ['Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional'],
            'jenisList' => ['Akademik', 'Nonakademik'],
            'jenisDokList' => ['Sertifikat', 'Dokumentasi kegiatan'],
            'daftarMahasiswa' => DB::table('mahasiswa')
                ->where('id_user', '<>', $user?->getKey())
                ->orderBy('nama')
                ->pluck('nama')
                ->all(),
            'peranList' => ['Anggota'],
            'kegiatanList' => $kegiatanList,
        ]);
    }

    public function prestasiSimpan(Request $request)
    {
        $data = $this->validasiPrestasi($request);
        $this->simpanPrestasi($data, $request->user());

        return redirect()->route('mahasiswa.prestasi')->with('status', 'Prestasi berhasil disimpan.');
    }

    public function prestasiEdit(Request $request, string $prestasi)
    {
        $record = Prestasi::query()
            ->where('created_by', $request->user()->getKey())
            ->with(['kegiatan.kategori', 'buktiPrestasi.jenisDokumen'])
            ->findOrFail($prestasi);
        $formData = is_array($record->form_data) ? $record->form_data : [];
        $activity = $record->kegiatan;

        $data = array_merge($formData, [
            'id' => (int) $record->getKey(),
            'sumber' => $record->id_kegiatan ? 'sebelumnya' : 'mandiri',
            'id_kegiatan' => $record->id_kegiatan,
            'kegiatan' => $activity
                ? $activity->nama_kegiatan.' • '.($activity->kategori?->nama_kategori ?? 'Kegiatan').' (#'.$activity->getKey().')'
                : '',
            'nama_lomba' => $record->nama_lomba,
            'penyelenggara' => $record->penyelenggara,
            'lokasi' => $record->lokasi,
            'dosen' => $record->dosen_pembimbing,
            'tingkat' => ucwords($record->tingkat_kejuaraan),
            'capaian' => $record->capaian,
            'jenis' => $record->jenis_prestasi === 'non-akademik' ? 'Nonakademik' : 'Akademik',
            'kategori' => $record->bidang_lomba ?? '',
            'tanggal_mulai' => $record->tanggal_mulai?->toDateString(),
            'tanggal_selesai' => $record->tanggal_selesai?->toDateString(),
            'bentuk' => ucfirst($record->bentuk_partisipasi),
            'deskripsi' => $record->deskripsi ?? '',
            'dokumen' => $record->buktiPrestasi->map(fn ($bukti) => [
                'id' => (int) $bukti->getKey(),
                'jenis' => $bukti->jenisDokumen?->nama_jenis_dokumen ?? 'Dokumen bukti',
                'nama' => $bukti->nama_file,
                'ukuran' => '',
                'url' => $bukti->link,
            ])->all(),
        ]);

        return $this->prestasiTambah($request)->with('prestasi', $data);
    }

    public function prestasiUpdate(Request $request, string $prestasi)
    {
        $record = Prestasi::query()
            ->where('created_by', $request->user()->getKey())
            ->findOrFail($prestasi);
        $data = $this->validasiPrestasi($request, $record);
        $this->simpanPrestasi($data, $request->user(), $record);

        return redirect()->route('mahasiswa.prestasi')->with('status', 'Prestasi berhasil diperbarui.');
    }

    public function prestasiHapus(Request $request, string $prestasi)
    {
        $record = Prestasi::query()
            ->where('created_by', $request->user()->getKey())
            ->findOrFail($prestasi);
        $record->delete();

        return redirect()->route('mahasiswa.prestasi')->with('status', 'Prestasi berhasil dihapus.');
    }

    private function validasiPrestasi(Request $request, ?Prestasi $record = null): array
    {
        $tingkat = ['Kabupaten/Kota', 'Provinsi', 'Nasional', 'Internasional'];
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
            'anggota' => ['exclude_unless:bentuk,Tim', 'exclude_unless:sumber,mandiri', 'required', 'array', 'min:1'],
            'anggota.*.nama' => ['required', 'string', 'max:150'],
            'anggota.*.peran' => ['required', Rule::in(['Anggota'])],
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

        $validator->after(function ($validator) use ($request, $record) {
            if ($request->input('sumber') === 'sebelumnya' && ! $validator->errors()->has('id_kegiatan')
                && ! Kegiatan::untukPrestasi($request->user())->whereKey($request->integer('id_kegiatan'))->exists()) {
                $validator->errors()->add('kegiatan', 'Pilih kegiatan yang sudah diterima dan Anda ikuti.');
            }

            if ($request->input('sumber') === 'mandiri' && $request->input('bentuk') === 'Tim') {
                $anggota = collect($request->input('anggota', []));
                $namaAnggota = $anggota->pluck('nama')->map(fn ($nama) => mb_strtolower(trim((string) $nama)));
                if ($namaAnggota->contains(mb_strtolower((string) $request->user()?->mahasiswa?->nama))
                    || $namaAnggota->duplicates()->isNotEmpty()) {
                    $validator->errors()->add('anggota', 'Nama anggota tidak boleh sama atau berulang.');
                }

                foreach ($anggota as $index => $anggotaData) {
                    $jumlahMahasiswa = DB::table('mahasiswa')
                        ->whereRaw('LOWER(nama) = ?', [mb_strtolower(trim((string) ($anggotaData['nama'] ?? '')))])
                        ->count();
                    if ($jumlahMahasiswa !== 1) {
                        $validator->errors()->add("anggota.$index.nama", 'Pilih nama mahasiswa yang terdaftar dan tidak ambigu.');
                    }
                }
            }

            $dokumenList = $request->input('dokumen', []);
            $jumlahDokumenBaru = 0;
            $tautan = [];
            if (is_array($dokumenList)) {
                foreach ($dokumenList as $index => $dokumen) {
                    if (! is_array($dokumen)) {
                        continue;
                    }

                    $punyaTaitan = filled($dokumen['url'] ?? null);
                    $punyaJenis = filled($dokumen['jenis'] ?? null);
                    if ($punyaTaitan && $punyaJenis) {
                        $jumlahDokumenBaru++;
                        $tautan[] = strtolower($dokumen['url']);
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
            $dokumenDipertahankan = is_array($dokumenDipertahankan) ? array_unique($dokumenDipertahankan) : [];
            if ($record && $dokumenDipertahankan !== []) {
                $dokumenLama = $record->buktiPrestasi()
                    ->whereIn('id_bukti_prestasi', $dokumenDipertahankan)
                    ->get(['id_bukti_prestasi', 'link']);
                if ($dokumenLama->count() !== count($dokumenDipertahankan)) {
                    $validator->errors()->add('dokumen_pertahankan', 'Dokumen yang dipertahankan tidak valid.');
                }
                $tautan = array_merge($tautan, $dokumenLama->pluck('link')->map(fn ($link) => strtolower($link))->all());
            } elseif (! $record && $dokumenDipertahankan !== []) {
                $validator->errors()->add('dokumen_pertahankan', 'Dokumen lama tidak dapat digunakan untuk prestasi baru.');
            }

            if (count($tautan) !== count(array_unique($tautan))) {
                $validator->errors()->add('dokumen', 'Tautan dokumen tidak boleh duplikat.');
            }

            $jumlahDipertahankan = count($dokumenDipertahankan);
            if ($jumlahDokumenBaru + $jumlahDipertahankan === 0) {
                $validator->errors()->add('dokumen', 'Tambahkan minimal satu tautan bukti prestasi.');
            } elseif ($jumlahDokumenBaru + $jumlahDipertahankan > 5) {
                $validator->errors()->add('dokumen', 'Maksimal 5 dokumen.');
            }
        });

        return $validator->validate();
    }

    private function simpanPrestasi(array $data, User $user, ?Prestasi $record = null): void
    {
        $formData = Arr::only($data, [
            'sumber', 'kegiatan', 'nama_lomba', 'penyelenggara', 'lokasi',
            'dosen', 'tingkat', 'capaian', 'jenis', 'kategori',
            'tanggal_mulai', 'tanggal_selesai', 'bentuk', 'anggota', 'deskripsi',
        ]);
        if ($data['sumber'] !== 'mandiri' || $data['bentuk'] !== 'Tim') {
            unset($formData['anggota']);
        }

        $attributes = [
            'id_user' => $record?->id_user ?? $user->getKey(),
            'created_by' => $record?->created_by ?? $user->getKey(),
            'id_kegiatan' => $data['sumber'] === 'sebelumnya' ? $data['id_kegiatan'] : null,
            'capaian' => $data['capaian'],
            'jenis_prestasi' => $data['jenis'] === 'Akademik' ? 'akademik' : 'non-akademik',
            'bidang_lomba' => $data['kategori'] ?? null,
            'nama_lomba' => $data['nama_lomba'],
            'penyelenggara' => $data['penyelenggara'],
            'lokasi' => $data['lokasi'],
            'dosen_pembimbing' => $data['dosen'] ?? null,
            'tingkat_kejuaraan' => strtolower($data['tingkat']),
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'bentuk_partisipasi' => strtolower($data['bentuk']),
            'deskripsi' => $data['deskripsi'] ?? null,
            'form_data' => $formData,
        ];
        $dokumenDipertahankan = $data['dokumen_pertahankan'] ?? [];

        DB::transaction(function () use ($data, $attributes, $record, $dokumenDipertahankan) {
            if ($record) {
                $record->update($attributes);
                $record->buktiPrestasi()
                    ->whereNotIn('id_bukti_prestasi', $dokumenDipertahankan)
                    ->delete();
            } else {
                $record = Prestasi::create($attributes);
            }

            if ($data['sumber'] === 'mandiri' && $data['bentuk'] === 'Tim') {
                $daftarAnggota = collect($data['anggota'])
                    ->map(function (array $anggota) {
                        $idUser = DB::table('mahasiswa')
                            ->whereRaw('LOWER(nama) = ?', [mb_strtolower(trim($anggota['nama']))])
                            ->value('id_user');

                        return ['id_user' => (int) $idUser, 'peran' => 'anggota'];
                    })
                    ->push(['id_user' => (int) $record->id_user, 'peran' => 'ketua'])
                    ->all();

                AnggotaKegiatan::simpanDaftar($record, $daftarAnggota);
            } else {
                DB::table('anggota_kegiatan')->where('id_prestasi', $record->getKey())->delete();
            }

            foreach ($data['dokumen'] ?? [] as $dokumen) {
                if (blank($dokumen['url'] ?? null) || blank($dokumen['jenis'] ?? null)) {
                    continue;
                }

                $jenis = JenisDokumen::firstOrCreate([
                    'nama_jenis_dokumen' => $dokumen['jenis'],
                ]);
                $record->buktiPrestasi()->create([
                    'id_jenis_dokumen' => $jenis->getKey(),
                    'link' => $dokumen['url'],
                    'nama_file' => mb_substr(
                        basename(parse_url($dokumen['url'], PHP_URL_PATH) ?? '') ?: $dokumen['jenis'],
                        0,
                        255
                    ),
                ]);
            }
        });
    }
}
