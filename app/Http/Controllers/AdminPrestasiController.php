<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller halaman Admin Prestasi.
 * Seluruh akses data dilakukan melalui HTTP (ApiClient), bukan Eloquent langsung.
 * Proyeksi `tampilan` dibentuk di API untuk mempertahankan kontrak Blade lama.
 */
class AdminPrestasiController extends Controller
{
    private const API = '/admin/prestasi';

    public function index(): View
    {
        $rows = [];
        $page = 1;

        // DataTables pada Blade lama memerlukan semua baris, sementara API
        // membatasi per_page maksimum 100. Ambil setiap halaman secara berurutan.
        do {
            $hasil = ApiClient::pengguna()->get(self::API, [
                'per_page' => 100,
                'page' => $page,
            ]);

            foreach ($hasil['data'] ?? [] as $item) {
                $rows[] = $item['tampilan'];
            }

            $lastPage = max(1, (int) ($hasil['meta']['last_page'] ?? 1));
            $page++;
        } while ($page <= $lastPage);

        return view('admin.prestasi', compact('rows'));
    }

    public function show(int $id): View
    {
        $hasil = ApiClient::pengguna()->get(self::API.'/'.$id);
        $prestasi = $hasil['data']['tampilan'];

        return view('admin.prestasi-detail', compact('prestasi'));
    }

    public function create(): View
    {
        return view('admin.prestasi-tambah', $this->pilihanForm());
    }

    public function store(Request $request): RedirectResponse
    {
        ApiClient::pengguna()->post(self::API, $this->payload($request));

        return redirect()->route('admin.prestasi')
            ->with('success', 'Prestasi berhasil ditambahkan.');
    }

    public function edit(int $id): View
    {
        $hasil = ApiClient::pengguna()->get(self::API.'/'.$id);
        $prestasi = $hasil['data']['tampilan'];

        return view('admin.prestasi-edit', array_merge(
            compact('prestasi'),
            $this->pilihanForm()
        ));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        ApiClient::pengguna()->put(self::API.'/'.$id, $this->payload($request));

        return redirect()->route('admin.prestasi')
            ->with('success', 'Prestasi berhasil diperbarui.');
    }

    public function destroy(int $id): RedirectResponse
    {
        ApiClient::pengguna()->delete(self::API.'/'.$id);

        return redirect()->route('admin.prestasi')
            ->with('success', 'Prestasi berhasil dihapus.');
    }

    private function pilihanForm(): array
    {
        $hasil = ApiClient::pengguna()->get(self::API.'/opsi');

        return $hasil['data'];
    }

    /**
     * Field yang dikirim oleh formulir Blade adalah kontrak input API.
     * Token CSRF dan spoofing _method hanya untuk route web, bukan isi API.
     */
    private function payload(Request $request): array
    {
        return $request->except(['_token', '_method']);
    }
}
