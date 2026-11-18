<?php

namespace App\Http\Controllers;

use App\Support\ApiClient;
use App\Support\ApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Hapus kegiatan dosen / mahasiswa melalui API DELETE /admin/kegiatan/{id}.
 * Hanya admin yang diizinkan menghapus; kaprodi akan ditolak API (403).
 */
class KegiatanHapusController extends Controller
{
    public function dosen(Request $request, string $id): RedirectResponse
    {
        return $this->hapus($request, 'dosen', $id);
    }

    public function mahasiswa(Request $request, string $id): RedirectResponse
    {
        return $this->hapus($request, 'mahasiswa', $id);
    }

    private function hapus(Request $request, string $jenis, string $id): RedirectResponse
    {
        $prefix = $request->routeIs('kaprodi.*') ? 'kaprodi' : 'admin';
        $tujuan = redirect()->route($prefix.'.kegiatan.index', ['tab' => $jenis]);

        try {
            $hasil = ApiClient::pengguna()->delete('/admin/kegiatan/'.$id);
        } catch (ApiException $e) {
            $status = $e->getStatusCode();

            if ($status === 401) {
                throw $e;
            }

            $pesan = match ($status) {
                403 => 'Anda tidak memiliki izin untuk menghapus kegiatan.',
                404 => 'Kegiatan tidak ditemukan atau sudah dihapus.',
                409 => $e->getMessage() ?: 'Kegiatan tidak dapat dihapus karena masih digunakan pada data prestasi.',
                default => $e->getMessage() ?: 'Gagal menghapus kegiatan.',
            };

            return $tujuan->with('error', $pesan);
        }

        $nama = $hasil['data']['nama'] ?? null;
        $pesanSukses = is_string($nama) && $nama !== ''
            ? 'Kegiatan "'.$nama.'" berhasil dihapus.'
            : ($hasil['message'] ?? 'Kegiatan berhasil dihapus.');

        return $tujuan->with('success', $pesanSukses);
    }
}