<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LandingMahasiswaController extends Controller
{
    public function index()
    {
        $hasil = ApiClient::pengguna()->get('/mahasiswa');
        $meta = $hasil['meta'] ?? [];

        return view('admin.mahasiswa', [
            'students' => $hasil['data'] ?? [],
            'levels' => $meta['levels'] ?? ['Tingkat 1', 'Tingkat 2', 'Tingkat 3', 'Tingkat 4'],
        ]);
    }

    public function show(string $id)
    {
        $hasil = ApiClient::pengguna()->get("/mahasiswa/{$id}");

        return view('admin.mahasiswa-detail', [
            'mahasiswa' => $hasil['data'] ?? [],
            'fotoUrl' => $hasil['fotoUrl'] ?? null,
            'academic' => $hasil['academic'] ?? [],
            'history' => $hasil['history'] ?? [],
            'kegiatan' => $hasil['kegiatan'] ?? [],
        ]);
    }

    public function create()
    {
        $hasil = ApiClient::pengguna()->get('/mahasiswa');
        $meta = $hasil['meta'] ?? [];

        return view('admin.mahasiswa-tambah', [
            'tahun' => $meta['tahun'] ?? range((int) date('Y'), (int) date('Y') - 9),
            'statusAkad' => $meta['status'] ?? ['Aktif', 'Cuti', 'Lulus', 'Non-aktif'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $request->only(['nama', 'nim', 'angkatan', 'status', 'email', 'password']);
        $hasil = ApiClient::pengguna()->post('/mahasiswa', $payload);
        $idBaru = $hasil['data']['id'] ?? null;

        if ($idBaru) {
            return redirect()
                ->route('admin.mahasiswa.show', ['id' => $idBaru])
                ->with('status', 'Mahasiswa baru berhasil ditambahkan.');
        }

        return redirect()
            ->route('admin.mahasiswa')
            ->with('status', 'Mahasiswa baru berhasil ditambahkan.');
    }

    public function edit(string $id)
    {
        $detail = ApiClient::pengguna()->get("/mahasiswa/{$id}");

        return view('admin.mahasiswa-edit', [
            'mahasiswa' => $detail['data'] ?? [],
            'fotoUrl' => $detail['fotoUrl'] ?? null,
            'tahun' => range((int) date('Y'), (int) date('Y') - 9),
            'statusAkad' => ['Aktif', 'Cuti', 'Lulus', 'Non-aktif'],
        ]);
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $payload = $request->only(['nama', 'nim', 'angkatan', 'status', 'email', 'password', 'hapus_foto']);

        if ($request->hasFile('foto')) {
            ApiClient::pengguna()->put("/mahasiswa/{$id}", $payload, ['foto' => $request->file('foto')]);
        } else {
            ApiClient::pengguna()->put("/mahasiswa/{$id}", $payload);
        }

        return redirect()
            ->route('admin.mahasiswa.show', ['id' => $id])
            ->with('status', 'Perubahan data mahasiswa berhasil disimpan.');
    }

    public function toggleStatus(string $id): RedirectResponse
    {
        $hasil = ApiClient::pengguna()->patch("/mahasiswa/{$id}/status");

        return back()->with('status', $hasil['message'] ?? 'Status mahasiswa berhasil diperbarui.');
    }

    public function buatKelasSemester(): RedirectResponse
    {
        $hasil = ApiClient::pengguna()->post('/mahasiswa/sinkronisasi-kelas');

        return back()->with('status', $hasil['message'] ?? 'Sinkronisasi kelas selesai.');
    }
}
