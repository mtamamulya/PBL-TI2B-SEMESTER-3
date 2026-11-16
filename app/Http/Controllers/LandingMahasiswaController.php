<?php

namespace App\Http\Controllers;

use App\Repositories\MahasiswaStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LandingMahasiswaController extends Controller
{
    public function __construct(private MahasiswaStore $store) {}

    /** Helper pemanggil internal API /api/v1/mahasiswa */
    private function callApi(string $method, string $uri, array $params = [], array $files = []): array
    {
        $apiRequest = Request::create($uri, $method, $params, [], $files);
        $apiRequest->headers->set('Accept', 'application/json');

        $response = app()->handle($apiRequest);

        return [
            'status' => $response->getStatusCode(),
            'body' => json_decode($response->getContent(), true) ?? [],
        ];
    }

    /** Daftar mahasiswa (mengambil data dari GET /api/v1/mahasiswa). */
    public function index()
    {
        $res = $this->callApi('GET', '/api/v1/mahasiswa');

        return view('admin.mahasiswa', [
            'students' => $res['body']['data'] ?? [],
            'levels' => $res['body']['levels'] ?? MahasiswaStore::TINGKAT,
        ]);
    }

    /** Detail satu mahasiswa (mengambil data dari GET /api/v1/mahasiswa/{id}). */
    public function show(string $id)
    {
        $res = $this->callApi('GET', "/api/v1/mahasiswa/{$id}");
        abort_if($res['status'] === 404, 404);

        return view('admin.mahasiswa-detail', [
            'mahasiswa' => $res['body']['data'],
            'academic' => $res['body']['academic'],
            'identity' => $res['body']['identity'],
            'history' => $res['body']['history'],
            'kegiatan' => $res['body']['kegiatan'],
        ]);
    }

    /** Form tambah mahasiswa. */
    public function create()
    {
        return view('admin.mahasiswa-tambah', [
            'tahun' => range((int) date('Y'), (int) date('Y') - 9),
            'statusAkad' => $this->store->opsiStatus(),
        ]);
    }

    /** Simpan mahasiswa baru via POST /api/v1/mahasiswa. */
    public function store(Request $request): RedirectResponse
    {
        $res = $this->callApi('POST', '/api/v1/mahasiswa', $request->except('_token'));

        if ($res['status'] === 422) {
            return back()->withErrors($res['body']['errors'] ?? [])->withInput();
        }

        $id = $res['body']['data']['id'];

        return redirect()->route('admin.mahasiswa.show', $id)
            ->with('status', $res['body']['message'] ?? 'Mahasiswa baru berhasil ditambahkan.');
    }

    /** Form edit mahasiswa (mengambil data dari GET /api/v1/mahasiswa/{id}). */
    public function edit(string $id)
    {
        $res = $this->callApi('GET', "/api/v1/mahasiswa/{$id}");
        abort_if($res['status'] === 404, 404);

        $mahasiswa = $res['body']['data'];

        $tahun = range((int) date('Y'), (int) date('Y') - 9);
        if (! in_array((int) $mahasiswa['angkatan'], $tahun, true)) {
            $tahun[] = (int) $mahasiswa['angkatan'];
            rsort($tahun);
        }

        return view('admin.mahasiswa-edit', [
            'mahasiswa' => $mahasiswa,
            'tahun' => $tahun,
            'statusAkad' => $this->store->opsiStatus(),
            'fotoUrl' => $res['body']['fotoUrl'] ?? null,
        ]);
    }

    /** Simpan perubahan mahasiswa via PUT /api/v1/mahasiswa/{id}. */
    public function update(Request $request, string $id): RedirectResponse
    {
        $res = $this->callApi('PUT', "/api/v1/mahasiswa/{$id}", $request->except(['_token', '_method']), $request->allFiles());

        abort_if($res['status'] === 404, 404);

        if ($res['status'] === 422) {
            return back()->withErrors($res['body']['errors'] ?? [])->withInput();
        }

        return redirect()->route('admin.mahasiswa.show', $id)
            ->with('status', $res['body']['message'] ?? 'Perubahan data mahasiswa berhasil disimpan.');
    }

    /** Sinkronisasi kelas via POST /api/v1/mahasiswa/sinkronisasi-kelas. */
    public function buatKelasSemester(): RedirectResponse
    {
        $res = $this->callApi('POST', '/api/v1/mahasiswa/sinkronisasi-kelas');

        if ($res['status'] !== 200) {
            return redirect()->route('admin.mahasiswa')
                ->with('error', $res['body']['message'] ?? 'Gagal melakukan sinkronisasi kelas.');
        }

        return redirect()->route('admin.mahasiswa')
            ->with('status', $res['body']['message']);
    }

    /** Ubah status aktif/nonaktif via PATCH /api/v1/mahasiswa/{id}/status. */
    public function toggleStatus(string $id): RedirectResponse
    {
        $res = $this->callApi('PATCH', "/api/v1/mahasiswa/{$id}/status");
        abort_if($res['status'] === 404, 404);

        return redirect()->back()
            ->with('status', $res['body']['message'] ?? 'Status mahasiswa berhasil diperbarui.');
    }
}
