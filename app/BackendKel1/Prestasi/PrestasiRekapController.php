<?php

namespace App\BackendKel1\Prestasi;

use App\Http\Controllers\Controller;
use App\Models\Prestasi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rekap prestasi untuk daftar kaprodi dan laporan PDF/Excel (admin dan kaprodi).
 * Memakai formatRow milik AdminPrestasiApiController, jadi angkanya sama dengan halaman Prestasi admin.
 *
 * GET /admin/prestasi/rekap?jenis=dosen|mahasiswa&per_page=1..200&page=1
 */
class PrestasiRekapController extends Controller
{
    /** Tipe pemilik prestasi ditentukan dari role akun pemilik. */
    private const ROLE_JENIS = ['dosen' => 3, 'mahasiswa' => 4];

    public function index(Request $request, AdminPrestasiApiController $admin): JsonResponse
    {
        $data = $request->validate([
            'jenis' => ['nullable', 'in:dosen,mahasiswa'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $halaman = Prestasi::with(AdminPrestasiApiController::RELASI)
            ->when($data['jenis'] ?? null, fn ($q, $jenis) => $q->whereHas(
                'user',
                fn ($u) => $u->where('id_role', self::ROLE_JENIS[$jenis])
            ))
            ->orderByDesc('id_prestasi')
            ->paginate((int) ($data['per_page'] ?? 100));

        return response()->json([
            'success' => true,
            'message' => 'Rekap prestasi berhasil diambil',
            'data' => $halaman->getCollection()
                ->map(fn (Prestasi $item) => $this->baris($admin->formatRow($item)))
                ->all(),
            'meta' => [
                'current_page' => $halaman->currentPage(),
                'per_page' => $halaman->perPage(),
                'total' => $halaman->total(),
                'last_page' => $halaman->lastPage(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $t  hasil formatRow
     * @return array<string, mixed>
     */
    private function baris(array $t): array
    {
        $mentah = $t['raw_date'] ?: null;

        return [
            'id' => (int) $t['id'],
            'nama' => (string) $t['nama'],
            'kategori' => (string) $t['kategori'],
            'pemilik_prestasi' => (string) $t['pemilik_prestasi'],
            'tipe' => (string) $t['tipe'],
            'tingkat' => (string) $t['tingkat'],
            'capaian' => (string) $t['capaian'],
            'tanggal' => (string) $t['tanggal'],
            'raw_date' => $mentah,
            'tahun' => $mentah ? (int) substr((string) $mentah, 0, 4) : null,
            'semester' => (string) $t['semester'],
        ];
    }
}
