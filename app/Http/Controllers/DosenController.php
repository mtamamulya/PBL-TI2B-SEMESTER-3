<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DosenController extends Controller
{
    /**
     * Daftar dosen (memanggil GET /api/v1/dosen via ApiClient).
     */
    public function index()
    {
        $hasil = ApiClient::pengguna()->get('/dosen');

        return view('admin.dosen', [
            'dosen' => $hasil['data'] ?? [],
            'jabatan' => $hasil['meta']['opsi_pangkat'] ?? [],
        ]);
    }

    /**
     * Detail satu dosen (memanggil GET /api/v1/dosen/{id} via ApiClient).
     */
    public function show(string $id)
    {
        $hasil = ApiClient::pengguna()->get("/dosen/{$id}");
        $data = $hasil['data'] ?? [];
        $profil = $data['profil'] ?? [];

        $dosen = [
            'id' => $profil['id'] ?? $id,
            'nama' => $profil['nama'] ?? '-',
            'nip' => $profil['nip'] ?? '-',
            'status' => $profil['status'] ?? 'nonaktif',
            'foto' => $profil['foto'] ?? null,
        ];

        $info = [
            ['Email akun', $profil['email'] ?? '-'],
            ['Role', $profil['role'] ?? 'Dosen'],
            ['Pangkat / golongan saat ini', ($profil['jabatan'] ?? '-').', '.($profil['golongan'] ?? '-')],
            ['Akun login', ($profil['status_akun'] ?? '') === 'aktif' ? 'Aktif' : 'Nonaktif'],
        ];

        $riwayat = array_map(fn ($r) => [
            'pangkat' => $r['pangkat'],
            'golongan' => $r['golongan'],
            'sk' => $r['sk'],
            'tanggal' => $r['tanggal_formatted'] ?? $r['tanggal'],
            'now' => $r['now'] ?? false,
        ], $data['riwayat'] ?? []);

        return view('admin.dosen-detail', [
            'dosen' => $dosen,
            'info' => $info,
            'riwayat' => $riwayat,
            'aktivitas' => $data['aktivitas'] ?? [],
        ]);
    }

    /**
     * Form tambah dosen (opsi dropdown diambil dari meta GET /api/v1/dosen).
     */
    public function create()
    {
        $hasil = ApiClient::pengguna()->get('/dosen');
        $meta = $hasil['meta'] ?? [];

        return view('admin.dosen-tambah', [
            'jabatan' => $meta['opsi_pangkat'] ?? [],
            'golongan' => $meta['opsi_golongan'] ?? [],
            'roles' => $meta['roles'] ?? [],
        ]);
    }

    /**
     * Simpan dosen baru (memanggil POST /api/v1/dosen via ApiClient; error 422 otomatis kembali ke form).
     */
    public function store(Request $request): RedirectResponse
    {
        $payload = $request->only([
            'nama_lengkap', 'nip', 'status_dosen', 'email',
            'role', 'password', 'pangkat', 'golongan', 'nomor_sk', 'tanggal_sk',
        ]);

        $files = $request->hasFile('foto') ? ['foto' => $request->file('foto')] : [];

        $hasil = ApiClient::pengguna()->post('/dosen', $payload, $files);
        $idBaru = $hasil['data']['id'] ?? null;

        return redirect()
            ->route($idBaru ? 'admin.dosen.show' : 'admin.dosen', $idBaru)
            ->with('status', 'Dosen baru berhasil ditambahkan.');
    }

    /**
     * Form edit dosen (GET /admin/dosen/{id}/edit).
     */
    public function edit(string $id)
    {
        $detail = ApiClient::pengguna()->get("/dosen/{$id}");
        $list = ApiClient::pengguna()->get('/dosen');

        $profil = $detail['data']['profil'] ?? [];
        $meta = $list['meta'] ?? [];

        return view('admin.dosen-edit', [
            'dosen' => [
                'id' => $profil['id'] ?? $id,
                'nama_lengkap' => $profil['nama'] ?? '',
                'nip' => $profil['nip'] ?? '',
                'status_dosen' => $profil['status'] ?? 'aktif',
                'email' => $profil['email'] ?? '',
                'role' => $profil['role_key'] ?? 'dosen',
                'status_akun' => $profil['status_akun'] ?? 'aktif',
                'foto' => $profil['foto'] ?? null,
                'pangkat' => '',
                'golongan' => '',
                'nomor_sk' => '',
                'tanggal_sk' => '',
            ],
            'jabatan' => $meta['opsi_pangkat'] ?? [],
            'golongan' => $meta['opsi_golongan'] ?? [],
            'roles' => $meta['roles'] ?? [],
        ]);
    }

    /**
     * Simpan perubahan dosen (PUT /admin/dosen/{id}).
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        $payload = $request->only([
            'nama_lengkap', 'nip', 'status_dosen', 'email',
            'role', 'status_akun', 'password',
            'pangkat', 'golongan', 'nomor_sk', 'tanggal_sk',
        ]);

        $files = $request->hasFile('foto') ? ['foto' => $request->file('foto')] : [];

        ApiClient::pengguna()->put("/dosen/{$id}", $payload, $files);

        return redirect()
            ->route('admin.dosen.show', $id)
            ->with('status', 'Perubahan data dosen berhasil disimpan.');
    }

    /**
     * Toggle status aktif/nonaktif dosen (PATCH /admin/dosen/{id}/status).
     */
    public function toggleStatus(string $id): RedirectResponse
    {
        $detail = ApiClient::pengguna()->get("/dosen/{$id}");
        $profil = $detail['data']['profil'] ?? [];

        $statusBaru = ($profil['status'] ?? 'aktif') === 'aktif' ? 'nonaktif' : 'aktif';

        ApiClient::pengguna()->put("/dosen/{$id}", [
            'nama_lengkap' => $profil['nama'] ?? '',
            'nip' => $profil['nip'] ?? '',
            'status_dosen' => $statusBaru,
            'email' => $profil['email'] ?? '',
            'role' => $profil['role_key'] ?? 'dosen',
            'status_akun' => $statusBaru,
        ]);

        $label = $statusBaru === 'aktif' ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()
            ->route('admin.dosen')
            ->with('status', "Status dosen berhasil {$label}.");
    }
}
