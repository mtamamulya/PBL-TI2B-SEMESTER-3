<?php

namespace App\Http\Controllers;

use App\Repositories\MataKuliahStore;
use App\Support\ApiClient;
use App\Support\ApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Mata Kuliah (admin; kaprodi memakai kelas turunan). Data lewat HTTP ke API (ApiClient):
 * bila server API mati halaman menjawab 503. Bentuk data untuk view tetap seperti MataKuliahStore.
 */
class AdminMataKuliahController extends Controller
{
    protected string $viewDaftar = 'admin.mata-kuliah';

    protected string $viewTambah = 'admin.tambah-mata-kuliah';

    protected string $viewEdit = 'kaprodi.mata-kuliah-edit';

    protected string $rutaDaftar = 'admin.kurikulum.mata-kuliah';

    /** Nama field API -> nama field form (supaya pesan galat tampil di kolom yang benar). */
    private const GALAT = ['kode_mk' => 'kode', 'nama_mk' => 'nama', 'sks_praktik' => 'sks_praktek'];

    /** Daftar per kurikulum & semester (?kurikulum=..&semester=..&q=..). */
    public function index(Request $request)
    {
        $daftar = $this->daftarKurikulum();
        $ids = array_column($daftar, 'id');
        $kurikulumId = in_array($request->query('kurikulum'), $ids, true) ? $request->query('kurikulum') : ($ids[0] ?? '');

        $semua = $kurikulumId !== '' ? $this->mataKuliah($kurikulumId) : [];

        $semester = (int) $request->query('semester');
        if ($semester < 1 || $semester > 8) {
            $ada = array_map(fn ($r) => $r['semester'], $semua);
            $semester = $ada ? min($ada) : 1;
        }
        $q = trim((string) $request->query('q'));

        return view($this->viewDaftar, [
            'kurikulumList' => $daftar,
            'kurikulumId' => $kurikulumId,
            'semesterList' => range(1, 8),
            'semester' => $semester,
            'q' => $q,
        ] + $this->susun($semua, $semester, $q));
    }

