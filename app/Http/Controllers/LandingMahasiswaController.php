<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LandingMahasiswaController extends Controller
{
    public const STATUS_OPSI = [
        'Aktif',
        'Cuti',
        'Drop Out',
        'Skripsi/TA',
        'Lulus',
        'Meninggal Dunia',
    ];

    public function index()
    {
        $hasil = ApiClient::pengguna()->get('/mahasiswa');
        $meta = $hasil['meta'] ?? [];

        return view('admin.mahasiswa', [
            'students' => $hasil['data'] ?? [],
            'levels' => $meta['levels'] ?? ['Tingkat 1', 'Tingkat 2', 'Tingkat 3', 'Tingkat 4'],
            'statusAkad' => $meta['status'] ?? self::STATUS_OPSI,
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
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $request->only(['nama', 'nim', 'angkatan', 'email', 'password']);
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
            'statusAkad' => self::STATUS_OPSI,
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

    public function toggleStatus(Request $request, string $id): RedirectResponse
    {
        $payload = $request->only(['status']);
        $hasil = ApiClient::pengguna()->patch("/mahasiswa/{$id}/status", $payload);

        return back()->with('status', $hasil['message'] ?? 'Status mahasiswa berhasil diperbarui.');
    }

    public function buatKelasSemester(): RedirectResponse
    {
        try {
            $hasil = ApiClient::pengguna()->post('/mahasiswa/sinkronisasi-kelas');
        } catch (ValidationException $e) {
            $pesan = collect($e->errors())->flatten()->first() ?: $e->getMessage();

            return back()->with('error', $pesan ?: 'Validasi sinkronisasi kelas gagal.');
        } catch (HttpException $e) {
            $pesan = trim($e->getMessage());

            return back()->with('error', 'HTTP '.$e->getStatusCode().': '.($pesan !== '' ? $pesan : 'Akses ditolak atau endpoint API bermasalah (cek token / port 8001).'));
        } catch (\Throwable $e) {
            $pesan = trim($e->getMessage());

            return back()->with('error', get_class($e).': '.($pesan !== '' ? $pesan : 'Terjadi kesalahan tanpa pesan.'));
        }

        if (! ($hasil['success'] ?? false)) {
            $pesan = trim((string) ($hasil['message'] ?? ''));

            return back()->with('error', $pesan !== '' ? $pesan : 'Gagal menjalankan sinkronisasi kelas (respons API: '.json_encode($hasil).').');
        }

        return back()->with('status', $hasil['message'] ?? 'Sinkronisasi kelas selesai.');
    }

    public function luluskanTa(): RedirectResponse
    {
        try {
            $hasil = ApiClient::pengguna()->post('/mahasiswa/luluskan-ta');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage() ?: 'Gagal memproses kelulusan Skripsi/TA.');
        }

        return back()->with('status', $hasil['message'] ?? 'Status Skripsi/TA berhasil diperbarui menjadi Lulus.');
    }
}
