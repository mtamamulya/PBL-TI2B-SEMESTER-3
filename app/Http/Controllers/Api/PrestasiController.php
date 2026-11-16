<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnggotaKegiatan;
use App\Models\BuktiPrestasi;
use App\Models\JenisDokumen;
use App\Models\Prestasi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrestasiController extends Controller
{
    public function index(Request $request)
    {
        $sortMap = [
            'nama_prestasi'    => 'nama_lomba',
            'penghargaan'      => 'capaian',
            'tingkat_prestasi' => 'tingkat_kejuaraan',
            'penyelenggara'    => 'penyelenggara',
        ];
        $sortBy  = $sortMap[$request->sort_by] ?? 'id_prestasi';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';

        $data = Prestasi::untukUser($request->user())
            ->when($request->search, function ($q, $s) {
                $q->where(fn ($q) => $q
                    ->where('nama_lomba', 'like', "%{$s}%")
                    ->orWhere('capaian', 'like', "%{$s}%")
                    ->orWhere('penyelenggara', 'like', "%{$s}%"));
            })
            ->when($request->tingkat, fn ($q, $t) => $q->where('tingkat_kejuaraan', $t))
            ->orderBy($sortBy, $sortDir)
            ->paginate((int) $request->get('per_page', 5))
            ->through(fn ($p) => [
                'id'               => $p->id_prestasi,
                'nama_prestasi'    => $p->nama_lomba,
                'penghargaan'      => $p->capaian,
                'tingkat_prestasi' => $p->tingkat_kejuaraan,
                'penyelenggara'    => $p->penyelenggara,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $data->items(),
            'meta'    => [
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
                'per_page'     => $data->perPage(),
                'from'         => $data->firstItem(),
                'to'           => $data->lastItem(),
                'total'        => $data->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePrestasi($request);
        $user = $request->user();

        $prestasi = DB::transaction(function () use ($validated, $user) {
            $idKegiatan = ($validated['sumber'] ?? 'mandiri') === 'kegiatan'
                ? ($validated['id_kegiatan'] ?? null)
                : null;

            $prestasi = Prestasi::create([
                'id_user'            => $user->id_user,
                'created_by'         => $user->id_user,
                'id_kegiatan'        => $idKegiatan,
                'nama_lomba'         => $validated['nama_prestasi'],
                'penyelenggara'      => $validated['penyelenggara'],
                'tingkat_kejuaraan'  => $validated['tingkat'],
                'jenis_prestasi'     => $validated['jenis'],
                'tanggal_mulai'      => $validated['tanggal_mulai'],
                'tanggal_selesai'    => $validated['tanggal_selesai'],
                'capaian'            => $validated['capaian'],
                'bidang_lomba'       => $validated['kategori'] ?? null,
                'bentuk_partisipasi' => strtolower($validated['bentuk']),
                'lokasi'             => $validated['lokasi'] ?? 'Semarang',
                'deskripsi'          => $validated['deskripsi'] ?? null,
            ]);

            $this->simpanRelasi($prestasi, $validated, $user);

            return $prestasi;
        });

        return response()->json([
            'success' => true,
            'message' => 'Prestasi berhasil disimpan',
            'data'    => $prestasi->load(['anggota.user', 'buktiPrestasi.jenisDokumen']),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $prestasi = Prestasi::untukUser($request->user())
            ->with(['user', 'kegiatan', 'anggota.user', 'buktiPrestasi.jenisDokumen'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $prestasi]);
    }

    public function update(Request $request, $id)
    {
        $prestasi = Prestasi::untukUser($request->user())->findOrFail($id);
        $validated = $this->validatePrestasi($request);
        $user = $request->user();

        DB::transaction(function () use ($prestasi, $validated, $user) {
            $idKegiatan = ($validated['sumber'] ?? 'mandiri') === 'kegiatan'
                ? ($validated['id_kegiatan'] ?? null)
                : null;

            $prestasi->update([
                'id_kegiatan'        => $idKegiatan,
                'nama_lomba'         => $validated['nama_prestasi'],
                'penyelenggara'      => $validated['penyelenggara'],
                'tingkat_kejuaraan'  => $validated['tingkat'],
                'jenis_prestasi'     => $validated['jenis'],
                'tanggal_mulai'      => $validated['tanggal_mulai'],
                'tanggal_selesai'    => $validated['tanggal_selesai'],
                'capaian'            => $validated['capaian'],
                'bidang_lomba'       => $validated['kategori'] ?? null,
                'bentuk_partisipasi' => strtolower($validated['bentuk']),
                'lokasi'             => $validated['lokasi'] ?? $prestasi->lokasi ?? 'Semarang',
                'deskripsi'          => $validated['deskripsi'] ?? null,
            ]);

            $this->simpanRelasi($prestasi, $validated, $user, true);
        });

        return response()->json([
            'success' => true,
            'message' => 'Prestasi berhasil diperbarui',
            'data'    => $prestasi->fresh(['anggota.user', 'buktiPrestasi.jenisDokumen']),
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        Prestasi::untukUser($request->user())->findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Prestasi dihapus']);
    }

    private function validatePrestasi(Request $request): array
    {
        return $request->validate([
            'sumber'            => 'required|in:kegiatan,mandiri',
            'id_kegiatan'       => 'nullable|required_if:sumber,kegiatan|integer|exists:kegiatan,id_kegiatan',
            'nama_prestasi'     => 'required|string|max:255',
            'penyelenggara'     => 'required|string|max:255',
            'tingkat'           => 'required|string|max:100',
            'jenis'             => 'required|string|max:100',
            'tanggal_mulai'     => 'required|date',
            'tanggal_selesai'   => 'required|date|after_or_equal:tanggal_mulai',
            'capaian'           => 'required|string|max:255',
            'kategori'          => 'nullable|string|max:255',
            'bentuk'            => 'required|in:Individu,Tim,individu,tim',
            'lokasi'            => 'nullable|string|max:255',
            'deskripsi'         => 'nullable|string|max:1000',
            'anggota'           => 'nullable|array',
            'anggota.*.id_user' => 'nullable|integer|exists:users,id_user',
            'anggota.*.nama'    => 'nullable|string',
            'anggota.*.peran'   => 'nullable|string',
            'dokumen'           => 'required|array|min:1',
            'dokumen.*.jenis'   => 'required|string',
            'dokumen.*.tautan'  => 'required|url',
        ]);
    }

    private function simpanRelasi(Prestasi $prestasi, array $validated, User $user, bool $isUpdate = false): void
    {
        if ($prestasi->isTim() && is_null($prestasi->id_kegiatan)) {
            $daftarAnggota = collect($validated['anggota'] ?? [])->map(function ($item) {
                $idUser = $item['id_user'] ?? User::where('nama', $item['nama'] ?? '')->value('id_user');
                $peran = strtolower($item['peran'] ?? 'anggota');

                return [
                    'id_user' => $idUser,
                    'peran'   => in_array($peran, ['ketua', 'anggota']) ? $peran : 'anggota',
                ];
            })->filter(fn ($a) => ! is_null($a['id_user']))->values()->all();

            AnggotaKegiatan::simpanDaftar($prestasi, $daftarAnggota);
        } elseif ($isUpdate && ! $prestasi->isTim()) {
            $prestasi->anggota()->where('id_user', '!=', $user->id_user)->delete();
        }

        if ($isUpdate) {
            $prestasi->buktiPrestasi()->delete();
        }

        foreach ($validated['dokumen'] as $dok) {
            $jenisDokumen = JenisDokumen::firstOrCreate([
                'nama_jenis_dokumen' => $dok['jenis'],
            ]);

            BuktiPrestasi::create([
                'id_prestasi'      => $prestasi->id_prestasi,
                'id_jenis_dokumen' => $jenisDokumen->id_jenis_dokumen,
                'link'             => $dok['tautan'],
                'nama_file'        => $dok['jenis'] . ' - ' . $prestasi->nama_lomba,
            ]);
        }
    }
}