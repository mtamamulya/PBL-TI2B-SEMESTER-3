<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\DosenStore;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DosenController extends Controller
{
    public function __construct(private DosenStore $store) {}

    /**
     * POST /api/v1/dosen
     * Menyimpan dosen baru (mendukung multipart/form-data untuk upload foto).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'nip' => ['required', 'regex:/^\d{8,20}$/', function ($attr, $value, $fail) {
                if ($this->store->exists('nip', $value)) {
                    $fail('NIP sudah terdaftar.');
                }
            }],
            'status_dosen' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'email' => ['required', 'email', 'max:150', function ($attr, $value, $fail) {
                if ($this->store->exists('email', $value)) {
                    $fail('Email sudah dipakai akun lain.');
                }
            }],
            'role' => ['required', Rule::in(array_keys(DosenStore::ROLE))],
            'password' => ['required', 'string', 'min:8'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'pangkat' => ['required', Rule::in($this->store->opsiPangkat())],
            'golongan' => ['required', Rule::in($this->store->opsiGolongan())],
            'nomor_sk' => ['required', 'string', 'max:100'],
            'tanggal_sk' => ['required', 'date'],
        ], [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'nip.required' => 'NIP wajib diisi.',
            'nip.regex' => 'NIP harus berupa angka 8–20 digit.',
            'email.required' => 'Email akun wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Password wajib diisi.',
            'password.min' => 'Password minimal 8 karakter.',
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'Foto harus JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 2 MB.',
            'pangkat.required' => 'Pangkat wajib dipilih.',
            'golongan.required' => 'Golongan wajib dipilih.',
            'nomor_sk.required' => 'Nomor SK wajib diisi.',
            'tanggal_sk.required' => 'Tanggal SK wajib diisi.',
            'tanggal_sk.date' => 'Tanggal SK tidak valid.',
        ]);

        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('foto-dosen', 'public');
        }

        $row = $this->store->create([
            'nama'     => $data['nama_lengkap'],
            'nip'      => $data['nip'],
            'status'   => $data['status_dosen'],
            'email'    => $data['email'],
            'role'     => $data['role'],
            'password' => $data['password'],
            'foto'     => $fotoPath,
            'pangkat'  => $data['pangkat'],
            'golongan' => $data['golongan'],
            'sk'       => $data['nomor_sk'],
            'tanggal'  => $data['tanggal_sk'],
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Dosen baru berhasil ditambahkan.',
            'data'    => $row,
        ], 201);
    }

    /**
     * GET /api/dosen
     * Query daftar dosen + opsi filter pangkat dalam format JSON.
     */
    public function index(Request $request): JsonResponse
    {
        $semuaDosen = $this->store->all();

        // Mapping kolom yang aman untuk dikonsumsi API (tanpa hash password)
        $dosen = array_map(fn ($r) => [
            'id'       => $r['id'],
            'nip'      => $r['nip'],
            'nama'     => $r['nama'],
            'jabatan'  => $r['jabatan'],
            'golongan' => $r['golongan'],
            'status'   => $r['status'],
        ], $semuaDosen);

        // Opsional: dukung filter langsung lewat query string API (?q=...&jabatan=...&status=...)
        if ($request->filled('q')) {
            $q = mb_strtolower($request->query('q'));
            $dosen = array_filter($dosen, fn ($d) =>
                str_contains(mb_strtolower($d['nama']), $q) ||
                str_contains(mb_strtolower($d['nip']), $q)
            );
        }

        if ($request->filled('jabatan') && $request->query('jabatan') !== 'all') {
            $dosen = array_filter($dosen, fn ($d) => $d['jabatan'] === $request->query('jabatan'));
        }

        if ($request->filled('status') && $request->query('status') !== 'all') {
            $dosen = array_filter($dosen, fn ($d) => $d['status'] === $request->query('status'));
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Data dosen berhasil diambil.',
            'meta'    => [
                'total'        => count($dosen),
                'opsi_pangkat' => $this->store->opsiPangkat(),
            ],
            'data'    => array_values($dosen),
        ], 200);
    }

    /**
     * GET /api/dosen/{id}
     * Query detail satu dosen beserta riwayat pangkat dan aktivitasnya.
     */
    public function show(string $id): JsonResponse
    {
        $row = $this->store->find($id);

        if (! $row) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data dosen tidak ditemukan.',
            ], 404);
        }

        $riwayat = [];
        foreach ($row['riwayat'] as $i => $r) {
            $riwayat[] = [
                'pangkat'           => $r['pangkat'],
                'golongan'          => $r['golongan'],
                'sk'                => $r['sk'],
                'tanggal'           => $r['tanggal'],
                'tanggal_formatted' => Carbon::parse($r['tanggal'])->locale('id')->translatedFormat('j F Y'),
                'now'               => $i === 0,
            ];
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail dosen berhasil diambil.',
            'data'    => [
                'profil' => [
                    'id'          => $row['id'],
                    'nama'        => $row['nama'],
                    'nip'         => $row['nip'],
                    'email'       => $row['email'],
                    'role'        => DosenStore::ROLE[$row['role']] ?? 'Dosen',
                    'jabatan'     => $row['jabatan'],
                    'golongan'    => $row['golongan'],
                    'status'      => $row['status'],
                    'status_akun' => $row['status_akun'],
                    'foto'        => ! empty($row['foto']) ? Storage::disk('public')->url($row['foto']) : null,
                ],
                'riwayat'   => $riwayat,
                'aktivitas' => $this->aktivitas($row),
            ],
        ], 200);
    }

    /**
     * PUT / PATCH / POST (dengan _method=PUT) /api/v1/dosen/{id}
     * Memperbarui data dosen, akun login, foto profil, serta riwayat pangkat baru (opsional).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $lama = $this->store->find($id);

        if (! $lama) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data dosen tidak ditemukan.',
            ], 404);
        }

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:100'],
            'nip' => ['required', 'regex:/^\d{8,20}$/', function ($attr, $value, $fail) use ($id) {
                if ($this->store->usedByOther('nip', $value, $id)) {
                    $fail('NIP sudah dipakai dosen lain.');
                }
            }],
            'status_dosen' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'email' => ['required', 'email', 'max:150', function ($attr, $value, $fail) use ($id) {
                if ($this->store->usedByOther('email', $value, $id)) {
                    $fail('Email sudah dipakai akun lain.');
                }
            }],
            'role' => ['required', Rule::in(array_keys(DosenStore::ROLE))],
            'status_akun' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'password' => ['nullable', 'string', 'min:8'],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            // Riwayat pangkat baru: opsional, tetapi kalau diisi salah satu wajib lengkap semua
            'pangkat' => ['nullable', 'required_with:golongan,nomor_sk,tanggal_sk', Rule::in($this->store->opsiPangkat())],
            'golongan' => ['nullable', 'required_with:pangkat,nomor_sk,tanggal_sk', Rule::in($this->store->opsiGolongan())],
            'nomor_sk' => ['nullable', 'required_with:pangkat,golongan,tanggal_sk', 'string', 'max:100'],
            'tanggal_sk' => ['nullable', 'required_with:pangkat,golongan,nomor_sk', 'date'],
        ], [
            'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
            'nip.required' => 'NIP wajib diisi.',
            'nip.regex' => 'NIP harus berupa angka 8–20 digit.',
            'email.required' => 'Email akun wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'foto.image' => 'File harus berupa gambar.',
            'foto.mimes' => 'Foto harus JPG, PNG, atau WebP.',
            'foto.max' => 'Ukuran foto maksimal 2 MB.',
            'pangkat.required_with' => 'Pangkat wajib diisi bila menambah riwayat.',
            'golongan.required_with' => 'Golongan wajib diisi bila menambah riwayat.',
            'nomor_sk.required_with' => 'Nomor SK wajib diisi bila menambah riwayat.',
            'tanggal_sk.required_with' => 'Tanggal SK wajib diisi bila menambah riwayat.',
            'tanggal_sk.date' => 'Tanggal SK tidak valid.',
        ]);

        $riwayatBaru = ! empty($data['pangkat'])
            ? [
                'pangkat'  => $data['pangkat'],
                'golongan' => $data['golongan'],
                'sk'       => $data['nomor_sk'],
                'tanggal'  => $data['tanggal_sk'],
            ]
            : null;

        // Jika upload foto baru: simpan lalu hapus foto lama
        $fotoPath = null;
        if ($request->hasFile('foto')) {
            $fotoPath = $request->file('foto')->store('foto-dosen', 'public');
            if (! empty($lama['foto'])) {
                Storage::disk('public')->delete($lama['foto']);
            }
        }

        $updated = $this->store->update($id, [
            'nama'        => $data['nama_lengkap'],
            'nip'         => $data['nip'],
            'status'      => $data['status_dosen'],
            'email'       => $data['email'],
            'role'        => $data['role'],
            'status_akun' => $data['status_akun'],
            'password'    => $data['password'] ?? null,
            'foto'        => $fotoPath,
        ], $riwayatBaru);

        return response()->json([
            'status'  => 'success',
            'message' => 'Perubahan data dosen berhasil disimpan.',
            'data'    => $updated ?? $this->store->find($id),
        ], 200);
    }

    private function aktivitas(array $row): array
    {
        $strukturKosong = [
            'penelitian' => ['label' => 'Penelitian', 'items' => []],
            'pengabdian' => ['label' => 'Pengabdian', 'items' => []],
            'pengajaran' => ['label' => 'Pengajaran', 'items' => []],
            'kegiatan'   => ['label' => 'Kegiatan', 'items' => []],
        ];

        if (config('admin.data_source', 'json') === 'database') {
            return $strukturKosong;
        }

        if ((int) $row['id'] > 100) {
            return $strukturKosong;
        }

        return [
            'penelitian' => ['label' => 'Penelitian', 'items' => [
                ['title' => 'Deteksi hoaks dengan machine learning', 'year' => '2026', 'type' => 'Penelitian Terapan', 'role' => 'Ketua'],
                ['title' => 'Analisis sentimen ulasan aplikasi', 'year' => '2025', 'type' => 'Penelitian Dasar', 'role' => 'Anggota'],
                ['title' => 'Sistem rekomendasi mata kuliah', 'year' => '2025', 'type' => 'Penelitian Pengembangan', 'role' => 'Anggota'],
            ]],
            'pengabdian' => ['label' => 'Pengabdian', 'items' => [
                ['title' => 'Pelatihan sensor sederhana pada sistem terbenam', 'year' => '2025', 'type' => 'Pengabdian kepada Masyarakat', 'role' => 'Ketua'],
                ['title' => 'Pendampingan digitalisasi UMKM', 'year' => '2024', 'type' => 'Pengabdian kepada Masyarakat', 'role' => 'Anggota'],
            ]],
            'pengajaran' => ['label' => 'Pengajaran', 'items' => [
                ['title' => 'Pemrograman Web Lanjut', 'year' => '2026', 'type' => 'Semester Ganjil · 3 SKS', 'role' => 'Pengampu'],
                ['title' => 'Basis Data II', 'year' => '2026', 'type' => 'Semester Ganjil · 3 SKS', 'role' => 'Pengampu'],
                ['title' => 'Jaringan Komputer', 'year' => '2025', 'type' => 'Semester Genap · 3 SKS', 'role' => 'Koordinator'],
            ]],
            'kegiatan' => ['label' => 'Kegiatan', 'items' => [
                ['title' => 'Seminar Nasional AI', 'year' => '2026', 'type' => 'Seminar', 'role' => 'Pemateri'],
                ['title' => 'Pelatihan Industri', 'year' => '2025', 'type' => 'Pelatihan', 'role' => 'Peserta'],
                ['title' => 'Kunjungan Industri', 'year' => '2025', 'type' => 'Kunjungan Industri', 'role' => 'Pendamping'],
            ]],
        ];
    }
}