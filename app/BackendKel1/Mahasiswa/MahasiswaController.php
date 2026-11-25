<?php

namespace App\BackendKel1\Mahasiswa;

use App\Http\Controllers\Controller;
use App\Models\TahunAkademik;
use App\Repositories\MahasiswaStore;
use App\Support\NimMahasiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MahasiswaController extends Controller
{
    private const STATUS_REGULER = [
        'Aktif',
        'Cuti',
        'Drop Out',
        'Meninggal Dunia',
    ];

    private const STATUS_TA_LULUS = [
        'Skripsi/TA',
        'Lulus',
    ];

    public function __construct(private MahasiswaStore $store) {}

    private function dataMahasiswa(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        $data = Arr::only($row, [
            'id', 'nim', 'nama', 'level', 'kelas', 'status', 'angkatan', 'email', 'foto',
        ]);

        $angkatan = isset($row['angkatan']) && is_numeric($row['angkatan']) ? (int) $row['angkatan'] : null;
        $data['semester_ke'] = $this->hitungSemester($angkatan);
        $data['allowed_statuses'] = $this->opsiStatusUntukMahasiswa($angkatan, $row['status'] ?? null);

        return $data;
    }

    private function hitungSemester(?int $angkatan): ?int
    {
        if (! $angkatan || $angkatan < 2000) {
            return null;
        }

        $taAktif = TahunAkademik::where('status', true)
            ->orderByDesc('id_tahun_akademik')
            ->value('tahun_akademik');

        if (is_string($taAktif) && preg_match('/(\d{4})\s*\/\s*\d{4}/', $taAktif, $m)) {
            $tahunAwal = (int) $m[1];
            $isGenap = stripos($taAktif, 'genap') !== false;
        } else {
            $bulan = (int) date('n');
            $tahun = (int) date('Y');
            $isGenap = $bulan < 7;
            $tahunAwal = $isGenap ? $tahun - 1 : $tahun;
        }

        return max(1, (($tahunAwal - $angkatan) * 2) + ($isGenap ? 2 : 1));
    }

    private function opsiStatusUntukMahasiswa(?int $angkatan, ?string $statusSaatIni = null): array
    {
        if ($statusSaatIni === 'Skripsi/TA') {
            return ['Skripsi/TA', 'Lulus'];
        }

        if ($statusSaatIni === 'Lulus') {
            return ['Lulus'];
        }

        $semester = $this->hitungSemester($angkatan);
        $bolehTaLulus = $semester !== null && $semester > 8;

        return $bolehTaLulus
            ? MahasiswaStore::STATUS
            : array_values(array_intersect(MahasiswaStore::STATUS, self::STATUS_REGULER));
    }

    /**
     * GET /api/v1/mahasiswa (Publik / lintas kelompok)
     */
    public function index(Request $request): JsonResponse
    {
        $rows = $this->store->all();

        $students = array_map(function ($r) {
            $angkatan = isset($r['angkatan']) && is_numeric($r['angkatan']) ? (int) $r['angkatan'] : null;

            return [
                'id' => $r['id'],
                'nim' => $r['nim'],
                'name' => $r['nama'],
                'level' => $r['level'] ?? 'Tingkat 1',
                'className' => $r['kelas'] ?? '-',
                'status' => $r['status'] ?? 'Aktif',
                'angkatan' => $r['angkatan'] ?? null,
                'semester_ke' => $this->hitungSemester($angkatan),
                'allowed_statuses' => $this->opsiStatusUntukMahasiswa($angkatan, $r['status'] ?? null),
            ];
        }, $rows);

        if ($request->filled('q')) {
            $q = mb_strtolower($request->query('q'));
            $students = array_filter($students, fn ($s) => str_contains(mb_strtolower($s['name']), $q) ||
                str_contains(mb_strtolower($s['nim']), $q)
            );
        }

        if ($request->filled('level') && $request->query('level') !== 'all') {
            $students = array_filter($students, fn ($s) => $s['level'] === $request->query('level'));
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $students = array_filter($students, fn ($s) => strcasecmp($s['status'], $request->query('status')) === 0);
        }

        return response()->json([
            'success' => true,
            'message' => 'Data mahasiswa berhasil diambil.',
            'meta' => [
                'total' => count($students),
            ],
            'data' => array_values($students),
        ], 200);
    }

    /**
     * GET /api/v1/mahasiswa/{id} (Publik / lintas kelompok)
     */
    public function show(string $id): JsonResponse
    {
        $row = $this->store->find($id);

        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan.',
            ], 404);
        }

        $fotoUrl = ! empty($row['foto']) ? Storage::disk('public')->url($row['foto']) : null;

        $academic = [
            ['Angkatan', $row['angkatan'] ?? '-'],
            ['Tingkat saat ini', $row['level'] ?? '-'],
            ['Kelas', $row['kelas'] ?? '-'],
            ['Email akun', $row['email'] ?? '-'],
        ];

        $history = $this->store->riwayatKelas($id) ?? [];
        $kegiatan = $this->store->kegiatan($id) ?? [];

        return response()->json([
            'success' => true,
            'message' => 'Detail mahasiswa berhasil diambil.',
            'data' => $this->dataMahasiswa($row),
            'fotoUrl' => $fotoUrl,
            'academic' => $academic,
            'history' => $history,
            'kegiatan' => $kegiatan,
        ], 200);
    }

    /**
     * POST /api/v1/mahasiswa (Khusus Admin)
     */
    public function store(Request $request): JsonResponse
    {
        $angkatan = $request->filled('angkatan') ? (int) $request->input('angkatan') : null;
        $opsiStatus = $this->opsiStatusUntukMahasiswa($angkatan);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'nim' => ['required', 'string', function ($attr, $value, $fail) use ($angkatan) {
                if ($pesan = NimMahasiswa::kesalahan((string) $value, $angkatan)) {
                    $fail($pesan);

                    return;
                }
                if ($this->store->usedByOther('nim', $value, '')) {
                    $fail('NIM sudah terdaftar.');
                }
            }],
            'angkatan' => ['required', 'integer', 'min:2000', 'max:2100'],
            'status' => ['nullable', Rule::in($opsiStatus)],
            'email' => ['required', 'email', 'max:150', function ($attr, $value, $fail) {
                if ($this->store->usedByOther('email', $value, '')) {
                    $fail('Email sudah dipakai akun lain.');
                }
            }],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'nim.required' => 'NIM wajib diisi.',
            'angkatan.required' => 'Angkatan wajib dipilih.',
            'status.in' => 'Status Skripsi/TA dan Lulus hanya berlaku untuk mahasiswa di atas semester 8.',
            'email.required' => 'Email akun wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $data['status'] = $data['status'] ?? 'Aktif';
        $row = $this->store->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Mahasiswa baru berhasil ditambahkan.',
            'data' => $this->dataMahasiswa($row),
        ], 201);
    }

    /**
     * PUT /api/v1/mahasiswa/{id} (Admin atau Mahasiswa pemilik profil)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $lama = $this->store->find($id);

        if (! $lama) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan.',
            ], 404);
        }

        if (($lama['status'] ?? '') === 'Lulus') {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa yang sudah berstatus Lulus tidak dapat diubah lagi.',
            ], 422);
        }

        $user = $request->user();
        $isAdmin = ($user->role->nama_role ?? $user->role ?? '') === 'admin'
            || (int) ($user->id_role ?? 0) === 1;

        $isPemilik = (isset($lama['id_user']) && (string) $lama['id_user'] === (string) $user->id)
            || (strcasecmp($lama['email'] ?? '', $user->email ?? '') === 0);

        if (! $isAdmin && ! $isPemilik) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki izin untuk mengubah data mahasiswa ini.',
            ], 403);
        }

        $angkatan = $request->filled('angkatan')
            ? (int) $request->input('angkatan')
            : (isset($lama['angkatan']) ? (int) $lama['angkatan'] : null);

        $opsiStatus = $this->opsiStatusUntukMahasiswa($angkatan, $lama['status'] ?? null);

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'nim' => [$isAdmin ? 'required' : 'nullable', 'string', function ($attr, $value, $fail) use ($id, $angkatan) {
                if (! $value) {
                    return;
                }
                if ($pesan = NimMahasiswa::kesalahan((string) $value, $angkatan)) {
                    $fail($pesan);

                    return;
                }
                if ($this->store->usedByOther('nim', $value, $id)) {
                    $fail('NIM sudah dipakai mahasiswa lain.');
                }
            }],
            'angkatan' => [$isAdmin ? 'required' : 'nullable', 'integer', 'min:2000', 'max:2100'],
            'status' => [$isAdmin ? 'required' : 'nullable', Rule::in($opsiStatus)],
            'email' => ['required', 'email', 'max:150', function ($attr, $value, $fail) use ($id) {
                if ($this->store->usedByOther('email', $value, $id)) {
                    $fail('Email sudah dipakai akun lain.');
                }
            }],
            'password' => ['nullable', 'string', 'min:8'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'hapus_foto' => ['nullable', 'boolean'],
        ], [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'nim.required' => 'NIM wajib diisi.',
            'angkatan.required' => 'Angkatan wajib dipilih.',
            'status.required' => 'Status akademik wajib dipilih.',
            'status.in' => 'Status Skripsi/TA dan Lulus hanya tersedia untuk mahasiswa di atas semester 8.',
            'email.required' => 'Email akun wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        $payloadUpdate = [
            'nama' => $data['nama'],
            'nim' => $isAdmin ? $data['nim'] : $lama['nim'],
            'angkatan' => $isAdmin ? (string) $data['angkatan'] : $lama['angkatan'],
            'status' => $isAdmin ? $data['status'] : $lama['status'],
            'email' => $data['email'],
            'tanggal_lahir' => $lama['tanggal_lahir'] ?? null,
            'telepon' => $lama['telepon'] ?? null,
            'password' => $data['password'] ?? null,
        ];

        if ($request->hasFile('foto')) {
            if (! empty($lama['foto'])) {
                Storage::disk('public')->delete($lama['foto']);
            }
            $payloadUpdate['foto'] = $request->file('foto')->store('mahasiswa-foto', 'public');
        } elseif ($request->boolean('hapus_foto')) {
            if (! empty($lama['foto'])) {
                Storage::disk('public')->delete($lama['foto']);
            }
            $payloadUpdate['foto'] = null;
        }

        $updated = $this->store->update($id, $payloadUpdate);

        return response()->json([
            'success' => true,
            'message' => 'Perubahan data mahasiswa berhasil disimpan.',
            'data' => $this->dataMahasiswa($updated ?? $this->store->find($id)),
        ], 200);
    }

    /**
     * PATCH /api/v1/mahasiswa/{id}/status (Khusus Admin)
     */
    public function toggleStatus(Request $request, string $id): JsonResponse
    {
        $lama = $this->store->find($id);

        if (! $lama) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan.',
            ], 404);
        }

        if (($lama['status'] ?? '') === 'Lulus') {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa yang sudah berstatus Lulus tidak dapat diubah lagi.',
            ], 422);
        }

        $angkatan = isset($lama['angkatan']) && is_numeric($lama['angkatan']) ? (int) $lama['angkatan'] : null;
        $opsiStatus = $this->opsiStatusUntukMahasiswa($angkatan, $lama['status'] ?? null);

        if ($request->filled('status')) {
            $validated = $request->validate([
                'status' => ['required', Rule::in($opsiStatus)],
            ], [
                'status.in' => 'Status Skripsi/TA dan Lulus hanya tersedia untuk mahasiswa di atas semester 8.',
            ]);
            $statusBaru = $validated['status'];
        } else {
            $statusBaru = strcasecmp($lama['status'] ?? 'Aktif', 'Aktif') === 0 ? 'Cuti' : 'Aktif';
        }

        $updated = $this->store->update($id, [
            'nama' => $lama['nama'],
            'nim' => $lama['nim'],
            'tanggal_lahir' => $lama['tanggal_lahir'] ?? null,
            'telepon' => $lama['telepon'] ?? null,
            'angkatan' => $lama['angkatan'],
            'status' => $statusBaru,
            'email' => $lama['email'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Status mahasiswa berhasil diubah menjadi {$statusBaru}.",
            'data' => $this->dataMahasiswa($updated ?? $this->store->find($id)),
        ], 200);
    }

    /**
     * POST /api/v1/mahasiswa/sinkronisasi-kelas (Khusus Admin)
     */
    public function sinkronisasiKelas(): JsonResponse
    {
        try {
            $ringkasan = $this->store->sinkronisasiKelas();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        if ($ringkasan === null) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada tahun akademik yang berstatus aktif. Aktifkan tahun akademik terlebih dahulu.',
            ], 422);
        }

        $ta = $ringkasan['tahun_akademik'] ?? 'aktif';
        $dibuat = $ringkasan['dibuat'] ?? 0;
        $sudahAda = $ringkasan['sudah-ada'] ?? 0;
        $skripsiTa = $ringkasan['skripsi-ta'] ?? 0;

        return response()->json([
            'success' => true,
            'message' => "Sinkronisasi kelas ({$ta}) selesai: {$dibuat} ditempatkan, {$sudahAda} sudah terdaftar, {$skripsiTa} masuk status Skripsi/TA.",
            'data' => $ringkasan,
        ], 200);
    }
}
