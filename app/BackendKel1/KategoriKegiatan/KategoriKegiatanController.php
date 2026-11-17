<?php

namespace App\BackendKel1\KategoriKegiatan;

use App\Http\Controllers\Controller;
use App\Models\IsianKategori;
use App\Models\JenisKegiatan;
use App\Models\Kategori;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * GET /api/v1/jenis-kegiatan, /tipe-isian, /kategori-kegiatan, /kategori-kegiatan/{id}  (semua pengguna yang login)
 * POST/PUT/DELETE /api/v1/kategori-kegiatan                                 (khusus admin)
 *
 * Satu kategori membawa daftar isian form (tabel isian_kategori) yang harus diisi pengaju.
 */
class KategoriKegiatanController extends Controller
{
    private const MAKS_ISIAN = 20;

    public function jenis(): JsonResponse
    {
        $rows = JenisKegiatan::query()
            ->orderBy('id_jenis_kegiatan')
            ->get()
            ->map(fn ($j) => [
                'id_jenis_kegiatan' => $j->id_jenis_kegiatan,
                'nama_jenis_kegiatan' => $j->nama_jenis_kegiatan,
            ])
            ->all();

        return ApiResponse::ok($rows);
    }

    /** Tipe isian yang bisa dipilih admin (tipe biasa + pilihan baku). */
    public function tipeIsian(): JsonResponse
    {
        return ApiResponse::ok(PilihanBaku::daftarTipe());
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'id_jenis_kegiatan' => ['nullable', 'integer'],
            'jenis' => ['nullable', 'string', 'max:100'],
            'kebutuhan' => ['nullable', 'in:kegiatan,surat'],
            'q' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $p = Kategori::query()
            ->with('jenisKegiatan')
            ->withCount('isianKategori')
            ->when($request->filled('id_jenis_kegiatan'), fn ($q) => $q->where('id_jenis_kegiatan', (int) $request->query('id_jenis_kegiatan')))
            ->when($request->filled('jenis'), fn ($q) => $q->whereHas(
                'jenisKegiatan',
                fn ($j) => $j->where('nama_jenis_kegiatan', $request->query('jenis'))
            ))
            ->when($request->filled('kebutuhan'), fn ($q) => $q->where('kebutuhan', $request->query('kebutuhan')))
            ->when($request->filled('q'), fn ($q) => $q->where('nama_kategori', 'like', '%'.$request->query('q').'%'))
            ->orderBy('id_jenis_kegiatan')
            ->orderBy('nama_kategori')
            ->paginate((int) $request->query('per_page', 15));

        return ApiResponse::paginated($p, $p->getCollection()->map(fn ($k) => self::bentuk($k))->all());
    }

    public function show(int $id): JsonResponse
    {
        $k = $this->cari($id);

        return $k ? ApiResponse::ok(self::bentuk($k, true)) : ApiResponse::gagal('Kategori kegiatan tidak ditemukan');
    }

