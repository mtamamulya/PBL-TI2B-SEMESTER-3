<?php

namespace App\BackendKel3\Profil;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfilController extends Controller
{
    /**
     * GET /api/v1/dosen/profil
     * Mengambil profil dosen yang sedang login beserta pangkat terakhir.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $dosen = $user?->dosen;

        if (! $dosen) {
            return ApiResponse::gagal('Data profil dosen tidak ditemukan untuk akun ini.', 404);
        }

        $pangkatTerakhir = $dosen->historiPangkatGolongan()
            ->orderByDesc('tanggal_sk')
            ->orderByDesc('id_histori_pangkat_golongan')
            ->first();

        $data = [
            'id_dosen' => $dosen->id_dosen,
            'nama' => $dosen->nama,
            'nip' => $dosen->nip,
            'email' => $user->email,
            'foto_profil' => $this->formatFotoUrl($user->foto_profil),
            'pangkat_terakhir' => $pangkatTerakhir ? [
                'pangkat' => $pangkatTerakhir->pangkat,
                'golongan' => $pangkatTerakhir->golongan,
                'nomor_sk' => $pangkatTerakhir->nomor_sk,
                'tanggal_sk' => $pangkatTerakhir->tanggal_sk?->format('Y-m-d'),
            ] : null,
        ];

        return ApiResponse::ok($data, 'Profil dosen berhasil diambil.');
    }

    /**
     * PATCH /api/v1/dosen/profil
     * Mengubah nama dosen dan (jika dikirim) foto profil user yang login.
     */
    public function update(UpdateProfilRequest $request): JsonResponse
    {
        $user = $request->user();
        $dosen = $user?->dosen;

        if (! $dosen || ! $user) {
            return ApiResponse::gagal('Data profil dosen tidak ditemukan untuk akun ini.', 404);
        }

        $validated = $request->validated();

        $dosen->update([
            'nama' => $validated['nama'],
        ]);

        if ($request->hasFile('foto_profil')) {
            $this->hapusFotoLama($user->foto_profil);

            $path = $request->file('foto_profil')->store('foto-profil', 'public');
            $user->update([
                'foto_profil' => '/storage/'.ltrim($path, '/'),
            ]);
            $user->refresh();
        }

        $pangkatTerakhir = $dosen->historiPangkatGolongan()
            ->orderByDesc('tanggal_sk')
            ->orderByDesc('id_histori_pangkat_golongan')
            ->first();

        $data = [
            'id_dosen' => $dosen->id_dosen,
            'nama' => $dosen->nama,
            'nip' => $dosen->nip,
            'email' => $user->email,
            'foto_profil' => $this->formatFotoUrl($user->foto_profil),
            'pangkat_terakhir' => $pangkatTerakhir ? [
                'pangkat' => $pangkatTerakhir->pangkat,
                'golongan' => $pangkatTerakhir->golongan,
                'nomor_sk' => $pangkatTerakhir->nomor_sk,
                'tanggal_sk' => $pangkatTerakhir->tanggal_sk?->format('Y-m-d'),
            ] : null,
        ];

        return ApiResponse::ok($data, 'Profil dosen berhasil diperbarui.');
    }

    /**
     * GET /api/v1/dosen/riwayat-pangkat
     * Mengambil daftar riwayat SK pangkat/golongan dosen, diurutkan dari yang terbaru.
     */
    public function riwayatPangkat(Request $request): JsonResponse
    {
        $dosen = $request->user()?->dosen;

        if (! $dosen) {
            return ApiResponse::gagal('Data profil dosen tidak ditemukan untuk akun ini.', 404);
        }

        $riwayat = $dosen->historiPangkatGolongan()
            ->orderByDesc('tanggal_sk')
            ->orderByDesc('id_histori_pangkat_golongan')
            ->get()
            ->map(fn ($item) => [
                'id_histori_pangkat_golongan' => $item->id_histori_pangkat_golongan,
                'pangkat' => $item->pangkat,
                'golongan' => $item->golongan,
                'nomor_sk' => $item->nomor_sk,
                'tanggal_sk' => $item->tanggal_sk?->format('Y-m-d'),
            ])
            ->values()
            ->all();

        return ApiResponse::ok($riwayat, 'Riwayat pangkat dan golongan berhasil diambil.');
    }

    private function formatFotoUrl(?string $fotoProfil): ?string
    {
        if (! $fotoProfil) {
            return null;
        }

        if (str_starts_with($fotoProfil, 'http://') || str_starts_with($fotoProfil, 'https://')) {
            return $fotoProfil;
        }

        return '/'.ltrim($fotoProfil, '/');
    }

    private function hapusFotoLama(?string $fotoProfil): void
    {
        if (! $fotoProfil) {
            return;
        }

        if (str_starts_with($fotoProfil, 'http://') || str_starts_with($fotoProfil, 'https://')) {
            return;
        }

        $normalized = ltrim($fotoProfil, '/');
        if (str_starts_with($normalized, 'storage/')) {
            $relativePath = substr($normalized, strlen('storage/'));
            Storage::disk('public')->delete($relativePath);
        }
    }
}
