<?php

namespace App\BackendKel3\Pengajaran;

use App\Http\Controllers\Controller;
use App\Models\Dokumen;
use App\Models\EnrollmentKelasMataKuliah;
use App\Models\EnrollmentMataKuliah;
use App\Models\EnrollmentTingkatKelas;
use App\Models\JenisDokumen;
use App\Models\Pengajaran;
use App\Models\TahunAkademik;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DosenPengajaranController extends Controller
{
    private const RELASI = [
        'dosen',
        'enrollmentMataKuliah.mataKuliah',
        'enrollmentMataKuliah.tahunAkademik',
        'enrollmentTingkatKelas.prodi',
        'enrollmentTingkatKelas.tingkat',
        'enrollmentTingkatKelas.kelas',
        'pengajaran.dokumen.jenisDokumen',
    ];

    // =================================================================
    // FUNGSI API UTAMA
    // =================================================================

    public function index(Request $request)
    {
        $dosen = $request->user()?->dosen;

        if (! $dosen) {
            return response()->json(['message' => 'Akun ini bukan akun dosen atau belum login.'], 403);
        }

        $pengajaran = EnrollmentKelasMataKuliah::with(self::RELASI)
            ->where('id_dosen', $dosen->id_dosen)
            ->get()
            ->map(fn (EnrollmentKelasMataKuliah $item) => $this->baris($item))
            ->sortByDesc('tahun')
            ->values()
            ->all();

        $kolom = collect($pengajaran);

        return response()->json([
            'status' => 'success',
            'data' => [
                'pengajaran' => $pengajaran,
                'daftarKelas' => $kolom->pluck('kelas')->unique()->sort()->values()->all(),
                'daftarTahun' => $kolom->pluck('tahun')->unique()->sortDesc()->values()->all(),
                'daftarSemester' => ['Ganjil', 'Genap'],
            ],
        ]);
    }

    public function opsiFormApi(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->opsiForm() + ['dosen' => $request->user()?->dosen?->nama],
        ]);
    }

    public function store(Request $request)
    {
        $dosen = $this->dosenLogin($request);

        try {
            $data = $this->validasi($request, (int) $dosen->id_dosen);

            $item = DB::transaction(function () use ($data, $dosen) {
                $created = EnrollmentKelasMataKuliah::create([
                    'id_enrollment_tingkat_kelas' => $data['id_enrollment_tingkat_kelas'],
                    'id_enrollment_mk' => $data['id_enrollment_mk'],
                    'id_dosen' => $dosen->id_dosen,
                ]);

                $this->simpanDokumen($created, $data['dokumen'] ?? []);

                return $created;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Pengajaran berhasil disimpan.',
                'data' => $item->load(self::RELASI),
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function show(Request $request, int $id)
    {
        try {
            $item = $this->milikDosen($request, $id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'detail' => $this->detail($item),
                    'dokumen' => $this->dokumen($item),
                    'form' => [
                        'tahun' => (string) $item->enrollmentTingkatKelas->id_tahun_akademik,
                        'prodi' => $item->enrollmentTingkatKelas->prodi->jenjang.' '.$item->enrollmentTingkatKelas->prodi->nama_prodi,
                        'rombel' => (string) $item->id_enrollment_tingkat_kelas,
                        'matkul' => (string) $item->id_enrollment_mk,
                        'dokumen' => $this->dokumen($item),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Data tidak ditemukan atau bukan milik Anda.'], 404);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $item = $this->milikDosen($request, $id);
            $data = $this->validasi($request, (int) $item->id_dosen, $item);

            DB::transaction(function () use ($item, $data, $request) {
                $item->update([
                    'id_enrollment_tingkat_kelas' => $data['id_enrollment_tingkat_kelas'],
                    'id_enrollment_mk' => $data['id_enrollment_mk'],
                ]);

                if ($request->has('dokumen')) {
                    $item->pengajaran()->delete();
                    $this->simpanDokumen($item, $data['dokumen'] ?? []);
                }
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Perubahan pengajaran berhasil disimpan.',
                'data' => $item->fresh(self::RELASI),
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Data tidak ditemukan atau bukan milik Anda.'], 404);
        }
    }

    // =================================================================
    // FUNGSI HELPER
    // =================================================================

    private function dosenLogin(Request $request)
    {
        $dosen = $request->user()?->dosen;
        if (! $dosen) {
            abort(response()->json(['message' => 'Akun ini bukan akun dosen.'], 403));
        }

        return $dosen;
    }

    private function milikDosen(Request $request, int $id): EnrollmentKelasMataKuliah
    {
        $dosen = $request->user()?->dosen;
        if (! $dosen) {
            abort(response()->json(['message' => 'Akun ini bukan akun dosen.'], 403));
        }

        return EnrollmentKelasMataKuliah::with(self::RELASI)
            ->where('id_dosen', $dosen->id_dosen)
            ->findOrFail($id);
    }

    private function validasi(Request $request, int $idDosen, ?EnrollmentKelasMataKuliah $ubah = null): array
    {
        $data = $request->validate([
            'id_enrollment_tingkat_kelas' => ['required', 'integer', 'exists:enrollment_tingkat_kelas,id_enrollment_tingkat_kelas'],
            'id_enrollment_mk' => ['required', 'integer', 'exists:enrollment_mata_kuliah,id_enrollment_mk'],
            'dokumen' => ['nullable', 'array'],
            'dokumen.*.nama' => ['required', 'string', 'max:255'],
            'dokumen.*.id_jenis_dokumen' => ['required', 'integer', 'exists:jenis_dokumen,id_jenis_dokumen'],
            'dokumen.*.tautan' => ['required', 'url:http,https', 'max:2048'],
        ], [
            'id_enrollment_tingkat_kelas.required' => 'Kelas wajib dipilih.',
            'id_enrollment_tingkat_kelas.exists' => 'Kelas yang dipilih tidak ditemukan.',
            'id_enrollment_mk.required' => 'Mata kuliah wajib dipilih.',
            'id_enrollment_mk.exists' => 'Mata kuliah yang dipilih tidak ditemukan.',
            'dokumen.*.nama.required' => 'Nama dokumen wajib diisi.',
            'dokumen.*.id_jenis_dokumen.required' => 'Jenis dokumen wajib dipilih.',
            'dokumen.*.id_jenis_dokumen.exists' => 'Jenis dokumen tidak valid.',
            'dokumen.*.tautan.required' => 'Tautan URL dokumen wajib diisi.',
            'dokumen.*.tautan.url' => 'Tautan dokumen harus berupa URL valid (http/https).',
        ]);

        $rombel = EnrollmentTingkatKelas::findOrFail($data['id_enrollment_tingkat_kelas']);
        $mataKuliah = EnrollmentMataKuliah::findOrFail($data['id_enrollment_mk']);

        if ((int) $rombel->id_tahun_akademik !== (int) $mataKuliah->id_tahun_akademik) {
            throw ValidationException::withMessages([
                'id_enrollment_mk' => 'Mata kuliah ini tidak ditawarkan pada tahun akademik kelas yang dipilih.',
            ]);
        }

        $sudahAda = EnrollmentKelasMataKuliah::query()
            ->where('id_enrollment_tingkat_kelas', $rombel->getKey())
            ->where('id_enrollment_mk', $mataKuliah->getKey())
            ->when($ubah, fn ($q) => $q->whereKeyNot($ubah->getKey()))
            ->first();

        if ($sudahAda) {
            throw ValidationException::withMessages([
                'id_enrollment_mk' => (int) $sudahAda->id_dosen === $idDosen
                    ? 'Anda sudah mengampu mata kuliah ini di kelas tersebut.'
                    : 'Mata kuliah ini di kelas tersebut sudah diampu dosen lain.',
            ]);
        }

        return $data;
    }

    private function simpanDokumen(EnrollmentKelasMataKuliah $kelasMk, array $daftarDokumen): void
    {
        foreach ($daftarDokumen as $doc) {
            $dokumen = Dokumen::create([
                'id_jenis_dokumen' => $doc['id_jenis_dokumen'],
                'nama_file' => $doc['nama'],
                'tipe_dokumen' => 'tautan',
                'link_dokumen' => $doc['tautan'],
                'is_lampiran' => false,
            ]);

            Pengajaran::create([
                'id_enrollment_kelas_mk' => $kelasMk->getKey(),
                'id_dokumen' => $dokumen->getKey(),
            ]);
        }
    }

    private function opsiForm(): array
    {
        return [
            'tahun' => TahunAkademik::orderByDesc('status')->orderByDesc('id_tahun_akademik')->get()
                ->map(fn (TahunAkademik $t) => [
                    'id' => $t->id_tahun_akademik,
                    'label' => $t->tahun_akademik,
                    'semester' => $this->pecahTahun($t->tahun_akademik)['semester'],
                ])->all(),
            'rombel' => EnrollmentTingkatKelas::with(['prodi', 'tingkat', 'kelas'])->get()
                ->map(fn (EnrollmentTingkatKelas $r) => [
                    'id' => $r->id_enrollment_tingkat_kelas,
                    'ta' => $r->id_tahun_akademik,
                    'prodi' => $r->prodi->jenjang.' '.$r->prodi->nama_prodi,
                    'kelas' => $r->tingkat->tingkat.$r->kelas->jenis_kelas,
                ])->sortBy('kelas')->values()->all(),
            'matkul' => EnrollmentMataKuliah::with('mataKuliah')->get()
                ->map(fn (EnrollmentMataKuliah $m) => [
                    'id' => $m->id_enrollment_mk,
                    'ta' => $m->id_tahun_akademik,
                    'label' => $m->mataKuliah->nama_mk.' ('.$m->mataKuliah->kode_mk.')',
                ])->sortBy('label')->values()->all(),
            'jenis_dokumen' => JenisDokumen::orderBy('nama_jenis_dokumen')->get()
                ->map(fn (JenisDokumen $j) => [
                    'id' => $j->getKey(),
                    'nama' => $j->nama_jenis_dokumen,
                ])->values()->all(),
        ];
    }

    private function baris(EnrollmentKelasMataKuliah $item): array
    {
        $ta = $this->pecahTahun($item->enrollmentMataKuliah->tahunAkademik->tahun_akademik);

        return [
            'id' => $item->getKey(),
            'matkul' => $item->enrollmentMataKuliah->mataKuliah->nama_mk,
            'kelas' => $this->namaKelas($item),
            'tahun' => $ta['tahun'],
            'semester' => $ta['semester'],
        ];
    }

    private function detail(EnrollmentKelasMataKuliah $item): array
    {
        $mk = $item->enrollmentMataKuliah->mataKuliah;
        $ta = $item->enrollmentMataKuliah->tahunAkademik->tahun_akademik;
        $pecah = $this->pecahTahun($ta);

        return [
            'tahun_akademik' => $pecah['tahun'].' '.$pecah['semester'],
            'tahun' => $pecah['tahun'],
            'semester' => $pecah['semester'],
            'prodi' => $item->enrollmentTingkatKelas->prodi->nama_prodi,
            'kelas' => 'Kelas '.$this->namaKelas($item),
            'matkul' => $mk->nama_mk,
            'dosen' => $item->dosen->nama.' (akun Anda)',
            'kode' => $mk->kode_mk,
            'sks' => $mk->sks_teori + $mk->sks_praktik,
            'dosen_lengkap' => $item->dosen->nama,
        ];
    }

    private function dokumen(EnrollmentKelasMataKuliah $item): array
    {
        return $item->pengajaran
            ->filter(fn ($p) => $p->dokumen !== null)
            ->map(fn ($p) => [
                'id' => $p->dokumen->getKey(),
                'id_jenis_dokumen' => (string) $p->dokumen->id_jenis_dokumen,
                'nama' => $p->dokumen->nama_file,
                'jenis' => $p->dokumen->jenisDokumen?->nama_jenis_dokumen ?? '-',
                'keterangan' => '',
                'tautan' => $p->dokumen->link_dokumen,
            ])->values()->all();
    }

    private function namaKelas(EnrollmentKelasMataKuliah $item): string
    {
        return $item->enrollmentTingkatKelas->tingkat->tingkat.$item->enrollmentTingkatKelas->kelas->jenis_kelas;
    }

    private function pecahTahun(string $tahunAkademik): array
    {
        preg_match('~\d{4}\s*/\s*\d{4}~', $tahunAkademik, $tahun);
        preg_match('~ganjil|genap~i', $tahunAkademik, $semester);

        return [
            'tahun' => isset($tahun[0]) ? preg_replace('~\s+~', '', $tahun[0]) : $tahunAkademik,
            'semester' => isset($semester[0]) ? ucfirst(strtolower($semester[0])) : '-',
        ];
    }
}