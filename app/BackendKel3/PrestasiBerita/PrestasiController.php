<?php

namespace App\BackendKel3\PrestasiBerita;

use App\Http\Controllers\Controller;
use App\Models\AnggotaKegiatan;
use App\Models\BuktiPrestasi;
use App\Models\Dosen;
use App\Models\JenisDokumen;
use App\Models\Kegiatan;
use App\Models\Mahasiswa;
use App\Models\Prestasi;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PrestasiController extends Controller
{
    public function index(Request $request)
    {
        $sortMap = [
            'nama_prestasi' => 'nama_lomba',
            'penghargaan' => 'capaian',
            'tingkat_prestasi' => 'tingkat_kejuaraan',
            'penyelenggara' => 'penyelenggara',
        ];
        $sortBy = $sortMap[$request->sort_by] ?? 'id_prestasi';
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
            ->paginate(max(1, min(100, $request->integer('per_page', 5))))
            ->through(fn ($p) => [
                'id' => $p->id_prestasi,
                'nama_prestasi' => $p->nama_lomba,
                'penghargaan' => $p->capaian,
                'tingkat_prestasi' => $p->tingkat_kejuaraan,
                'penyelenggara' => $p->penyelenggara,
                'bisa_diubah' => (int) $p->id_user === (int) $request->user()->getKey(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Daftar prestasi berhasil diambil.',
            'data' => $data->items(),
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'from' => $data->firstItem(),
                'to' => $data->lastItem(),
                'total' => $data->total(),
            ],
        ]);
    }

    public function opsi(Request $request)
    {
        $user = $request->user();
        $kegiatan = Kegiatan::untukPrestasi($user)->denganTanggal()->with('detail.isianKategori')
            ->orderBy('nama_kegiatan')->get()->map(fn (Kegiatan $k) => [
                'id' => $k->getKey(),
                'nama' => $k->nama_kegiatan,
                'penyelenggara' => $k->nilaiIsian('penyelenggara'),
                'mulai' => $k->tanggal_mulai?->toDateString(),
                'selesai' => $k->tanggal_selesai?->toDateString(),
            ]);
        $kandidat = Dosen::whereNotNull('id_user')->get(['id_user', 'nama'])
            ->map(fn ($p) => ['id_user' => $p->id_user, 'nama' => $p->nama, 'status' => 'Dosen'])
            ->concat(Mahasiswa::whereNotNull('id_user')->get(['id_user', 'nama'])
                ->map(fn ($p) => ['id_user' => $p->id_user, 'nama' => $p->nama, 'status' => 'Mahasiswa']))
            ->sortBy('nama')->values();

        return response()->json(['success' => true, 'message' => 'Opsi form prestasi berhasil diambil.', 'data' => [
            'kegiatan' => $kegiatan,
            'kandidat' => $kandidat,
            'pengaju' => ['id_user' => $user->getKey(), 'nama' => $user->dosen?->nama ?? $user->email, 'status' => 'Dosen'],
        ]]);
    }

    public function store(PrestasiRequest $request)
    {
        $validated = $request->validated();
        $user = $request->user();

        $prestasi = DB::transaction(function () use ($validated, $user) {
            $prestasi = Prestasi::create($this->atribut($validated) + [
                'id_user' => $user->getKey(),
                'created_by' => $user->getKey(),
            ]);
            $this->simpanRelasi($prestasi, $validated);

            return $prestasi;
        });

        return response()->json([
            'success' => true,
            'message' => 'Prestasi berhasil disimpan',
            'data' => $prestasi->load(['anggota.user:id_user,email', 'buktiPrestasi.jenisDokumen']),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $prestasi = Prestasi::untukUser($request->user())
            ->with(['kegiatan:id_kegiatan,nama_kegiatan', 'anggota.user:id_user,email', 'anggota.user.dosen:id_user,nama', 'anggota.user.mahasiswa:id_user,nama', 'buktiPrestasi.jenisDokumen'])
            ->findOrFail($id);

        $penerima = User::with('dosen:id_user,nama', 'mahasiswa:id_user,nama')->whereKey($prestasi->daftarPenerima()->pluck('id_user'))->get()
            ->map(fn ($user) => $user->dosen?->nama ?? $user->mahasiswa?->nama ?? $user->email)
            ->values();

        return response()->json(['success' => true, 'message' => 'Detail prestasi berhasil diambil.', 'data' => $prestasi, 'penerima' => $penerima]);
    }

    public function update(PrestasiRequest $request, int $id)
    {
        $prestasi = Prestasi::untukUser($request->user())->findOrFail($id);
        $validated = $request->validated();
        DB::transaction(function () use ($prestasi, $validated) {
            $prestasi->update($this->atribut($validated, $prestasi->lokasi ?? 'Semarang'));
            $this->simpanRelasi($prestasi, $validated, true);
        });

        return response()->json([
            'success' => true,
            'message' => 'Prestasi berhasil diperbarui',
            'data' => $prestasi->fresh(['anggota.user:id_user,email', 'buktiPrestasi.jenisDokumen']),
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        Prestasi::untukUser($request->user())->findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Prestasi dihapus']);
    }

    private function atribut(array $validated, string $lokasi = 'Semarang'): array
    {
        return [
            'id_kegiatan' => ($validated['sumber'] ?? 'mandiri') === 'kegiatan' ? ($validated['id_kegiatan'] ?? null) : null,
            'nama_lomba' => $validated['nama_prestasi'],
            'penyelenggara' => $validated['penyelenggara'],
            'tingkat_kejuaraan' => $validated['tingkat'],
            'jenis_prestasi' => $validated['jenis'],
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'capaian' => $validated['capaian'],
            'bidang_lomba' => $validated['kategori'] ?? null,
            'bentuk_partisipasi' => strtolower($validated['bentuk'] ?? 'individu'),
            'lokasi' => $validated['lokasi'] ?? $lokasi,
            'deskripsi' => $validated['deskripsi'] ?? null,
        ];
    }

    private function simpanRelasi(Prestasi $prestasi, array $validated, bool $isUpdate = false): void
    {
        if ($prestasi->isTim() && is_null($prestasi->id_kegiatan)) {
            $daftarAnggota = collect($validated['anggota'] ?? [])->map(function ($item) {
                $nama = $item['nama'] ?? '';
                $idUser = $item['id_user']
                    ?? Dosen::where('nama', $nama)->value('id_user')
                    ?? Mahasiswa::where('nama', $nama)->value('id_user');
                $peran = strtolower($item['peran'] ?? 'anggota');

                return [
                    'id_user' => $idUser,
                    'peran' => in_array($peran, ['ketua', 'anggota']) ? $peran : 'anggota',
                ];
            })->filter(fn ($a) => ! is_null($a['id_user']))->values()->all();

            AnggotaKegiatan::simpanDaftar($prestasi, $daftarAnggota);
        } elseif ($isUpdate) {
            $prestasi->anggota()->delete();
        }

        if ($isUpdate) {
            $prestasi->buktiPrestasi()->delete();
        }

        foreach ($validated['dokumen'] as $dok) {
            $jenisDokumen = JenisDokumen::firstOrCreate([
                'nama_jenis_dokumen' => $dok['jenis'],
            ]);

            BuktiPrestasi::create([
                'id_prestasi' => $prestasi->id_prestasi,
                'id_jenis_dokumen' => $jenisDokumen->id_jenis_dokumen,
                'link' => $dok['tautan'],
                'nama_file' => mb_substr($dok['jenis'].' - '.$prestasi->nama_lomba, 0, 255),
            ]);
        }
    }
}