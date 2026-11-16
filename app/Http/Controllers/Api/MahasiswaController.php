<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mahasiswa;
use App\Repositories\MahasiswaStore;
use App\Repositories\PenempatanKelasMahasiswa;
use App\Support\NimMahasiswa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;

class MahasiswaController extends Controller
{
    public function __construct(private MahasiswaStore $store) {}

    /** GET /api/v1/mahasiswa */
    public function index(): JsonResponse
    {
        $students = array_map(fn ($r) => [
            'id' => $r['id'],
            'nim' => $r['nim'],
            'name' => $r['nama'],
            'nama' => $r['nama'],
            'level' => $r['level'],
            'className' => $r['kelas'],
            'kelas' => $r['kelas'],
            'status' => $r['status'],
        ], $this->store->all());

        return response()->json([
            'success' => true,
            'message' => 'Daftar data mahasiswa berhasil diambil.',
            'levels' => MahasiswaStore::TINGKAT,
            'data' => $students,
        ], 200);
    }

    /** GET /api/v1/mahasiswa/{id} */
    public function show(string $id): JsonResponse
    {
        $row = $this->store->find($id);

        if (! $row) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan.',
            ], 404);
        }

        $mhsModel = Mahasiswa::with('user')->find($id);
        $fotoProfil = $mhsModel && $mhsModel->user ? $mhsModel->user->foto_profil : ($row['foto'] ?? null);

        $mahasiswa = [
            'id' => $row['id'],
            'nama' => $row['nama'],
            'nim' => $row['nim'],
            'angkatan' => $row['angkatan'],
            'email' => $row['email'],
            'status' => $row['status'],
            'foto' => $fotoProfil,
        ];

        $academic = [
            ['Angkatan', $row['angkatan']],
            ['Program studi', config('admin.prodi.nama', 'Teknologi Rekayasa Komputer').', '.config('admin.prodi.jenjang', 'D4')],
            ['Tingkat saat ini', $row['level']],
            ['Kelas saat ini', Str::after($row['kelas'], 'TI-')],
        ];

        $identity = [
            ['Email akun', $row['email']],
            ['Akun login', $row['status'] === 'Non-aktif' ? 'Nonaktif' : 'Aktif'],
        ];

        $history = $this->store->riwayatKelas($id) ?? [];
        $kegiatan = $this->store->kegiatan($id) ?? [];

        return response()->json([
            'success' => true,
            'message' => 'Detail data mahasiswa berhasil diambil.',
            'data' => $mahasiswa,
            'academic' => $academic,
            'identity' => $identity,
            'history' => $history,
            'kegiatan' => $kegiatan,
            'fotoUrl' => ! empty($fotoProfil) ? Storage::disk('public')->url($fotoProfil) : null,
        ], 200);
    }

    /** POST /api/v1/mahasiswa */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'nim' => $this->aturanNim($request, ''),
            'angkatan' => ['required', 'integer', 'between:2000,'.((int) date('Y') + 1)],
            'status' => ['required', Rule::in($this->store->opsiStatus())],
            'email' => ['required', 'email', 'max:150', 'ends_with:@mhs.polines.ac.id', function ($attr, $value, $fail) {
                if ($this->store->usedByOther('email', $value, '')) {
                    $fail('Email sudah dipakai akun lain.');
                }
            }],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'nim.required' => 'NIM wajib diisi.',
            'angkatan.required' => 'Angkatan wajib dipilih.',
            'email.required' => 'Email akun wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.ends_with' => 'Email harus menggunakan domain institusi @mhs.polines.ac.id.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
        ]);

        $row = $this->store->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Mahasiswa baru berhasil ditambahkan.',
            'data' => $row,
        ], 201);
    }

    /** PUT /api/v1/mahasiswa/{id} */
    public function update(Request $request, string $id): JsonResponse
    {
        $lama = $this->store->find($id);

        if (! $lama) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan.',
            ], 404);
        }

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'nim' => $this->aturanNim($request, $id),
            'angkatan' => ['required', 'integer', 'between:2000,'.((int) date('Y') + 1)],
            'status' => ['required', Rule::in($this->store->opsiStatus())],
            'email' => ['required', 'email', 'max:150', 'ends_with:@mhs.polines.ac.id', function ($attr, $value, $fail) use ($id) {
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
            'email.required' => 'Email akun wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.ends_with' => 'Email harus menggunakan domain institusi @mhs.polines.ac.id.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'Foto harus berformat JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 2 MB.',
        ]);

        $fotoLama = $lama['foto'] ?? null;
        unset($data['foto'], $data['hapus_foto']);

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('mahasiswa-foto', 'public');
            if ($fotoLama) {
                Storage::disk('public')->delete($fotoLama);
            }
        } elseif ($request->boolean('hapus_foto')) {
            $data['foto'] = null;
            if ($fotoLama) {
                Storage::disk('public')->delete($fotoLama);
            }
        }

        $this->store->update($id, $data);

        return response()->json([
            'success' => true,
            'message' => 'Perubahan data mahasiswa berhasil disimpan.',
            'data' => $this->store->find($id),
        ], 200);
    }

    /** PATCH /api/v1/mahasiswa/{id}/status */
    public function toggleStatus(string $id): JsonResponse
    {
        $success = $this->store->toggleStatus($id);

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Data mahasiswa tidak ditemukan.',
            ], 404);
        }

        $terbaru = $this->store->find($id);

        return response()->json([
            'success' => true,
            'message' => 'Status mahasiswa berhasil diperbarui.',
            'data' => [
                'id' => $id,
                'status' => $terbaru['status'] ?? null,
            ],
        ], 200);
    }

    /** POST /api/v1/mahasiswa/sinkronisasi-kelas */
    public function buatKelasSemester(PenempatanKelasMahasiswa $penempatan): JsonResponse
    {
        $ta = $penempatan->tahunAkademikAktif();
        if (! $ta) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada tahun akademik yang aktif.',
            ], 422);
        }

        try {
            $r = $penempatan->buatUntukSemester((int) $ta->id_tahun_akademik);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        $pesan = "Kelas {$ta->tahun_akademik}: {$r['dibuat']} mahasiswa ditempatkan, {$r['sudah-ada']} sudah punya kelas";
        foreach (['lulus' => 'sudah lulus', 'belum-mulai' => 'belum masuk', 'nim-tidak-valid' => 'dilewati karena format NIM salah'] as $kunci => $teks) {
            if ($r[$kunci] > 0) {
                $pesan .= ", {$r[$kunci]} {$teks}";
            }
        }

        return response()->json([
            'success' => true,
            'message' => $pesan.'.',
            'data' => $r,
        ], 200);
    }

    /** Validasi NIM format D4 TRK */
    private function aturanNim(Request $request, string $exceptId): array
    {
        $unik = function ($attr, $value, $fail) use ($exceptId) {
            if ($this->store->usedByOther('nim', $value, $exceptId)) {
                $fail($exceptId === '' ? 'NIM sudah terdaftar.' : 'NIM sudah dipakai mahasiswa lain.');
            }
        };

        return ['bail', 'required', function ($attr, $value, $fail) use ($request) {
            $angkatan = $request->filled('angkatan') ? (int) $request->input('angkatan') : null;
            if ($pesan = NimMahasiswa::kesalahan((string) $value, $angkatan)) {
                $fail($pesan);
            }
        }, $unik];
    }

    /** GET /api/v1/mahasiswa/opsi */
    public function opsi(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Opsi form mahasiswa berhasil diambil.',
            'data' => [
                'tahun' => range((int) date('Y'), (int) date('Y') - 9),
                'status' => $this->store->opsiStatus(),
            ],
        ], 200);
    }
}