    public function create(Request $request)
    {
        return view($this->viewTambah, [
            'kurikulumOptions' => $this->opsi(),
            'nilai' => [
                'kurikulum' => $request->query('kurikulum'),
                'semester' => $request->query('semester'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $kurikulum = (string) $request->input('kurikulum');
        if (! ctype_digit($kurikulum)) {
            return back()->withInput()->withErrors(['kurikulum' => 'Pilihan tidak valid.']);
        }

        return $this->denganGalat(function () use ($request, $kurikulum) {
            ApiClient::pengguna()->post("/kurikulum/{$kurikulum}/mata-kuliah", $this->payload($request));

            return $this->kembali($request->all(), 'Mata kuliah "'.$request->input('nama').'" berhasil ditambahkan.');
        });
    }

    public function edit(int $id)
    {
        $mk = self::untukView(ApiClient::pengguna()->get("/mata-kuliah/{$id}")['data']);

        return view($this->viewEdit, [
            'kurikulumOptions' => $this->opsi(),
            'mk' => $mk,
            'nilai' => $mk,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $ada = self::untukView(ApiClient::pengguna()->get("/mata-kuliah/{$id}")['data']);

        if ((string) $request->input('kurikulum') !== $ada['kurikulum']) {
            return back()->withInput()->withErrors(['kurikulum' => 'Kurikulum mata kuliah tidak dapat dipindahkan.']);
        }

        return $this->denganGalat(function () use ($request, $id) {
            ApiClient::pengguna()->put("/mata-kuliah/{$id}", $this->payload($request));

            return $this->kembali($request->all(), 'Mata kuliah "'.$request->input('nama').'" berhasil diperbarui.');
        });
    }

    public function destroy(int $id): RedirectResponse
    {
        $mk = self::untukView(ApiClient::pengguna()->get("/mata-kuliah/{$id}")['data']);

        return $this->denganGalat(function () use ($id, $mk) {
            ApiClient::pengguna()->delete("/mata-kuliah/{$id}");

            return $this->kembali($mk, 'Mata kuliah "'.$mk['nama'].'" berhasil dihapus.');
        });
    }

    /** 422 dikembalikan ke form dengan nama kolom form; 409 (masih dipakai) kembali dengan pesan galat. */
    private function denganGalat(callable $aksi): RedirectResponse
    {
        try {
            return $aksi();
        } catch (ApiException $e) {
            if ($e->getStatusCode() === 422) {
                $galat = [];
                foreach ($e->errors ?: ['api' => [$e->getMessage()]] as $kolom => $pesan) {
                    $galat[self::GALAT[$kolom] ?? $kolom] = $pesan;
                }

                return back()->withInput()->withErrors($galat);
            }
            if ($e->getStatusCode() === 409) {
                return back()->withErrors(['api' => $e->getMessage()]);
            }
            throw $e;
        }
    }

    private function payload(Request $request): array
    {
        return [
            'semester' => $request->input('semester'),
            'kode_mk' => trim((string) $request->input('kode')),
            'nama_mk' => trim((string) $request->input('nama')),
            'sks_teori' => $request->input('sks_teori'),
            'sks_praktik' => $request->input('sks_praktek'),
            'jam_teori' => $request->input('jam_teori'),
            'jam_praktek' => $request->input('jam_praktek'),
        ];
    }

    private function kembali(array $data, string $pesan): RedirectResponse
    {
        return redirect()
            ->route($this->rutaDaftar, ['kurikulum' => $data['kurikulum'], 'semester' => $data['semester']])
            ->with('success', $pesan);
    }

    /** @return array<string, string> id kurikulum => nama */
    private function opsi(): array
    {
        return collect($this->daftarKurikulum())->pluck('nama', 'id')->all();
    }

    /** Kurikulum semua status, tahun terbit terbaru dulu. @return array<int, array{id: string, nama: string}> */
    private function daftarKurikulum(): array
    {
        $rows = $this->semuaHalaman('/kurikulum');
        usort($rows, fn ($a, $b) => strcmp((string) ($b['tahun_terbit'] ?? ''), (string) ($a['tahun_terbit'] ?? ''))
            ?: strcmp($a['nama_kurikulum'], $b['nama_kurikulum']));

        return array_map(fn ($r) => ['id' => (string) $r['id_kurikulum'], 'nama' => $r['nama_kurikulum']], $rows);
    }

    private function mataKuliah(string $kurikulumId): array
    {
        return array_map(
            fn ($r) => self::untukView($r),
            $this->semuaHalaman('/mata-kuliah', ['id_kurikulum' => $kurikulumId])
        );
    }

    /** API membatasi per_page 100, jadi ambil setiap halaman. */
    private function semuaHalaman(string $path, array $query = []): array
    {
        $rows = [];
        $halaman = 1;
        do {
            $hasil = ApiClient::pengguna()->get($path, $query + ['per_page' => 100, 'page' => $halaman]);
            array_push($rows, ...($hasil['data'] ?? []));
            $terakhir = max(1, (int) ($hasil['meta']['last_page'] ?? 1));
            $halaman++;
        } while ($halaman <= $terakhir);

        return $rows;
    }

    /** Baris yang tampil (hasil cari), jumlah semua, dan sub total (selalu dari SEMUA mata kuliah semester itu). */
    private function susun(array $semua, int $semester, string $q): array
    {
        $semuaSemester = array_values(array_filter($semua, fn ($r) => $r['semester'] === $semester));
        usort($semuaSemester, fn ($a, $b) => strnatcasecmp($a['kode'], $b['kode']));

        $tampil = $q === '' ? $semuaSemester : array_values(array_filter(
            $semuaSemester,
            fn ($r) => stripos($r['kode'], $q) !== false || stripos($r['nama'], $q) !== false
        ));

        return [
            'courses' => $tampil,
            'jumlahSemua' => count($semuaSemester),
            'romawi' => MataKuliahStore::ROMAWI[$semester] ?? (string) $semester,
            'total' => [
                'sks_teori' => array_sum(array_column($semuaSemester, 'sks_teori')),
                'sks_praktek' => array_sum(array_column($semuaSemester, 'sks_praktek')),
                'jam_teori' => array_sum(array_column($semuaSemester, 'jam_teori')),
                'jam_praktek' => array_sum(array_column($semuaSemester, 'jam_praktek')),
            ],
        ];
    }

    /** Bentuk API (nama kolom database) -> bentuk lama yang dipakai Blade. */
    public static function untukView(array $m): array
    {
        return [
            'id' => (int) $m['id_mk'],
            'kurikulum' => (string) $m['id_kurikulum'],
            'semester' => (int) $m['semester'],
            'kode' => $m['kode_mk'],
            'nama' => $m['nama_mk'],
            'sks_teori' => (int) $m['sks_teori'],
            'sks_praktek' => (int) $m['sks_praktik'],
            'jam_teori' => (int) $m['jam_teori'],
            'jam_praktek' => (int) $m['jam_praktek'],
        ];
    }
}