    public function store(Request $request): JsonResponse
    {
        if ($this->bukanAdmin($request)) {
            return $this->dilarang();
        }

        $data = $this->validasi($request);

        try {
            $k = DB::transaction(function () use ($data) {
                $k = Kategori::create(Arr::only($data, ['id_jenis_kegiatan', 'nama_kategori', 'deskripsi', 'isi_surat', 'lampiran_peserta', 'kebutuhan']));

                if (isset($data['isian'])) {
                    $this->sinkronIsian($k, $data['isian']);
                }

                return $k;
            });
        } catch (QueryException) {
            return ApiResponse::gagal('Kategori gagal disimpan. Periksa isian form.', 409);
        }

        return ApiResponse::ok(self::bentuk($this->cari($k->getKey()), true), 'Kategori kegiatan dibuat.')->setStatusCode(201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if ($this->bukanAdmin($request)) {
            return $this->dilarang();
        }

        $k = Kategori::query()->find($id);
        if (! $k) {
            return ApiResponse::gagal('Kategori kegiatan tidak ditemukan');
        }

        $data = $this->validasi($request, $id);

        try {
            DB::transaction(function () use ($k, $data) {
                $k->update(Arr::only($data, ['id_jenis_kegiatan', 'nama_kategori', 'deskripsi', 'isi_surat', 'lampiran_peserta', 'kebutuhan']));

                // `isian` tidak dikirim = daftar isian dibiarkan. Dikirim (walau kosong) = diganti sesuai kiriman.
                if (array_key_exists('isian', $data)) {
                    $this->sinkronIsian($k, $data['isian'] ?? []);
                }
            });
        } catch (QueryException) {
            return ApiResponse::gagal('Isian yang dihapus sudah dipakai oleh kegiatan, jadi tidak bisa dihapus.', 409);
        }

        return ApiResponse::ok(self::bentuk($this->cari($id), true), 'Kategori kegiatan diperbarui.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if ($this->bukanAdmin($request)) {
            return $this->dilarang();
        }

        $k = Kategori::query()->find($id);
        if (! $k) {
            return ApiResponse::gagal('Kategori kegiatan tidak ditemukan');
        }

        if ($k->kegiatan()->exists()) {
            return ApiResponse::gagal('Kategori ini sudah dipakai oleh kegiatan, jadi tidak bisa dihapus.', 409);
        }

        try {
            DB::transaction(function () use ($k) {
                $k->isianKategori()->delete();
                $k->delete();
            });
        } catch (QueryException) {
            return ApiResponse::gagal('Kategori ini masih dipakai data lain, jadi tidak bisa dihapus.', 409);
        }

        return ApiResponse::ok(null, 'Kategori kegiatan dihapus.');
    }

    /** Bentuk respons. Nama kunci mengikuti kolom database; `wajib` adalah kolom `aturan` (NOT NULL = true). */
    public static function bentuk(Kategori $k, bool $denganIsian = false): array
    {
        $out = [
            'id_kategori' => $k->id_kategori,
            'id_jenis_kegiatan' => $k->id_jenis_kegiatan,
            'jenis' => $k->jenisKegiatan?->nama_jenis_kegiatan,
            'nama_kategori' => $k->nama_kategori,
            'deskripsi' => $k->deskripsi,
            'kebutuhan' => $k->kebutuhan,
            'isi_surat' => $k->isi_surat,
            'lampiran_peserta' => (bool) $k->lampiran_peserta,
        ];

        if ($denganIsian) {
            $out['isian'] = $k->isianKategori->map(function ($i) {
                $row = self::bentukIsian($i);

                if ($tetap = PilihanBaku::nilai($i->jenis_input)) {
                    $row['pilihan'] = array_map(fn ($v) => ['nilai' => $v, 'label' => Str::title($v)], $tetap);
                }

                return $row;
            })->all();
        } else {
            $out['total_isian'] = (int) ($k->isian_kategori_count ?? 0);
        }

        return $out;
    }

    public static function bentukIsian(IsianKategori $i): array
    {
        return [
            'id_isian_kategori' => $i->id_isian_kategori,
            'input' => $i->input,
            'jenis_input' => $i->jenis_input,
            'wajib' => $i->aturan === 'NOT NULL',
            'jumlah_isian' => empty($i->jumlah_isian) ? 0 : 1, // 1 = satu isian, 0 = lebih dari satu
            'pilihan' => null, // diisi pada detail untuk tipe pilihan baku
        ];
    }

    private function cari(int $id): ?Kategori
    {
        return Kategori::query()
            ->with([
                'jenisKegiatan',
                'isianKategori' => fn ($q) => $q->orderBy('id_isian_kategori'),
            ])
            ->find($id);
    }

    private function bukanAdmin(Request $request): bool
    {
        return $request->user()?->loadMissing('role')->role?->nama_role !== 'admin';
    }

    private function dilarang(): JsonResponse
    {
        return ApiResponse::gagal('Hanya admin yang boleh mengubah kategori kegiatan.', 403);
    }

    private function validasi(Request $request, ?int $id = null): array
    {
        // Nama lama dari form web tetap diterima.
        if (! $request->has('isi_surat') && $request->has('template_surat')) {
            $request->merge(['isi_surat' => $request->input('template_surat')]);
        }

        // Jenis boleh dikirim sebagai nama ("Kegiatan Mahasiswa") atau sebagai id_jenis_kegiatan.
        if (! $request->filled('id_jenis_kegiatan') && $request->filled('jenis')) {
            $idJenis = JenisKegiatan::query()
                ->where('nama_jenis_kegiatan', $request->input('jenis'))
                ->value('id_jenis_kegiatan');

            if ($idJenis) {
                $request->merge(['id_jenis_kegiatan' => $idJenis]);
            }
        }

        $data = $request->validate([
            'id_jenis_kegiatan' => ['required', 'integer', Rule::exists('jenis_kegiatan', 'id_jenis_kegiatan')],
            'nama_kategori' => [
                'required', 'string', 'max:100',
                Rule::unique('kategori', 'nama_kategori')
                    ->where('id_jenis_kegiatan', $request->input('id_jenis_kegiatan'))
                    ->ignore($id, 'id_kategori'),
            ],
            'deskripsi' => ['nullable', 'string', 'max:255'],
            'isi_surat' => ['nullable', 'string'],
            'lampiran_peserta' => ['nullable', 'boolean'],
            'kebutuhan' => ['nullable', 'in:kegiatan,surat'],
            'isian' => ['nullable', 'array', 'max:'.self::MAKS_ISIAN],
            'isian.*.id_isian_kategori' => ['nullable', 'integer'],
            'isian.*.input' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'isian.*.jenis_input' => ['required', Rule::in(PilihanBaku::semuaJenis())],
            'isian.*.wajib' => ['nullable', 'boolean'],
            'isian.*.jumlah_isian' => ['nullable', 'boolean'],
        ], [
            'id_jenis_kegiatan.required' => 'Jenis kegiatan wajib dipilih.',
            'id_jenis_kegiatan.exists' => 'Jenis kegiatan tidak valid.',
            'nama_kategori.required' => 'Nama kategori wajib diisi.',
            'nama_kategori.max' => 'Nama kategori maksimal 100 karakter.',
            'nama_kategori.unique' => 'Nama kategori sudah ada pada jenis kegiatan ini.',
            'isian.max' => 'Isian form maksimal '.self::MAKS_ISIAN.'.',
            'isian.*.input.required' => 'Nama isian wajib diisi.',
            'isian.*.jenis_input.in' => 'Tipe isian tidak valid.',
            'isian.*.input.distinct' => 'Nama isian tidak boleh sama.',
            'isian.*.jumlah_isian.boolean' => 'Jumlah isian harus 1 (satu isian) atau 0 (lebih dari satu).',
        ]);

        return $data;
    }

    /**
     * Samakan isian di database dengan daftar kiriman:
     * isian ber-`id_isian_kategori` diperbarui (id tetap), tanpa id ditambah, yang tidak dikirim lagi dihapus
     * (isian yang sudah dijawab kegiatan tidak bisa dihapus: 409).
     * Urutan tampil mengikuti id (urutan dibuat).
     */
    private function sinkronIsian(Kategori $k, array $daftar): void
    {
        $ada = $k->isianKategori()->get()->keyBy('id_isian_kategori');
        $dipakai = [];

        foreach (array_values($daftar) as $i => $f) {
            $nilai = [
                'input' => $f['input'],
                'jenis_input' => $f['jenis_input'],
                'aturan' => ! empty($f['wajib']) ? 'NOT NULL' : 'NULL',
                // jumlah_isian: 1 = satu isian (juga bila tidak dikirim); 0 atau null = lebih dari satu
                'jumlah_isian' => array_key_exists('jumlah_isian', $f) ? (empty($f['jumlah_isian']) ? 0 : 1) : 1,
            ];

            $id = $f['id_isian_kategori'] ?? null;

            if ($id === null) {
                $dipakai[] = $k->isianKategori()->create($nilai)->getKey();

                continue;
            }

            $baris = $ada->get((int) $id);
            if (! $baris) {
                throw ValidationException::withMessages(["isian.$i.id_isian_kategori" => 'Isian tidak ditemukan pada kategori ini.']);
            }

            // Model melarang mengubah tipe isian yang sudah dijawab; beri pesan yang jelas per isian.
            if ($baris->jenis_input !== $nilai['jenis_input'] && $baris->detailKegiatan()->exists()) {
                throw ValidationException::withMessages(["isian.$i.jenis_input" => 'Tipe isian yang sudah dijawab tidak dapat diubah.']);
            }

            $baris->update($nilai);
            $dipakai[] = $baris->getKey();
        }

        foreach ($ada as $id => $baris) {
            if (in_array($id, $dipakai, true)) {
                continue;
            }

            if ($baris->detailKegiatan()->exists()) {
                throw new HttpResponseException(
                    ApiResponse::gagal("Isian \"{$baris->input}\" sudah dijawab oleh kegiatan, jadi tidak bisa dihapus.", 409)
                );
            }

            $baris->delete();
        }
    }
}
